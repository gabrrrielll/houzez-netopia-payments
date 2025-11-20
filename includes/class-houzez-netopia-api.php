<?php
/**
 * Netopia Payments API integration class.
 *
 * @package    Houzez_Netopia
 * @subpackage Houzez_Netopia/includes
 */

use Netopia\Payment2\Request;
use Netopia\Payment2\VerifyAuth;
use Netopia\Payment2\Status;

class Houzez_Netopia_API {

	/**
	 * API Key for Netopia Payments.
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Signature ID for Netopia Payments.
	 *
	 * @var string
	 */
	private $signature;

	/**
	 * Whether to use sandbox or live environment.
	 *
	 * @var bool
	 */
	private $is_sandbox;

	/**
	 * API base URL.
	 *
	 * @var string
	 */
	private $api_url;

	/**
	 * Initialize the API class.
	 */
	public function __construct() {
		$this->is_sandbox = get_option( 'houzez_netopia_sandbox', '1' ) === '1';
		$this->signature = get_option( 'houzez_netopia_signature', '' );
		
		// Get API key based on sandbox/live mode
		if ( $this->is_sandbox ) {
			$this->api_key = get_option( 'houzez_netopia_api_key_sandbox', '' );
			$this->api_url = 'https://secure.sandbox.netopia-payments.com';
		} else {
			$this->api_key = get_option( 'houzez_netopia_api_key_live', '' );
			$this->api_url = 'https://secure.netopia-payments.com';
		}
		
		// Migration: If new API keys don't exist, try old one
		if ( empty( $this->api_key ) ) {
			$old_api_key = get_option( 'houzez_netopia_api_key', '' );
			if ( ! empty( $old_api_key ) ) {
				$this->api_key = $old_api_key;
			}
		}
	}

	/**
	 * Start a payment transaction using Netopia SDK.
	 *
	 * @param array $payment_data Payment data array with config, payment, order.
	 * @return array|WP_Error Response array or WP_Error on failure.
	 */
	public function start_payment( $payment_data ) {
		try {
			$request = new Request();
			$request->posSignature = $this->signature;
			$request->apiKey = $this->api_key;
			$request->isLive = ! $this->is_sandbox;
			$request->notifyUrl = isset( $payment_data['config']['notifyUrl'] ) ? $payment_data['config']['notifyUrl'] : '';
			$request->redirectUrl = isset( $payment_data['config']['redirectUrl'] ) ? $payment_data['config']['redirectUrl'] : null;

			// Prepare card data
			$card_data = array(
				'account' => $payment_data['payment']['instrument']['account'],
				'expMonth' => $payment_data['payment']['instrument']['expMonth'],
				'expYear' => $payment_data['payment']['instrument']['expYear'],
				'secretCode' => $payment_data['payment']['instrument']['secretCode'],
			);

			// Prepare order data object
			$order_data = new \stdClass();
			$order_data->description = $payment_data['order']['description'];
			$order_data->orderID = $payment_data['order']['orderID'];
			$order_data->amount = $payment_data['order']['amount'];
			$order_data->currency = $payment_data['order']['currency'];
			
			// Billing
			$order_data->billing = new \stdClass();
			$order_data->billing->email = $payment_data['order']['billing']['email'];
			$order_data->billing->phone = $payment_data['order']['billing']['phone'];
			$order_data->billing->firstName = $payment_data['order']['billing']['firstName'];
			$order_data->billing->lastName = $payment_data['order']['billing']['lastName'];
			$order_data->billing->city = $payment_data['order']['billing']['city'];
			$order_data->billing->country = $payment_data['order']['billing']['country'];
			$order_data->billing->state = $payment_data['order']['billing']['state'];
			$order_data->billing->postalCode = $payment_data['order']['billing']['postalCode'];
			$order_data->billing->details = isset( $payment_data['order']['billing']['details'] ) ? $payment_data['order']['billing']['details'] : '';

			// Shipping
			$order_data->shipping = new \stdClass();
			$order_data->shipping->email = $payment_data['order']['shipping']['email'];
			$order_data->shipping->phone = $payment_data['order']['shipping']['phone'];
			$order_data->shipping->firstName = $payment_data['order']['shipping']['firstName'];
			$order_data->shipping->lastName = $payment_data['order']['shipping']['lastName'];
			$order_data->shipping->city = $payment_data['order']['shipping']['city'];
			$order_data->shipping->country = $payment_data['order']['shipping']['country'];
			$order_data->shipping->state = $payment_data['order']['shipping']['state'];
			$order_data->shipping->postalCode = $payment_data['order']['shipping']['postalCode'];
			$order_data->shipping->details = isset( $payment_data['order']['shipping']['details'] ) ? $payment_data['order']['shipping']['details'] : '';

			// Products
			$order_data->products = $payment_data['order']['products'];

			// Prepare 3D Secure data
			$three_d_secure_data = wp_json_encode( $payment_data['payment']['data'] );

			// Set request
			$request->jsonRequest = $request->setRequest( $payment_data['config'], $card_data, $order_data, $three_d_secure_data );

			// Start payment
			$result = $request->startPayment();
			
			// Decode response - SDK returns JSON string wrapped in another structure
			$response = json_decode( $result, true );

			// SDK wraps response in 'data' field
			if ( isset( $response['status'] ) && $response['status'] == 1 && isset( $response['data'] ) ) {
				// Extract actual response from data field
				$actual_response = $response['data'];
				// Convert to array if it's an object
				if ( is_object( $actual_response ) ) {
					$actual_response = json_decode( wp_json_encode( $actual_response ), true );
				}
				// Return in expected format
				return array(
					'status' => 1,
					'code' => isset( $response['code'] ) ? $response['code'] : 200,
					'message' => isset( $response['message'] ) ? $response['message'] : 'Success',
					'data' => $actual_response,
				);
			}

			return new WP_Error( 'netopia_error', isset( $response['message'] ) ? $response['message'] : 'Unknown error occurred' );

		} catch ( \Exception $e ) {
			return new WP_Error( 'netopia_exception', $e->getMessage() );
		}
	}

	/**
	 * Verify 3D Secure authentication using Netopia SDK.
	 *
	 * @param string $authentication_token Authentication token from start payment.
	 * @param string $ntp_id Transaction ID.
	 * @param string $pa_res Payment authentication response from bank.
	 * @return array|WP_Error Response array or WP_Error on failure.
	 */
	public function verify_authentication( $authentication_token, $ntp_id, $pa_res ) {
		try {
			$verify_auth = new VerifyAuth();
			$verify_auth->apiKey = $this->api_key;
			$verify_auth->isLive = ! $this->is_sandbox;
			$verify_auth->authenticationToken = $authentication_token;
			$verify_auth->ntpID = $ntp_id;
			$verify_auth->postData = array(
				'paRes' => $pa_res,
			);

			// Set verify auth parameters
			$json_auth_param = $verify_auth->setVerifyAuth();

			// Send verify auth request
			$result = $verify_auth->sendRequestVerifyAuth( $json_auth_param );

			// Decode response - SDK returns JSON string wrapped in another structure
			$response = json_decode( $result, true );

			// SDK wraps response in 'data' field
			if ( isset( $response['status'] ) && $response['status'] == 1 && isset( $response['data'] ) ) {
				// Extract actual response from data field
				$actual_response = $response['data'];
				// Convert to array if it's an object
				if ( is_object( $actual_response ) ) {
					$actual_response = json_decode( wp_json_encode( $actual_response ), true );
				}
				// Return in expected format
				return array(
					'status' => 1,
					'code' => isset( $response['code'] ) ? $response['code'] : 200,
					'message' => isset( $response['message'] ) ? $response['message'] : 'Success',
					'data' => $actual_response,
				);
			}

			return new WP_Error( 'netopia_verify_error', isset( $response['message'] ) ? $response['message'] : 'Authentication verification failed' );

		} catch ( \Exception $e ) {
			return new WP_Error( 'netopia_verify_exception', $e->getMessage() );
		}
	}

	/**
	 * Get payment status using Netopia SDK.
	 *
	 * @param string $ntp_id Transaction ID.
	 * @param string $order_id Order ID.
	 * @return array|WP_Error Response array or WP_Error on failure.
	 */
	public function get_payment_status( $ntp_id, $order_id = '' ) {
		try {
			$status = new Status();
			$status->apiKey = $this->api_key;
			$status->posSignature = $this->signature;
			$status->isLive = ! $this->is_sandbox;
			$status->ntpID = $ntp_id;
			$status->orderID = $order_id;

			// Validate parameters
			$status->validateParam();

			// Set status parameters
			$json_status_param = $status->setStatus();

			// Get status
			$result = $status->getStatus( $json_status_param );

			// Decode response
			$response = json_decode( $result, true );

			return $response;

		} catch ( \Exception $e ) {
			return new WP_Error( 'netopia_status_exception', $e->getMessage() );
		}
	}

	/**
	 * Check if API is configured.
	 *
	 * @return bool
	 */
	public function is_configured() {
		// Check if API key exists for current mode (sandbox or live)
		$api_key = $this->is_sandbox ? get_option( 'houzez_netopia_api_key_sandbox', '' ) : get_option( 'houzez_netopia_api_key_live', '' );
		
		// Fallback to old API key if new ones don't exist
		if ( empty( $api_key ) ) {
			$api_key = get_option( 'houzez_netopia_api_key', '' );
		}
		
		return ! empty( $api_key ) && ! empty( $this->signature );
	}

	/**
	 * Get API URL.
	 *
	 * @return string
	 */
	public function get_api_url() {
		return $this->api_url;
	}

	/**
	 * Get signature.
	 *
	 * @return string
	 */
	public function get_signature() {
		return $this->signature;
	}
}

