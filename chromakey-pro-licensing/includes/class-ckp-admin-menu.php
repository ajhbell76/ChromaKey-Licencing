<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CKP_Admin_Menu {

	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_ckp_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_ckp_generate_keys', array( $this, 'handle_generate_keys' ) );
		add_action( 'admin_post_ckp_save_customer', array( $this, 'handle_save_customer' ) );
		add_action( 'admin_post_ckp_customer_action', array( $this, 'handle_customer_action' ) );
		add_action( 'admin_post_ckp_save_licence', array( $this, 'handle_save_licence' ) );
		add_action( 'admin_post_ckp_licence_action', array( $this, 'handle_licence_action' ) );
		add_action( 'admin_post_ckp_activation_action', array( $this, 'handle_activation_action' ) );
		add_action( 'admin_post_ckp_export_audit_log', array( $this, 'handle_export_audit_log' ) );
	}

	public function register_menus() {
		add_menu_page(
			'ChromaKey Licensing',
			'ChromaKey Licensing',
			CKP_CAPABILITY,
			'ckp-licensing',
			array( $this, 'render_dashboard' ),
			'dashicons-admin-network',
			80
		);

		add_submenu_page( 'ckp-licensing', 'Dashboard',    'Dashboard',    CKP_CAPABILITY, 'ckp-licensing',    array( $this, 'render_dashboard' ) );
		add_submenu_page( 'ckp-licensing', 'Customers',    'Customers',    CKP_CAPABILITY, 'ckp-customers',    array( $this, 'render_customers' ) );
		add_submenu_page( 'ckp-licensing', 'Licences',     'Licences',     CKP_CAPABILITY, 'ckp-licences',     array( $this, 'render_licences' ) );
		add_submenu_page( 'ckp-licensing', 'Activations',  'Activations',  CKP_CAPABILITY, 'ckp-activations',  array( $this, 'render_activations' ) );
		add_submenu_page( 'ckp-licensing', 'Audit Log',    'Audit Log',    CKP_CAPABILITY, 'ckp-audit-log',    array( $this, 'render_audit_log' ) );
		add_submenu_page( 'ckp-licensing', 'Settings',     'Settings',     CKP_CAPABILITY, 'ckp-settings',     array( $this, 'render_settings' ) );
	}

	public function enqueue_assets( $hook ) {
		if ( strpos( $hook, 'ckp-' ) === false && $hook !== 'toplevel_page_ckp-licensing' ) {
			return;
		}
		wp_enqueue_style( 'ckp-admin', CKP_PLUGIN_URL . 'admin/assets/admin.css', array(), CKP_VERSION );
		wp_enqueue_script( 'ckp-admin', CKP_PLUGIN_URL . 'admin/assets/admin.js', array( 'jquery' ), CKP_VERSION, true );
	}

	// -------------------------------------------------------------------------
	// Render callbacks
	// -------------------------------------------------------------------------

	public function render_dashboard() {
		require CKP_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	public function render_customers() {
		$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : 'list';
		if ( $action === 'new' || $action === 'edit' ) {
			require CKP_PLUGIN_DIR . 'admin/views/customer-edit.php';
		} else {
			require CKP_PLUGIN_DIR . 'admin/views/customers-list.php';
		}
	}

	public function render_licences() {
		$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : 'list';
		if ( $action === 'new' || $action === 'edit' ) {
			require CKP_PLUGIN_DIR . 'admin/views/licence-edit.php';
		} else {
			require CKP_PLUGIN_DIR . 'admin/views/licences-list.php';
		}
	}

	public function render_activations() {
		require CKP_PLUGIN_DIR . 'admin/views/activations-list.php';
	}

	public function render_audit_log() {
		require CKP_PLUGIN_DIR . 'admin/views/audit-log.php';
	}

	public function render_settings() {
		require CKP_PLUGIN_DIR . 'admin/views/settings.php';
	}

	// -------------------------------------------------------------------------
	// Settings handlers
	// -------------------------------------------------------------------------

	public function handle_save_settings() {
		if ( ! current_user_can( CKP_CAPABILITY ) ) {
			wp_die( 'Unauthorized' );
		}
		check_admin_referer( 'ckp_save_settings', 'ckp_nonce' );

		foreach ( array( 'default_activation_limit', 'default_validation_interval', 'default_grace_period' ) as $key ) {
			if ( isset( $_POST[ 'ckp_' . $key ] ) ) {
				CKP_Settings::set( $key, (int) $_POST[ 'ckp_' . $key ] );
			}
		}
		CKP_Settings::set( 'api_enabled', isset( $_POST['ckp_api_enabled'] ) ? '1' : '0' );
		CKP_Settings::set( 'debug_logging', isset( $_POST['ckp_debug_logging'] ) ? '1' : '0' );

		CKP_Audit_Service::log( 'settings_changed', 'settings', 0 );

		wp_redirect( admin_url( 'admin.php?page=ckp-settings&updated=1' ) );
		exit;
	}

	public function handle_generate_keys() {
		if ( ! current_user_can( CKP_CAPABILITY ) ) {
			wp_die( 'Unauthorized' );
		}
		check_admin_referer( 'ckp_generate_keys', 'ckp_nonce' );

		$error = CKP_DB::generate_signing_keys();
		if ( $error ) {
			wp_redirect( admin_url( 'admin.php?page=ckp-settings&key_error=' . urlencode( $error ) ) );
		} else {
			wp_redirect( admin_url( 'admin.php?page=ckp-settings&keys_generated=1' ) );
		}
		exit;
	}

	// -------------------------------------------------------------------------
	// Customer handlers
	// -------------------------------------------------------------------------

	public function handle_save_customer() {
		if ( ! current_user_can( CKP_CAPABILITY ) ) {
			wp_die( 'Unauthorized' );
		}
		check_admin_referer( 'ckp_save_customer', 'ckp_nonce' );

		$id    = (int) ( $_POST['customer_id'] ?? 0 );
		$email = sanitize_email( $_POST['ckp_email'] ?? '' );

		if ( ! is_email( $email ) ) {
			$back = $id
				? admin_url( 'admin.php?page=ckp-customers&action=edit&id=' . $id )
				: admin_url( 'admin.php?page=ckp-customers&action=new' );
			wp_redirect( $back . '&ckp_error=' . urlencode( 'A valid email address is required.' ) );
			exit;
		}

		global $wpdb;
		$table = CKP_DB::table( 'accounts' );
		$now   = current_time( 'mysql', true );

		if ( $id ) {
			$old = self::get_customer( $id );
			$wpdb->update(
				$table,
				array(
					'display_name' => sanitize_text_field( $_POST['ckp_display_name'] ?? '' ),
					'company_name' => sanitize_text_field( $_POST['ckp_company_name'] ?? '' ),
					'status'       => in_array( $_POST['ckp_status'] ?? '', array( 'active', 'disabled' ), true ) ? $_POST['ckp_status'] : 'active',
					'notes'        => sanitize_textarea_field( $_POST['ckp_notes'] ?? '' ),
					'updated_at'   => $now,
				),
				array( 'id' => $id ),
				array( '%s', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);
			CKP_Audit_Service::log( 'customer_updated', 'account', $id, (array) $old );
			wp_redirect( admin_url( 'admin.php?page=ckp-customers&action=edit&id=' . $id . '&ckp_msg=' . urlencode( 'Customer saved.' ) ) );
		} else {
			// Check for duplicate email.
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `$table` WHERE email = %s LIMIT 1", $email ) );
			if ( $exists ) {
				wp_redirect( admin_url( 'admin.php?page=ckp-customers&action=new&ckp_error=' . urlencode( 'A customer with that email already exists.' ) ) );
				exit;
			}
			$wpdb->insert(
				$table,
				array(
					'email'        => $email,
					'display_name' => sanitize_text_field( $_POST['ckp_display_name'] ?? '' ),
					'company_name' => sanitize_text_field( $_POST['ckp_company_name'] ?? '' ),
					'status'       => 'active',
					'notes'        => sanitize_textarea_field( $_POST['ckp_notes'] ?? '' ),
					'created_at'   => $now,
					'updated_at'   => $now,
				),
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
			);
			$new_id = (int) $wpdb->insert_id;
			CKP_Audit_Service::log( 'customer_created', 'account', $new_id, null, array( 'email' => $email ) );
			wp_redirect( admin_url( 'admin.php?page=ckp-customers&action=edit&id=' . $new_id . '&ckp_msg=' . urlencode( 'Customer created.' ) ) );
		}
		exit;
	}

	public function handle_customer_action() {
		if ( ! current_user_can( CKP_CAPABILITY ) ) {
			wp_die( 'Unauthorized' );
		}
		$id = (int) ( $_GET['id'] ?? 0 );
		check_admin_referer( 'ckp_customer_action_' . $id );

		$customer_action = sanitize_key( $_GET['customer_action'] ?? '' );
		$status_map      = array( 'enable' => 'active', 'disable' => 'disabled' );

		if ( $id && isset( $status_map[ $customer_action ] ) ) {
			global $wpdb;
			$wpdb->update(
				CKP_DB::table( 'accounts' ),
				array( 'status' => $status_map[ $customer_action ], 'updated_at' => current_time( 'mysql', true ) ),
				array( 'id' => $id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
			CKP_Audit_Service::log( 'customer_' . $customer_action . 'd', 'account', $id );
		}

		wp_redirect( admin_url( 'admin.php?page=ckp-customers&ckp_msg=' . urlencode( 'Customer updated.' ) ) );
		exit;
	}

	// -------------------------------------------------------------------------
	// Licence handlers
	// -------------------------------------------------------------------------

	public function handle_save_licence() {
		if ( ! current_user_can( CKP_CAPABILITY ) ) {
			wp_die( 'Unauthorized' );
		}
		check_admin_referer( 'ckp_save_licence', 'ckp_nonce' );

		global $wpdb;
		$id    = (int) ( $_POST['licence_id'] ?? 0 );
		$now   = current_time( 'mysql', true );
		$table = CKP_DB::table( 'licences' );

		$expires_raw = sanitize_text_field( $_POST['ckp_expires_at'] ?? '' );
		if ( ! $expires_raw ) {
			$back = $id
				? admin_url( 'admin.php?page=ckp-licences&action=edit&id=' . $id )
				: admin_url( 'admin.php?page=ckp-licences&action=new' );
			wp_redirect( $back . '&ckp_error=' . urlencode( 'An expiry date is required.' ) );
			exit;
		}

		if ( $id ) {
			// Edit existing licence.
			$old = self::get_licence( $id );
			$wpdb->update(
				$table,
				array(
					'plan_code'               => sanitize_text_field( $_POST['ckp_plan_code'] ?? 'beta' ),
					'status'                  => sanitize_key( $_POST['ckp_status'] ?? 'active' ),
					'activation_limit'        => max( 1, (int) ( $_POST['ckp_activation_limit'] ?? 2 ) ),
					'starts_at'               => sanitize_text_field( $_POST['ckp_starts_at'] ?? '' ) . ' 00:00:00',
					'expires_at'              => $expires_raw . ' 23:59:59',
					'validation_interval_days' => max( 1, (int) ( $_POST['ckp_validation_interval_days'] ?? 30 ) ),
					'grace_period_days'       => max( 0, (int) ( $_POST['ckp_grace_period_days'] ?? 7 ) ),
					'notes'                   => sanitize_textarea_field( $_POST['ckp_notes'] ?? '' ),
					'updated_at'              => $now,
				),
				array( 'id' => $id ),
				array( '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%s', '%s' ),
				array( '%d' )
			);
			CKP_Audit_Service::log( 'licence_updated', 'licence', $id, (array) $old );
			wp_redirect( admin_url( 'admin.php?page=ckp-licences&action=edit&id=' . $id . '&ckp_msg=' . urlencode( 'Licence saved.' ) ) );
			exit;
		}

		// Create new licence — resolve account by email.
		$email      = sanitize_email( $_POST['ckp_account_email'] ?? '' );
		$atbl       = CKP_DB::table( 'accounts' );
		$account    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `$atbl` WHERE email = %s LIMIT 1", $email ) );

		if ( ! $account ) {
			wp_redirect( admin_url( 'admin.php?page=ckp-licences&action=new&ckp_error=' . urlencode( 'No customer found with that email. Add the customer first.' ) ) );
			exit;
		}

		// Generate key.
		$raw_key  = self::generate_licence_key();
		$key_hash = hash( 'sha256', $raw_key );
		$last4    = substr( $raw_key, -4 );

		$wpdb->insert(
			$table,
			array(
				'account_id'               => $account->id,
				'product_code'             => CKP_PRODUCT_CODE,
				'licence_key_hash'         => $key_hash,
				'licence_key_last4'        => $last4,
				'plan_code'                => sanitize_text_field( $_POST['ckp_plan_code'] ?? 'beta' ),
				'status'                   => 'active',
				'activation_limit'         => max( 1, (int) ( $_POST['ckp_activation_limit'] ?? 2 ) ),
				'validation_interval_days' => max( 1, (int) ( $_POST['ckp_validation_interval_days'] ?? 30 ) ),
				'grace_period_days'        => max( 0, (int) ( $_POST['ckp_grace_period_days'] ?? 7 ) ),
				'starts_at'               => sanitize_text_field( $_POST['ckp_starts_at'] ?? date( 'Y-m-d' ) ) . ' 00:00:00',
				'expires_at'              => $expires_raw . ' 23:59:59',
				'created_by_user_id'      => get_current_user_id(),
				'notes'                   => sanitize_textarea_field( $_POST['ckp_notes'] ?? '' ),
				'created_at'              => $now,
				'updated_at'              => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s' )
		);
		$new_id = (int) $wpdb->insert_id;

		CKP_Audit_Service::log( 'licence_created', 'licence', $new_id, null, array( 'account_id' => $account->id, 'plan_code' => $_POST['ckp_plan_code'] ?? 'beta' ) );

		// Store raw key in a short-lived transient — shown once on the edit page.
		set_transient( 'ckp_rawkey_' . $new_id, $raw_key, 5 * MINUTE_IN_SECONDS );

		wp_redirect( admin_url( 'admin.php?page=ckp-licences&action=edit&id=' . $new_id . '&new_key=1' ) );
		exit;
	}

	public function handle_licence_action() {
		if ( ! current_user_can( CKP_CAPABILITY ) ) {
			wp_die( 'Unauthorized' );
		}
		$id = (int) ( $_GET['id'] ?? 0 );
		check_admin_referer( 'ckp_licence_action_' . $id );

		$licence_action = sanitize_key( $_GET['licence_action'] ?? '' );
		$status_map     = array(
			'suspend'   => 'suspended',
			'revoke'    => 'revoked',
			'reinstate' => 'active',
		);

		if ( $id && isset( $status_map[ $licence_action ] ) ) {
			global $wpdb;
			$wpdb->update(
				CKP_DB::table( 'licences' ),
				array( 'status' => $status_map[ $licence_action ], 'updated_at' => current_time( 'mysql', true ) ),
				array( 'id' => $id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
			CKP_Audit_Service::log( 'licence_' . $licence_action . 'd', 'licence', $id );
		}

		wp_redirect( admin_url( 'admin.php?page=ckp-licences&ckp_msg=' . urlencode( 'Licence updated.' ) ) );
		exit;
	}

	// -------------------------------------------------------------------------
	// Activation handlers
	// -------------------------------------------------------------------------

	public function handle_activation_action() {
		if ( ! current_user_can( CKP_CAPABILITY ) ) {
			wp_die( 'Unauthorized' );
		}
		$id = (int) ( $_GET['id'] ?? 0 );
		check_admin_referer( 'ckp_activation_action_' . $id );

		$activation_action = sanitize_key( $_GET['activation_action'] ?? '' );

		$result = null;
		switch ( $activation_action ) {
			case 'deactivate':
				CKP_Activation_Service::admin_deactivate( $id );
				$msg = 'Machine deactivated.';
				break;
			case 'revoke':
				CKP_Activation_Service::admin_revoke( $id );
				$msg = 'Activation revoked.';
				break;
			case 'reactivate':
				$result = CKP_Activation_Service::admin_reactivate( $id );
				$msg    = is_wp_error( $result ) ? $result->get_error_message() : 'Activation reinstated.';
				break;
			default:
				$msg = 'Unknown action.';
		}

		wp_redirect( admin_url( 'admin.php?page=ckp-activations&ckp_msg=' . urlencode( $msg ) ) );
		exit;
	}

	// -------------------------------------------------------------------------
	// CSV export
	// -------------------------------------------------------------------------

	public function handle_export_audit_log() {
		if ( ! current_user_can( CKP_CAPABILITY ) ) {
			wp_die( 'Unauthorized' );
		}
		check_admin_referer( 'ckp_export_audit_log' );

		global $wpdb;
		$table = CKP_DB::table( 'audit_log' );

		$conditions = array( '1=1' );
		if ( ! empty( $_GET['filter_action'] ) ) {
			$conditions[] = $wpdb->prepare( 'action = %s', sanitize_key( $_GET['filter_action'] ) );
		}
		if ( ! empty( $_GET['filter_entity_type'] ) ) {
			$conditions[] = $wpdb->prepare( 'entity_type = %s', sanitize_key( $_GET['filter_entity_type'] ) );
		}
		if ( ! empty( $_GET['filter_date_from'] ) ) {
			$conditions[] = $wpdb->prepare( 'created_at >= %s', sanitize_text_field( $_GET['filter_date_from'] ) . ' 00:00:00' );
		}
		if ( ! empty( $_GET['filter_date_to'] ) ) {
			$conditions[] = $wpdb->prepare( 'created_at <= %s', sanitize_text_field( $_GET['filter_date_to'] ) . ' 23:59:59' );
		}
		$where = 'WHERE ' . implode( ' AND ', $conditions );

		$rows = $wpdb->get_results( "SELECT * FROM `$table` $where ORDER BY created_at DESC", ARRAY_A );

		$filename = 'ckp-audit-log-' . date( 'Y-m-d' ) . '.csv';
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'ID', 'When (UTC)', 'Actor Type', 'Actor ID', 'Action', 'Entity Type', 'Entity ID', 'Old Value', 'New Value' ) );

		foreach ( $rows as $row ) {
			fputcsv( $out, array(
				$row['id'],
				$row['created_at'],
				$row['actor_type'],
				$row['actor_id'],
				$row['action'],
				$row['entity_type'],
				$row['entity_id'],
				$row['old_value_json'],
				$row['new_value_json'],
			) );
		}
		fclose( $out );
		exit;
	}

	// -------------------------------------------------------------------------
	// Static helpers used by views
	// -------------------------------------------------------------------------

	public static function get_customer( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM `' . CKP_DB::table( 'accounts' ) . '` WHERE id = %d LIMIT 1',
			$id
		) );
	}

	public static function get_licence( $id ) {
		global $wpdb;
		$ltbl = CKP_DB::table( 'licences' );
		$atbl = CKP_DB::table( 'accounts' );
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT l.*, a.email FROM `$ltbl` l LEFT JOIN `$atbl` a ON a.id = l.account_id WHERE l.id = %d LIMIT 1",
			$id
		) );
	}

	private static function generate_licence_key() {
		$chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
		$len   = strlen( $chars ) - 1;
		$segs  = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$seg = '';
			for ( $j = 0; $j < 4; $j++ ) {
				$seg .= $chars[ random_int( 0, $len ) ];
			}
			$segs[] = $seg;
		}
		return 'CKP-BETA-' . implode( '-', $segs );
	}
}
