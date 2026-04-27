<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CKP_DB {

	const SCHEMA_VERSION     = '2';
	const SCHEMA_VERSION_KEY = 'ckp_schema_version';

	public static function install() {
		$installed_version = get_option( self::SCHEMA_VERSION_KEY, '0' );

		if ( $installed_version === self::SCHEMA_VERSION ) {
			return;
		}

		self::create_tables();
		self::seed_defaults();

		update_option( self::SCHEMA_VERSION_KEY, self::SCHEMA_VERSION );
	}

	private static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$c = $wpdb->get_charset_collate();
		$p = $wpdb->prefix . 'ckp_';

		dbDelta( "CREATE TABLE {$p}accounts (
  id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  email varchar(255) NOT NULL DEFAULT '',
  display_name varchar(255) NOT NULL DEFAULT '',
  company_name varchar(255) NOT NULL DEFAULT '',
  status varchar(20) NOT NULL DEFAULT 'active',
  notes text NOT NULL,
  created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY  (id),
  UNIQUE KEY email (email)
) $c;" );

		dbDelta( "CREATE TABLE {$p}licences (
  id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  account_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  product_code varchar(100) NOT NULL DEFAULT '',
  licence_key_hash varchar(255) NOT NULL DEFAULT '',
  licence_key_last4 varchar(10) NOT NULL DEFAULT '',
  plan_code varchar(50) NOT NULL DEFAULT 'beta',
  status varchar(20) NOT NULL DEFAULT 'active',
  activation_limit int(11) NOT NULL DEFAULT 2,
  validation_interval_days int(11) NOT NULL DEFAULT 30,
  grace_period_days int(11) NOT NULL DEFAULT 7,
  starts_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  expires_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  created_by_user_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  notes text NOT NULL,
  created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY  (id),
  KEY account_id (account_id),
  KEY licence_key_hash (licence_key_hash(20))
) $c;" );

		dbDelta( "CREATE TABLE {$p}activations (
  id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  licence_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  account_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  device_fingerprint_hash varchar(255) NOT NULL DEFAULT '',
  installation_id_hash varchar(255) NOT NULL DEFAULT '',
  computer_name varchar(255) NOT NULL DEFAULT '',
  os_name varchar(100) NOT NULL DEFAULT '',
  app_version varchar(50) NOT NULL DEFAULT '',
  status varchar(20) NOT NULL DEFAULT 'active',
  first_activated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  last_validated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  next_validation_due_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  deactivated_at datetime DEFAULT NULL,
  deactivated_reason varchar(255) NOT NULL DEFAULT '',
  created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY  (id),
  KEY licence_id (licence_id),
  KEY status (status)
) $c;" );

		dbDelta( "CREATE TABLE {$p}validation_log (
  id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  licence_id bigint(20) UNSIGNED DEFAULT NULL,
  activation_id bigint(20) UNSIGNED DEFAULT NULL,
  email varchar(255) NOT NULL DEFAULT '',
  result varchar(20) NOT NULL DEFAULT '',
  reason varchar(100) NOT NULL DEFAULT '',
  product_code varchar(100) NOT NULL DEFAULT '',
  app_version varchar(50) NOT NULL DEFAULT '',
  ip_address varchar(45) NOT NULL DEFAULT '',
  created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY  (id),
  KEY licence_id (licence_id),
  KEY result (result)
) $c;" );

		dbDelta( "CREATE TABLE {$p}audit_log (
  id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  actor_type varchar(20) NOT NULL DEFAULT '',
  actor_id bigint(20) UNSIGNED DEFAULT NULL,
  action varchar(100) NOT NULL DEFAULT '',
  entity_type varchar(50) NOT NULL DEFAULT '',
  entity_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  old_value_json longtext DEFAULT NULL,
  new_value_json longtext DEFAULT NULL,
  created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY  (id),
  KEY entity_type_id (entity_type, entity_id),
  KEY action (action)
) $c;" );

		dbDelta( "CREATE TABLE {$p}settings (
  id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  setting_key varchar(100) NOT NULL DEFAULT '',
  setting_value longtext NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY setting_key (setting_key)
) $c;" );
	}

	private static function seed_defaults() {
		$defaults = array(
			'product_code'               => CKP_PRODUCT_CODE,
			'default_activation_limit'   => '2',
			'default_validation_interval' => '30',
			'default_grace_period'       => '7',
			'api_enabled'                => '1',
			'debug_logging'              => '0',
			'signing_private_key'        => '',
			'signing_public_key'         => '',
		);

		foreach ( $defaults as $key => $value ) {
			// Only insert if the key doesn't already exist.
			if ( CKP_Settings::get( $key ) === null ) {
				CKP_Settings::set( $key, $value );
			}
		}

		// Generate RSA signing keys if not already present.
		if ( CKP_Settings::get( 'signing_private_key' ) === '' ) {
			self::generate_signing_keys();
		}
	}

	public static function generate_signing_keys() {
		if ( ! extension_loaded( 'openssl' ) ) {
			return 'The PHP openssl extension is not available on this server.';
		}

		// Clear any previous openssl errors.
		while ( openssl_error_string() !== false );

		$key_resource = openssl_pkey_new( array(
			'private_key_bits' => 2048,
			'private_key_type' => OPENSSL_KEYTYPE_RSA,
		) );

		if ( ! $key_resource ) {
			$errors = array();
			while ( $msg = openssl_error_string() ) {
				$errors[] = $msg;
			}
			return 'openssl_pkey_new() failed: ' . implode( ' | ', $errors );
		}

		if ( ! openssl_pkey_export( $key_resource, $private_key_pem ) ) {
			return 'openssl_pkey_export() failed.';
		}

		$details = openssl_pkey_get_details( $key_resource );
		if ( ! $details ) {
			return 'openssl_pkey_get_details() failed.';
		}

		CKP_Settings::set( 'signing_private_key', $private_key_pem );
		CKP_Settings::set( 'signing_public_key', $details['key'] );

		return null; // null = success
	}

	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'ckp_' . $name;
	}
}
