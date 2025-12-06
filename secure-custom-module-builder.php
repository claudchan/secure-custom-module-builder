<?php
/**
 * Plugin Name: Secure Custom Module Builder (SCMB)
 * Plugin URI: https://github.com/claudchan/secure-custom-module-builder
 * Description: Build custom Gutenberg blocks with a visual interface - like HubSpot modules for WordPress
 * Version: 1.0.0
 * Author: Claud Chan
 * Author URI: https://github.com/claudchan
 * License: GPL v2 or later
 * Text Domain: scmb
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Checks if Advanced Custom Fields is active on plugin load.
 * If it's not, the plugin is deactivated and an admin notice is shown.
 */
function scmb_check_for_acf_dependency() {
    // Check if the ACF class exists.
    if ( ! class_exists( 'ACF' ) ) {

        // Deactivate the plugin.
        deactivate_plugins( plugin_basename( __FILE__ ) );

        // Add a notice to the admin dashboard.
        add_action( 'admin_notices', 'scmb_acf_missing_notice' );

        // Hide the "Plugin activated" notice.
        if ( isset( $_GET['activate'] ) ) {
            unset( $_GET['activate'] );
        }
    }
}
add_action( 'admin_init', 'scmb_check_for_acf_dependency' );


/**
 * Displays an admin notice if ACF is not active.
 */
function scmb_acf_missing_notice() {
    $plugin_name = '<strong>' . esc_html__( 'Secure Custom Module Builder', 'scmb' ) . '</strong>';
    $acf_link    = esc_url( 'https://www.advancedcustomfields.com/pro/' );
    ?>
    <div class="notice notice-error is-dismissible">
        <p>
            <?php
            printf(
                /* translators: 1: Plugin name, 2: ACF link */
                esc_html__( '%1$s has been deactivated. It requires the %2$s plugin to be installed and activated.', 'scmb' ),
                $plugin_name,
                '<a href="' . $acf_link . '" target="_blank">' . esc_html__( 'Advanced Custom Fields (ACF)', 'scmb' ) . '</a>'
            );
            ?>
        </p>
    </div>
    <?php
}

// Define plugin constants
define('SCMB_VERSION', '1.0.0');
define('SCMB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SCMB_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SCMB_PLUGIN_FILE', __FILE__);

/**
 * Main SCMB Class
 */
class Secure_Custom_Module_Builder {
    
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->includes();
        $this->init_hooks();
    }
    
    /**
     * Include required files
     */
    private function includes() {
        require_once SCMB_PLUGIN_DIR . 'includes/class-scmb-post-type.php';
        require_once SCMB_PLUGIN_DIR . 'includes/class-scmb-admin.php';
        require_once SCMB_PLUGIN_DIR . 'includes/class-scmb-blocks.php';
        require_once SCMB_PLUGIN_DIR . 'includes/class-scmb-renderer.php';
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('plugins_loaded', [$this, 'init']);
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
    }
    
    /**
     * Initialize plugin
     * Note: ACF dependency is checked on admin_init via scmb_check_for_acf_dependency()
     */
    public function init() {
        // Initialize components
        SCMB_Post_Type::get_instance();
        SCMB_Admin::get_instance();
        SCMB_Blocks::get_instance();
        SCMB_Renderer::get_instance();
        
        // Load text domain
        load_plugin_textdomain('scmb', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        flush_rewrite_rules();
    }
    

}

/**
 * Initialize the plugin
 */
function scmb() {
    return Secure_Custom_Module_Builder::get_instance();
}

// Start the plugin
scmb();

/**
 * Enqueue scripts for the plugins page
 */
function scmb_enqueue_admin_scripts( $hook_suffix ) {
    // Only load on plugins page
    if ( 'plugins.php' !== $hook_suffix ) {
        return;
    }

    // Check if ACF is missing
    if ( class_exists( 'ACF' ) ) {
        return;
    }

    wp_enqueue_script(
        'scmb-disable-activation',
        SCMB_PLUGIN_URL . 'assets/js/disable-activation.js',
        [],
        SCMB_VERSION,
        true
    );
}
add_action( 'admin_enqueue_scripts', 'scmb_enqueue_admin_scripts' );