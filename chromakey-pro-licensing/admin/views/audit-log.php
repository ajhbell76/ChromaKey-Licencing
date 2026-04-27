<?php if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$table = CKP_DB::table( 'audit_log' );

// Filters.
$filter_action      = isset( $_GET['filter_action'] ) ? sanitize_key( $_GET['filter_action'] ) : '';
$filter_entity_type = isset( $_GET['filter_entity_type'] ) ? sanitize_key( $_GET['filter_entity_type'] ) : '';
$filter_date_from   = isset( $_GET['filter_date_from'] ) ? sanitize_text_field( $_GET['filter_date_from'] ) : '';
$filter_date_to     = isset( $_GET['filter_date_to'] ) ? sanitize_text_field( $_GET['filter_date_to'] ) : '';

$conditions = array( '1=1' );
if ( $filter_action ) {
	$conditions[] = $wpdb->prepare( 'action = %s', $filter_action );
}
if ( $filter_entity_type ) {
	$conditions[] = $wpdb->prepare( 'entity_type = %s', $filter_entity_type );
}
if ( $filter_date_from ) {
	$conditions[] = $wpdb->prepare( 'created_at >= %s', $filter_date_from . ' 00:00:00' );
}
if ( $filter_date_to ) {
	$conditions[] = $wpdb->prepare( 'created_at <= %s', $filter_date_to . ' 23:59:59' );
}
$where = 'WHERE ' . implode( ' AND ', $conditions );

$per_page = 50;
$paged    = max( 1, isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1 );
$offset   = ( $paged - 1 ) * $per_page;

$entries = $wpdb->get_results(
	"SELECT * FROM `$table` $where ORDER BY created_at DESC LIMIT $per_page OFFSET $offset"
);
$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$table` $where" );

// Distinct values for dropdowns.
$distinct_actions = $wpdb->get_col( "SELECT DISTINCT action FROM `$table` ORDER BY action ASC" );
$distinct_entities = $wpdb->get_col( "SELECT DISTINCT entity_type FROM `$table` ORDER BY entity_type ASC" );

$export_url = wp_nonce_url(
	admin_url( 'admin-post.php?action=ckp_export_audit_log'
		. ( $filter_action ? '&filter_action=' . urlencode( $filter_action ) : '' )
		. ( $filter_entity_type ? '&filter_entity_type=' . urlencode( $filter_entity_type ) : '' )
		. ( $filter_date_from ? '&filter_date_from=' . urlencode( $filter_date_from ) : '' )
		. ( $filter_date_to ? '&filter_date_to=' . urlencode( $filter_date_to ) : '' )
	),
	'ckp_export_audit_log'
);
?>

<div class="wrap ckp-wrap">
	<h1 class="wp-heading-inline">Audit Log</h1>
	<a href="<?php echo esc_url( $export_url ); ?>" class="page-title-action">Export CSV</a>
	<hr class="wp-header-end">

	<form method="get" class="ckp-filter-form">
		<input type="hidden" name="page" value="ckp-audit-log">
		<div class="ckp-filters">
			<select name="filter_action">
				<option value="">All actions</option>
				<?php foreach ( $distinct_actions as $a ) : ?>
					<option value="<?php echo esc_attr( $a ); ?>" <?php selected( $filter_action, $a ); ?>>
						<?php echo esc_html( $a ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<select name="filter_entity_type">
				<option value="">All entities</option>
				<?php foreach ( $distinct_entities as $e ) : ?>
					<option value="<?php echo esc_attr( $e ); ?>" <?php selected( $filter_entity_type, $e ); ?>>
						<?php echo esc_html( $e ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<label>From <input type="date" name="filter_date_from" value="<?php echo esc_attr( $filter_date_from ); ?>"></label>
			<label>To <input type="date" name="filter_date_to" value="<?php echo esc_attr( $filter_date_to ); ?>"></label>

			<?php submit_button( 'Filter', 'secondary', 'submit', false ); ?>

			<?php if ( $filter_action || $filter_entity_type || $filter_date_from || $filter_date_to ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ckp-audit-log' ) ); ?>" class="button">Clear</a>
			<?php endif; ?>
		</div>
	</form>

	<p class="ckp-filter-count">Showing <?php echo number_format( $total ); ?> entr<?php echo $total === 1 ? 'y' : 'ies'; ?></p>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th style="width:145px;">When (UTC)</th>
				<th style="width:80px;">Actor</th>
				<th style="width:180px;">Action</th>
				<th style="width:80px;">Entity</th>
				<th style="width:50px;">ID</th>
				<th>Detail</th>
			</tr>
		</thead>
		<tbody>
		<?php if ( $entries ) : ?>
			<?php foreach ( $entries as $e ) : ?>
				<tr>
					<td><?php echo esc_html( date_i18n( 'd M Y H:i:s', strtotime( $e->created_at ) ) ); ?></td>
					<td>
						<?php echo esc_html( $e->actor_type ); ?>
						<?php echo $e->actor_id ? '<br><small>#' . (int) $e->actor_id . '</small>' : ''; ?>
					</td>
					<td><code><?php echo esc_html( $e->action ); ?></code></td>
					<td><?php echo esc_html( $e->entity_type ); ?></td>
					<td><?php echo (int) $e->entity_id; ?></td>
					<td>
						<?php if ( $e->new_value_json ) : ?>
							<span class="ckp-audit-detail"><?php echo esc_html( $e->new_value_json ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		<?php else : ?>
			<tr><td colspan="6">No audit entries match the current filters.</td></tr>
		<?php endif; ?>
		</tbody>
	</table>

	<?php if ( $total > $per_page ) : ?>
		<div class="tablenav bottom">
			<?php echo paginate_links( array(
				'base'    => add_query_arg( 'paged', '%#%' ),
				'format'  => '',
				'current' => $paged,
				'total'   => ceil( $total / $per_page ),
			) ); ?>
		</div>
	<?php endif; ?>
</div>
