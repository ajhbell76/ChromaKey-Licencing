<?php if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;

$activation_id = (int) ( $_GET['activation_id'] ?? 0 );
$atbl          = CKP_DB::table( 'activations' );
$vtbl          = CKP_DB::table( 'validation_log' );
$acctbl        = CKP_DB::table( 'accounts' );
$ltbl          = CKP_DB::table( 'licences' );

$activation = $wpdb->get_row( $wpdb->prepare(
	"SELECT a.*, acc.email, l.licence_key_last4
	FROM `$atbl` a
	LEFT JOIN `$acctbl` acc ON acc.id = a.account_id
	LEFT JOIN `$ltbl` l ON l.id = a.licence_id
	WHERE a.id = %d LIMIT 1",
	$activation_id
) );

if ( ! $activation ) {
	echo '<div class="wrap"><p>Activation not found.</p></div>';
	return;
}

$per_page = 50;
$paged    = max( 1, isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1 );
$offset   = ( $paged - 1 ) * $per_page;

$log_entries = $wpdb->get_results( $wpdb->prepare(
	"SELECT * FROM `$vtbl` WHERE activation_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
	$activation_id, $per_page, $offset
) );
$total = (int) $wpdb->get_var( $wpdb->prepare(
	"SELECT COUNT(*) FROM `$vtbl` WHERE activation_id = %d",
	$activation_id
) );
?>

<div class="wrap ckp-wrap">
	<h1>Validation Log &mdash; <?php echo esc_html( $activation->computer_name ?: 'Unknown machine' ); ?></h1>
	<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=ckp-activations' ) ); ?>">&larr; Back to Activations</a></p>

	<table class="widefat fixed" style="margin-bottom:16px;">
		<tr>
			<th style="width:140px;">Customer</th>
			<td><?php echo esc_html( $activation->email ); ?></td>
			<th style="width:140px;">Licence</th>
			<td><code>CKP-BETA-****-****-<?php echo esc_html( $activation->licence_key_last4 ); ?></code></td>
		</tr>
		<tr>
			<th>Computer</th>
			<td><?php echo esc_html( $activation->computer_name ); ?></td>
			<th>OS</th>
			<td><?php echo esc_html( $activation->os_name ); ?></td>
		</tr>
		<tr>
			<th>App Version</th>
			<td><?php echo esc_html( $activation->app_version ); ?></td>
			<th>Status</th>
			<td><span class="ckp-badge <?php echo $activation->status === 'active' ? 'ckp-badge-active' : 'ckp-badge-inactive'; ?>">
				<?php echo esc_html( $activation->status ); ?>
			</span></td>
		</tr>
		<tr>
			<th>First Activated</th>
			<td><?php echo esc_html( date_i18n( 'd M Y H:i', strtotime( $activation->first_activated_at ) ) ); ?> UTC</td>
			<th>Last Validated</th>
			<td><?php echo esc_html( date_i18n( 'd M Y H:i', strtotime( $activation->last_validated_at ) ) ); ?> UTC</td>
		</tr>
	</table>

	<h2>Validation Attempts (<?php echo $total; ?>)</h2>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th style="width:150px;">When (UTC)</th>
				<th style="width:80px;">Result</th>
				<th style="width:120px;">Reason</th>
				<th style="width:80px;">App Ver</th>
				<th>IP Address</th>
			</tr>
		</thead>
		<tbody>
		<?php if ( $log_entries ) : ?>
			<?php foreach ( $log_entries as $entry ) : ?>
				<tr>
					<td><?php echo esc_html( date_i18n( 'd M Y H:i:s', strtotime( $entry->created_at ) ) ); ?></td>
					<td>
						<?php if ( $entry->result === 'success' ) : ?>
							<span class="ckp-badge ckp-badge-active">success</span>
						<?php else : ?>
							<span class="ckp-badge ckp-badge-inactive">failed</span>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( $entry->reason ?: '—' ); ?></td>
					<td><?php echo esc_html( $entry->app_version ?: '—' ); ?></td>
					<td><?php echo esc_html( $entry->ip_address ?: '—' ); ?></td>
				</tr>
			<?php endforeach; ?>
		<?php else : ?>
			<tr><td colspan="5">No validation attempts recorded yet.</td></tr>
		<?php endif; ?>
		</tbody>
	</table>

	<?php if ( $total > $per_page ) : ?>
		<div class="tablenav bottom">
			<?php echo paginate_links( array(
				'base'    => add_query_arg( 'paged', '%#%' ),
				'current' => $paged,
				'total'   => ceil( $total / $per_page ),
			) ); ?>
		</div>
	<?php endif; ?>
</div>
