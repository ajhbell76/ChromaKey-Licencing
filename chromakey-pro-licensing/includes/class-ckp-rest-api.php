<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CKP_Rest_API {

	const NAMESPACE = 'ckp-licensing/v1';

	public function init() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		// Suppress PHP error output on REST requests so raw errors never reach clients.
		add_filter( 'rest_pre_dispatch', array( $this, 'suppress_errors_for_rest' ), 10, 3 );
	}

	public function suppress_errors_for_rest( $result, $server, $request ) {
		if ( strpos( $request->get_route(), '/' . self::NAMESPACE ) === 0 ) {
			@ini_set( 'display_errors', '0' );
		}
		return $result;
	}

	public function register_routes() {
		register_rest_route( self::NAMESPACE, '/activate', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'activate' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( self::NAMESPACE, '/validate', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'validate' ),
			'permission_callback' => '__return_true',
		) );
		register_rest_route( self::NAMESPACE, '/deactivate', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'deactivate' ),
			'permission_callback' => '__return_true',
		) );
		register_rest_route( self::NAMESPACE, '/status', array(
			'methods'             => array( 'GET', 'POST' ),
			'callback'            => array( $this, 'status' ),
			'permission_callback' => '__return_true',
		) );
		register_rest_route( self::NAMESPACE, '/health', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'health' ),
			'permission_callback' => '__return_true',
		) );
	}

	// -------------------------------------------------------------------------
	// POST /activate
	// -------------------------------------------------------------------------

	public function activate( WP_REST_Request $request ) {
		if ( ! $this->is_api_enabled() ) {
			return $this->error( 'api_disabled', 'The licensing API is currently disabled.', 503 );
		}

		$ip = $this->get_client_ip( $request );
		if ( CKP_Rate_Limiter::is_limited( $ip ) ) {
			return $this->error( 'too_many_requests', 'Too many failed attempts. Please try again later.', 429 );
		}

		// --- Required field validation ---
		$required = array( 'email', 'licence_key', 'product_code', 'device_fingerprint_hash', 'installation_id_hash' );
		foreach ( $required as $field ) {
			if ( empty( $request->get_param( $field ) ) ) {
				return $this->error( 'missing_field', "Field '$field' is required.", 400 );
			}
		}

		$email       = sanitize_email( $request->get_param( 'email' ) );
		$licence_key = sanitize_text_field( $request->get_param( 'licence_key' ) );
		$product     = sanitize_text_field( $request->get_param( 'product_code' ) );

		if ( $product !== CKP_PRODUCT_CODE ) {
			CKP_Rate_Limiter::record_failure( $ip );
			$this->log_attempt( null, null, $email, 'failed', 'invalid_product_code', $request );
			return $this->error( 'invalid_product_code', 'Unknown product code.', 400 );
		}

		// --- Licence lookup ---
		$licence = CKP_Licence_Service::find_by_email_and_key( $email, $licence_key );
		if ( ! $licence ) {
			CKP_Rate_Limiter::record_failure( $ip );
			$this->log_attempt( null, null, $email, 'failed', 'invalid_key', $request );
			return $this->error( 'invalid_key', 'Licence key or email is incorrect.', 401 );
		}

		// --- Licence status checks ---
		if ( $licence->status === 'suspended' ) {
			$this->log_attempt( $licence->id, null, $email, 'failed', 'licence_suspended', $request );
			return $this->error( 'licence_suspended', 'This licence has been suspended.', 403 );
		}
		if ( $licence->status === 'revoked' ) {
			$this->log_attempt( $licence->id, null, $email, 'failed', 'licence_revoked', $request );
			return $this->error( 'licence_revoked', 'This licence has been revoked.', 403 );
		}
		if ( $licence->status !== 'active' ) {
			$this->log_attempt( $licence->id, null, $email, 'failed', 'licence_inactive', $request );
			return $this->error( 'licence_inactive', 'This licence is not active.', 403 );
		}
		if ( ! CKP_Licence_Service::is_within_dates( $licence ) ) {
			$this->log_attempt( $licence->id, null, $email, 'failed', 'licence_expired', $request );
			return $this->error( 'licence_expired', 'This licence has expired.', 403 );
		}

		// --- Activation ---
		$params = array(
			'device_fingerprint_hash' => sanitize_text_field( $request->get_param( 'device_fingerprint_hash' ) ),
			'installation_id_hash'    => sanitize_text_field( $request->get_param( 'installation_id_hash' ) ),
			'computer_name'           => sanitize_text_field( $request->get_param( 'computer_name' ) ?? '' ),
			'os_name'                 => sanitize_text_field( $request->get_param( 'os_name' ) ?? '' ),
			'app_version'             => sanitize_text_field( $request->get_param( 'app_version' ) ?? '' ),
		);

		$activation = CKP_Activation_Service::activate( $licence, $params );

		if ( is_wp_error( $activation ) ) {
			if ( $activation->get_error_code() === 'activation_limit_reached' ) {
				$machines = CKP_Activation_Service::get_active_machines( $licence->id );
				$this->log_attempt( $licence->id, null, $email, 'failed', 'activation_limit_reached', $request );
				return new WP_REST_Response( array(
					'result'           => 'activation_limit_reached',
					'activation_limit' => (int) $licence->activation_limit,
					'active_count'     => CKP_Activation_Service::count_active( $licence->id ),
					'activations'      => array_map( function( $m ) {
						return array(
							'activation_id'     => (int) $m->activation_id,
							'computer_name'     => $m->computer_name,
							'last_validated_at' => $this->to_iso8601( $m->last_validated_at ),
						);
					}, $machines ),
				), 200 );
			}
			$this->log_attempt( $licence->id, null, $email, 'failed', $activation->get_error_code(), $request );
			return $this->error( $activation->get_error_code(), $activation->get_error_message(), 500 );
		}

		// --- Build signed response ---
		$now       = gmdate( 'Y-m-d\TH:i:s\Z' );
		$due_at    = $this->to_iso8601( $activation->next_validation_due_at );
		$grace_ts  = strtotime( $activation->next_validation_due_at ) + ( $licence->grace_period_days * DAY_IN_SECONDS );
		$features  = CKP_Licence_Service::get_features( $licence );

		$payload = CKP_Signing_Service::build_payload( array(
			'activation_id'          => (int) $activation->id,
			'email'                  => $email,
			'expires_at'             => $this->to_iso8601( $licence->expires_at ),
			'features'               => $features,
			'grace_ends_at'          => gmdate( 'Y-m-d\TH:i:s\Z', $grace_ts ),
			'last_validated_at'      => $this->to_iso8601( $activation->last_validated_at ),
			'licence_id'             => (int) $licence->id,
			'licence_status'         => $licence->status,
			'next_validation_due_at' => $due_at,
			'plan_code'              => $licence->plan_code,
			'product_code'           => CKP_PRODUCT_CODE,
			'server_time_utc'        => $now,
		) );

		$signature = CKP_Signing_Service::sign( $payload );

		CKP_Rate_Limiter::reset( $ip );
		$this->log_attempt( $licence->id, $activation->id, $email, 'success', '', $request );

		return new WP_REST_Response( array_merge(
			array( 'result' => 'valid' ),
			$payload,
			array(
				'activation_limit' => (int) $licence->activation_limit,
				'signature'        => $signature,
			)
		), 200 );
	}

	// -------------------------------------------------------------------------
	// POST /validate
	// -------------------------------------------------------------------------

	public function validate( WP_REST_Request $request ) {
		if ( ! $this->is_api_enabled() ) {
			return $this->error( 'api_disabled', 'The licensing API is currently disabled.', 503 );
		}

		$ip = $this->get_client_ip( $request );
		if ( CKP_Rate_Limiter::is_limited( $ip ) ) {
			return $this->error( 'too_many_requests', 'Too many failed attempts. Please try again later.', 429 );
		}

		$required = array( 'licence_id', 'activation_id', 'product_code', 'device_fingerprint_hash' );
		foreach ( $required as $field ) {
			if ( empty( $request->get_param( $field ) ) ) {
				return $this->error( 'missing_field', "Field '$field' is required.", 400 );
			}
		}

		$params = array(
			'licence_id'              => (int) $request->get_param( 'licence_id' ),
			'activation_id'           => (int) $request->get_param( 'activation_id' ),
			'product_code'            => sanitize_text_field( $request->get_param( 'product_code' ) ),
			'device_fingerprint_hash' => sanitize_text_field( $request->get_param( 'device_fingerprint_hash' ) ),
			'computer_name'           => sanitize_text_field( $request->get_param( 'computer_name' ) ?? '' ),
			'app_version'             => sanitize_text_field( $request->get_param( 'app_version' ) ?? '' ),
			'os_name'                 => sanitize_text_field( $request->get_param( 'os_name' ) ?? '' ),
		);

		$result = CKP_Validation_Service::validate( $params );

		if ( is_wp_error( $result ) ) {
			$code = $result->get_error_code();
			CKP_Rate_Limiter::record_failure( $ip );
			$this->log_attempt( $params['licence_id'], $params['activation_id'], '', 'failed', $code, $request );
			$status = in_array( $code, array( 'licence_not_found', 'activation_not_found' ), true ) ? 404 : 403;
			return $this->error( $code, $result->get_error_message(), $status );
		}

		CKP_Rate_Limiter::reset( $ip );

		$licence    = $result['licence'];
		$activation = $result['activation'];
		$now        = gmdate( 'Y-m-d\TH:i:s\Z' );
		$grace_ts   = strtotime( $activation->next_validation_due_at ) + ( $licence->grace_period_days * DAY_IN_SECONDS );

		// Fetch the account email for the signed payload.
		global $wpdb;
		$email = $wpdb->get_var( $wpdb->prepare(
			'SELECT email FROM `' . CKP_DB::table( 'accounts' ) . '` WHERE id = %d LIMIT 1',
			$licence->account_id
		) );

		$payload = CKP_Signing_Service::build_payload( array(
			'activation_id'          => (int) $activation->id,
			'email'                  => $email,
			'expires_at'             => $this->to_iso8601( $licence->expires_at ),
			'features'               => CKP_Licence_Service::get_features( $licence ),
			'grace_ends_at'          => gmdate( 'Y-m-d\TH:i:s\Z', $grace_ts ),
			'last_validated_at'      => $this->to_iso8601( $activation->last_validated_at ),
			'licence_id'             => (int) $licence->id,
			'licence_status'         => $licence->status,
			'next_validation_due_at' => $this->to_iso8601( $activation->next_validation_due_at ),
			'plan_code'              => $licence->plan_code,
			'product_code'           => CKP_PRODUCT_CODE,
			'server_time_utc'        => $now,
		) );

		$this->log_attempt( $licence->id, $activation->id, $email, 'success', '', $request );

		return new WP_REST_Response( array_merge(
			array( 'result' => 'valid' ),
			$payload,
			array( 'signature' => CKP_Signing_Service::sign( $payload ) )
		), 200 );
	}

	// -------------------------------------------------------------------------
	// POST /deactivate
	// -------------------------------------------------------------------------

	public function deactivate( WP_REST_Request $request ) {
		if ( ! $this->is_api_enabled() ) {
			return $this->error( 'api_disabled', 'The licensing API is currently disabled.', 503 );
		}

		$required = array( 'licence_id', 'activation_id', 'device_fingerprint_hash' );
		foreach ( $required as $field ) {
			if ( empty( $request->get_param( $field ) ) ) {
				return $this->error( 'missing_field', "Field '$field' is required.", 400 );
			}
		}

		$licence_id    = (int) $request->get_param( 'licence_id' );
		$activation_id = (int) $request->get_param( 'activation_id' );
		$fingerprint   = sanitize_text_field( $request->get_param( 'device_fingerprint_hash' ) );

		// Confirm the activation belongs to the stated licence before deactivating.
		global $wpdb;
		$activation = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM `' . CKP_DB::table( 'activations' ) . '` WHERE id = %d AND licence_id = %d LIMIT 1',
			$activation_id, $licence_id
		) );

		if ( ! $activation ) {
			return $this->error( 'activation_not_found', 'Activation not found for this licence.', 404 );
		}

		$result = CKP_Activation_Service::deactivate( $activation_id, $fingerprint );

		if ( is_wp_error( $result ) ) {
			$status = $result->get_error_code() === 'device_mismatch' ? 403 : 400;
			return $this->error( $result->get_error_code(), $result->get_error_message(), $status );
		}

		return new WP_REST_Response( array( 'result' => 'deactivated' ), 200 );
	}

	// -------------------------------------------------------------------------
	// POST /status
	// -------------------------------------------------------------------------

	public function status( WP_REST_Request $request ) {
		if ( ! $this->is_api_enabled() ) {
			return $this->error( 'api_disabled', 'The licensing API is currently disabled.', 503 );
		}

		// Accepts params from JSON body (POST) or query string (GET).
		$licence_id    = (int) ( $request->get_param( 'licence_id' ) ?? $request->get_query_params()['licence_id'] ?? 0 );
		$activation_id = (int) ( $request->get_param( 'activation_id' ) ?? $request->get_query_params()['activation_id'] ?? 0 );

		if ( ! $licence_id || ! $activation_id ) {
			return $this->error( 'missing_field', 'licence_id and activation_id are required.', 400 );
		}

		global $wpdb;
		$licence = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM `' . CKP_DB::table( 'licences' ) . '` WHERE id = %d LIMIT 1',
			$licence_id
		) );

		if ( ! $licence ) {
			return $this->error( 'licence_not_found', 'Licence not found.', 404 );
		}

		$activation = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM `' . CKP_DB::table( 'activations' ) . '` WHERE id = %d AND licence_id = %d LIMIT 1',
			$activation_id, $licence_id
		) );

		if ( ! $activation ) {
			return $this->error( 'activation_not_found', 'Activation not found.', 404 );
		}

		return new WP_REST_Response( array(
			'result'                 => $activation->status,
			'licence_status'         => $licence->status,
			'expires_at'             => $this->to_iso8601( $licence->expires_at ),
			'activation_limit'       => (int) $licence->activation_limit,
			'active_count'           => CKP_Activation_Service::count_active( $licence_id ),
			'next_validation_due_at' => $this->to_iso8601( $activation->next_validation_due_at ),
		), 200 );
	}

	// -------------------------------------------------------------------------
	// GET /health
	// -------------------------------------------------------------------------

	public function health( WP_REST_Request $request ) {
		return new WP_REST_Response( array(
			'status'     => 'ok',
			'api'        => self::NAMESPACE,
			'product'    => CKP_PRODUCT_CODE,
			'time_utc'   => gmdate( 'Y-m-d\TH:i:s\Z' ),
		), 200 );
	}

	public function not_implemented( WP_REST_Request $request ) {
		return $this->error( 'not_implemented', 'This endpoint is not yet available.', 501 );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function is_api_enabled() {
		return CKP_Settings::get( 'api_enabled', '1' ) === '1';
	}

	private function error( $code, $message, $status = 400 ) {
		return new WP_REST_Response( array(
			'result'  => $code,
			'message' => $message,
		), $status );
	}

	private function to_iso8601( $mysql_datetime ) {
		if ( ! $mysql_datetime || $mysql_datetime === '0000-00-00 00:00:00' ) {
			return null;
		}
		return gmdate( 'Y-m-d\TH:i:s\Z', strtotime( $mysql_datetime ) );
	}

	private function log_attempt( $licence_id, $activation_id, $email, $result, $reason, WP_REST_Request $request ) {
		global $wpdb;
		$wpdb->insert(
			CKP_DB::table( 'validation_log' ),
			array(
				'licence_id'    => $licence_id,
				'activation_id' => $activation_id,
				'email'         => $email,
				'result'        => $result,
				'reason'        => $reason,
				'product_code'  => CKP_PRODUCT_CODE,
				'app_version'   => sanitize_text_field( $request->get_param( 'app_version' ) ?? '' ),
				'ip_address'    => $this->get_client_ip( $request ),
				'created_at'    => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	private function get_client_ip( WP_REST_Request $request ) {
		foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ) as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				return sanitize_text_field( explode( ',', $_SERVER[ $key ] )[0] );
			}
		}
		return '';
	}
}
