<?php
/**
 * Fired during plugin activation.
 *
 * @package    Houzez_Netopia
 * @subpackage Houzez_Netopia/includes
 */
class Houzez_Netopia_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 */
	public static function activate() {
		// Set default options
		$default_options = array(
			'houzez_netopia_enabled' => '0',
			'houzez_netopia_sandbox' => '1',
			'houzez_netopia_api_key_sandbox' => '',
			'houzez_netopia_api_key_live' => '',
			'houzez_netopia_signature' => '',
			'houzez_netopia_currency' => 'RON',
		);

		foreach ( $default_options as $key => $value ) {
			if ( get_option( $key ) === false ) {
				add_option( $key, $value );
			}
		}

		// Flush rewrite rules
		flush_rewrite_rules();
	}
}

