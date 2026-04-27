<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CKP_Settings {

	public static function get( $key, $default = null ) {
		global $wpdb;
		$table = CKP_DB::table( 'settings' );
		$value = $wpdb->get_var(
			$wpdb->prepare( "SELECT setting_value FROM `$table` WHERE setting_key = %s LIMIT 1", $key )
		);
		return $value !== null ? $value : $default;
	}

	public static function set( $key, $value ) {
		global $wpdb;
		$table = CKP_DB::table( 'settings' );
		$existing = $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM `$table` WHERE setting_key = %s LIMIT 1", $key )
		);

		if ( $existing ) {
			$wpdb->update( $table, array( 'setting_value' => $value ), array( 'setting_key' => $key ) );
		} else {
			$wpdb->insert( $table, array( 'setting_key' => $key, 'setting_value' => $value ) );
		}
	}
}
