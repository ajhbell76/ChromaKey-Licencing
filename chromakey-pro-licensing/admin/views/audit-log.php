<?php if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$table = CKP_DB::table( 'audit_log' );
$per_page = 50;
$paged = max( 1, isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1 );
$offset = ( $paged - 1 ) * $per_page;

$entries = $wpdb->get_results( $wpdb->prepare(
	"SELECT * FROM `$table` ORDER BY created_at DESC LIMIT %d OFFSET %d",
	$per_page, $offset
) );
$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$table`" );
?>

<div class="wrap ckp-wrap">
	<h1>Audit Log</h1>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th style="width:140px;">When (UTC)</th>
				<th style="width:70px;">Actor</th>
				<th style="width:160px;">Action</th>
				<th style="width:80px;">Entity</th>
				<th style="width:50px;">ID</th>
				<th>Detail</th>
			</tr>
		</thead>
		<tbody>
		<?php if ( $entries ) : ?>
			<?php foreach ( $entries as $e ) : ?>
				<tr>
					<td><?php echo esc_html( date_i18n( 'd M Y H:i', strtotime( $e->created_at ) ) ); ?></td>
					<td><?php echo esc_html( $e->actor_type ); ?><?php echo $e->actor_id ? ' #' . (int) $e->actor_id : ''; ?></td>
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
			<tr><td colspan="6">No audit entries yet.</td></tr>
		<?php endif; ?>
		</tbody>
	</table>

	<?php if ( $total > $per_page ) : ?>
		<div class="tablenav bottom">
			<?php
			echo paginate_links( array(
				'base'    => add_query_arg( 'paged', '%#%' ),
				'format'  => '',
				'current' => $paged,
				'total'   => ceil( $total / $per_page ),
			) );
			?>
		</div>
	<?php endif; ?>
</div>
