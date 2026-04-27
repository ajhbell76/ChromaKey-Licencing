<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CKP_Validation_Service {

	/**
	 * Validate an existing activation.
	 *
	 * Returns an array with 'licence' and 'activation' on success,
	 * or WP_Error with a result code on failure.
	 */
	public static function validate( array $params ) {
		global $wpdb;

		$licence_id    = (int) $params['licence_id'];
		$activation_id = (int) $params['activation_id'];
		$product_code  = $params['product_code'];
		$fingerprint   = $params['device_fingerprint_hash'];

		// 1. Licence exists.
		$licence = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM `' . CKP_DB::table( 'licences' ) . '` WHERE id = %d LIMIT 1',
			$licence_id
		) );

		if ( ! $licence ) {
			return new WP_Error( 'licence_not_found', 'Licence not found.' );
		}

		// 2–4. Product code, licence status, expiry.
		if ( $licence->product_code !== $product_code ) {
			return new WP_Error( 'invalid_product_code', 'Product code does not match.' );
		}
		if ( $licence->status === 'suspended' ) {
			return new WP_Error( 'licence_suspended', 'This licence has been suspended.' );
		}
		if ( $licence->status === 'revoked' ) {
			return new WP_Error( 'licence_revoked', 'This licence has been revoked.' );
		}
		if ( $licence->status !== 'active' ) {
			return new WP_Error( 'licence_inactive', 'This licence is not active.' );
		}
		if ( ! CKP_Licence_Service::is_within_dates( $licence ) ) {
			return new WP_Error( 'licence_expired', 'This licence has expired.' );
		}

		// 5. Activation exists and belongs to licence.
		$activation = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM `' . CKP_DB::table( 'activations' ) . '` WHERE id = %d AND licence_id = %d LIMIT 1',
			$activation_id, $licence_id
		) );

		if ( ! $activation ) {
			return new WP_Error( 'activation_not_found', 'Activation not found.' );
		}

		// 6. Device fingerprint must match.
		if ( ! hash_equals( $activation->device_fingerprint_hash, $fingerprint ) ) {
			return new WP_Error( 'device_mismatch', 'Device fingerprint does not match.' );
		}

		// 7. Activation must be active.
		if ( $activation->status === 'deactivated' ) {
			return new WP_Error( 'activation_deactivated', 'This machine has been deactivated.' );
		}
		if ( $activation->status === 'revoked' ) {
			return new WP_Error( 'activation_revoked', 'This activation has been revoked.' );
		}
		if ( $activation->status !== 'active' ) {
			return new WP_Error( 'activation_inactive', 'This activation is not active.' );
		}

		// 8. Update last_validated_at and next_validation_due_at.
		$now    = current_time( 'mysql', true );
		$due_at = gmdate( 'Y-m-d H:i:s', strtotime( "+{$licence->validation_interval_days} days" ) );

		$wpdb->update(
			CKP_DB::table( 'activations' ),
			array(
				'last_validated_at'      => $now,
				'next_validation_due_at' => $due_at,
				'computer_name'          => sanitize_text_field( $params['computer_name'] ?? $activation->computer_name ),
				'app_version'            => sanitize_text_field( $params['app_version'] ?? $activation->app_version ),
				'os_name'                => sanitize_text_field( $params['os_name'] ?? $activation->os_name ),
				'updated_at'             => $now,
			),
			array( 'id' => $activation_id ),
			array( '%s', '%s', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		// Return refreshed activation row.
		$activation->last_validated_at      = $now;
		$activation->next_validation_due_at = $due_at;

		CKP_Audit_Service::log( 'validation_success', 'activation', $activation_id, null, null, 'app' );

		return array( 'licence' => $licence, 'activation' => $activation );
	}
}
