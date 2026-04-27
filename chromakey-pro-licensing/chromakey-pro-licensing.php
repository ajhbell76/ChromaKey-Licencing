<?php
/**
 * Plugin Name:       ChromaKey Pro Licensing
 * Plugin URI:        https://github.com/ajhbell76/ChromaKey-Licencing
 * Description:       Beta licensing system for ChromaKey Pro desktop application.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            ChromaKey Pro
 * License:           Proprietary
 * Text Domain:       ckp-licensing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CKP_VERSION', '0.1.0' );
define( 'CKP_PLUGIN_FILE', __FILE__ );
define( 'CKP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CKP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CKP_PRODUCT_CODE', 'chromakey_pro' );
define( 'CKP_CAPABILITY', 'manage_ckp_licensing' );

require_once CKP_PLUGIN_DIR . 'includes/class-ckp-activator.php';
require_once CKP_PLUGIN_DIR . 'includes/class-ckp-db.php';
require_once CKP_PLUGIN_DIR . 'includes/class-ckp-settings.php';
require_once CKP_PLUGIN_DIR . 'includes/class-ckp-admin-menu.php';

register_activation_hook( __FILE__, array( 'CKP_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CKP_Activator', 'deactivate' ) );

add_action( 'plugins_loaded', 'ckp_init' );

function ckp_init() {
	// Grant administrators the licensing capability during beta.
	ckp_grant_admin_capability();

	$admin_menu = new CKP_Admin_Menu();
	$admin_menu->init();
}

function ckp_grant_admin_capability() {
	$admin_role = get_role( 'administrator' );
	if ( $admin_role && ! $admin_role->has_cap( CKP_CAPABILITY ) ) {
		$admin_role->add_cap( CKP_CAPABILITY );
	}
}
