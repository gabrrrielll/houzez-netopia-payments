<?php
/**
 * Define the internationalization functionality.
 *
 * @package    Houzez_Netopia
 * @subpackage Houzez_Netopia/includes
 */
class Houzez_Netopia_i18n {

	/**
	 * Load the plugin text domain for translation.
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain(
			'houzez-netopia',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);
	}
}

