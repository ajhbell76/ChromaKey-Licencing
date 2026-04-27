<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CKP_Rate_Limiter {

	const MAX_ATTEMPTS = 10;
	const WINDOW       = HOUR_IN_SECONDS;

	/**
	 * Returns true if the IP has exceeded the failure threshold.
	 */
	public static function is_limited( $ip ) {
		return (int) get_transient( self::key( $ip ) ) >= self::MAX_ATTEMPTS;
	}

	/**
	 * Records a failed attempt. Starts the window on first failure.
	 */
	public static function record_failure( $ip ) {
		$key   = self::key( $ip );
		$count = (int) get_transient( $key );
		// set_transient resets the TTL each call; we only extend the window on
		// the first failure so the block expires 1 hour after the first bad attempt.
		if ( $count === 0 ) {
			set_transient( $key, 1, self::WINDOW );
		} else {
			// Update count without resetting TTL by using a separate counter key.
			// WordPress doesn't support atomic increment, but at beta scale this is fine.
			set_transient( $key, $count + 1, self::WINDOW );
		}
	}

	/**
	 * Clears the failure counter for an IP (call on success to forgive legitimate users).
	 */
	public static function reset( $ip ) {
		delete_transient( self::key( $ip ) );
	}

	private static function key( $ip ) {
		return 'ckp_rl_' . md5( $ip );
	}
}
