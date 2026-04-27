<?php if ( ! defined( 'ABSPATH' ) ) exit;

$table = new CKP_Customers_Table();
$table->prepare_items();
?>

<div class="wrap ckp-wrap">
	<h1 class="wp-heading-inline">Customers</h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=ckp-customers&action=new' ) ); ?>"
		class="page-title-action">Add Customer</a>
	<hr class="wp-header-end">

	<?php if ( isset( $_GET['ckp_msg'] ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html( urldecode( $_GET['ckp_msg'] ) ); ?></p>
		</div>
	<?php endif; ?>

	<form method="get">
		<input type="hidden" name="page" value="ckp-customers">
		<?php $table->search_box( 'Search customers', 'ckp_customer_search' ); ?>
	</form>

	<form method="post">
		<?php $table->display(); ?>
	</form>
</div>
