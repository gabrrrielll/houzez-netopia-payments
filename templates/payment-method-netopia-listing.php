<?php
/**
 * Netopia payment method template for per-listing payments.
 *
 * @package    Houzez_Netopia
 * @subpackage Houzez_Netopia/templates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$enabled = get_option( 'houzez_netopia_enabled', '0' );
if ( $enabled !== '1' ) {
	return;
}

$enable_paypal = function_exists( 'houzez_option' ) ? houzez_option( 'enable_paypal' ) : 0;
$enable_stripe = function_exists( 'houzez_option' ) ? houzez_option( 'enable_stripe' ) : 0;
$enable_wireTransfer = function_exists( 'houzez_option' ) ? houzez_option( 'enable_wireTransfer' ) : 0;

$checked_netopia = '';
if ( $enable_paypal != 1 && $enable_stripe != 1 && $enable_wireTransfer != 1 ) {
	$checked_netopia = 'checked';
}
?>
<div class="payment-method-block netopia-method mb-2">
	<div class="form-group">
		<label class="control control--radio radio-tab d-flex align-items-center">
			<input type="radio" class="payment-netopia" name="houzez_payment_type" value="netopia" <?php echo esc_attr( $checked_netopia ); ?>>
			<span class="control-text ms-3 flex-grow-1"><strong><?php esc_html_e( 'Netopia Payments', 'houzez-netopia' ); ?></strong></span>
			<span class="ms-4"><?php esc_html_e( 'Visa, Mastercard, Maestro', 'houzez-netopia' ); ?></span>
			<span class="control__indicator start-0 end-auto"></span>
		</label>
	</div>
</div>

<!-- Netopia Card Form (hidden by default, shown when Netopia is selected) -->
<div id="netopia-card-form-listing" class="netopia-card-form" style="display: none;">
	<div class="row">
		<div class="col-md-12">
			<div class="form-group">
				<label for="netopia_card_number_listing"><?php esc_html_e( 'Card Number', 'houzez-netopia' ); ?></label>
				<input type="text" id="netopia_card_number_listing" name="netopia_card_number" class="form-control" placeholder="1234 5678 9012 3456" maxlength="19" required>
			</div>
		</div>
		<div class="col-md-4">
			<div class="form-group">
				<label for="netopia_exp_month_listing"><?php esc_html_e( 'Exp Month', 'houzez-netopia' ); ?></label>
				<select id="netopia_exp_month_listing" name="netopia_exp_month" class="form-control" required>
					<option value="" disabled selected><?php esc_html_e( 'Select Month', 'houzez-netopia' ); ?></option>
					<?php for ( $i = 1; $i <= 12; $i++ ) : ?>
						<option value="<?php echo esc_attr( sprintf( '%02d', $i ) ); ?>"><?php echo esc_html( sprintf( '%02d', $i ) ); ?></option>
					<?php endfor; ?>
				</select>
			</div>
		</div>
		<div class="col-md-4">
			<div class="form-group">
				<label for="netopia_exp_year_listing"><?php esc_html_e( 'Exp Year', 'houzez-netopia' ); ?></label>
				<select id="netopia_exp_year_listing" name="netopia_exp_year" class="form-control" required>
					<option value="" disabled selected><?php esc_html_e( 'Select Year', 'houzez-netopia' ); ?></option>
					<?php
					$current_year = intval( date( 'Y' ) );
					for ( $i = $current_year; $i <= $current_year + 10; $i++ ) :
						?>
						<option value="<?php echo esc_attr( $i ); ?>"><?php echo esc_html( $i ); ?></option>
					<?php endfor; ?>
				</select>
			</div>
		</div>
		<div class="col-md-4">
			<div class="form-group">
				<label for="netopia_cvv_listing"><?php esc_html_e( 'CVV', 'houzez-netopia' ); ?></label>
				<input type="text" id="netopia_cvv_listing" name="netopia_cvv" class="form-control" placeholder="123" maxlength="4" required>
			</div>
		</div>
	</div>
</div>

