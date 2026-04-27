<?php if ( ! defined( 'ABSPATH' ) ) exit;

$table = new CKP_Licences_Table();
$table->prepare_items();

$filter_account_id = isset( $_GET['account_id'] ) ? (int) $_GET['account_id'] : 0;
?>

<div class="wrap ckp-wrap">
	<h1 class="wp-heading-inline">Licences</h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=ckp-licences&action=new' . ( $filter_account_id ? '&account_id=' . $filter_account_id : '' ) ) ); ?>"
		class="page-title-action">Create Licence</a>
	<hr class="wp-header-end">

	<?php if ( isset( $_GET['ckp_msg'] ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html( urldecode( $_GET['ckp_msg'] ) ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( $filter_account_id ) : ?>
		<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=ckp-licences' ) ); ?>">&larr; Show all licences</a></p>
	<?php endif; ?>

	<form method="get">
		<input type="hidden" name="page" value="ckp-licences">
		<?php if ( $filter_account_id ) : ?>
			<input type="hidden" name="account_id" value="<?php echo esc_attr( $filter_account_id ); ?>">
		<?php endif; ?>
		<?php $table->search_box( 'Search by email', 'ckp_licence_search' ); ?>
	</form>

	<form method="post">
		<?php $table->display(); ?>
	</form>
</div>
