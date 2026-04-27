<?php if ( ! defined( 'ABSPATH' ) ) exit;

$id       = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
$customer = $id ? CKP_Admin_Menu::get_customer( $id ) : null;
$is_new   = ! $customer;
$title    = $is_new ? 'Add Customer' : 'Edit Customer';

$error = isset( $_GET['ckp_error'] ) ? sanitize_text_field( urldecode( $_GET['ckp_error'] ) ) : '';
?>

<div class="wrap ckp-wrap">
	<h1><?php echo esc_html( $title ); ?></h1>

	<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=ckp-customers' ) ); ?>">&larr; Back to Customers</a></p>

	<?php if ( $error ) : ?>
		<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'ckp_save_customer', 'ckp_nonce' ); ?>
		<input type="hidden" name="action" value="ckp_save_customer">
		<input type="hidden" name="customer_id" value="<?php echo esc_attr( $id ); ?>">

		<table class="form-table" role="presentation">
			<tr>
				<th><label for="ckp_email">Email <span class="required">*</span></label></th>
				<td>
					<input type="email" id="ckp_email" name="ckp_email" required
						value="<?php echo esc_attr( $customer->email ?? '' ); ?>"
						class="regular-text" <?php echo ! $is_new ? 'readonly' : ''; ?>>
					<?php if ( ! $is_new ) : ?>
						<p class="description">Email cannot be changed after creation.</p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th><label for="ckp_display_name">Display Name</label></th>
				<td>
					<input type="text" id="ckp_display_name" name="ckp_display_name"
						value="<?php echo esc_attr( $customer->display_name ?? '' ); ?>"
						class="regular-text">
				</td>
			</tr>
			<tr>
				<th><label for="ckp_company_name">Company</label></th>
				<td>
					<input type="text" id="ckp_company_name" name="ckp_company_name"
						value="<?php echo esc_attr( $customer->company_name ?? '' ); ?>"
						class="regular-text">
				</td>
			</tr>
			<?php if ( ! $is_new ) : ?>
			<tr>
				<th><label for="ckp_status">Status</label></th>
				<td>
					<select id="ckp_status" name="ckp_status">
						<option value="active" <?php selected( $customer->status ?? 'active', 'active' ); ?>>Active</option>
						<option value="disabled" <?php selected( $customer->status ?? 'active', 'disabled' ); ?>>Disabled</option>
					</select>
				</td>
			</tr>
			<?php endif; ?>
			<tr>
				<th><label for="ckp_notes">Internal Notes</label></th>
				<td>
					<textarea id="ckp_notes" name="ckp_notes" rows="4" class="large-text"><?php echo esc_textarea( $customer->notes ?? '' ); ?></textarea>
				</td>
			</tr>
		</table>

		<?php submit_button( $is_new ? 'Add Customer' : 'Save Changes' ); ?>
	</form>

	<?php if ( ! $is_new ) : ?>
		<hr>
		<h2>Licences</h2>
		<p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=ckp-licences&action=new&account_id=' . $id ) ); ?>"
				class="button button-primary">Add Licence</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=ckp-licences&account_id=' . $id ) ); ?>"
				class="button">View All Licences</a>
		</p>
	<?php endif; ?>
</div>
