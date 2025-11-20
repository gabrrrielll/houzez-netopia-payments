<?php
/**
 * Payment processor class for handling payment data preparation.
 *
 * @package    Houzez_Netopia
 * @subpackage Houzez_Netopia/includes
 */
class Houzez_Netopia_Payment_Processor {

	/**
	 * Netopia API instance.
	 *
	 * @var Houzez_Netopia_API
	 */
	private $api;

	/**
	 * Initialize the payment processor.
	 */
	public function __construct() {
		$this->api = new Houzez_Netopia_API();
	}

	/**
	 * Prepare payment data for membership package.
	 *
	 * @param int   $package_id Package post ID.
	 * @param int   $user_id User ID.
	 * @param array $card_data Card data from form.
	 * @return array Payment data array.
	 */
	public function prepare_package_payment( $package_id, $user_id, $card_data ) {
		$user = get_userdata( $user_id );
		$package_price = get_post_meta( $package_id, 'fave_package_price', true );
		$package_tax = get_post_meta( $package_id, 'fave_package_tax', true );

		// Calculate tax
		$tax_amount = 0;
		if ( ! empty( $package_tax ) && ! empty( $package_price ) ) {
			$tax_amount = ( floatval( $package_tax ) / 100 ) * floatval( $package_price );
			$tax_amount = round( $tax_amount, 2 );
		}

		$total_price = floatval( $package_price ) + $tax_amount;

		// Generate unique order ID
		$order_id = 'PKG_' . $package_id . '_' . $user_id . '_' . time();

		// Get user billing information
		$billing_data = $this->get_user_billing_data( $user_id );

		// Prepare config
		$notify_url = add_query_arg( array(
			'houzez_netopia_ipn' => '1',
			'type' => 'package',
		), home_url( '/' ) );

		$redirect_url = add_query_arg( array(
			'houzez_netopia_return' => '1',
			'type' => 'package',
			'package_id' => $package_id,
			'order_id' => $order_id,
		), home_url( '/' ) );

		$config = array(
			'notifyUrl' => $notify_url,
			'redirectUrl' => $redirect_url,
			'language' => 'RO',
		);

		// Prepare payment instrument
		$instrument = array(
			'type' => 'card',
			'account' => sanitize_text_field( $card_data['card_number'] ),
			'expMonth' => intval( $card_data['exp_month'] ),
			'expYear' => intval( $card_data['exp_year'] ),
			'secretCode' => sanitize_text_field( $card_data['cvv'] ),
			'token' => null,
		);

		// Prepare payment data
		$payment_data = array(
			'options' => array(
				'installments' => 1,
				'bonus' => 0,
			),
			'instrument' => $instrument,
			'data' => $this->get_browser_data(),
		);

		// Prepare order data
		$order_data = array(
			'ntpID' => '',
			'posSignature' => $this->api->get_signature(),
			'dateTime' => date( 'c' ),
			'description' => 'Membership Package: ' . get_the_title( $package_id ),
			'orderID' => $order_id,
			'amount' => round( $total_price, 2 ),
			'currency' => 'RON',
			'billing' => $billing_data,
			'shipping' => $billing_data,
			'products' => array(
				array(
					'name' => get_the_title( $package_id ),
					'code' => 'PKG-' . $package_id,
					'category' => 'Membership',
					'price' => round( $total_price, 2 ),
					'vat' => round( $tax_amount, 2 ),
				),
			),
		);

		// Prepare 3D Secure data (if needed)
		$three_d_secure_data = array();

		// Store temporary transaction data
		$transaction_data = array(
			'type' => 'package',
			'package_id' => $package_id,
			'user_id' => $user_id,
			'amount' => $total_price,
			'tax' => $tax_amount,
			'order_id' => $order_id,
			'timestamp' => time(),
		);

		// Store in transient (expires in 1 hour)
		set_transient( 'houzez_netopia_' . $order_id, $transaction_data, HOUR_IN_SECONDS );

		return array(
			'config' => $config,
			'payment' => $payment_data,
			'order' => $order_data,
		);
	}

	/**
	 * Prepare payment data for listing payment.
	 *
	 * @param int   $property_id Property post ID.
	 * @param int   $user_id User ID.
	 * @param bool  $is_featured Whether listing is featured.
	 * @param bool  $is_upgrade Whether this is an upgrade to featured.
	 * @param array $card_data Card data from form.
	 * @return array Payment data array.
	 */
	public function prepare_listing_payment( $property_id, $user_id, $is_featured, $is_upgrade, $card_data ) {
		$user = get_userdata( $user_id );
		
		// Get prices
		$price_per_submission = 0;
		$price_featured_submission = 0;
		$tax_percentage_per_listing = 0;
		$tax_percentage_featured = 0;
		
		if ( function_exists( 'houzez_option' ) ) {
			$price_per_submission = floatval( houzez_option( 'price_listing_submission', 0 ) );
			$price_featured_submission = floatval( houzez_option( 'price_featured_listing_submission', 0 ) );
			$tax_percentage_per_listing = floatval( houzez_option( 'tax_percentage_per_listing', 0 ) );
			$tax_percentage_featured = floatval( houzez_option( 'tax_percentage_featured', 0 ) );
		}
		
		// Calculate taxes
		$tax_per_listing = 0;
		$tax_featured = 0;
		
		if ( ! empty( $tax_percentage_per_listing ) && ! empty( $price_per_submission ) ) {
			$tax_per_listing = ( $tax_percentage_per_listing / 100 ) * $price_per_submission;
			$tax_per_listing = round( $tax_per_listing, 2 );
		}
		
		if ( ! empty( $tax_percentage_featured ) && ! empty( $price_featured_submission ) ) {
			$tax_featured = ( $tax_percentage_featured / 100 ) * $price_featured_submission;
			$tax_featured = round( $tax_featured, 2 );
		}
		
		// Calculate total
		if ( $is_upgrade ) {
			$total_price = $price_featured_submission + $tax_featured;
			$total_taxes = $tax_featured;
			$description = 'Upgrade to Featured';
		} else {
			if ( $is_featured ) {
				$total_price = $price_per_submission + $tax_per_listing + $price_featured_submission + $tax_featured;
				$total_taxes = $tax_per_listing + $tax_featured;
				$description = 'Publish Listing with Featured';
			} else {
				$total_price = $price_per_submission + $tax_per_listing;
				$total_taxes = $tax_per_listing;
				$description = 'Listing';
			}
		}

		// Generate unique order ID
		$order_id = 'LST_' . $property_id . '_' . $user_id . '_' . time();

		// Get user billing information
		$billing_data = $this->get_user_billing_data( $user_id );

		// Prepare config
		$notify_url = add_query_arg( array(
			'houzez_netopia_ipn' => '1',
			'type' => 'listing',
		), home_url( '/' ) );

		$redirect_url = add_query_arg( array(
			'houzez_netopia_return' => '1',
			'type' => 'listing',
			'property_id' => $property_id,
			'is_featured' => $is_featured ? '1' : '0',
			'is_upgrade' => $is_upgrade ? '1' : '0',
			'order_id' => $order_id,
		), home_url( '/' ) );

		$config = array(
			'notifyUrl' => $notify_url,
			'redirectUrl' => $redirect_url,
			'language' => 'RO',
		);

		// Prepare payment instrument
		$instrument = array(
			'type' => 'card',
			'account' => sanitize_text_field( $card_data['card_number'] ),
			'expMonth' => intval( $card_data['exp_month'] ),
			'expYear' => intval( $card_data['exp_year'] ),
			'secretCode' => sanitize_text_field( $card_data['cvv'] ),
			'token' => null,
		);

		// Prepare payment data
		$payment_data = array(
			'options' => array(
				'installments' => 1,
				'bonus' => 0,
			),
			'instrument' => $instrument,
			'data' => $this->get_browser_data(),
		);

		// Prepare order data
		$order_data = array(
			'ntpID' => '',
			'posSignature' => $this->api->get_signature(),
			'dateTime' => date( 'c' ),
			'description' => $description . ': ' . get_the_title( $property_id ),
			'orderID' => $order_id,
			'amount' => round( $total_price, 2 ),
			'currency' => 'RON',
			'billing' => $billing_data,
			'shipping' => $billing_data,
			'products' => $this->prepare_listing_products( $is_featured, $is_upgrade, $price_per_submission, $price_featured_submission, $tax_per_listing, $tax_featured ),
		);

		// Store temporary transaction data
		$transaction_data = array(
			'type' => 'listing',
			'property_id' => $property_id,
			'user_id' => $user_id,
			'amount' => $total_price,
			'tax' => $total_taxes,
			'order_id' => $order_id,
			'is_featured' => $is_featured,
			'is_upgrade' => $is_upgrade,
			'timestamp' => time(),
		);

		// Store in transient (expires in 1 hour)
		set_transient( 'houzez_netopia_' . $order_id, $transaction_data, HOUR_IN_SECONDS );

		return array(
			'config' => $config,
			'payment' => $payment_data,
			'order' => $order_data,
		);
	}

	/**
	 * Get user billing data.
	 *
	 * @param int $user_id User ID.
	 * @return array Billing data array.
	 */
	private function get_user_billing_data( $user_id ) {
		$user = get_userdata( $user_id );
		
		$first_name = get_user_meta( $user_id, 'first_name', true );
		$last_name = get_user_meta( $user_id, 'last_name', true );
		$phone = get_user_meta( $user_id, 'fave_author_mobile', true );
		
		if ( empty( $first_name ) ) {
			$first_name = $user->first_name;
		}
		if ( empty( $last_name ) ) {
			$last_name = $user->last_name;
		}
		if ( empty( $phone ) ) {
			$phone = get_user_meta( $user_id, 'fave_author_phone', true );
		}

		return array(
			'email' => $user->user_email,
			'phone' => ! empty( $phone ) ? $phone : '0000000000',
			'firstName' => ! empty( $first_name ) ? $first_name : 'User',
			'lastName' => ! empty( $last_name ) ? $last_name : 'Name',
			'city' => get_user_meta( $user_id, 'fave_author_city', true ) ?: 'Bucharest',
			'country' => 642, // Romania country code
			'state' => get_user_meta( $user_id, 'fave_author_state', true ) ?: 'Bucharest',
			'postalCode' => get_user_meta( $user_id, 'fave_author_postcode', true ) ?: '000000',
			'details' => '',
		);
	}

	/**
	 * Get browser data for 3D Secure.
	 *
	 * @return array Browser data array.
	 */
	private function get_browser_data() {
		return array(
			'BROWSER_USER_AGENT' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ) : '',
			'OS' => $this->get_os(),
			'OS_VERSION' => '',
			'MOBILE' => wp_is_mobile() ? 'true' : 'false',
			'SCREEN_POINT' => 'false',
			'SCREEN_PRINT' => $this->get_screen_info(),
			'BROWSER_COLOR_DEPTH' => '24',
			'BROWSER_SCREEN_HEIGHT' => isset( $_SERVER['HTTP_SCREEN_HEIGHT'] ) ? sanitize_text_field( $_SERVER['HTTP_SCREEN_HEIGHT'] ) : '1080',
			'BROWSER_SCREEN_WIDTH' => isset( $_SERVER['HTTP_SCREEN_WIDTH'] ) ? sanitize_text_field( $_SERVER['HTTP_SCREEN_WIDTH'] ) : '1920',
			'BROWSER_PLUGINS' => '',
			'BROWSER_JAVA_ENABLED' => 'false',
			'BROWSER_LANGUAGE' => get_locale(),
			'BROWSER_TZ' => wp_timezone_string(),
			'BROWSER_TZ_OFFSET' => (string) ( get_option( 'gmt_offset' ) * -60 ),
			'IP_ADDRESS' => $this->get_client_ip(),
		);
	}

	/**
	 * Get operating system from user agent.
	 *
	 * @return string OS name.
	 */
	private function get_os() {
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '';
		
		if ( stripos( $user_agent, 'Windows' ) !== false ) {
			return 'Windows';
		} elseif ( stripos( $user_agent, 'Mac' ) !== false ) {
			return 'Mac';
		} elseif ( stripos( $user_agent, 'Linux' ) !== false ) {
			return 'Linux';
		} elseif ( stripos( $user_agent, 'Android' ) !== false ) {
			return 'Android';
		} elseif ( stripos( $user_agent, 'iOS' ) !== false ) {
			return 'iOS';
		}
		
		return 'Unknown';
	}

	/**
	 * Get screen information.
	 *
	 * @return string Screen info.
	 */
	private function get_screen_info() {
		$width = isset( $_SERVER['HTTP_SCREEN_WIDTH'] ) ? sanitize_text_field( $_SERVER['HTTP_SCREEN_WIDTH'] ) : '1920';
		$height = isset( $_SERVER['HTTP_SCREEN_HEIGHT'] ) ? sanitize_text_field( $_SERVER['HTTP_SCREEN_HEIGHT'] ) : '1080';
		
		return sprintf( 'Current Resolution: %sx%s, Available Resolution: %sx%s, Color Depth: 24', $width, $height, $width, $height );
	}

	/**
	 * Get client IP address.
	 *
	 * @return string IP address.
	 */
	private function get_client_ip() {
		$ip_keys = array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );
		
		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( $_SERVER[ $key ] );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}
		
		return '0.0.0.0';
	}

	/**
	 * Prepare listing products array.
	 *
	 * @param bool  $is_featured Whether listing is featured.
	 * @param bool  $is_upgrade Whether this is an upgrade.
	 * @param float $price_per_submission Regular listing price.
	 * @param float $price_featured Featured listing price.
	 * @param float $tax_per_listing Regular listing tax.
	 * @param float $tax_featured Featured listing tax.
	 * @return array Products array.
	 */
	private function prepare_listing_products( $is_featured, $is_upgrade, $price_per_submission, $price_featured, $tax_per_listing, $tax_featured ) {
		$products = array();

		if ( $is_upgrade ) {
			// Only featured upgrade
			$products[] = array(
				'name' => 'Featured Listing Upgrade',
				'code' => 'FEATURED-UPGRADE',
				'category' => 'Listing',
				'price' => round( $price_featured, 2 ),
				'vat' => round( $tax_featured, 2 ),
			);
		} else {
			// Regular listing
			$products[] = array(
				'name' => 'Property Listing',
				'code' => 'LISTING',
				'category' => 'Listing',
				'price' => round( $price_per_submission, 2 ),
				'vat' => round( $tax_per_listing, 2 ),
			);

			// Featured listing (if applicable)
			if ( $is_featured ) {
				$products[] = array(
					'name' => 'Featured Listing',
					'code' => 'FEATURED',
					'category' => 'Listing',
					'price' => round( $price_featured, 2 ),
					'vat' => round( $tax_featured, 2 ),
				);
			}
		}

		return $products;
	}
}

