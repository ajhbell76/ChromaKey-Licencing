<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CKP_Admin_Menu {

	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
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
