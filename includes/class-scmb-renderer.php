<?php
/**
 * Frontend rendering
 */

if (!defined('ABSPATH')) {
    exit;
}

class SCMB_Renderer {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
    }
    
    /**
     * Enqueue frontend assets (CSS/JS from modules)
     */
    public function enqueue_frontend_assets() {
        // This is handled by the block's enqueue_assets callback
        // But we can add global styles here if needed
    }
}