<?php
/**
 * Plugin Name: Houzez Netopia Payments
 * Plugin URI: https://roomshare.ro
 * Description: Netopia Payments gateway integration for Houzez theme. Supports membership packages and per-listing payments.
 * Version: 1.0.0
 * Author: Roomshare.ro
 * Author URI: https://roomshare.ro
 * Text Domain: houzez-netopia
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Current plugin version.
 */
define( 'HOUZEZ_NETOPIA_VERSION', '1.0.0' );
define( 'HOUZEZ_NETOPIA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'HOUZEZ_NETOPIA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'HOUZEZ_NETOPIA_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Load Composer autoloader
if ( file_exists( HOUZEZ_NETOPIA_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once HOUZEZ_NETOPIA_PLUGIN_DIR . 'vendor/autoload.php';
}

/**
 * The code that runs during plugin activation.
 */
function activate_houzez_netopia() {
	require_once HOUZEZ_NETOPIA_PLUGIN_DIR . 'includes/class-houzez-netopia-activator.php';
	Houzez_Netopia_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_houzez_netopia() {
	require_once HOUZEZ_NETOPIA_PLUGIN_DIR . 'includes/class-houzez-netopia-deactivator.php';
	Houzez_Netopia_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_houzez_netopia' );
register_deactivation_hook( __FILE__, 'deactivate_houzez_netopia' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require HOUZEZ_NETOPIA_PLUGIN_DIR . 'includes/class-houzez-netopia.php';

/**
 * Begins execution of the plugin.
 */
function run_houzez_netopia() {
	$plugin = new Houzez_Netopia();
	$plugin->run();
}
run_houzez_netopia();

