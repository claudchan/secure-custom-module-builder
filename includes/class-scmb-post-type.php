<?php
/**
 * Custom Post Type for Modules
 */

if (!defined('ABSPATH')) {
    exit;
}

class SCMB_Post_Type {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', [$this, 'register_post_type']);
        add_action('acf/init', [$this, 'register_acf_fields']);
    }
    
    /**
     * Register custom post type for modules
     */
    public function register_post_type() {
        $labels = [
            'name'               => __('Custom Modules', 'scmb'),
            'singular_name'      => __('Module', 'scmb'),
            'add_new'            => __('Add New Module', 'scmb'),
            'add_new_item'       => __('Add New Module', 'scmb'),
            'edit_item'          => __('Edit Module', 'scmb'),
            'new_item'           => __('New Module', 'scmb'),
            'view_item'          => __('View Module', 'scmb'),
            'search_items'       => __('Search Modules', 'scmb'),
            'not_found'          => __('No modules found', 'scmb'),
            'not_found_in_trash' => __('No modules found in trash', 'scmb'),
            'menu_name'          => __('Module Builder', 'scmb'),
        ];
        
        $args = [
            'labels'              => $labels,
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'menu_icon'           => 'dashicons-editor-table',
            'menu_position'       => 25,
            'capability_type'     => 'post',
            'hierarchical'        => false,
            'supports'            => ['title'],
            'show_in_rest'        => false,
            'has_archive'         => false,
            'rewrite'             => false,
        ];
        
        register_post_type('scmb_module', $args);
    }
    
    /**
     * Register ACF fields for module configuration
     */
    public function register_acf_fields() {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }
        
        // Module Configuration
        acf_add_local_field_group([
            'key' => 'group_scmb_config',
            'title' => __('Module Configuration', 'scmb'),
            'fields' => [
                [
                    'key' => 'field_module_label',
                    'label' => __('Module Label', 'scmb'),
                    'name' => 'module_label',
                    'type' => 'text',
                    'instructions' => __('Label shown in block inserter', 'scmb'),
                    'required' => 1,
                ],
                [
                    'key' => 'field_module_category',
                    'label' => __('Block Category', 'scmb'),
                    'name' => 'module_category',
                    'type' => 'select',
                    'instructions' => __('Category in block inserter', 'scmb'),
                    'choices' => [
                        'text' => __('Text', 'scmb'),
                        'media' => __('Media', 'scmb'),
                        'design' => __('Design', 'scmb'),
                        'widgets' => __('Widgets', 'scmb'),
                        'theme' => __('Theme', 'scmb'),
                        'embed' => __('Embed', 'scmb'),
                        'custom' => __('Custom', 'scmb'),
                    ],
                    'default_value' => 'custom',
                ],
                [
                    'key' => 'field_module_icon',
                    'label' => __('Block Icon', 'scmb'),
                    'name' => 'module_icon',
                    'type' => 'text',
                    'instructions' => __('Dashicon name (e.g., "admin-post", "star-filled")', 'scmb'),
                    'default_value' => 'admin-post',
                ],
                [
                    'key' => 'field_module_description',
                    'label' => __('Description', 'scmb'),
                    'name' => 'module_description',
                    'type' => 'textarea',
                    'rows' => 3,
                ],
                [
                    'key' => 'field_module_status',
                    'label' => __('Module Status', 'scmb'),
                    'name' => 'module_status',
                    'type' => 'true_false',
                    'message' => __('Active (available in block editor)', 'scmb'),
                    'default_value' => 1,
                    'ui' => 1,
                ],
                [
                    'key' => 'field_module_compact_code',
                    'label' => __('Compact Code', 'scmb'),
                    'name' => 'module_compact_code',
                    'type' => 'true_false',
                    'message' => __('Minify HTML, CSS, and JavaScript for smaller file size', 'scmb'),
                    'default_value' => 0,
                    'ui' => 1,
                ],
            ],
            'location' => [
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'scmb_module',
                    ],
                ],
            ],
            'position' => 'normal',
            'style' => 'default',
            'menu_order' => 1,
        ]);
        
        // Module Template
        acf_add_local_field_group([
            'key' => 'group_scmb_template',
            'title' => __('Module Template', 'scmb'),
            'fields' => [
                [
                    'key' => 'field_module_html',
                    'label' => __('HTML Template', 'scmb'),
                    'name' => 'module_html',
                    'type' => 'textarea',
                    'instructions' => __('Use {{field_name}} for field values. Example: <h2>{{title}}</h2>', 'scmb'),
                    'rows' => 15,
                    'placeholder' => '<div class="custom-block">
    <h2>{{title}}</h2>
    <div class="content">{{content}}</div>
</div>',
                ],
                [
                    'key' => 'field_module_css',
                    'label' => __('CSS Styles', 'scmb'),
                    'name' => 'module_css',
                    'type' => 'textarea',
                    'rows' => 15,
                    'placeholder' => '.custom-block {
    padding: 20px;
    background: #f5f5f5;
}',
                ],
                [
                    'key' => 'field_module_js',
                    'label' => __('JavaScript', 'scmb'),
                    'name' => 'module_js',
                    'type' => 'textarea',
                    'rows' => 15,
                    'placeholder' => '// jQuery available as $
$(document).ready(function() {
    // Your code here
});',
                ],
            ],
            'location' => [
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'scmb_module',
                    ],
                ],
            ],
            'position' => 'normal',
            'style' => 'default',
            'menu_order' => 0,
        ]);
        
        // Module Fields
        acf_add_local_field_group([
            'key' => 'group_scmb_fields',
            'title' => __('Module Fields', 'scmb'),
            'fields' => [
                [
                    'key' => 'field_module_fields',
                    'label' => __('Fields', 'scmb'),
                    'name' => 'module_fields',
                    'type' => 'repeater',
                    'instructions' => __('Define fields that users can edit when using this block', 'scmb'),
                    'button_label' => __('Add Field', 'scmb'),
                    'layout' => 'block',
                    'sub_fields' => [
                        [
                            'key' => 'field_field_name',
                            'label' => __('Field Name', 'scmb'),
                            'name' => 'field_name',
                            'type' => 'text',
                            'instructions' => __('Use lowercase, no spaces (e.g., title, content, image)', 'scmb'),
                            'required' => 1,
                            'wrapper' => [
                                'width' => '50',
                            ],
                        ],
                        [
                            'key' => 'field_field_label',
                            'label' => __('Field Label', 'scmb'),
                            'name' => 'field_label',
                            'type' => 'text',
                            'instructions' => __('Label shown in editor', 'scmb'),
                            'required' => 1,
                            'wrapper' => [
                                'width' => '50',
                            ],
                        ],
                        [
                            'key' => 'field_field_type',
                            'label' => __('Field Type', 'scmb'),
                            'name' => 'field_type',
                            'type' => 'select',
                            'choices' => [
                                'text' => __('Text', 'scmb'),
                                'textarea' => __('Textarea', 'scmb'),
                                'wysiwyg' => __('Rich Text (WYSIWYG)', 'scmb'),
                                'image' => __('Image', 'scmb'),
                                'url' => __('URL', 'scmb'),
                                'select' => __('Select', 'scmb'),
                                'checkbox' => __('Checkbox', 'scmb'),
                                'true_false' => __('True/False', 'scmb'),
                                'repeater' => __('Repeater', 'scmb'),
                            ],
                            'default_value' => 'text',
                            'wrapper' => [
                                'width' => '33',
                            ],
                        ],
                        [
                            'key' => 'field_field_default',
                            'label' => __('Default Value', 'scmb'),
                            'name' => 'field_default',
                            'type' => 'text',
                            'wrapper' => [
                                'width' => '33',
                            ],
                        ],
                        [
                            'key' => 'field_field_required',
                            'label' => __('Required', 'scmb'),
                            'name' => 'field_required',
                            'type' => 'true_false',
                            'ui' => 1,
                            'wrapper' => [
                                'width' => '33',
                            ],
                        ],
                        [
                            'key'   => 'field_field_sub_fields',
                            'type'  => 'textarea',
                            'name'  => 'field_sub_fields',
                            'label' => __('Sub-fields (for Repeater)', 'scmb'),
                            'instructions' => __('Enter sub-field names, one per line. Format: field_name|Field Label|field_type', 'scmb'),
                            'placeholder' => "item_title|Item Title|text\nitem_content|Item Content|textarea",
                            'rows' => 4,
                            'conditional_logic' => [
                                [
                                    [
                                        'field' => 'field_field_type',
                                        'operator' => '==',
                                        'value' => 'repeater',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'location' => [
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'scmb_module',
                    ],
                ],
            ],
            'position' => 'normal',
            'style' => 'default',
            'menu_order' => 2,
        ]);
    }
}