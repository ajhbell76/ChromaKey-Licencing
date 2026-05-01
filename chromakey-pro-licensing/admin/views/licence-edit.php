<?php if ( ! defined( 'ABSPATH' ) ) exit;

$id      = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
$licence = $id ? CKP_Admin_Menu::get_licence( $id ) : null;
$is_new  = ! $licence;
$title   = $is_new ? 'Create Licence' : 'Edit Licence';

// Pre-fill account from query param when creating from customer page.
$preselect_account_id = isset( $_GET['account_id'] ) ? (int) $_GET['account_id'] : 0;
$preselect_customer   = $preselect_account_id ? CKP_Admin_Menu::get_customer( $preselect_account_id ) : null;

$error = isset( $_GET['ckp_error'] ) ? sanitize_text_field( urldecode( $_GET['ckp_error'] ) ) : '';
$msg   = isset( $_GET['ckp_msg'] )   ? sanitize_text_field( urldecode( $_GET['ckp_msg'] ) )   : '';

// Show raw key once via transient (set immediately after creation).
$raw_key = '';
if ( $id && isset( $_GET['new_key'] ) ) {
	$raw_key = get_transient( 'ckp_rawkey_' . $id );
	if ( $raw_key ) {
		delete_transient( 'ckp_rawkey_' . $id );
	}
}

$default_limit    = CKP_Settings::get( 'default_activation_limit', '2' );
$default_interval = CKP_Settings::get( 'default_validation_interval', '30' );
$default_grace    = CKP_Settings::get( 'default_grace_period', '7' );
?>

<div class="wrap ckp-wrap">
	<h1><?php echo esc_html( $title ); ?></h1>

	<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=ckp-licences' ) ); ?>">&larr; Back to Licences</a></p>

	<?php if ( $raw_key ) : ?>
		<div class="notice notice-warning ckp-key-reveal">
			<p><strong>Copy this licence key now — it will not be shown again.</strong></p>
			<p class="ckp-raw-key"><code><?php echo esc_html( $raw_key ); ?></code>
				<button type="button" class="button ckp-copy-key" data-key="<?php echo esc_attr( $raw_key ); ?>">Copy</button>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $msg ); ?></p></div>
	<?php endif; ?>

	<?php if ( $error ) : ?>
		<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'ckp_save_licence', 'ckp_nonce' ); ?>
		<input type="hidden" name="action" value="ckp_save_licence">
		<input type="hidden" name="licence_id" value="<?php echo esc_attr( $id ); ?>">

		<table class="form-table" role="presentation">
			<tr>
				<th><label for="ckp_account_email">Customer Email <span class="required">*</span></label></th>
				<td>
					<?php if ( $is_new ) : ?>
						<input type="email" id="ckp_account_email" name="ckp_account_email" required
							value="<?php echo esc_attr( $preselect_customer->email ?? '' ); ?>"
							class="regular-text">
						<p class="description">Must match an existing customer. <a href="<?php echo esc_url( admin_url( 'admin.php?page=ckp-customers&action=new' ) ); ?>">Add customer</a></p>
					<?php else : ?>
						<input type="text" value="<?php echo esc_attr( $licence->email ?? '' ); ?>" class="regular-text" readonly>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th><label for="ckp_plan_code">Plan</label></th>
				<td>
					<select id="ckp_plan_code" name="ckp_plan_code">
						<?php foreach ( array( 'beta', 'trial', 'pro' ) as $plan ) : ?>
							<option value="<?php echo esc_attr( $plan ); ?>"
								<?php selected( $licence->plan_code ?? 'beta', $plan ); ?>>
								<?php echo esc_html( ucfirst( $plan ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<?php if ( ! $is_new ) : ?>
			<tr>
				<th><label for="ckp_status">Status</label></th>
				<td>
					<select id="ckp_status" name="ckp_status">
						<?php foreach ( array( 'active', 'suspended', 'revoked', 'expired' ) as $s ) : ?>
							<option value="<?php echo esc_attr( $s ); ?>"
								<?php selected( $licence->status ?? 'active', $s ); ?>>
								<?php echo esc_html( ucfirst( $s ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<?php endif; ?>
			<tr>
				<th><label for="ckp_activation_limit">Activation Limit</label></th>
				<td>
					<input type="number" id="ckp_activation_limit" name="ckp_activation_limit" min="1" max="99"
						value="<?php echo esc_attr( $licence->activation_limit ?? $default_limit ); ?>"
						class="small-text">
					<p class="description">Maximum number of machines that can activate this licence.</p>
				</td>
			</tr>
			<tr>
				<th><label for="ckp_starts_at">Start Date</label></th>
				<td>
					<input type="date" id="ckp_starts_at" name="ckp_starts_at"
						value="<?php
							$starts = $licence->starts_at ?? '';
							echo esc_attr( $starts && $starts !== '0000-00-00 00:00:00' ? date( 'Y-m-d', strtotime( $starts ) ) : date( 'Y-m-d' ) );
						?>">
				</td>
			</tr>
			<tr>
				<th><label for="ckp_expires_at">Expiry Date <span class="required">*</span></label></th>
				<td>
					<input type="date" id="ckp_expires_at" name="ckp_expires_at" required
						value="<?php
							$expires = $licence->expires_at ?? '';
							echo esc_attr( $expires && $expires !== '0000-00-00 00:00:00' ? date( 'Y-m-d', strtotime( $expires ) ) : '' );
						?>">
				</td>
			</tr>
			<tr>
				<th><label for="ckp_validation_interval_days">Validation Interval (days)</label></th>
				<td>
					<input type="number" id="ckp_validation_interval_days" name="ckp_validation_interval_days" min="1" max="365"
						value="<?php echo esc_attr( $licence->validation_interval_days ?? $default_interval ); ?>"
						class="small-text">
				</td>
			</tr>
			<tr>
				<th><label for="ckp_grace_period_days">Grace Period (days)</label></th>
				<td>
					<input type="number" id="ckp_grace_period_days" name="ckp_grace_period_days" min="0" max="30"
						value="<?php echo esc_attr( $licence->grace_period_days ?? $default_grace ); ?>"
						class="small-text">
				</td>
			</tr>
			<tr>
				<th><label for="ckp_notes">Internal Notes</label></th>
				<td>
					<textarea id="ckp_notes" name="ckp_notes" rows="3" class="large-text"><?php echo esc_textarea( $licence->notes ?? '' ); ?></textarea>
				</td>
			</tr>
		<?php if ( $is_new ) : ?>
			<tr>
				<th><label for="ckp_send_email">Email Key to Customer</label></th>
				<td>
					<input type="checkbox" id="ckp_send_email" name="ckp_send_email" value="1" checked>
					<label for="ckp_send_email">Send the licence key to the customer by email on creation</label>
				</td>
			</tr>
		<?php endif; ?>
		</table>

		<?php submit_button( $is_new ? 'Create Licence &amp; Generate Key' : 'Save Changes' ); ?>
	</form>

	<?php if ( ! $is_new ) : ?>
		<hr>
		<p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=ckp-activations&licence_id=' . $id ) ); ?>"
				class="button">View Activations</a>
			&nbsp;
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
				<?php wp_nonce_field( 'ckp_reissue_key_' . $id, 'ckp_nonce' ); ?>
				<input type="hidden" name="action" value="ckp_reissue_licence_key">
				<input type="hidden" name="licence_id" value="<?php echo esc_attr( $id ); ?>">
				<button type="submit" class="button ckp-reissue-key">Re-issue Key &amp; Email Customer</button>
			</form>
		</p>
	<?php endif; ?>
</div>
