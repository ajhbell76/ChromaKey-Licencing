<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CKP_Activation_Service {

	/**
	 * Find an existing activation for this machine (any status).
	 */
	public static function find_existing( $licence_id, $fingerprint_hash, $install_hash ) {
		global $wpdb;
		$table = CKP_DB::table( 'activations' );
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM `$table`
			WHERE licence_id = %d
			AND device_fingerprint_hash = %s
			AND installation_id_hash = %s
			LIMIT 1",
			$licence_id, $fingerprint_hash, $install_hash
		) );
	}

	/**
	 * Count active activations for a licence.
	 */
	public static function count_active( $licence_id ) {
		global $wpdb;
		$table = CKP_DB::table( 'activations' );
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM `$table` WHERE licence_id = %d AND status = 'active'",
			$licence_id
		) );
	}

	/**
	 * Return active machines for a licence (for limit-reached response).
	 */
	public static function get_active_machines( $licence_id ) {
		global $wpdb;
		$table = CKP_DB::table( 'activations' );
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT id AS activation_id, computer_name, last_validated_at
			FROM `$table` WHERE licence_id = %d AND status = 'active'
			ORDER BY last_validated_at DESC",
			$licence_id
		) );
	}

	/**
	 * Activate or reuse an activation for the requesting machine.
	 *
	 * Returns the activation row (new or existing) or WP_Error.
	 */
	public static function activate( $licence, $params ) {
		global $wpdb;
		$table = CKP_DB::table( 'activations' );
		$now   = current_time( 'mysql', true );

		$fingerprint = $params['device_fingerprint_hash'];
		$install_id  = $params['installation_id_hash'];
		$due_at      = gmdate( 'Y-m-d H:i:s', strtotime( "+{$licence->validation_interval_days} days" ) );

		$existing = self::find_existing( $licence->id, $fingerprint, $install_id );

		if ( $existing && $existing->status === 'active' ) {
			// Reuse — refresh metadata without consuming a new slot.
			$wpdb->update(
				$table,
				array(
					'computer_name'        => sanitize_text_field( $params['computer_name'] ),
					'os_name'              => sanitize_text_field( $params['os_name'] ),
					'app_version'          => sanitize_text_field( $params['app_version'] ),
					'last_validated_at'    => $now,
					'next_validation_due_at' => $due_at,
					'updated_at'           => $now,
				),
				array( 'id' => $existing->id ),
				array( '%s', '%s', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);
			CKP_Audit_Service::log( 'activation_reused', 'activation', $existing->id, null, null, 'app' );
			return self::get_activation( $existing->id );
		}

		// Check slot availability.
		$active_count = self::count_active( $licence->id );
		if ( $active_count >= $licence->activation_limit ) {
			return new WP_Error( 'activation_limit_reached', 'Activation limit reached.' );
		}

		// Create new activation.
		$wpdb->insert(
			$table,
			array(
				'licence_id'              => $licence->id,
				'account_id'              => $licence->account_id,
				'device_fingerprint_hash' => $fingerprint,
				'installation_id_hash'    => $install_id,
				'computer_name'           => sanitize_text_field( $params['computer_name'] ),
				'os_name'                 => sanitize_text_field( $params['os_name'] ),
				'app_version'             => sanitize_text_field( $params['app_version'] ),
				'status'                  => 'active',
				'first_activated_at'      => $now,
				'last_validated_at'       => $now,
				'next_validation_due_at'  => $due_at,
				'created_at'              => $now,
				'updated_at'              => $now,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		$new_id = (int) $wpdb->insert_id;
		CKP_Audit_Service::log( 'activation_created', 'activation', $new_id, null, array( 'licence_id' => $licence->id ), 'app' );

		return self::get_activation( $new_id );
	}

	/**
	 * Deactivate a specific activation by the app (must match fingerprint).
	 */
	public static function deactivate( $activation_id, $fingerprint_hash ) {
		global $wpdb;
		$table = CKP_DB::table( 'activations' );
		$now   = current_time( 'mysql', true );

		$activation = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM `$table` WHERE id = %d LIMIT 1",
			$activation_id
		) );

		if ( ! $activation ) {
			return new WP_Error( 'not_found', 'Activation not found.' );
		}
		if ( ! hash_equals( $activation->device_fingerprint_hash, $fingerprint_hash ) ) {
			return new WP_Error( 'device_mismatch', 'Device fingerprint does not match.' );
		}

		$wpdb->update(
			$table,
			array(
				'status'            => 'deactivated',
				'deactivated_at'    => $now,
				'deactivated_reason' => 'user_request',
				'updated_at'        => $now,
			),
			array( 'id' => $activation_id ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		CKP_Audit_Service::log( 'activation_deactivated', 'activation', $activation_id, null, null, 'app' );
		return true;
	}

	/**
	 * Admin deactivate — no fingerprint check required.
	 */
	public static function admin_deactivate( $activation_id ) {
		global $wpdb;
		$now = current_time( 'mysql', true );
		$wpdb->update(
			CKP_DB::table( 'activations' ),
			array(
				'status'             => 'deactivated',
				'deactivated_at'     => $now,
				'deactivated_reason' => 'admin_action',
				'updated_at'         => $now,
			),
			array( 'id' => $activation_id ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);
		CKP_Audit_Service::log( 'activation_deactivated', 'activation', $activation_id );
	}

	/**
	 * Admin revoke — blocks the activation slot permanently unless admin reinstates.
	 */
	public static function admin_revoke( $activation_id ) {
		global $wpdb;
		$now = current_time( 'mysql', true );
		$wpdb->update(
			CKP_DB::table( 'activations' ),
			array(
				'status'             => 'revoked',
				'deactivated_at'     => $now,
				'deactivated_reason' => 'admin_revoke',
				'updated_at'         => $now,
			),
			array( 'id' => $activation_id ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);
		CKP_Audit_Service::log( 'activation_revoked', 'activation', $activation_id );
	}

	/**
	 * Admin reactivate — restores a deactivated activation.
	 */
	public static function admin_reactivate( $activation_id ) {
		global $wpdb;
		$now = current_time( 'mysql', true );

		// Check the licence still has room.
		$activation = self::get_activation( $activation_id );
		if ( $activation ) {
			$active_count = self::count_active( $activation->licence_id );
			$limit        = (int) $wpdb->get_var( $wpdb->prepare(
				'SELECT activation_limit FROM `' . CKP_DB::table( 'licences' ) . '` WHERE id = %d',
				$activation->licence_id
			) );
			if ( $active_count >= $limit ) {
				return new WP_Error( 'activation_limit_reached', 'Activation limit already reached — deactivate another machine first.' );
			}
		}

		$wpdb->update(
			CKP_DB::table( 'activations' ),
			array(
				'status'             => 'active',
				'deactivated_at'     => null,
				'deactivated_reason' => '',
				'updated_at'         => $now,
			),
			array( 'id' => $activation_id ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);
		CKP_Audit_Service::log( 'activation_reactivated', 'activation', $activation_id );
		return true;
	}

	private static function get_activation( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM `' . CKP_DB::table( 'activations' ) . '` WHERE id = %d LIMIT 1',
			$id
		) );
	}
}
