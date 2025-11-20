<?php
/**
 * Settings page template.
 *
 * @package    Houzez_Netopia
 * @subpackage Houzez_Netopia/admin/partials
 */
?>

<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<form method="post" action="">
		<?php wp_nonce_field( 'houzez_netopia_settings' ); ?>

		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row">
						<label for="houzez_netopia_enabled"><?php esc_html_e( 'Enable Netopia Payments', 'houzez-netopia' ); ?></label>
					</th>
					<td>
						<input type="checkbox" id="houzez_netopia_enabled" name="houzez_netopia_enabled" value="1" <?php checked( $enabled, '1' ); ?>>
						<p class="description"><?php esc_html_e( 'Enable Netopia Payments as a payment gateway option.', 'houzez-netopia' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="houzez_netopia_sandbox"><?php esc_html_e( 'Sandbox Mode', 'houzez-netopia' ); ?></label>
					</th>
					<td>
						<input type="checkbox" id="houzez_netopia_sandbox" name="houzez_netopia_sandbox" value="1" <?php checked( $sandbox, '1' ); ?>>
						<p class="description"><?php esc_html_e( 'Enable sandbox mode for testing. Uncheck for live payments.', 'houzez-netopia' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="houzez_netopia_api_key_sandbox"><?php esc_html_e( 'API Key (Sandbox)', 'houzez-netopia' ); ?></label>
					</th>
					<td>
						<input type="text" id="houzez_netopia_api_key_sandbox" name="houzez_netopia_api_key_sandbox" value="<?php echo esc_attr( $api_key_sandbox ); ?>" class="regular-text">
						<p class="description"><?php esc_html_e( 'Your Netopia Payments API Key for Sandbox (testing) environment. You can generate it from your Netopia admin panel.', 'houzez-netopia' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="houzez_netopia_api_key_live"><?php esc_html_e( 'API Key (Live)', 'houzez-netopia' ); ?></label>
					</th>
					<td>
						<input type="text" id="houzez_netopia_api_key_live" name="houzez_netopia_api_key_live" value="<?php echo esc_attr( $api_key_live ); ?>" class="regular-text">
						<p class="description"><?php esc_html_e( 'Your Netopia Payments API Key for Live (production) environment. You can generate it from your Netopia admin panel.', 'houzez-netopia' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="houzez_netopia_signature"><?php esc_html_e( 'Signature ID', 'houzez-netopia' ); ?></label>
					</th>
					<td>
						<input type="text" id="houzez_netopia_signature" name="houzez_netopia_signature" value="<?php echo esc_attr( $signature ); ?>" class="regular-text" required>
						<p class="description"><?php esc_html_e( 'Your Netopia Payments Signature ID (POS Signature).', 'houzez-netopia' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="houzez_netopia_currency"><?php esc_html_e( 'Currency', 'houzez-netopia' ); ?></label>
					</th>
					<td>
						<select id="houzez_netopia_currency" name="houzez_netopia_currency">
							<option value="RON" <?php selected( $currency, 'RON' ); ?>>RON (Romanian Leu)</option>
							<option value="EUR" <?php selected( $currency, 'EUR' ); ?>>EUR (Euro)</option>
							<option value="USD" <?php selected( $currency, 'USD' ); ?>>USD (US Dollar)</option>
						</select>
						<p class="description"><?php esc_html_e( 'Currency for Netopia Payments transactions.', 'houzez-netopia' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>

		<?php submit_button( __( 'Save Settings', 'houzez-netopia' ), 'primary', 'houzez_netopia_save_settings' ); ?>
	</form>

	<div class="card" style="max-width: 800px; margin-top: 20px;">
		<h2><?php esc_html_e( 'Documentation', 'houzez-netopia' ); ?></h2>
		<p><?php esc_html_e( 'For more information about Netopia Payments integration, please visit:', 'houzez-netopia' ); ?></p>
		<ul>
			<li><a href="https://doc.netopia-payments.com/docs/payment-api/v2.x/intro" target="_blank"><?php esc_html_e( 'Netopia Payments API Documentation', 'houzez-netopia' ); ?></a></li>
			<li><a href="https://doc.netopia-payments.com/docs/payment-sdks/php" target="_blank"><?php esc_html_e( 'Netopia Payments PHP SDK', 'houzez-netopia' ); ?></a></li>
		</ul>
	</div>
</div>

