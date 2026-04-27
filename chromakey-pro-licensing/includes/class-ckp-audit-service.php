<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CKP_Audit_Service {

	public static function log( $action, $entity_type, $entity_id, $old = null, $new = null, $actor_type = 'admin' ) {
		global $wpdb;

		$wpdb->insert(
			CKP_DB::table( 'audit_log' ),
			array(
				'actor_type'     => $actor_type,
				'actor_id'       => get_current_user_id() ?: null,
				'action'         => $action,
				'entity_type'    => $entity_type,
				'entity_id'      => (int) $entity_id,
				'old_value_json' => $old !== null ? wp_json_encode( $old ) : null,
				'new_value_json' => $new !== null ? wp_json_encode( $new ) : null,
				'created_at'     => current_time( 'mysql', true ),
			),
			array( '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s' )
		);
	}
}
