<?php
/**
 * Netopia IPN (Instant Payment Notification) Handler.
 *
 * @package    Houzez_Netopia
 * @subpackage Houzez_Netopia/includes
 */
class Houzez_Netopia_IPN_Handler {

	/**
	 * Netopia API instance.
	 *
	 * @var Houzez_Netopia_API
	 */
	private $api;

	/**
	 * Initialize the IPN handler.
	 */
	public function __construct() {
		$this->api = new Houzez_Netopia_API();
	}

	/**
	 * Handle IPN requests from Netopia.
	 */
	public function handle_ipn() {
		// Check if this is an IPN request
		if ( ! isset( $_GET['houzez_netopia_ipn'] ) || $_GET['houzez_netopia_ipn'] !== '1' ) {
			return;
		}

		// Get raw POST data
		$raw_post = file_get_contents( 'php://input' );
		$data = json_decode( $raw_post, true );

		// If no JSON data, try to get from POST
		if ( empty( $data ) ) {
			$data = $_POST;
		}

		// Log IPN request for debugging
		$this->log_ipn( $data );

		// Verify IPN data
		if ( ! $this->verify_ipn( $data ) ) {
			status_header( 400 );
			exit( 'Invalid IPN data' );
		}

		// Process IPN based on type
		$type = isset( $_GET['type'] ) ? sanitize_text_field( $_GET['type'] ) : '';
		
		if ( $type === 'package' ) {
			$this->process_package_ipn( $data );
		} elseif ( $type === 'listing' ) {
			$this->process_listing_ipn( $data );
		}

		// Return success response
		status_header( 200 );
		exit( 'OK' );
	}

	/**
	 * Verify IPN data authenticity.
	 *
	 * @param array $data IPN data.
	 * @return bool True if valid, false otherwise.
	 */
	private function verify_ipn( $data ) {
		// Basic validation - check if required fields exist
		if ( empty( $data ) ) {
			return false;
		}

		// You can add additional verification logic here
		// For example, verify signature, check transaction ID, etc.

		return true;
	}

	/**
	 * Process package payment IPN.
	 *
	 * @param array $data IPN data.
	 */
	private function process_package_ipn( $data ) {
		$ntp_id = isset( $data['ntpID'] ) ? sanitize_text_field( $data['ntpID'] ) : '';
		$order_id = isset( $data['orderID'] ) ? sanitize_text_field( $data['orderID'] ) : '';
		$payment_status = isset( $data['status'] ) ? intval( $data['status'] ) : 0;

		if ( empty( $ntp_id ) || empty( $order_id ) ) {
			return;
		}

		// Extract order ID from the format PKG_123_456_timestamp
		$order_parts = explode( '_', $order_id );
		if ( count( $order_parts ) < 3 ) {
			return;
		}

		$package_id = isset( $order_parts[1] ) ? intval( $order_parts[1] ) : 0;
		$user_id = isset( $order_parts[2] ) ? intval( $order_parts[2] ) : 0;

		if ( empty( $package_id ) || empty( $user_id ) ) {
			return;
		}

		// Check if payment is already processed
		$last_transaction = get_user_meta( $user_id, 'houzez_netopia_last_transaction_id', true );
		if ( $last_transaction === $ntp_id ) {
			// Already processed
			return;
		}

		// Get payment status from Netopia API
		$status_response = $this->api->get_payment_status( $ntp_id, $order_id );
		
		if ( is_wp_error( $status_response ) ) {
			$this->log_ipn( array( 'error' => 'Failed to get payment status', 'ntp_id' => $ntp_id, 'message' => $status_response->get_error_message() ) );
			return;
		}

		// SDK wraps response in 'data' field
		$payment_data = isset( $status_response['data'] ) ? $status_response['data'] : $status_response;
		$payment_status = isset( $payment_data['payment']['status'] ) ? intval( $payment_data['payment']['status'] ) : 0;
		$error_code = isset( $payment_data['error']['code'] ) ? $payment_data['error']['code'] : '';

		// Status 3 = Paid, Error code 00 = Approved
		if ( $payment_status === 3 && $error_code === '00' ) {
			// Update user membership package
			if ( function_exists( 'houzez_update_membership_package' ) ) {
				houzez_update_membership_package( $user_id, $package_id );
			}

			// Generate invoice if not exists
			$date = date( 'Y-m-d H:i:s' );
			$payment_method = 'Netopia Payments';
			
			if ( function_exists( 'houzez_generate_invoice' ) ) {
				// Check if invoice already exists for this transaction
				$existing_invoice = $this->find_invoice_by_transaction( $ntp_id );
				
				if ( ! $existing_invoice ) {
					$invoice_id = houzez_generate_invoice( 'package', 'one_time', $package_id, $date, $user_id, 0, 0, '', $payment_method, 1 );
					update_post_meta( $invoice_id, 'invoice_payment_status', 1 );
					update_post_meta( $invoice_id, 'houzez_netopia_transaction_id', $ntp_id );
				}
			}

			// Save user packages record
			if ( function_exists( 'houzez_save_user_packages_record' ) ) {
				houzez_save_user_packages_record( $user_id, $package_id );
			}

			// Store transaction ID
			update_user_meta( $user_id, 'houzez_netopia_last_transaction_id', $ntp_id );
		}
	}

	/**
	 * Process listing payment IPN.
	 *
	 * @param array $data IPN data.
	 */
	private function process_listing_ipn( $data ) {
		$ntp_id = isset( $data['ntpID'] ) ? sanitize_text_field( $data['ntpID'] ) : '';
		$order_id = isset( $data['orderID'] ) ? sanitize_text_field( $data['orderID'] ) : '';
		$payment_status = isset( $data['status'] ) ? intval( $data['status'] ) : 0;

		if ( empty( $ntp_id ) || empty( $order_id ) ) {
			return;
		}

		// Extract order ID from the format LST_123_456_timestamp
		$order_parts = explode( '_', $order_id );
		if ( count( $order_parts ) < 3 ) {
			return;
		}

		$property_id = isset( $order_parts[1] ) ? intval( $order_parts[1] ) : 0;
		$user_id = isset( $order_parts[2] ) ? intval( $order_parts[2] ) : 0;

		if ( empty( $property_id ) || empty( $user_id ) ) {
			return;
		}

		// Check if payment is already processed
		$last_transaction = get_post_meta( $property_id, 'houzez_netopia_transaction_id', true );
		if ( $last_transaction === $ntp_id ) {
			// Already processed
			return;
		}

		// Get payment status from Netopia API
		$status_response = $this->api->get_payment_status( $ntp_id, $order_id );
		
		if ( is_wp_error( $status_response ) ) {
			$this->log_ipn( array( 'error' => 'Failed to get payment status', 'ntp_id' => $ntp_id, 'message' => $status_response->get_error_message() ) );
			return;
		}

		// SDK wraps response in 'data' field
		$payment_data = isset( $status_response['data'] ) ? $status_response['data'] : $status_response;
		$payment_status = isset( $payment_data['payment']['status'] ) ? intval( $payment_data['payment']['status'] ) : 0;
		$error_code = isset( $payment_data['error']['code'] ) ? $payment_data['error']['code'] : '';

		// Status 3 = Paid, Error code 00 = Approved
		if ( $payment_status === 3 && $error_code === '00' ) {
			// Get transaction data to determine if featured/upgrade
			$transaction_data = get_transient( 'houzez_netopia_' . $order_id );
			$is_featured = isset( $transaction_data['is_featured'] ) ? $transaction_data['is_featured'] : false;
			$is_upgrade = isset( $transaction_data['is_upgrade'] ) ? $transaction_data['is_upgrade'] : false;

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

			// Generate invoice if not exists
			$date = date( 'Y-m-d H:i:s' );
			$payment_method = 'Netopia Payments';
			
			if ( function_exists( 'houzez_generate_invoice' ) ) {
				// Check if invoice already exists for this transaction
				$existing_invoice = $this->find_invoice_by_transaction( $ntp_id );
				
				if ( ! $existing_invoice ) {
					if ( $is_upgrade ) {
						$invoice_id = houzez_generate_invoice( 'Upgrade to Featured', 'one_time', $property_id, $date, $user_id, 0, 1, '', $payment_method );
					} elseif ( $is_featured ) {
						$invoice_id = houzez_generate_invoice( 'Publish Listing with Featured', 'one_time', $property_id, $date, $user_id, 1, 0, '', $payment_method );
					} else {
						$invoice_id = houzez_generate_invoice( 'Listing', 'one_time', $property_id, $date, $user_id, 0, 0, '', $payment_method );
					}
					update_post_meta( $invoice_id, 'invoice_payment_status', 1 );
					update_post_meta( $invoice_id, 'houzez_netopia_transaction_id', $ntp_id );
				}
			}

			// Store transaction ID
			update_post_meta( $property_id, 'houzez_netopia_transaction_id', $ntp_id );
		}
	}

	/**
	 * Find invoice by transaction ID.
	 *
	 * @param string $transaction_id Transaction ID.
	 * @return int|false Invoice post ID or false if not found.
	 */
	private function find_invoice_by_transaction( $transaction_id ) {
		$args = array(
			'post_type' => 'houzez_invoice',
			'posts_per_page' => 1,
			'meta_query' => array(
				array(
					'key' => 'houzez_netopia_transaction_id',
					'value' => $transaction_id,
					'compare' => '=',
				),
			),
		);

		$query = new WP_Query( $args );
		
		if ( $query->have_posts() ) {
			return $query->posts[0]->ID;
		}

		return false;
	}

	/**
	 * Log IPN data for debugging.
	 *
	 * @param array $data Data to log.
	 */
	private function log_ipn( $data ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$log_file = HOUZEZ_NETOPIA_PLUGIN_DIR . 'logs/ipn.log';
			$log_dir = dirname( $log_file );
			
			if ( ! file_exists( $log_dir ) ) {
				wp_mkdir_p( $log_dir );
			}
			
			$log_entry = date( 'Y-m-d H:i:s' ) . ' - ' . wp_json_encode( $data ) . PHP_EOL;
			file_put_contents( $log_file, $log_entry, FILE_APPEND );
		}
	}
}

