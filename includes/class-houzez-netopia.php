<?php
/**
 * The core plugin class.
 *
 * @package    Houzez_Netopia
 * @subpackage Houzez_Netopia/includes
 */
class Houzez_Netopia
{
    /**
     * The loader that's responsible for maintaining and registering all hooks.
     *
     * @var      Houzez_Netopia_Loader    $loader    Maintains and registers all hooks for the plugin.
     */
    protected $loader;

    /**
     * The unique identifier of this plugin.
     *
     * @var      string    $plugin_name    The string used to uniquely identify this plugin.
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @var      string    $version    The current version of the plugin.
     */
    protected $version;

    /**
     * Define the core functionality of the plugin.
     */
    public function __construct()
    {
        if (defined('HOUZEZ_NETOPIA_VERSION')) {
            $this->version = HOUZEZ_NETOPIA_VERSION;
        } else {
            $this->version = '1.0.0';
        }
        $this->plugin_name = 'houzez-netopia';

        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_payment_hooks();
    }

    /**
     * Load the required dependencies for this plugin.
     */
    private function load_dependencies()
    {
        require_once HOUZEZ_NETOPIA_PLUGIN_DIR . 'includes/class-houzez-netopia-loader.php';
        require_once HOUZEZ_NETOPIA_PLUGIN_DIR . 'includes/class-houzez-netopia-i18n.php';
        require_once HOUZEZ_NETOPIA_PLUGIN_DIR . 'includes/class-houzez-netopia-activator.php';
        require_once HOUZEZ_NETOPIA_PLUGIN_DIR . 'includes/class-houzez-netopia-deactivator.php';
        require_once HOUZEZ_NETOPIA_PLUGIN_DIR . 'includes/class-houzez-netopia-admin.php';
        require_once HOUZEZ_NETOPIA_PLUGIN_DIR . 'includes/class-houzez-netopia-api.php';
        require_once HOUZEZ_NETOPIA_PLUGIN_DIR . 'includes/class-houzez-netopia-gateway.php';
        require_once HOUZEZ_NETOPIA_PLUGIN_DIR . 'includes/class-houzez-netopia-payment-processor.php';
        require_once HOUZEZ_NETOPIA_PLUGIN_DIR . 'includes/class-houzez-netopia-ipn-handler.php';

        $this->loader = new Houzez_Netopia_Loader();
    }

    /**
     * Define the locale for this plugin for internationalization.
     */
    private function set_locale()
    {
        $plugin_i18n = new Houzez_Netopia_i18n();
        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
    }

    /**
     * Register all of the hooks related to the admin area functionality.
     */
    private function define_admin_hooks()
    {
        $plugin_admin = new Houzez_Netopia_Admin($this->get_plugin_name(), $this->get_version());
        $this->loader->add_action('admin_menu', $plugin_admin, 'add_admin_menu');
        $this->loader->add_action('admin_init', $plugin_admin, 'register_settings');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
    }

    /**
     * Register all of the hooks related to the public-facing functionality.
     */
    private function define_public_hooks()
    {
        // Enqueue frontend scripts and styles
        $this->loader->add_action('wp_enqueue_scripts', $this, 'enqueue_public_assets');

        // Add Netopia payment option to membership payment form (after payment-method.php)
        $this->loader->add_action('wp_footer', $this, 'add_netopia_to_membership_form');

        // Add Netopia payment option to listing payment form (after per-listing/payment-method.php)
        $this->loader->add_action('wp_footer', $this, 'add_netopia_to_listing_form');
    }

    /**
     * Enqueue public-facing scripts and styles.
     */
    public function enqueue_public_assets()
    {
        // Only load on payment pages
        if (! is_page_template('template-payment.php') && ! isset($_GET['selected_package']) && ! isset($_GET['prop-id'])) {
            return;
        }

        // CSS-ul și JS-ul sunt încărcate inline în fișierul principal al pluginului
        // Nu mai folosim wp_enqueue_style și wp_enqueue_script aici
    }

    /**
     * Add Netopia to membership payment form.
     */
    public function add_netopia_to_membership_form()
    {
        // Only on payment page and if membership is enabled
        if (! is_page_template('template-payment.php') && ! isset($_GET['selected_package'])) {
            return;
        }

        if (! function_exists('houzez_option')) {
            return;
        }

        $enable_paid_submission = houzez_option('enable_paid_submission');
        if ($enable_paid_submission !== 'membership') {
            return;
        }

        if (get_option('houzez_netopia_enabled', '0') === '1') {
            // Inject template after payment method form
            ?>
			<script type="text/javascript">
			jQuery(document).ready(function($) {
				if ($('.payment-method').length && !$('.netopia-method').length) {
					var netopiaHtml = <?php echo json_encode($this->get_netopia_membership_template()); ?>;
					$('.payment-method').append(netopiaHtml);
				}
			});
			</script>
			<?php
        }
    }

    /**
     * Add Netopia to listing payment form.
     */
    public function add_netopia_to_listing_form()
    {
        // Only on payment page and if per listing is enabled
        if (! is_page_template('template-payment.php') && ! isset($_GET['prop-id']) && ! isset($_GET['upgrade_id'])) {
            return;
        }

        if (! function_exists('houzez_option')) {
            return;
        }

        $enable_paid_submission = houzez_option('enable_paid_submission');
        if ($enable_paid_submission !== 'per_listing' && $enable_paid_submission !== 'free_paid_listing') {
            return;
        }

        if (get_option('houzez_netopia_enabled', '0') === '1') {
            // Inject template after payment method form
            ?>
			<script type="text/javascript">
			jQuery(document).ready(function($) {
				if ($('.payment-method').length && !$('.netopia-method').length) {
					var netopiaHtml = <?php echo json_encode($this->get_netopia_listing_template()); ?>;
					$('.payment-method').append(netopiaHtml);
				}
			});
			</script>
			<?php
        }
    }

    /**
     * Get Netopia membership template HTML.
     *
     * @return string Template HTML.
     */
    private function get_netopia_membership_template()
    {
        ob_start();
        include HOUZEZ_NETOPIA_PLUGIN_DIR . 'templates/payment-method-netopia.php';
        return ob_get_clean();
    }

    /**
     * Get Netopia listing template HTML.
     *
     * @return string Template HTML.
     */
    private function get_netopia_listing_template()
    {
        ob_start();
        include HOUZEZ_NETOPIA_PLUGIN_DIR . 'templates/payment-method-netopia-listing.php';
        return ob_get_clean();
    }

    /**
     * Register all payment-related hooks.
     */
    private function define_payment_hooks()
    {
        $gateway = new Houzez_Netopia_Gateway();

        // Add Netopia as payment option in membership payment form
        $this->loader->add_filter('houzez_payment_methods', $gateway, 'add_payment_method');

        // AJAX handlers for payment processing
        $this->loader->add_action('wp_ajax_houzez_netopia_package_payment', $gateway, 'process_package_payment');
        $this->loader->add_action('wp_ajax_nopriv_houzez_netopia_package_payment', $gateway, 'process_package_payment');
        $this->loader->add_action('wp_ajax_houzez_netopia_listing_payment', $gateway, 'process_listing_payment');
        $this->loader->add_action('wp_ajax_nopriv_houzez_netopia_listing_payment', $gateway, 'process_listing_payment');

        // IPN handler
        $ipn_handler = new Houzez_Netopia_IPN_Handler();
        $this->loader->add_action('init', $ipn_handler, 'handle_ipn');

        // Payment return handler
        $this->loader->add_action('init', $gateway, 'handle_payment_return');
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     */
    public function run()
    {
        $this->loader->run();
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     *
     * @return    string    The name of the plugin.
     */
    public function get_plugin_name()
    {
        return $this->plugin_name;
    }

    /**
     * The reference to the class that orchestrates the hooks with the plugin.
     *
     * @return    Houzez_Netopia_Loader    Orchestrates the hooks of the plugin.
     */
    public function get_loader()
    {
        return $this->loader;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @return    string    The version number of the plugin.
     */
    public function get_version()
    {
        return $this->version;
    }
}
