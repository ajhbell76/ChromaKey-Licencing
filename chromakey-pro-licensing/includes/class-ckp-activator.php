<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CKP_Activator {

	public static function activate() {
		CKP_DB::install();
		flush_rewrite_rules();
	}

	public static function deactivate() {
		// Intentionally does not delete data.
		flush_rewrite_rules();
	}
}
