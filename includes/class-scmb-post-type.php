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
        add_action('acf/save_post', [$this, 'ensure_module_key'], 20);
    }
    
    /**
     * Register custom post type for modules
     */
    public function register_post_type() {
        $labels = [
            'name'               => __('Custom Modules', 'secure-custom-module-builder'),
            'singular_name'      => __('Module', 'secure-custom-module-builder'),
            'add_new'            => __('Add New Module', 'secure-custom-module-builder'),
            'add_new_item'       => __('Add New Module', 'secure-custom-module-builder'),
            'edit_item'          => __('Edit Module', 'secure-custom-module-builder'),
            'new_item'           => __('New Module', 'secure-custom-module-builder'),
            'view_item'          => __('View Module', 'secure-custom-module-builder'),
            'search_items'       => __('Search Modules', 'secure-custom-module-builder'),
            'not_found'          => __('No modules found', 'secure-custom-module-builder'),
            'not_found_in_trash' => __('No modules found in trash', 'secure-custom-module-builder'),
            'menu_name'          => __('Module Builder', 'secure-custom-module-builder'),
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
            'title' => __('Module Configuration', 'secure-custom-module-builder'),
            'fields' => [
                [
                    'key' => 'field_module_label',
                    'label' => __('Module Label', 'secure-custom-module-builder'),
                    'name' => 'module_label',
                    'type' => 'text',
                    'instructions' => __('Label shown in block inserter', 'secure-custom-module-builder'),
                    'required' => 1,
                ],
                [
                    'key' => 'field_module_key',
                    'label' => __('Module Key', 'secure-custom-module-builder'),
                    'name' => 'module_key',
                    'type' => 'text',
                    'instructions' => __('Stable internal key used for imports, exports, and Gutenberg block registration. Use lowercase letters, numbers, and hyphens only.', 'secure-custom-module-builder'),
                    'required' => 0,
                    'placeholder' => 'hero-banner',
                ],
                [
                    'key' => 'field_module_category',
                    'label' => __('Block Category', 'secure-custom-module-builder'),
                    'name' => 'module_category',
                    'type' => 'select',
                    'instructions' => __('Category in block inserter', 'secure-custom-module-builder'),
                    'choices' => [
                        'text' => __('Text', 'secure-custom-module-builder'),
                        'media' => __('Media', 'secure-custom-module-builder'),
                        'design' => __('Design', 'secure-custom-module-builder'),
                        'widgets' => __('Widgets', 'secure-custom-module-builder'),
                        'theme' => __('Theme', 'secure-custom-module-builder'),
                        'embed' => __('Embed', 'secure-custom-module-builder'),
                        'custom' => __('Custom', 'secure-custom-module-builder'),
                    ],
                    'default_value' => 'custom',
                ],
                [
                    'key' => 'field_module_icon',
                    'label' => __('Block Icon', 'secure-custom-module-builder'),
                    'name' => 'module_icon',
                    'type' => 'text',
                    'instructions' => __('Dashicon name (e.g., "admin-post", "star-filled")', 'secure-custom-module-builder'),
                    'default_value' => 'admin-post',
                ],
                [
                    'key' => 'field_module_description',
                    'label' => __('Description', 'secure-custom-module-builder'),
                    'name' => 'module_description',
                    'type' => 'textarea',
                    'rows' => 3,
                ],
                [
                    'key' => 'field_module_preview_image',
                    'label' => __('Preview Thumbnail', 'secure-custom-module-builder'),
                    'name' => 'module_preview_image',
                    'type' => 'image',
                    'instructions' => __('Shown in the block inserter preview. Uploads are stored in the WordPress Media Library.', 'secure-custom-module-builder'),
                    'return_format' => 'id',
                    'preview_size' => 'medium',
                    'library' => 'all',
                ],
                [
                    'key' => 'field_module_status',
                    'label' => __('Module Status', 'secure-custom-module-builder'),
                    'name' => 'module_status',
                    'type' => 'true_false',
                    'message' => __('Active (available in block editor)', 'secure-custom-module-builder'),
                    'default_value' => 1,
                    'ui' => 1,
                ],
                [
                    'key' => 'field_module_single_instance',
                    'label' => __('Single Instance', 'secure-custom-module-builder'),
                    'name' => 'module_single_instance',
                    'type' => 'true_false',
                    'instructions' => __('Uses WordPress core block support to allow this block only once per page or post.', 'secure-custom-module-builder'),
                    'message' => __('Allow only one instance per page/post', 'secure-custom-module-builder'),
                    'default_value' => 0,
                    'ui' => 1,
                ],
                [
                    'key' => 'field_module_compact_code',
                    'label' => __('Compact Code', 'secure-custom-module-builder'),
                    'name' => 'module_compact_code',
                    'type' => 'true_false',
                    'message' => __('Minify HTML, CSS, and JavaScript for smaller file size', 'secure-custom-module-builder'),
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
            'title' => __('Module Template', 'secure-custom-module-builder'),
            'fields' => [
                [
                    'key' => 'field_module_html',
                    'label' => __('HTML Template', 'secure-custom-module-builder'),
                    'name' => 'module_html',
                    'type' => 'textarea',
                    'instructions' => __(
                        '<span class="scmb-help-text">Use <code>{{field_name}}</code> for field values. Example: <code>&lt;h2&gt;{{title}}&lt;/h2&gt;</code><br>Use <code>{{#field_name}}...{{/field_name}}</code> for repeaters. Inside repeaters you can use <code>{{#if __first}}active{{/if}}</code>, <code>{{__index}}</code>, <code>{{__position}}</code>, and <code>{{#if __last}}...{{/if}}</code>. Repeaters also expose <code>{{repeater_name__count}}</code> and <code>{{#if repeater_name__count &gt; 1}}</code>.<br>Use <code>{{#if field_name}}...{{else}}...{{/if}}</code> for conditional output.<br>Simple expressions are supported: <code>{{#if title && content}}</code>, <code>{{#if title || content}}</code>, <code>{{#if !title}}</code>.</span><div class="scmb-snippet-panel" aria-live="polite"><div class="scmb-snippet-panel__header"><strong>Field Snippets</strong><span>Copy or insert snippets from your Module Fields.</span></div><div class="scmb-snippet-list"><p class="scmb-snippet-empty">Add fields in Module Fields to generate snippets.</p></div></div>',
                        'secure-custom-module-builder'
                    ),
                    'rows' => 15,
                    'placeholder' => '<div class="custom-block">
    <h2>{{title}}</h2>
    <div class="content">{{content}}</div>
</div>',
                ],
                [
                    'key' => 'field_module_css',
                    'label' => __('CSS Styles', 'secure-custom-module-builder'),
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
                    'label' => __('JavaScript', 'secure-custom-module-builder'),
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
            'title' => __('Module Fields', 'secure-custom-module-builder'),
            'fields' => [
                [
                    'key' => 'field_module_fields',
                    'label' => __('Fields', 'secure-custom-module-builder'),
                    'name' => 'module_fields',
                    'type' => 'repeater',
                    'instructions' => __('Define fields that users can edit when using this block', 'secure-custom-module-builder'),
                    'button_label' => __('Add Field', 'secure-custom-module-builder'),
                    'layout' => 'block',
                    'sub_fields' => [
                        [
                            'key' => 'field_field_label',
                            'label' => __('Field Label', 'secure-custom-module-builder'),
                            'name' => 'field_label',
                            'type' => 'text',
                            'instructions' => __('Label shown in editor', 'secure-custom-module-builder'),
                            'required' => 1,
                            'wrapper' => [
                                'width' => '50',
                            ],
                        ],
                        [
                            'key' => 'field_field_name',
                            'label' => __('Field Name', 'secure-custom-module-builder'),
                            'name' => 'field_name',
                            'type' => 'text',
                            'instructions' => __('Use lowercase underscores only (e.g., title, hero_headline, hero_rotating_headline). Spaces, dashes, and pasted text are normalized automatically.', 'secure-custom-module-builder'),
                            'required' => 1,
                            'wrapper' => [
                                'width' => '50',
                            ],
                        ],
                        [
                            'key' => 'field_field_type',
                            'label' => __('Field Type', 'secure-custom-module-builder'),
                            'name' => 'field_type',
                            'type' => 'select',
                            'choices' => [
                                'text' => __('Text', 'secure-custom-module-builder'),
                                'textarea' => __('Textarea', 'secure-custom-module-builder'),
                                'wysiwyg' => __('Rich Text (WYSIWYG)', 'secure-custom-module-builder'),
                                'image' => __('Image', 'secure-custom-module-builder'),
                                'url' => __('URL', 'secure-custom-module-builder'),
                                'select' => __('Select', 'secure-custom-module-builder'),
                                'checkbox' => __('Checkbox', 'secure-custom-module-builder'),
                                'true_false' => __('True/False', 'secure-custom-module-builder'),
                                'repeater' => __('Repeater', 'secure-custom-module-builder'),
                            ],
                            'default_value' => 'text',
                            'wrapper' => [
                                'width' => '33',
                            ],
                        ],
                        [
                            'key' => 'field_field_default',
                            'label' => __('Default Value', 'secure-custom-module-builder'),
                            'name' => 'field_default',
                            'type' => 'text',
                            'instructions' => __('For select fields, use one of the configured choice values.', 'secure-custom-module-builder'),
                            'wrapper' => [
                                'width' => '33',
                            ],
                        ],
                        [
                            'key' => 'field_field_choices',
                            'label' => __('Select Choices', 'secure-custom-module-builder'),
                            'name' => 'field_choices',
                            'type' => 'textarea',
                            'instructions' => __('Enter choices as value:Label pairs, separated by commas or new lines. Example: primary:Primary, ghost:Ghost', 'secure-custom-module-builder'),
                            'placeholder' => "primary:Primary\nghost:Ghost\noutline:Outline",
                            'rows' => 3,
                            'conditional_logic' => [
                                [
                                    [
                                        'field' => 'field_field_type',
                                        'operator' => '==',
                                        'value' => 'select',
                                    ],
                                ],
                            ],
                        ],
                        [
                            'key' => 'field_field_allow_null',
                            'label' => __('Allow Null', 'secure-custom-module-builder'),
                            'name' => 'field_allow_null',
                            'type' => 'true_false',
                            'instructions' => __('Allow users to leave this select field empty.', 'secure-custom-module-builder'),
                            'ui' => 1,
                            'wrapper' => [
                                'width' => '33',
                            ],
                            'conditional_logic' => [
                                [
                                    [
                                        'field' => 'field_field_type',
                                        'operator' => '==',
                                        'value' => 'select',
                                    ],
                                ],
                            ],
                        ],
                        [
                            'key' => 'field_field_required',
                            'label' => __('Required', 'secure-custom-module-builder'),
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
                            'label' => __('Sub-fields (for Repeater)', 'secure-custom-module-builder'),
                            'instructions' => __('Enter sub-field names, one per line. Format: field_name|Field Label|field_type. Indent lines below a repeater sub-field to create nested repeater fields. For select fields, add choices as value:Label pairs. Add |allow_null as the fifth value to allow an empty selection. For nested repeaters, add |max=4 to limit rows.', 'secure-custom-module-builder'),
                            'placeholder' => "item_title|Item Title|text\nitem_content|Item Content|textarea\nitem_tags|Tags|repeater|max=4\n  tag_label|Tag Label|text\n  tag_url|Tag URL|url\nbutton_style|Button Style|select|primary:Primary,ghost:Ghost\nbutton_target|Button Target|select|same:Same Tab,new:New Tab|allow_null",
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
                        [
                            'key' => 'field_field_repeater_max',
                            'label' => __('Max Rows (for Repeater)', 'secure-custom-module-builder'),
                            'name' => 'field_repeater_max',
                            'type' => 'number',
                            'instructions' => __('Leave blank for unlimited rows.', 'secure-custom-module-builder'),
                            'min' => 1,
                            'step' => 1,
                            'wrapper' => [
                                'width' => '33',
                            ],
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

    /**
     * Ensure every module has a stable key for portable imports/exports.
     *
     * @param int|string $post_id ACF save post ID.
     * @return void
     */
    public function ensure_module_key($post_id) {
        if (!is_numeric($post_id) || 'scmb_module' !== get_post_type((int) $post_id)) {
            return;
        }

        $post_id = (int) $post_id;
        $key = get_post_meta($post_id, 'module_key', true);
        $key = $this->sanitize_module_key($key);

        if (empty($key)) {
            $key = $this->generate_unique_module_key(get_the_title($post_id), $post_id);
        } elseif ($this->module_key_exists($key, $post_id)) {
            $key = $this->generate_unique_module_key($key, $post_id);
        }

        if (function_exists('update_field')) {
            update_field('module_key', $key, $post_id);
        } else {
            update_post_meta($post_id, 'module_key', $key);
            update_post_meta($post_id, '_module_key', 'field_module_key');
        }
    }

    /**
     * Sanitize a module key.
     *
     * @param string $key Raw key.
     * @return string
     */
    private function sanitize_module_key($key) {
        $key = sanitize_title((string) $key);
        return preg_replace('/[^a-z0-9-]/', '', $key);
    }

    /**
     * Generate a unique module key.
     *
     * @param string $source Source text.
     * @param int    $exclude_post_id Existing post ID to ignore.
     * @return string
     */
    private function generate_unique_module_key($source, $exclude_post_id = 0) {
        $base = $this->sanitize_module_key($source);

        if (empty($base)) {
            $base = 'module';
        }

        $key = $base;
        $suffix = 2;

        while ($this->module_key_exists($key, $exclude_post_id)) {
            $key = $base . '-' . $suffix;
            $suffix++;
        }

        return $key;
    }

    /**
     * Check if a module key is already used.
     *
     * @param string $key Module key.
     * @param int    $exclude_post_id Existing post ID to ignore.
     * @return bool
     */
    private function module_key_exists($key, $exclude_post_id = 0) {
        $posts = get_posts([
            'post_type' => 'scmb_module',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => 'module_key',
                    'value' => $key,
                    'compare' => '=',
                ],
            ],
        ]);

        foreach ($posts as $post_id) {
            if ((int) $post_id !== (int) $exclude_post_id) {
                return true;
            }
        }

        return false;
    }
}
