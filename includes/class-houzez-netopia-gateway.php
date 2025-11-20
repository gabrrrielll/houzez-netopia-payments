<?php
/**
 * Netopia Payment Gateway class.
 *
 * @package    Houzez_Netopia
 * @subpackage Houzez_Netopia/includes
 */
class Houzez_Netopia_Gateway {

	/**
	 * Netopia API instance.
	 *
	 * @var Houzez_Netopia_API
	 */
	private $api;

	/**
	 * Payment processor instance.
	 *
	 * @var Houzez_Netopia_Payment_Processor
	 */
	private $processor;

	/**
	 * Initialize the gateway.
	 */
	public function __construct() {
		$this->api = new Houzez_Netopia_API();
		$this->processor = new Houzez_Netopia_Payment_Processor();
	}

	/**
	 * Add Netopia as payment method option.
	 *
	 * @param array $methods Existing payment methods.
	 * @return array Modified payment methods array.
	 */
	public function add_payment_method( $methods ) {
		if ( ! $this->api->is_configured() ) {
			return $methods;
		}

		$methods['netopia'] = array(
			'label' => __( 'Netopia Payments', 'houzez-netopia' ),
			'enabled' => get_option( 'houzez_netopia_enabled', '0' ) === '1',
		);

		return $methods;
	}

	/**
	 * Process package payment (membership).
	 */
	public function process_package_payment() {
		check_ajax_referer( 'houzez_register_nonce2', 'houzez_register_security2' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in to make a payment.', 'houzez-netopia' ) ) );
		}

		$user_id = get_current_user_id();
		$package_id = isset( $_POST['package_id'] ) ? intval( $_POST['package_id'] ) : 0;

		if ( empty( $package_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid package ID.', 'houzez-netopia' ) ) );
		}

		// Get card data from POST
		$card_data = array(
			'card_number' => isset( $_POST['card_number'] ) ? sanitize_text_field( $_POST['card_number'] ) : '',
			'exp_month' => isset( $_POST['exp_month'] ) ? sanitize_text_field( $_POST['exp_month'] ) : '',
			'exp_year' => isset( $_POST['exp_year'] ) ? sanitize_text_field( $_POST['exp_year'] ) : '',
			'cvv' => isset( $_POST['cvv'] ) ? sanitize_text_field( $_POST['cvv'] ) : '',
		);

		// Validate card data
		if ( empty( $card_data['card_number'] ) || empty( $card_data['exp_month'] ) || empty( $card_data['exp_year'] ) || empty( $card_data['cvv'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Please fill in all card details.', 'houzez-netopia' ) ) );
		}

		// Check if API is configured
		if ( ! $this->api->is_configured() ) {
			wp_send_json_error( array( 'message' => __( 'Netopia Payments is not configured. Please check API Key and Signature in settings.', 'houzez-netopia' ) ) );
		}

		// Prepare payment data
		$payment_data = $this->processor->prepare_package_payment( $package_id, $user_id, $card_data );

		// Start payment with Netopia
		$response = $this->api->start_payment( $payment_data );

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			// Check if it's an authorization error
			if ( strpos( $error_message, 'Authorization required' ) !== false ) {
				$error_message = __( 'Authorization failed. Please check your API Key and Signature in Netopia Payments settings. Make sure you are using the correct credentials for Sandbox or Live mode.', 'houzez-netopia' );
			}
			wp_send_json_error( array( 'message' => $error_message ) );
		}

		// Check if 3D Secure is required
		if ( isset( $response['data']['customerAction']['type'] ) && $response['data']['customerAction']['type'] === 'Authentication3D' ) {
			// Store authentication token and transaction ID for later verification
			$auth_token = $response['data']['customerAction']['authenticationToken'];
			$ntp_id = isset( $response['data']['payment']['ntpID'] ) ? $response['data']['payment']['ntpID'] : '';
			$form_data = $response['data']['customerAction']['formData'];
			$auth_url = $response['data']['customerAction']['url'];

			// Store transaction data temporarily
			$order_id = $payment_data['order']['orderID'];
			
			// Update backUrl in form_data to include order_id
			if ( isset( $form_data['backUrl'] ) ) {
				$back_url = $form_data['backUrl'];
				$back_url = add_query_arg( 'order_id', $order_id, $back_url );
				$form_data['backUrl'] = $back_url;
			}
			
			$transaction_data = get_transient( 'houzez_netopia_' . $order_id );
			if ( $transaction_data ) {
				$transaction_data['auth_token'] = $auth_token;
				$transaction_data['ntp_id'] = $ntp_id;
				$transaction_data['form_data'] = $form_data;
				set_transient( 'houzez_netopia_' . $order_id, $transaction_data, HOUR_IN_SECONDS );
			}

			// Return 3D Secure redirect data
			wp_send_json_success( array(
				'requires_3ds' => true,
				'auth_url' => $auth_url,
				'form_data' => $form_data,
				'auth_token' => $auth_token,
				'ntp_id' => $ntp_id,
				'order_id' => $order_id,
			) );
		} else {
			// Payment completed without 3D Secure
			$this->complete_package_payment( $response, $package_id, $user_id );
		}
	}

	/**
	 * Process listing payment.
	 */
	public function process_listing_payment() {
		check_ajax_referer( 'houzez_register_nonce2', 'houzez_register_security2' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in to make a payment.', 'houzez-netopia' ) ) );
		}

		$user_id = get_current_user_id();
		$property_id = isset( $_POST['property_id'] ) ? intval( $_POST['property_id'] ) : 0;
		$is_featured = isset( $_POST['is_featured'] ) ? ( $_POST['is_featured'] === '1' || $_POST['is_featured'] === true ) : false;
		$is_upgrade = isset( $_POST['is_upgrade'] ) ? ( $_POST['is_upgrade'] === '1' || $_POST['is_upgrade'] === true ) : false;

		if ( empty( $property_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid property ID.', 'houzez-netopia' ) ) );
		}

		// Get card data from POST
		$card_data = array(
			'card_number' => isset( $_POST['card_number'] ) ? sanitize_text_field( $_POST['card_number'] ) : '',
			'exp_month' => isset( $_POST['exp_month'] ) ? sanitize_text_field( $_POST['exp_month'] ) : '',
			'exp_year' => isset( $_POST['exp_year'] ) ? sanitize_text_field( $_POST['exp_year'] ) : '',
			'cvv' => isset( $_POST['cvv'] ) ? sanitize_text_field( $_POST['cvv'] ) : '',
		);

		// Validate card data
		if ( empty( $card_data['card_number'] ) || empty( $card_data['exp_month'] ) || empty( $card_data['exp_year'] ) || empty( $card_data['cvv'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Please fill in all card details.', 'houzez-netopia' ) ) );
		}

		// Check if API is configured
		if ( ! $this->api->is_configured() ) {
			wp_send_json_error( array( 'message' => __( 'Netopia Payments is not configured. Please check API Key and Signature in settings.', 'houzez-netopia' ) ) );
		}

		// Prepare payment data
		$payment_data = $this->processor->prepare_listing_payment( $property_id, $user_id, $is_featured, $is_upgrade, $card_data );

		// Start payment with Netopia
		$response = $this->api->start_payment( $payment_data );

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			// Check if it's an authorization error
			if ( strpos( $error_message, 'Authorization required' ) !== false ) {
				$error_message = __( 'Authorization failed. Please check your API Key and Signature in Netopia Payments settings. Make sure you are using the correct credentials for Sandbox or Live mode.', 'houzez-netopia' );
			}
			wp_send_json_error( array( 'message' => $error_message ) );
		}

		// Check if 3D Secure is required
		if ( isset( $response['data']['customerAction']['type'] ) && $response['data']['customerAction']['type'] === 'Authentication3D' ) {
			// Store authentication token and transaction ID for later verification
			$auth_token = $response['data']['customerAction']['authenticationToken'];
			$ntp_id = isset( $response['data']['payment']['ntpID'] ) ? $response['data']['payment']['ntpID'] : '';
			$form_data = $response['data']['customerAction']['formData'];
			$auth_url = $response['data']['customerAction']['url'];

			// Store transaction data temporarily
			$order_id = $payment_data['order']['orderID'];
			
			// Update backUrl in form_data to include order_id
			if ( isset( $form_data['backUrl'] ) ) {
				$back_url = $form_data['backUrl'];
				$back_url = add_query_arg( 'order_id', $order_id, $back_url );
				$form_data['backUrl'] = $back_url;
			}
			
			$transaction_data = get_transient( 'houzez_netopia_' . $order_id );
			if ( $transaction_data ) {
				$transaction_data['auth_token'] = $auth_token;
				$transaction_data['ntp_id'] = $ntp_id;
				$transaction_data['form_data'] = $form_data;
				set_transient( 'houzez_netopia_' . $order_id, $transaction_data, HOUR_IN_SECONDS );
			}

			// Return 3D Secure redirect data
			wp_send_json_success( array(
				'requires_3ds' => true,
				'auth_url' => $auth_url,
				'form_data' => $form_data,
				'auth_token' => $auth_token,
				'ntp_id' => $ntp_id,
				'order_id' => $order_id,
			) );
		} else {
			// Payment completed without 3D Secure
			$this->complete_listing_payment( $response, $property_id, $user_id, $is_featured, $is_upgrade );
		}
	}

	/**
	 * Handle payment return from Netopia (after 3D Secure).
	 */
	public function handle_payment_return() {
		if ( ! isset( $_GET['houzez_netopia_return'] ) || $_GET['houzez_netopia_return'] !== '1' ) {
			return;
		}

		$type = isset( $_GET['type'] ) ? sanitize_text_field( $_GET['type'] ) : '';
		
		// PaRes can come from GET or POST (depending on Netopia's redirect method)
		$pa_res = '';
		if ( isset( $_POST['PaRes'] ) ) {
			$pa_res = sanitize_text_field( $_POST['PaRes'] );
		} elseif ( isset( $_GET['PaRes'] ) ) {
			$pa_res = sanitize_text_field( $_GET['PaRes'] );
		}

		// Get order ID from GET or POST
		$order_id = isset( $_GET['order_id'] ) ? sanitize_text_field( $_GET['order_id'] ) : '';
		if ( empty( $order_id ) ) {
			$order_id = isset( $_POST['order_id'] ) ? sanitize_text_field( $_POST['order_id'] ) : '';
		}

		// If no order_id, try to get it from property_id or package_id
		if ( empty( $order_id ) ) {
			if ( $type === 'listing' && isset( $_GET['property_id'] ) ) {
				$property_id = intval( $_GET['property_id'] );
				$user_id = get_current_user_id();
				// Try to reconstruct order_id
				$transients = get_option( '_transient_timeout_houzez_netopia_', array() );
				// Search for matching transient
				global $wpdb;
				$transient_keys = $wpdb->get_col( 
					$wpdb->prepare( 
						"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
						$wpdb->esc_like( '_transient_houzez_netopia_' ) . '%'
					)
				);
				foreach ( $transient_keys as $key ) {
					$transient_order_id = str_replace( '_transient_houzez_netopia_', '', $key );
					$transient_data = get_transient( 'houzez_netopia_' . $transient_order_id );
					if ( $transient_data && isset( $transient_data['property_id'] ) && $transient_data['property_id'] == $property_id ) {
						$order_id = $transient_order_id;
						break;
					}
				}
			}
		}

		if ( empty( $order_id ) ) {
			wp_redirect( home_url( '/?netopia_payment=error&reason=missing_order_id' ) );
			exit;
		}

		// Get transaction data
		$transaction_data = get_transient( 'houzez_netopia_' . $order_id );
		if ( ! $transaction_data ) {
			wp_redirect( home_url( '/?netopia_payment=error&reason=transaction_not_found' ) );
			exit;
		}

		// If PaRes is empty, check if payment was cancelled
		if ( empty( $pa_res ) ) {
			if ( isset( $_GET['cancel'] ) || isset( $_POST['cancel'] ) ) {
				wp_redirect( home_url( '/?netopia_payment=cancelled' ) );
				exit;
			}
			// If no PaRes and no cancel, might be a redirect issue
			wp_redirect( home_url( '/?netopia_payment=error&reason=missing_pares' ) );
			exit;
		}

		// Verify authentication
		$verify_response = $this->api->verify_authentication(
			$transaction_data['auth_token'],
			$transaction_data['ntp_id'],
			$pa_res
		);

		if ( is_wp_error( $verify_response ) ) {
			wp_redirect( home_url( '/?netopia_payment=error&message=' . urlencode( $verify_response->get_error_message() ) ) );
			exit;
		}

		// Complete payment based on type
		if ( $type === 'package' ) {
			$this->complete_package_payment( $verify_response, $transaction_data['package_id'], $transaction_data['user_id'] );
		} elseif ( $type === 'listing' ) {
			$this->complete_listing_payment(
				$verify_response,
				$transaction_data['property_id'],
				$transaction_data['user_id'],
				$transaction_data['is_featured'],
				$transaction_data['is_upgrade']
			);
		} else {
			wp_redirect( home_url( '/?netopia_payment=error&reason=invalid_type' ) );
			exit;
		}

		// Redirect to success page
		wp_redirect( home_url( '/?netopia_payment=success' ) );
		exit;
	}

	/**
	 * Complete package payment after successful verification.
	 *
	 * @param array $response API response.
	 * @param int   $package_id Package ID.
	 * @param int   $user_id User ID.
	 */
	private function complete_package_payment( $response, $package_id, $user_id ) {
		// Check payment status
		if ( ! isset( $response['data']['payment']['status'] ) ) {
			return;
		}

		$payment_status = intval( $response['data']['payment']['status'] );
		$error_code = isset( $response['data']['error']['code'] ) ? $response['data']['error']['code'] : '';

		// Status 3 = Paid, Error code 00 = Approved
		if ( $payment_status === 3 && $error_code === '00' ) {
			// Update user membership package
			if ( function_exists( 'houzez_update_membership_package' ) ) {
				houzez_update_membership_package( $user_id, $package_id );
			}

			// Generate invoice
			$date = date( 'Y-m-d H:i:s' );
			$payment_method = 'Netopia Payments';
			if ( function_exists( 'houzez_generate_invoice' ) ) {
				$invoice_id = houzez_generate_invoice( 'package', 'one_time', $package_id, $date, $user_id, 0, 0, '', $payment_method, 1 );
				update_post_meta( $invoice_id, 'invoice_payment_status', 1 );
			}

			// Save user packages record
			if ( function_exists( 'houzez_save_user_packages_record' ) ) {
				houzez_save_user_packages_record( $user_id, $package_id );
			}

			// Store transaction ID
			$ntp_id = isset( $response['data']['payment']['ntpID'] ) ? $response['data']['payment']['ntpID'] : '';
			if ( ! empty( $ntp_id ) ) {
				update_user_meta( $user_id, 'houzez_netopia_last_transaction_id', $ntp_id );
			}
		}
	}

	/**
	 * Complete listing payment after successful verification.
	 *
	 * @param array $response API response.
	 * @param int   $property_id Property ID.
	 * @param int   $user_id User ID.
	 * @param bool  $is_featured Whether listing is featured.
	 * @param bool  $is_upgrade Whether this is an upgrade.
	 */
	private function complete_listing_payment( $response, $property_id, $user_id, $is_featured, $is_upgrade ) {
		// Check payment status
		if ( ! isset( $response['data']['payment']['status'] ) ) {
			return;
		}

		$payment_status = intval( $response['data']['payment']['status'] );
		$error_code = isset( $response['data']['error']['code'] ) ? $response['data']['error']['code'] : '';

		// Status 3 = Paid, Error code 00 = Approved
		if ( $payment_status === 3 && $error_code === '00' ) {
			// Update property payment status
			update_post_meta( $property_id, 'fave_payment_status', 'paid' );

			// Handle property status
			$listings_admin_approved = '';
			$paid_submission_status = '';
			if ( function_exists( 'houzez_option' ) ) {
				$listings_admin_approved = houzez_option( 'listings_admin_approved' );
				$paid_submission_status = houzez_option( 'enable_paid_submission' );
			}

			if ( $listings_admin_approved != 'yes' && $paid_submission_status == 'per_listing' ) {
				$post = array(
					'ID' => $property_id,
					'post_status' => 'publish',
				);
				wp_update_post( $post );
			} else {
				$post = array(
					'ID' => $property_id,
					'post_status' => 'pending',
				);
				wp_update_post( $post );
			}

			// Set featured status
			if ( $is_featured || $is_upgrade ) {
				update_post_meta( $property_id, 'fave_featured', 1 );
			}

			// Generate invoice
			$date = date( 'Y-m-d H:i:s' );
			$payment_method = 'Netopia Payments';
			
			if ( function_exists( 'houzez_generate_invoice' ) ) {
				if ( $is_upgrade ) {
					$invoice_id = houzez_generate_invoice( 'Upgrade to Featured', 'one_time', $property_id, $date, $user_id, 0, 1, '', $payment_method );
				} elseif ( $is_featured ) {
					$invoice_id = houzez_generate_invoice( 'Publish Listing with Featured', 'one_time', $property_id, $date, $user_id, 1, 0, '', $payment_method );
				} else {
					$invoice_id = houzez_generate_invoice( 'Listing', 'one_time', $property_id, $date, $user_id, 0, 0, '', $payment_method );
				}
				update_post_meta( $invoice_id, 'invoice_payment_status', 1 );
			}

			// Store transaction ID
			$ntp_id = isset( $response['data']['payment']['ntpID'] ) ? $response['data']['payment']['ntpID'] : '';
			if ( ! empty( $ntp_id ) ) {
				update_post_meta( $property_id, 'houzez_netopia_transaction_id', $ntp_id );
			}
		}
	}
}

