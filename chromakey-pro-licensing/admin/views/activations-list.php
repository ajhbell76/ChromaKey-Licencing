<?php if ( ! defined( 'ABSPATH' ) ) exit;

// Sub-view: validation log for a specific activation.
$view          = isset( $_GET['view'] ) ? sanitize_key( $_GET['view'] ) : 'list';
$activation_id = isset( $_GET['activation_id'] ) ? (int) $_GET['activation_id'] : 0;

if ( $view === 'log' && $activation_id ) {
	require CKP_PLUGIN_DIR . 'admin/views/activation-log.php';
	return;
}

$table      = new CKP_Activations_Table();
$table->prepare_items();

$filter_licence_id = isset( $_GET['licence_id'] ) ? (int) $_GET['licence_id'] : 0;
$filter_status     = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
?>

<div class="wrap ckp-wrap">
	<h1 class="wp-heading-inline">Activations</h1>
	<hr class="wp-header-end">

	<?php if ( isset( $_GET['ckp_msg'] ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html( urldecode( $_GET['ckp_msg'] ) ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( $filter_licence_id ) : ?>
		<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=ckp-activations' ) ); ?>">&larr; Show all activations</a></p>
	<?php endif; ?>

	<form method="get">
		<input type="hidden" name="page" value="ckp-activations">
		<?php if ( $filter_licence_id ) : ?>
			<input type="hidden" name="licence_id" value="<?php echo esc_attr( $filter_licence_id ); ?>">
		<?php endif; ?>

		<div style="display:flex; gap:8px; align-items:center; margin-bottom:8px;">
			<select name="status" onchange="this.form.submit()">
				<option value="">All statuses</option>
				<?php foreach ( array( 'active', 'deactivated', 'revoked' ) as $s ) : ?>
					<option value="<?php echo esc_attr( $s ); ?>" <?php selected( $filter_status, $s ); ?>>
						<?php echo esc_html( ucfirst( $s ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>
	</form>

	<form method="post">
		<?php $table->display(); ?>
	</form>
</div>
