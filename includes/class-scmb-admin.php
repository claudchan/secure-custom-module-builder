<?php
/**
 * Admin functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class SCMB_Admin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_filter('admin_footer_text', [$this, 'admin_footer_text']);
        add_action('admin_menu', [$this, 'register_settings_page']);
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on module edit screens
        global $post_type;
        if ($post_type !== 'scmb_module') {
            return;
        }

        $admin_css_version = filemtime( SCMB_PLUGIN_DIR . 'assets/css/admin.css' );
        $admin_js_version  = filemtime( SCMB_PLUGIN_DIR . 'assets/js/admin.js' );
        
        // Enqueue our local CodeMirror library
        wp_enqueue_style(
            'scmb-codemirror',
            SCMB_PLUGIN_URL . 'assets/lib/codemirror/codemirror.min.css',
            [],
            SCMB_VERSION
        );
        
        wp_enqueue_style(
            'scmb-codemirror-theme-nord',
            SCMB_PLUGIN_URL . 'assets/lib/codemirror/theme/nord.min.css',
            [],
            SCMB_VERSION
        );
        
        wp_enqueue_script(
            'scmb-codemirror',
            SCMB_PLUGIN_URL . 'assets/lib/codemirror/codemirror.min.js',
            [],
            SCMB_VERSION,
            false
        );

        // Enqueue the necessary Mode and Addons JS files
        wp_enqueue_script(
            'scmb-codemirror-mode-xml',
            SCMB_PLUGIN_URL . 'assets/lib/codemirror/mode/xml/xml.min.js',
            ['scmb-codemirror'],
            SCMB_VERSION,
            false
        );
        wp_enqueue_script(
            'scmb-codemirror-mode-javascript',
            SCMB_PLUGIN_URL . 'assets/lib/codemirror/mode/javascript/javascript.min.js',
            ['scmb-codemirror'],
            SCMB_VERSION,
            false
        );
        wp_enqueue_script(
            'scmb-codemirror-mode-css',
            SCMB_PLUGIN_URL . 'assets/lib/codemirror/mode/css/css.min.js',
            ['scmb-codemirror'],
            SCMB_VERSION,
            false
        );
        wp_enqueue_script(
            'scmb-codemirror-mode-htmlmixed',
            SCMB_PLUGIN_URL . 'assets/lib/codemirror/mode/htmlmixed/htmlmixed.min.js',
            ['scmb-codemirror'],
            SCMB_VERSION,
            false
        );
        wp_enqueue_script(
            'scmb-codemirror-addon-edit-matchbrackets',
            SCMB_PLUGIN_URL . 'assets/lib/codemirror/addon/edit/matchbrackets.min.js',
            ['scmb-codemirror'],
            SCMB_VERSION,
            false
        );
        wp_enqueue_script(
            'scmb-codemirror-addon-edit-closetag',
            SCMB_PLUGIN_URL . 'assets/lib/codemirror/addon/edit/closetag.min.js',
            ['scmb-codemirror'],
            SCMB_VERSION,
            false
        );
        wp_enqueue_script(
            'scmb-codemirror-addon-hint-show-hint',
            SCMB_PLUGIN_URL . 'assets/lib/codemirror/addon/hint/show-hint.min.js',
            ['scmb-codemirror'],
            SCMB_VERSION,
            false
        );
        wp_enqueue_script(
            'scmb-codemirror-addon-hint-html-hint',
            SCMB_PLUGIN_URL . 'assets/lib/codemirror/addon/hint/html-hint.min.js',
            ['scmb-codemirror'],
            SCMB_VERSION,
            false
        );
        
        // Enqueue our custom admin CSS
        wp_enqueue_style(
            'scmb-admin',
            SCMB_PLUGIN_URL . 'assets/css/admin.css',
            ['scmb-codemirror'],
            $admin_css_version
        );
        
        // Enqueue our custom admin JS
        wp_enqueue_script(
            'scmb-admin',
            SCMB_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery', 'scmb-codemirror'],
            $admin_js_version,
            true
        );
    }
    
    /**
     * Modify admin footer text on SCMB pages
     */
    public function admin_footer_text($text) {
        global $post_type;
        if ($post_type === 'scmb_module') {
            $text = wp_kses(
                __('Thank you for using <strong>Secure Custom Module Builder</strong>!', 'secure-custom-module-builder'),
                [
                    'strong' => [],
                ]
            );
        }
        return $text;
    }

    /**
     * Register settings submenu under Module Builder.
     */
    public function register_settings_page() {
        add_submenu_page(
            'edit.php?post_type=scmb_module',
            __('Module Builder Settings', 'secure-custom-module-builder'),
            __('Settings', 'secure-custom-module-builder'),
            'manage_options',
            'scmb-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Render settings page.
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'secure-custom-module-builder'));
        }

        // Save settings if posted
        if (isset($_POST['scmb_save_settings']) && check_admin_referer('scmb_save_settings_nonce', 'scmb_settings_nonce')) {
            $global_css = isset($_POST['scmb_global_editor_css']) ? wp_strip_all_tags($_POST['scmb_global_editor_css']) : '';
            update_option('scmb_global_editor_css', $global_css);
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved.', 'secure-custom-module-builder') . '</p></div>';
        }

        $global_css = get_option('scmb_global_editor_css', '');
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Module Builder Settings', 'secure-custom-module-builder'); ?></h1>
            <form method="post" action="">
                <?php wp_nonce_field('scmb_save_settings_nonce', 'scmb_settings_nonce'); ?>
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="scmb_global_editor_css"><?php esc_html_e('Global Editor Custom CSS', 'secure-custom-module-builder'); ?></label>
                            </th>
                            <td>
                                <textarea name="scmb_global_editor_css" id="scmb_global_editor_css" rows="12" class="large-text code" placeholder="/* Add your global editor styles here. E.g. */&#10;.scmb-block-preview h1 {&#10;    color: #333;&#10;    font-family: sans-serif;&#10;}"><?php echo esc_textarea($global_css); ?></textarea>
                                <p class="description">
                                    <?php esc_html_e('Enter raw custom CSS code to load inside the Gutenberg block editor. You can use this to apply layout, fonts, or colors globally within your block previews.', 'secure-custom-module-builder'); ?>
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p class="submit">
                    <input type="submit" name="scmb_save_settings" id="submit" class="button button-primary" value="<?php esc_attr_e('Save Changes', 'secure-custom-module-builder'); ?>">
                </p>
            </form>
        </div>
        <?php
    }
}
