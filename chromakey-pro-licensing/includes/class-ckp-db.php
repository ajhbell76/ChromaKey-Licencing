<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CKP_DB {

	const SCHEMA_VERSION = '1';
	const SCHEMA_VERSION_KEY = 'ckp_schema_version';

	public static function install() {
		$installed_version = get_option( self::SCHEMA_VERSION_KEY, '0' );

		if ( $installed_version === self::SCHEMA_VERSION ) {
			return;
		}

		self::create_tables();

		update_option( self::SCHEMA_VERSION_KEY, self::SCHEMA_VERSION );
	}

	private static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$p        = $wpdb->prefix . 'ckp_';

		// Tables are created in WP-2. This stub ensures the install flow
		// works without errors in WP-1.
		// dbDelta() calls will be added here phase by phase.
	}

	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'ckp_' . $name;
	}
}
