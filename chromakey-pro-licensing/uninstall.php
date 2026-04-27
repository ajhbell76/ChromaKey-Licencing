<?php
// Only run when WordPress triggers a proper uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove the schema version option.
delete_option( 'ckp_schema_version' );

// Remove the capability from the administrator role.
$admin_role = get_role( 'administrator' );
if ( $admin_role ) {
	$admin_role->remove_cap( 'manage_ckp_licensing' );
}

// Custom tables are NOT dropped on uninstall to protect beta data.
// A future admin tool can offer table removal explicitly.
