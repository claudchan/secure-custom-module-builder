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
     */
    public function init() {
        // Check if ACF is active
        if (!function_exists('acf_add_local_field_group')) {
            add_action('admin_notices', [$this, 'acf_missing_notice']);
            return;
        }
        
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
    
    /**
     * Display notice if ACF is not installed.
     */
    public function acf_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php esc_html_e( 'Secure Custom Module Builder', 'scmb' ); ?></strong>
                <?php esc_html_e( 'requires Advanced Custom Fields (ACF) plugin to be installed and activated.', 'scmb' ); ?>
                <a href="<?php echo esc_url( admin_url( 'plugin-install.php?s=advanced+custom+fields&tab=search' ) ); ?>">
                    <?php esc_html_e( 'Install Advanced Custom Fields', 'scmb' ); ?>
                </a>
            </p>
        </div>
        <?php
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