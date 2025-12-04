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
        // Admin functionality will be added in next phase
    }
}