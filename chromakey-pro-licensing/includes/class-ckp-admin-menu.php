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
	}

	public function handle_save_settings() {
		if ( ! current_user_can( CKP_CAPABILITY ) ) {
			wp_die( 'Unauthorized' );
		}

		check_admin_referer( 'ckp_save_settings', 'ckp_nonce' );

		$fields = array(
			'default_activation_limit'    => 'intval',
			'default_validation_interval' => 'intval',
			'default_grace_period'        => 'intval',
		);

		foreach ( $fields as $key => $sanitize ) {
			if ( isset( $_POST[ 'ckp_' . $key ] ) ) {
				CKP_Settings::set( $key, $sanitize( $_POST[ 'ckp_' . $key ] ) );
			}
		}

		CKP_Settings::set( 'api_enabled', isset( $_POST['ckp_api_enabled'] ) ? '1' : '0' );
		CKP_Settings::set( 'debug_logging', isset( $_POST['ckp_debug_logging'] ) ? '1' : '0' );

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

		add_submenu_page(
			'ckp-licensing',
			'Dashboard',
			'Dashboard',
			CKP_CAPABILITY,
			'ckp-licensing',
			array( $this, 'render_dashboard' )
		);

		add_submenu_page(
			'ckp-licensing',
			'Customers',
			'Customers',
			CKP_CAPABILITY,
			'ckp-customers',
			array( $this, 'render_customers' )
		);

		add_submenu_page(
			'ckp-licensing',
			'Licences',
			'Licences',
			CKP_CAPABILITY,
			'ckp-licences',
			array( $this, 'render_licences' )
		);

		add_submenu_page(
			'ckp-licensing',
			'Activations',
			'Activations',
			CKP_CAPABILITY,
			'ckp-activations',
			array( $this, 'render_activations' )
		);

		add_submenu_page(
			'ckp-licensing',
			'Audit Log',
			'Audit Log',
			CKP_CAPABILITY,
			'ckp-audit-log',
			array( $this, 'render_audit_log' )
		);

		add_submenu_page(
			'ckp-licensing',
			'Settings',
			'Settings',
			CKP_CAPABILITY,
			'ckp-settings',
			array( $this, 'render_settings' )
		);
	}

	public function enqueue_assets( $hook ) {
		// Only load on CKP admin pages.
		if ( strpos( $hook, 'ckp-' ) === false && $hook !== 'toplevel_page_ckp-licensing' ) {
			return;
		}
		wp_enqueue_style(
			'ckp-admin',
			CKP_PLUGIN_URL . 'admin/assets/admin.css',
			array(),
			CKP_VERSION
		);
		wp_enqueue_script(
			'ckp-admin',
			CKP_PLUGIN_URL . 'admin/assets/admin.js',
			array( 'jquery' ),
			CKP_VERSION,
			true
		);
	}

	public function render_dashboard() {
		require CKP_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	public function render_customers() {
		require CKP_PLUGIN_DIR . 'admin/views/customers-list.php';
	}

	public function render_licences() {
		require CKP_PLUGIN_DIR . 'admin/views/licences-list.php';
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
}
