<?php if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;

$licences_table    = CKP_DB::table( 'licences' );
$activations_table = CKP_DB::table( 'activations' );
$accounts_table    = CKP_DB::table( 'accounts' );
$val_table         = CKP_DB::table( 'validation_log' );

$active_licences   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$licences_table` WHERE status = 'active'" );
$expiring_soon     = (int) $wpdb->get_var( $wpdb->prepare(
	"SELECT COUNT(*) FROM `$licences_table` WHERE status = 'active' AND expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL %d DAY)",
	14
) );
$active_activations = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$activations_table` WHERE status = 'active'" );
$failed_activations = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$val_table` WHERE result = 'failed'" );
$total_customers   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$accounts_table`" );
?>

<div class="wrap ckp-wrap">
	<h1>ChromaKey Licensing &mdash; Dashboard</h1>

	<div class="ckp-dashboard-grid">
		<div class="ckp-stat-card">
			<span class="ckp-stat-number"><?php echo $active_licences; ?></span>
			<span class="ckp-stat-label">Active Licences</span>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=ckp-licences' ) ); ?>">View all &rarr;</a>
		</div>
		<div class="ckp-stat-card <?php echo $expiring_soon ? 'ckp-stat-warning' : ''; ?>">
			<span class="ckp-stat-number"><?php echo $expiring_soon; ?></span>
			<span class="ckp-stat-label">Expiring in 14 Days</span>
		</div>
		<div class="ckp-stat-card">
			<span class="ckp-stat-number"><?php echo $active_activations; ?></span>
			<span class="ckp-stat-label">Active Machines</span>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=ckp-activations' ) ); ?>">View all &rarr;</a>
		</div>
		<div class="ckp-stat-card <?php echo $failed_activations ? 'ckp-stat-alert' : ''; ?>">
			<span class="ckp-stat-number"><?php echo $failed_activations; ?></span>
			<span class="ckp-stat-label">Failed Activations (total)</span>
		</div>
		<div class="ckp-stat-card">
			<span class="ckp-stat-number"><?php echo $total_customers; ?></span>
			<span class="ckp-stat-label">Customers</span>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=ckp-customers' ) ); ?>">View all &rarr;</a>
		</div>
	</div>

	<h2>Quick Actions</h2>
	<p>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=ckp-customers&action=new' ) ); ?>" class="button button-primary">Add Customer</a>
		&nbsp;
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=ckp-licences&action=new' ) ); ?>" class="button button-primary">Create Licence</a>
		&nbsp;
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=ckp-audit-log' ) ); ?>" class="button">Audit Log</a>
	</p>
</div>
