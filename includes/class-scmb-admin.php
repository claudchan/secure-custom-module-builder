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
        
        // Enqueue our local CodeMirror library
        wp_enqueue_style(
            'scmb-codemirror',
            SCMB_PLUGIN_URL . 'assets/lib/codemirror/codemirror.min.css',
            [],
            SCMB_VERSION
        );
        
        wp_enqueue_style(
            'scmb-codemirror-theme-monokai',
            SCMB_PLUGIN_URL . 'assets/lib/codemirror/theme/monokai.min.css',
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
            SCMB_VERSION
        );
        
        // Enqueue our custom admin JS
        wp_enqueue_script(
            'scmb-admin',
            SCMB_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery', 'scmb-codemirror'],
            SCMB_VERSION,
            true
        );
    }
    
    /**
     * Modify admin footer text on SCMB pages
     */
    public function admin_footer_text($text) {
        global $post_type;
        if ($post_type === 'scmb_module') {
            $text = sprintf(
                __('Thank you for using <strong>Secure Custom Module Builder</strong>!', 'scmb')
            );
        }
        return $text;
    }
}