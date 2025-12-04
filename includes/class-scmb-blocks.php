<?php
/**
 * Gutenberg blocks registration
 */

if (!defined('ABSPATH')) {
    exit;
}

class SCMB_Blocks {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('acf/init', [$this, 'register_blocks']);
    }
    
    /**
     * Register all active modules as Gutenberg blocks
     */
    public function register_blocks() {
        if (!function_exists('acf_register_block_type')) {
            return;
        }
        
        // Get all active modules
        $modules = $this->get_active_modules();
        
        if (empty($modules)) {
            return;
        }
        
        foreach ($modules as $module) {
            $this->register_single_block($module);
        }
    }
    
    /**
     * Get all active modules
     */
    private function get_active_modules() {
        $args = [
            'post_type' => 'scmb_module',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => 'module_status',
                    'value' => '1',
                    'compare' => '='
                ]
            ]
        ];
        
        return get_posts($args);
    }
    
    /**
     * Register a single module as a block
     */
    private function register_single_block($module) {
        $module_id = $module->ID;
        $block_slug = sanitize_title($module->post_title);
        
        // Get module configuration
        $label = get_field('module_label', $module_id) ?: $module->post_title;
        $description = get_field('module_description', $module_id) ?: '';
        $category = get_field('module_category', $module_id) ?: 'custom';
        $icon = get_field('module_icon', $module_id) ?: 'admin-post';
        $fields = get_field('module_fields', $module_id) ?: [];
        
        // Register ACF field group for this block FIRST
        $this->register_block_fields($block_slug, $module_id, $label, $fields);
        
        // Register the block type
        acf_register_block_type([
            'name' => $block_slug,
            'title' => $label,
            'description' => $description,
            'category' => $category,
            'icon' => $icon,
            'keywords' => ['custom', 'scmb', $block_slug],
            'mode' => 'edit',
            'supports' => [
                'align' => true,
                'mode' => true,
                'jsx' => true,
            ],
            'render_callback' => function($block, $content = '', $is_preview = false, $post_id = 0) use ($module_id) {
                $this->render_block($block, $module_id, $is_preview, $post_id);
            },
            'enqueue_style' => null,
            'enqueue_script' => null,
            'enqueue_assets' => function() use ($module_id) {
                $this->enqueue_block_assets($module_id);
            },
        ]);
    }
    
    /**
     * Register ACF fields for a block
     */
    private function register_block_fields($block_slug, $module_id, $label, $fields) {
        if (empty($fields) || !is_array($fields)) {
            return;
        }
        
        $acf_fields = [];
        
        foreach ($fields as $field) {
            if (empty($field['field_name']) || empty($field['field_type'])) {
                continue;
            }
            
            $field_key = 'scmb_' . $module_id . '_' . $field['field_name'];
            
            $acf_field = [
                'key' => $field_key,
                'label' => $field['field_label'] ?: ucfirst($field['field_name']),
                'name' => $field['field_name'],
                'type' => $field['field_type'],
                'required' => !empty($field['field_required']) ? 1 : 0,
                'default_value' => $field['field_default'] ?? '',
            ];
            
            // Special settings for WYSIWYG
            if ($field['field_type'] === 'wysiwyg') {
                $acf_field['tabs'] = 'all';
                $acf_field['toolbar'] = 'full';
                $acf_field['media_upload'] = 1;
                $acf_field['delay'] = 0;
            }
            
            // Special settings for Repeater
            if ($field['field_type'] === 'repeater') {
                $acf_field['layout'] = 'block';
                $acf_field['button_label'] = 'Add Item';
                $acf_field['sub_fields'] = [];
                
                // Parse sub-fields from field_sub_fields
                if (!empty($field['field_sub_fields'])) {
                    $sub_fields_raw = explode("\n", $field['field_sub_fields']);
                    
                    foreach ($sub_fields_raw as $index => $sub_field_line) {
                        $sub_field_line = trim($sub_field_line);
                        if (empty($sub_field_line)) continue;
                        
                        // Parse format: field_name|Field Label|field_type
                        $parts = explode('|', $sub_field_line);
                        $sub_field_name = trim($parts[0]);
                        $sub_field_label = isset($parts[1]) ? trim($parts[1]) : ucfirst($sub_field_name);
                        $sub_field_type = isset($parts[2]) ? trim($parts[2]) : 'text';
                        
                        $sub_field = [
                            'key' => $field_key . '_' . $sub_field_name,
                            'label' => $sub_field_label,
                            'name' => $sub_field_name,
                            'type' => $sub_field_type,
                        ];
                        
                        // Special settings for wysiwyg sub-fields
                        if ($sub_field_type === 'wysiwyg') {
                            $sub_field['tabs'] = 'all';
                            $sub_field['toolbar'] = 'full';
                            $sub_field['media_upload'] = 1;
                        }
                        
                        $acf_field['sub_fields'][] = $sub_field;
                    }
                }
            }
            
            $acf_fields[] = $acf_field;
        }
        
        // Register field group
        if (!empty($acf_fields)) {
            acf_add_local_field_group([
                'key' => 'group_scmb_' . $module_id,
                'title' => $label . ' Fields',
                'fields' => $acf_fields,
                'location' => [
                    [
                        [
                            'param' => 'block',
                            'operator' => '==',
                            'value' => 'acf/' . $block_slug,
                        ],
                    ],
                ],
                'menu_order' => 0,
                'position' => 'normal',
                'style' => 'default',
                'label_placement' => 'top',
                'instruction_placement' => 'label',
                'hide_on_screen' => '',
                'active' => true,
            ]);
        }
    }
    
    /**
     * Render block on frontend and in editor.
     *
     * Safely renders the module block with proper output escaping to prevent XSS attacks.
     *
     * @param array $block The block object.
     * @param int   $module_id The module post ID.
     * @param bool  $is_preview Whether this is a preview context.
     * @param int   $post_id The current post ID.
     *
     * @return void
     */
    private function render_block( $block, $module_id, $is_preview, $post_id ) {
        // Get module template.
        $html_template = get_field( 'module_html', $module_id );
        $module_fields = get_field( 'module_fields', $module_id ) ?: [];

        if ( empty( $html_template ) ) {
            echo wp_kses_post(
                sprintf(
                    '<div style="padding: 20px; background: #f0f0f0; border: 2px dashed #ccc; text-align: center;">
                        <p><strong>%s</strong></p>
                        <p>%s</p>
                    </div>',
                    esc_html( get_the_title( $module_id ) ),
                    esc_html__( 'No HTML template defined for this module.', 'scmb' )
                )
            );
            return;
        }

        // Collect field values with proper escaping based on field type.
        $field_values = [];
        foreach ( $module_fields as $field ) {
            if ( ! empty( $field['field_name'] ) ) {
                // Get field value - ACF automatically knows the context.
                $value = get_field( $field['field_name'] );
                $value = ( false !== $value ) ? $value : '';

                // Apply appropriate escaping based on field type.
                switch ( $field['field_type'] ) {
                    case 'wysiwyg':
                        // Allow HTML tags for rich text - use wp_kses_post for security.
                        $field_values[ $field['field_name'] ] = wp_kses_post( $value );
                        break;
                    case 'url':
                        $field_values[ $field['field_name'] ] = esc_url( $value );
                        break;
                    case 'image':
                        // For images, store ID and let template handle retrieval.
                        $field_values[ $field['field_name'] ] = ! empty( $value ) ? (int) $value : '';
                        break;
                    case 'repeater':
                        // For repeaters, keep as array - escaping happens in process_repeater().
                        $field_values[ $field['field_name'] ] = is_array( $value ) ? $value : [];
                        break;
                    default:
                        // Escape plain text values to prevent XSS.
                        $field_values[ $field['field_name'] ] = esc_html( $value );
                }
            }
        }

        // Replace template variables.
        $output = $this->replace_template_variables( $html_template, $field_values );

        // Wrap in preview div if in editor.
        if ( $is_preview ) {
            echo '<div class="scmb-block-preview">';
        }

        // Output HTML - safe because all values are pre-escaped.
        echo wp_kses_post( $output );

        if ( $is_preview ) {
            echo '</div>';
        }
    }
    
    /**
     * Replace {{field_name}} with field values.
     *
     * Replaces {{field_name}} placeholders in templates with corresponding field values.
     * Removes any unreplaced variables to prevent information leakage.
     *
     * @param string $template The HTML template with {{field_name}} placeholders.
     * @param array  $values Associative array of field names and their values (pre-escaped).
     *
     * @return string The template with variables replaced and unreplaced variables removed.
     */
    private function replace_template_variables( $template, $values ) {
        foreach ( $values as $key => $value ) {
            // Validate key format to prevent injection.
            if ( ! preg_match( '/^[a-zA-Z_][a-zA-Z0-9_]*$/', $key ) ) {
                continue;
            }

            // Handle repeater fields - check if value is array of items
            if (is_array($value) && !empty($value) && isset($value[0]) && is_array($value[0])) {
                // This is a repeater field
                $repeater_output = $this->process_repeater($template, $key, $value);
                if ($repeater_output !== false) {
                    $template = $repeater_output;
                    continue;
                }
            }

            // Handle other array values
            if ( is_array( $value ) ) {
                $value = isset( $value['value'] ) ? $value['value'] : '';
            } elseif ( null === $value || false === $value ) {
                $value = '';
            }

            // Replace {{field_name}} with value (already escaped in render_block).
            $template = str_replace( '{{' . $key . '}}', $value, $template );
        }

        // Remove any unreplaced variables to prevent information leakage.
        $template = preg_replace( '/\{\{[^}]+\}\}/', '', $template );

        return $template;
    }

    /**
     * Process repeater field loops in template
     *
     * @param string $template The HTML template with repeater loop tags.
     * @param string $field_name The repeater field name.
     * @param array  $rows Array of repeater row data.
     *
     * @return string|false The template with repeater loop processed, or false if no loop found.
     */
    private function process_repeater( $template, $field_name, $rows ) {
        // Look for repeater loop: {{#field_name}} ... {{/field_name}}
        $pattern = '/\{\{#' . preg_quote( $field_name ) . '\}\}(.*?)\{\{\/' . preg_quote( $field_name ) . '\}\}/s';

        if ( ! preg_match( $pattern, $template, $matches ) ) {
            return false;
        }

        $loop_template = $matches[1];
        $output        = '';

        // Loop through each row
        foreach ( $rows as $row ) {
            $row_output = $loop_template;

            // Replace sub-field variables in this row
            foreach ( $row as $sub_field_name => $sub_field_value ) {
                // Don't escape - values should already be escaped in render_block()
                // Only convert arrays to strings
                if ( is_array( $sub_field_value ) ) {
                    $sub_field_value = '';
                }

                $row_output = str_replace( '{{' . $sub_field_name . '}}', $sub_field_value, $row_output );
            }

            $output .= $row_output;
        }

        // Replace the entire loop block with the output
        $template = preg_replace( $pattern, $output, $template );

        return $template;
    }
    
    /**
     * Enqueue block-specific CSS and JS
     */
    private function enqueue_block_assets($module_id) {
        $css = get_field('module_css', $module_id);
        $js = get_field('module_js', $module_id);
        
        // Inline CSS
        if (!empty($css)) {
            // Generate unique handle for this module
            $handle = 'scmb-module-' . $module_id;
            
            // Register a dummy style to attach inline CSS
            wp_register_style($handle, false);
            wp_enqueue_style($handle);
            wp_add_inline_style($handle, $css);
        }
        
        // Inline JS
        if (!empty($js)) {
            wp_add_inline_script('jquery', $js);
        }
    }
}