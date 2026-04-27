<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="wrap ckp-wrap">
	<h1>Settings</h1>

	<?php if ( isset( $_GET['updated'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['keys_generated'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p>Signing key pair generated successfully.</p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['key_error'] ) ) : ?>
		<div class="notice notice-error is-dismissible"><p><strong>Key generation failed:</strong> <?php echo esc_html( urldecode( $_GET['key_error'] ) ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'ckp_save_settings', 'ckp_nonce' ); ?>
		<input type="hidden" name="action" value="ckp_save_settings">

		<table class="form-table" role="presentation">
			<tr>
				<th><label for="ckp_product_code">Product Code</label></th>
				<td>
					<input type="text" id="ckp_product_code" name="ckp_product_code"
						value="<?php echo esc_attr( CKP_Settings::get( 'product_code', CKP_PRODUCT_CODE ) ); ?>"
						class="regular-text" readonly>
					<p class="description">Keep this stable — the desktop app depends on it.</p>
				</td>
			</tr>
			<tr>
				<th><label for="ckp_default_activation_limit">Default Activation Limit</label></th>
				<td>
					<input type="number" id="ckp_default_activation_limit" name="ckp_default_activation_limit"
						value="<?php echo esc_attr( CKP_Settings::get( 'default_activation_limit', '2' ) ); ?>"
						min="1" max="99" class="small-text">
					<p class="description">Number of machines allowed per licence by default.</p>
				</td>
			</tr>
			<tr>
				<th><label for="ckp_default_validation_interval">Validation Interval (days)</label></th>
				<td>
					<input type="number" id="ckp_default_validation_interval" name="ckp_default_validation_interval"
						value="<?php echo esc_attr( CKP_Settings::get( 'default_validation_interval', '30' ) ); ?>"
						min="1" max="365" class="small-text">
					<p class="description">How often the desktop app must check in with the server.</p>
				</td>
			</tr>
			<tr>
				<th><label for="ckp_default_grace_period">Grace Period (days)</label></th>
				<td>
					<input type="number" id="ckp_default_grace_period" name="ckp_default_grace_period"
						value="<?php echo esc_attr( CKP_Settings::get( 'default_grace_period', '7' ) ); ?>"
						min="0" max="30" class="small-text">
					<p class="description">Extra days the app can run offline after validation is overdue.</p>
				</td>
			</tr>
			<tr>
				<th><label for="ckp_api_enabled">API Enabled</label></th>
				<td>
					<input type="checkbox" id="ckp_api_enabled" name="ckp_api_enabled" value="1"
						<?php checked( CKP_Settings::get( 'api_enabled', '1' ), '1' ); ?>>
					<label for="ckp_api_enabled">Allow the desktop app to call the licensing API</label>
				</td>
			</tr>
			<tr>
				<th><label for="ckp_debug_logging">Debug Logging</label></th>
				<td>
					<input type="checkbox" id="ckp_debug_logging" name="ckp_debug_logging" value="1"
						<?php checked( CKP_Settings::get( 'debug_logging', '0' ), '1' ); ?>>
					<label for="ckp_debug_logging">Write verbose entries to the WordPress debug log</label>
				</td>
			</tr>
			<tr>
				<th>Signing Key</th>
				<td>
					<?php $pub = CKP_Settings::get( 'signing_public_key', '' ); ?>
					<?php if ( $pub ) : ?>
						<span class="ckp-key-status ckp-key-ok">&#10003; Key pair generated</span>
						<p class="description">
							Copy the public key below to embed it in your desktop app build.
						</p>
						<textarea class="large-text code" rows="8" readonly><?php echo esc_textarea( $pub ); ?></textarea>
					<?php else : ?>
						<span class="ckp-key-status ckp-key-missing">&#10007; No key pair found</span>
					<?php endif; ?>
					<p style="margin-top:8px;">
						<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ckp_generate_keys' ), 'ckp_generate_keys', 'ckp_nonce' ) ); ?>"
							class="button"
							onclick="return confirm('<?php echo $pub ? 'This will replace the existing key pair. Any desktop apps holding the old public key will stop validating. Continue?' : 'Generate a new signing key pair?'; ?>');">
							<?php echo $pub ? 'Regenerate Keys' : 'Generate Keys'; ?>
						</a>
					</p>
				</td>
			</tr>
		</table>

		<?php submit_button( 'Save Settings' ); ?>
	</form>
</div>
