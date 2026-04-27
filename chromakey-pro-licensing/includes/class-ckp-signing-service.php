<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CKP_Signing_Service {

	/**
	 * Signs the payload array and returns a base64-encoded signature string.
	 *
	 * The payload is sorted by key and JSON-encoded without escaping.
	 * The Python app verifies using:
	 *   json.dumps(payload, sort_keys=True, ensure_ascii=False, separators=(',',':'))
	 *
	 * Returns null on failure.
	 */
	public static function sign( array $payload ) {
		$private_key_pem = CKP_Settings::get( 'signing_private_key', '' );
		if ( ! $private_key_pem ) {
			return null;
		}

		ksort( $payload );
		// Nested objects (features) also need sorted keys.
		if ( isset( $payload['features'] ) && is_array( $payload['features'] ) ) {
			ksort( $payload['features'] );
		}

		$json = json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( $json === false ) {
			return null;
		}

		$key_resource = openssl_pkey_get_private( $private_key_pem );
		if ( ! $key_resource ) {
			return null;
		}

		$raw_sig = '';
		if ( ! openssl_sign( $json, $raw_sig, $key_resource, OPENSSL_ALGO_SHA256 ) ) {
			return null;
		}

		return base64_encode( $raw_sig );
	}

	/**
	 * Build the signable payload fields from a response data array.
	 * Only the fields defined in the spec (§10.1) are included.
	 */
	public static function build_payload( array $data ) {
		return array(
			'activation_id'          => $data['activation_id'],
			'email'                  => $data['email'],
			'expires_at'             => $data['expires_at'],
			'features'               => $data['features'],
			'grace_ends_at'          => $data['grace_ends_at'],
			'last_validated_at'      => $data['last_validated_at'],
			'licence_id'             => $data['licence_id'],
			'licence_status'         => $data['licence_status'],
			'next_validation_due_at' => $data['next_validation_due_at'],
			'plan_code'              => $data['plan_code'],
			'product_code'           => $data['product_code'],
			'server_time_utc'        => $data['server_time_utc'],
		);
	}
}
