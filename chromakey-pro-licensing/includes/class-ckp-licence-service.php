<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CKP_Licence_Service {

	/**
	 * Find a licence by email + raw licence key, confirming product code.
	 * Returns the licence row (with account email) or null.
	 */
	public static function find_by_email_and_key( $email, $raw_key ) {
		global $wpdb;
		$key_hash = hash( 'sha256', $raw_key );
		$ltbl     = CKP_DB::table( 'licences' );
		$atbl     = CKP_DB::table( 'accounts' );

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT l.*, a.email AS account_email
			FROM `$ltbl` l
			JOIN `$atbl` a ON a.id = l.account_id
			WHERE a.email = %s
			AND l.licence_key_hash = %s
			AND l.product_code = %s
			LIMIT 1",
			$email, $key_hash, CKP_PRODUCT_CODE
		) );
	}

	/**
	 * True if the licence is within its start/expiry window (UTC comparison).
	 */
	public static function is_within_dates( $licence ) {
		$now     = time();
		$starts  = strtotime( $licence->starts_at );
		$expires = strtotime( $licence->expires_at );
		return $now >= $starts && $now <= $expires;
	}

	/**
	 * Build the standard features block for beta licences.
	 * Can be extended per plan_code later.
	 */
	public static function get_features( $licence ) {
		return array(
			'batch_processing' => true,
			'export_enabled'   => true,
			'watermark'        => false,
		);
	}
}
