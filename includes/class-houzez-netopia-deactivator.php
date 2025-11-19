<?php
/**
 * Fired during plugin deactivation.
 *
 * @package    Houzez_Netopia
 * @subpackage Houzez_Netopia/includes
 */
class Houzez_Netopia_Deactivator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 */
	public static function deactivate() {
		// Flush rewrite rules
		flush_rewrite_rules();
	}
}

