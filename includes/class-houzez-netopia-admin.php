<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @package    Houzez_Netopia
 * @subpackage Houzez_Netopia/admin
 */
class Houzez_Netopia_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version = $version;
	}

	/**
	 * Register the stylesheets for the admin area.
	 */
	public function enqueue_styles() {
		$screen = get_current_screen();
		if ( $screen && strpos( $screen->id, 'houzez-netopia' ) !== false ) {
			wp_enqueue_style( $this->plugin_name, HOUZEZ_NETOPIA_PLUGIN_URL . 'assets/css/admin.css', array(), $this->version, 'all' );
		}
	}

	/**
	 * Register the JavaScript for the admin area.
	 */
	public function enqueue_scripts() {
		$screen = get_current_screen();
		if ( $screen && strpos( $screen->id, 'houzez-netopia' ) !== false ) {
			wp_enqueue_script( $this->plugin_name, HOUZEZ_NETOPIA_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), $this->version, false );
		}
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu() {
		add_menu_page(
			__( 'Netopia Payments', 'houzez-netopia' ),
			__( 'Netopia Payments', 'houzez-netopia' ),
			'manage_options',
			'houzez-netopia',
			array( $this, 'display_settings_page' ),
			'dashicons-money-alt',
			30
		);

		add_submenu_page(
			'houzez-netopia',
			__( 'Settings', 'houzez-netopia' ),
			__( 'Settings', 'houzez-netopia' ),
			'manage_options',
			'houzez-netopia',
			array( $this, 'display_settings_page' )
		);
	}

	/**
	 * Register plugin settings.
	 */
	public function register_settings() {
		register_setting( 'houzez_netopia_settings', 'houzez_netopia_enabled' );
		register_setting( 'houzez_netopia_settings', 'houzez_netopia_sandbox' );
		register_setting( 'houzez_netopia_settings', 'houzez_netopia_api_key' );
		register_setting( 'houzez_netopia_settings', 'houzez_netopia_signature' );
		register_setting( 'houzez_netopia_settings', 'houzez_netopia_currency' );
	}

	/**
	 * Display settings page.
	 */
	public function display_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Save settings
		if ( isset( $_POST['houzez_netopia_save_settings'] ) && check_admin_referer( 'houzez_netopia_settings' ) ) {
			update_option( 'houzez_netopia_enabled', isset( $_POST['houzez_netopia_enabled'] ) ? '1' : '0' );
			update_option( 'houzez_netopia_sandbox', isset( $_POST['houzez_netopia_sandbox'] ) ? '1' : '0' );
			update_option( 'houzez_netopia_api_key', sanitize_text_field( $_POST['houzez_netopia_api_key'] ) );
			update_option( 'houzez_netopia_signature', sanitize_text_field( $_POST['houzez_netopia_signature'] ) );
			update_option( 'houzez_netopia_currency', sanitize_text_field( $_POST['houzez_netopia_currency'] ) );

			echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved successfully!', 'houzez-netopia' ) . '</p></div>';
		}

		// Get current settings
		$enabled = get_option( 'houzez_netopia_enabled', '0' );
		$sandbox = get_option( 'houzez_netopia_sandbox', '1' );
		$api_key = get_option( 'houzez_netopia_api_key', '' );
		$signature = get_option( 'houzez_netopia_signature', '' );
		$currency = get_option( 'houzez_netopia_currency', 'RON' );

		include HOUZEZ_NETOPIA_PLUGIN_DIR . 'admin/partials/settings-page.php';
	}
}

