<?php
/**
 * Gutenberg blocks registration
 */

if (!defined('ABSPATH')) {
    exit;
}

class SCMB_Blocks {
    
    private static $instance = null;
    private static $enqueued_modules = []; // Track which modules have been enqueued
    
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
        // Get module template and CSS.
        $html_template = get_field( 'module_html', $module_id );
        $css = get_field( 'module_css', $module_id );
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

        // Collect field values with escaping tailored to each field type.
        $field_values = $this->build_template_context( $module_fields );

        // Render the template using the prepared field context.
        $output = $this->render_template( $html_template, $field_values );

        // Wrap in preview div if in editor and output CSS inline for preview.
        if ( $is_preview ) {
            echo '<div class="scmb-block-preview">';
            
            // Output CSS inline for ACF editor preview
            if ( ! empty( $css ) ) {
                $sanitized_css = $this->sanitize_css( $css );
                echo '<style>';
                echo wp_kses_post( $sanitized_css );
                echo '</style>';
            }
        }

        // Output HTML - safe because all values are pre-escaped.
        echo wp_kses_post( $output );

        if ( $is_preview ) {
            echo '</div>';
        }
    }

    /**
     * Build the rendering context for a module template.
     *
     * @param array $module_fields Module field definitions.
     * @return array
     */
    private function build_template_context( $module_fields ) {
        $context = [];

        foreach ( $module_fields as $field ) {
            if ( empty( $field['field_name'] ) || empty( $field['field_type'] ) ) {
                continue;
            }

            $value = get_field( $field['field_name'] );
            $value = ( false !== $value ) ? $value : '';

            $context[ $field['field_name'] ] = $this->prepare_field_value( $field, $value );
        }

        return $context;
    }

    /**
     * Prepare a single field value for template rendering.
     *
     * @param array $field Field definition.
     * @param mixed $value Raw field value.
     * @return mixed
     */
    private function prepare_field_value( $field, $value ) {
        switch ( $field['field_type'] ) {
            case 'wysiwyg':
                return wp_kses_post( $value );

            case 'url':
                return esc_url( $value );

            case 'image':
                return ! empty( $value ) ? (int) $value : '';

            case 'true_false':
                return ! empty( $value );

            case 'repeater':
                return $this->prepare_repeater_rows( $value, $field );

            default:
                return is_scalar( $value ) ? esc_html( (string) $value ) : '';
        }
    }

    /**
     * Prepare repeater rows for template rendering.
     *
     * @param mixed $rows  Raw repeater rows.
     * @param array $field Repeater field definition.
     * @return array
     */
    private function prepare_repeater_rows( $rows, $field ) {
        if ( ! is_array( $rows ) ) {
            return [];
        }

        $prepared_rows   = [];
        $sub_field_types = $this->get_repeater_sub_field_types( $field );

        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }

            $prepared_row = [];

            foreach ( $row as $sub_field_name => $sub_field_value ) {
                $sub_field_type = isset( $sub_field_types[ $sub_field_name ] ) ? $sub_field_types[ $sub_field_name ] : 'text';
                $prepared_row[ $sub_field_name ] = $this->prepare_sub_field_value( $sub_field_type, $sub_field_value );
            }

            $prepared_rows[] = $prepared_row;
        }

        return $prepared_rows;
    }

    /**
     * Get the configured types for repeater sub-fields.
     *
     * @param array $field Repeater field definition.
     * @return array
     */
    private function get_repeater_sub_field_types( $field ) {
        $sub_field_types = [];

        if ( empty( $field['field_sub_fields'] ) ) {
            return $sub_field_types;
        }

        $sub_fields_raw = explode( "\n", $field['field_sub_fields'] );

        foreach ( $sub_fields_raw as $sub_field_line ) {
            $sub_field_line = trim( $sub_field_line );

            if ( empty( $sub_field_line ) ) {
                continue;
            }

            $parts          = array_map( 'trim', explode( '|', $sub_field_line ) );
            $sub_field_name = isset( $parts[0] ) ? $parts[0] : '';
            $sub_field_type = isset( $parts[2] ) ? $parts[2] : 'text';

            if ( empty( $sub_field_name ) ) {
                continue;
            }

            $sub_field_types[ $sub_field_name ] = $sub_field_type;
        }

        return $sub_field_types;
    }

    /**
     * Prepare a repeater sub-field value for template rendering.
     *
     * @param string $field_type Sub-field type.
     * @param mixed  $value      Raw sub-field value.
     * @return mixed
     */
    private function prepare_sub_field_value( $field_type, $value ) {
        switch ( $field_type ) {
            case 'wysiwyg':
                return wp_kses_post( $value );

            case 'url':
                return esc_url( $value );

            case 'image':
                return ! empty( $value ) ? (int) $value : '';

            case 'true_false':
                return ! empty( $value );

            default:
                return is_scalar( $value ) ? esc_html( (string) $value ) : '';
        }
    }

    /**
     * Render a template string using the supplied context.
     *
     * Supported syntax:
     * - {{field_name}}
     * - {{#field_name}} ... {{/field_name}}
     * - {{#if field_name}} ... {{else}} ... {{/if}}
     * - {{#if title && content}} ... {{/if}}
     * - {{#if title || content}} ... {{/if}}
     * - {{#if !title}} ... {{/if}}
     *
     * @param string $template Template markup.
     * @param array  $context  Rendering context.
     * @return string
     */
    private function render_template( $template, $context ) {
        if ( ! is_string( $template ) || empty( $template ) ) {
            return '';
        }

        $template = $this->render_template_sections( $template, $context );
        $template = $this->replace_template_variables( $template, $context );

        // Remove any unsupported or unresolved tags from the final output.
        return preg_replace( '/\{\{[^}]+\}\}/', '', $template );
    }

    /**
     * Render template control structures such as sections and conditionals.
     *
     * @param string $template Template markup.
     * @param array  $context  Rendering context.
     * @return string
     */
    private function render_template_sections( $template, $context ) {
        $opening_pattern = '/\{\{\s*#(?:(if)\s+([^}]+?)|([a-zA-Z_][a-zA-Z0-9_]*))\s*\}\}/';

        while ( preg_match( $opening_pattern, $template, $matches, PREG_OFFSET_CAPTURE ) ) {
            $full_match     = $matches[0][0];
            $opening_offset = $matches[0][1];
            $opening_length = strlen( $full_match );
            $section_type   = ! empty( $matches[1][0] ) ? 'if' : 'repeater';
            $section_name   = 'if' === $section_type ? trim( $matches[2][0] ) : $matches[3][0];
            $section_end    = $opening_offset + $opening_length;
            $boundaries     = $this->find_template_section_boundaries( $template, $section_end, $section_type, $section_name );

            if ( false === $boundaries ) {
                break;
            }

            if ( 'if' === $section_type ) {
                $replacement = $this->render_if_section( $template, $context, $section_name, $section_end, $boundaries );
            } else {
                $replacement = $this->render_repeater_section( $template, $context, $section_name, $section_end, $boundaries );
            }

            $template = substr_replace(
                $template,
                $replacement,
                $opening_offset,
                $boundaries['close_end'] - $opening_offset
            );
        }

        return $template;
    }

    /**
     * Find the closing boundary for a template section.
     *
     * @param string $template     Template markup.
     * @param int    $offset       Search offset immediately after the opening tag.
     * @param string $section_type Section type: if or repeater.
     * @param string $section_name Section field name.
     * @return array|false
     */
    private function find_template_section_boundaries( $template, $offset, $section_type, $section_name ) {
        $pattern       = '/\{\{\s*(#if\s+[^}]+|#[a-zA-Z_][a-zA-Z0-9_]*|\/if|\/[a-zA-Z_][a-zA-Z0-9_]*|else)\s*\}\}/';
        $search_offset = $offset;
        $depth         = 1;
        $else_tag      = null;

        while ( preg_match( $pattern, $template, $matches, PREG_OFFSET_CAPTURE, $search_offset ) ) {
            $tag      = $matches[1][0];
            $tag_text = $matches[0][0];
            $tag_pos  = $matches[0][1];
            $tag_end  = $tag_pos + strlen( $tag_text );

            if ( 'if' === $section_type ) {
                if ( 0 === strpos( $tag, '#if ' ) ) {
                    ++$depth;
                } elseif ( '/if' === $tag ) {
                    --$depth;

                    if ( 0 === $depth ) {
                        return [
                            'close_start' => $tag_pos,
                            'close_end'   => $tag_end,
                            'else_tag'    => $else_tag,
                        ];
                    }
                } elseif ( 'else' === $tag && 1 === $depth && null === $else_tag ) {
                    $else_tag = [
                        'start' => $tag_pos,
                        'end'   => $tag_end,
                    ];
                }
            } else {
                if ( '#' . $section_name === $tag ) {
                    ++$depth;
                } elseif ( '/' . $section_name === $tag ) {
                    --$depth;

                    if ( 0 === $depth ) {
                        return [
                            'close_start' => $tag_pos,
                            'close_end'   => $tag_end,
                            'else_tag'    => null,
                        ];
                    }
                }
            }

            $search_offset = $tag_end;
        }

        return false;
    }

    /**
     * Render an if/else section.
     *
     * @param string $template     Template markup.
     * @param array  $context      Rendering context.
     * @param string $section_name Conditional expression.
     * @param int    $content_start Start of section content.
     * @param array  $boundaries   Section boundary data.
     * @return string
     */
    private function render_if_section( $template, $context, $section_name, $content_start, $boundaries ) {
        $else_tag       = $boundaries['else_tag'];
        $true_content   = substr( $template, $content_start, $boundaries['close_start'] - $content_start );
        $false_content  = '';
        $condition_value = $this->evaluate_condition_expression( $section_name, $context );

        if ( is_array( $else_tag ) ) {
            $true_content  = substr( $template, $content_start, $else_tag['start'] - $content_start );
            $false_content = substr( $template, $else_tag['end'], $boundaries['close_start'] - $else_tag['end'] );
        }

        if ( $this->is_template_value_truthy( $condition_value ) ) {
            return $this->render_template( $true_content, $context );
        }

        return $this->render_template( $false_content, $context );
    }

    /**
     * Render a repeater section.
     *
     * @param string $template      Template markup.
     * @param array  $context       Rendering context.
     * @param string $section_name  Repeater field name.
     * @param int    $content_start Start of section content.
     * @param array  $boundaries    Section boundary data.
     * @return string
     */
    private function render_repeater_section( $template, $context, $section_name, $content_start, $boundaries ) {
        $section_value   = $this->get_template_context_value( $context, $section_name );
        $section_content = substr( $template, $content_start, $boundaries['close_start'] - $content_start );
        $output          = '';

        if ( ! is_array( $section_value ) ) {
            return '';
        }

        foreach ( $section_value as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }

            $row_context = $this->build_child_template_context( $context, $row );
            $output     .= $this->render_template( $section_content, $row_context );
        }

        return $output;
    }

    /**
     * Merge a child row context onto the current context.
     *
     * @param array $context Current rendering context.
     * @param array $row     Child row values.
     * @return array
     */
    private function build_child_template_context( $context, $row ) {
        return array_merge( $context, $row );
    }

    /**
     * Get a value from the template context.
     *
     * @param array  $context    Rendering context.
     * @param string $field_name Context key.
     * @return mixed
     */
    private function get_template_context_value( $context, $field_name ) {
        return isset( $context[ $field_name ] ) ? $context[ $field_name ] : null;
    }

    /**
     * Determine whether a template value should be treated as truthy.
     *
     * @param mixed $value Template value.
     * @return bool
     */
    private function is_template_value_truthy( $value ) {
        if ( is_array( $value ) ) {
            return ! empty( $value );
        }

        if ( is_bool( $value ) ) {
            return $value;
        }

        if ( is_numeric( $value ) ) {
            return 0.0 !== (float) $value;
        }

        return '' !== trim( (string) $value );
    }

    /**
     * Evaluate a supported conditional expression.
     *
     * Supported operators:
     * - &&
     * - ||
     * - !
     *
     * Expressions are intentionally simple and field-based so the template
     * language remains predictable and safe to extend.
     *
     * @param string $expression Conditional expression.
     * @param array  $context    Rendering context.
     * @return bool
     */
    private function evaluate_condition_expression( $expression, $context ) {
        $expression = trim( (string) $expression );

        if ( '' === $expression ) {
            return false;
        }

        $or_parts = preg_split( '/\s*\|\|\s*/', $expression );

        if ( false === $or_parts || empty( $or_parts ) ) {
            return false;
        }

        foreach ( $or_parts as $or_part ) {
            if ( $this->evaluate_and_expression( $or_part, $context ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Evaluate an AND expression segment.
     *
     * @param string $expression AND expression segment.
     * @param array  $context    Rendering context.
     * @return bool
     */
    private function evaluate_and_expression( $expression, $context ) {
        $and_parts = preg_split( '/\s*&&\s*/', trim( $expression ) );

        if ( false === $and_parts || empty( $and_parts ) ) {
            return false;
        }

        foreach ( $and_parts as $and_part ) {
            if ( ! $this->evaluate_condition_operand( $and_part, $context ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Evaluate a single conditional operand.
     *
     * @param string $operand Conditional operand.
     * @param array  $context Rendering context.
     * @return bool
     */
    private function evaluate_condition_operand( $operand, $context ) {
        $operand  = trim( $operand );
        $negated  = false;

        while ( 0 === strpos( $operand, '!' ) ) {
            $negated = ! $negated;
            $operand = ltrim( substr( $operand, 1 ) );
        }

        if ( ! preg_match( '/^[a-zA-Z_][a-zA-Z0-9_]*$/', $operand ) ) {
            return false;
        }

        $value  = $this->get_template_context_value( $context, $operand );
        $result = $this->is_template_value_truthy( $value );

        return $negated ? ! $result : $result;
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
        return preg_replace_callback(
            '/\{\{\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*\}\}/',
            function( $matches ) use ( $values ) {
                $value = $this->get_template_context_value( $values, $matches[1] );
                return $this->stringify_template_value( $value );
            },
            $template
        );
    }

    /**
     * Convert a template value into a string for interpolation.
     *
     * @param mixed $value Template value.
     * @return string
     */
    private function stringify_template_value( $value ) {
        if ( is_array( $value ) ) {
            return isset( $value['value'] ) && is_scalar( $value['value'] )
                ? (string) $value['value']
                : '';
        }

        if ( is_bool( $value ) ) {
            return $value ? '1' : '';
        }

        if ( null === $value || false === $value ) {
            return '';
        }

        return is_scalar( $value ) ? (string) $value : '';
    }
    
    /**
     * Enqueue block-specific CSS and JS
     */
    private function enqueue_block_assets($module_id) {
        // Skip if this module's assets have already been enqueued
        if (in_array($module_id, self::$enqueued_modules)) {
            return;
        }
        
        // Mark this module as enqueued
        self::$enqueued_modules[] = $module_id;
        
        $css = get_field('module_css', $module_id);
        $js = get_field('module_js', $module_id);
        $compact_code = get_field('module_compact_code', $module_id);
        
        // Inline CSS
        if (!empty($css)) {
            // Generate unique handle for this module
            $handle = 'scmb-module-' . $module_id;
            
            // Register a dummy style to attach inline CSS
            wp_register_style($handle, false);
            wp_enqueue_style($handle);
            
            // Sanitize CSS to prevent CSS injection
            $sanitized_css = $this->sanitize_css($css);
            
            // Compact CSS if enabled
            if ($compact_code) {
                $sanitized_css = $this->compact_css($sanitized_css);
            }
            
            wp_add_inline_style($handle, $sanitized_css);
        }
        
        // Inline JS
        if (!empty($js)) {
            // Sanitize and validate JavaScript
            $sanitized_js = $this->sanitize_javascript($js);
            
            if (empty($sanitized_js)) {
                return;
            }
            
            // Compact JS if enabled
            if ($compact_code) {
                $sanitized_js = $this->compact_javascript($sanitized_js);
            }
            
            // Remove DOMContentLoaded listener from user code since we'll handle it
            $cleaned_js = $this->remove_dom_ready_wrapper($sanitized_js);
            
            // Wrap the JS to execute immediately without jQuery dependency
            // This ensures it runs regardless of jQuery availability
            $wrapped_js = sprintf(
                '(function(){if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",function(){%s});}else{%s;}})();',
                $cleaned_js,
                $cleaned_js
            );
            
            // Output script tag directly in footer to ensure it executes
            // Use wp_footer hook to add script at the very end of the page
            add_action('wp_footer', function() use ($wrapped_js) {
                echo '<script type="text/javascript">' . "\n" . $wrapped_js . "\n" . '</script>' . "\n";
            }, 999);
        }
    }
    
    /**
     * Sanitize CSS to prevent injection attacks
     * 
     * @param string $css Raw CSS from module
     * @return string Sanitized CSS
     */
    private function sanitize_css($css) {
        // Remove dangerous CSS that could execute scripts
        $dangerous_patterns = [
            '/expression\s*\(/i',           // IE expressions
            '/behavior\s*:/i',              // IE behavior
            '/javascript:/i',               // JavaScript protocol
            '/@import/i',                   // @import (could load external malicious CSS)
            '/-moz-binding/i',              // Mozilla binding
        ];
        
        foreach ($dangerous_patterns as $pattern) {
            $css = preg_replace($pattern, '', $css);
        }
        
        return wp_kses_post($css);
    }
    
    /**
     * Sanitize and validate JavaScript to prevent injection attacks
     * 
     * @param string $js Raw JavaScript from module
     * @return string Sanitized JavaScript
     */
    private function sanitize_javascript($js) {
        // Check if user has capability to execute code
        if (!current_user_can('manage_options')) {
            error_log('SCMB: Non-admin attempted to use custom JavaScript');
            return '';
        }
        
        // Detect dangerous functions and patterns
        $dangerous_patterns = [
            '/eval\s*\(/i',                          // eval()
            '/function\s+constructor/i',            // Function constructor
            '/window\.location\s*=/i',              // window.location (but allow read)
            '/document\.domain\s*=/i',              // document.domain modification
            '/document\.write\s*\(/i',              // document.write
            '/innerHTML\s*=/i',                     // innerHTML direct assignment
            '/exec\s*\(/i',                         // exec()
            '/setTimeout\s*\(\s*["\'].*[\'"]\s*,/i', // setTimeout with string (indirect eval)
            '/setInterval\s*\(\s*["\'].*[\'"]\s*,/i', // setInterval with string
        ];
        
        foreach ($dangerous_patterns as $pattern) {
            if (preg_match($pattern, $js)) {
                error_log('SCMB Security: Dangerous pattern detected in module JavaScript: ' . $pattern);
                return '';
            }
        }
        
        // Additional safety check: ensure code is valid JavaScript
        // This is a basic check - full validation would require a JS parser
        if (!$this->is_valid_javascript_syntax($js)) {
            error_log('SCMB: Invalid JavaScript syntax detected');
            return '';
        }
        
        return $js; // Already validated, safe to use
    }
    
    /**
     * Basic JavaScript syntax validation
     * 
     * @param string $js JavaScript code
     * @return bool True if syntax appears valid
     */
    private function is_valid_javascript_syntax($js) {
        // Remove comments and strings to avoid false positives
        $js_cleaned = preg_replace('(/\*.*?\*/)', '', $js); // Remove multi-line comments
        $js_cleaned = preg_replace('(//.*)', '', $js_cleaned); // Remove single-line comments
        $js_cleaned = preg_replace('("(?:\\.|[^"\\])*")', '""', $js_cleaned); // Remove strings
        $js_cleaned = preg_replace("('(?:\\.|[^'\\])*')", "''", $js_cleaned); // Remove strings
        
        // Basic checks for balanced brackets
        $open_braces = substr_count($js_cleaned, '{') + substr_count($js_cleaned, '[') + substr_count($js_cleaned, '(');
        $close_braces = substr_count($js_cleaned, '}') + substr_count($js_cleaned, ']') + substr_count($js_cleaned, ')');
        
        if ($open_braces !== $close_braces) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Encode JavaScript to base64 to bypass WAF/Cloudflare restrictions
     * 
     * @param string $js JavaScript code
     * @return string Base64 encoded JavaScript
     */
    private function encode_javascript($js) {
        return base64_encode($js);
    }
    
    /**
     * Decode base64 JavaScript (used in frontend via atob())
     * Note: The actual decoding happens in the browser via JavaScript atob() function
     * 
     * @param string $encoded_js Base64 encoded JavaScript
     * @return string Decoded JavaScript
     */
    public static function decode_javascript($encoded_js) {
        return base64_decode($encoded_js, true);
    }
    
    /**
     * Compact/Minify CSS
     * Removes unnecessary whitespace, newlines, and comments
     * 
     * @param string $css CSS code
     * @return string Minified CSS
     */
    private function compact_css($css) {
        // Remove comments
        $css = preg_replace('!/\*[^*]*\*+(?:[^/*][^*]*\*+)*/!', '', $css);
        
        // Remove newlines and multiple spaces
        $css = preg_replace('/[\r\n\t]+/', ' ', $css);
        $css = preg_replace('/\s+/', ' ', $css);
        
        // Remove spaces around special characters
        $css = preg_replace('/\s*([{}:;,>+~])\s*/', '$1', $css);
        
        // Remove trailing semicolon in declarations
        $css = preg_replace('/;}/', '}', $css);
        
        // Trim
        return trim($css);
    }
    
    /**
     * Remove DOMContentLoaded wrapper from user's JavaScript code
     * This prevents double-wrapping when we add our own DOMContentLoaded handler
     * 
     * @param string $js JavaScript code
     * @return string JavaScript without DOMContentLoaded wrapper
     */
    private function remove_dom_ready_wrapper($js) {
        // Pattern to match: document.addEventListener('DOMContentLoaded', function() { ... });
        $patterns = [
            '/document\s*\.\s*addEventListener\s*\(\s*[\'"]DOMContentLoaded[\'"]\s*,\s*function\s*\(\s*\)\s*\{([\s\S]*)\}\s*\)\s*;?/i',
            '/\$\s*\(\s*document\s*\)\s*\.\s*ready\s*\(\s*function\s*\(\s*\)\s*\{([\s\S]*)\}\s*\)\s*;?/i',
            '/\$\(function\s*\(\s*\)\s*\{([\s\S]*)\}\s*\)\s*;?/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $js, $matches)) {
                // Return just the content inside the wrapper
                return trim($matches[1]);
            }
        }
        
        // If no wrapper found, return original JS
        return $js;
    }
    
    /**
     * Compact/Minify JavaScript
     * Removes unnecessary whitespace, newlines, and comments
     * Preserves string literals and regex patterns
     * 
     * @param string $js JavaScript code
     * @return string Minified JavaScript
     */
    private function compact_javascript($js) {
        // Remove single-line comments (preserve URLs in strings by being careful)
        $js = preg_replace('/\/\/.*?(?=[\r\n]|$)/m', '', $js);
        
        // Remove multi-line comments
        $js = preg_replace('/\/\*[\s\S]*?\*\//m', '', $js);
        
        // Replace newlines with spaces
        $js = str_replace(["\r\n", "\r", "\n"], " ", $js);
        
        // Replace tabs with spaces
        $js = str_replace("\t", " ", $js);
        
        // Reduce multiple spaces to single space (but preserve in strings)
        // This is safer than trying to minify around operators
        $js = preg_replace('/  +/', ' ', $js);
        
        // Remove space before semicolons and commas
        $js = preg_replace('/\s+;/', ';', $js);
        $js = preg_replace('/\s+,/', ',', $js);
        
        // Remove space before closing braces/brackets/parentheses
        $js = preg_replace('/\s+}/', '}', $js);
        $js = preg_replace('/\s+]/', ']', $js);
        $js = preg_replace('/\s+\)/', ')', $js);
        
        // Remove space after opening braces/brackets/parentheses
        $js = preg_replace('/{[\s]+/', '{', $js);
        $js = preg_replace('/\[[\s]+/', '[', $js);
        $js = preg_replace('/\([\s]+/', '(', $js);
        
        // Remove spaces around colons (in object properties)
        $js = preg_replace('/\s*:\s*/', ':', $js);
        
        // Trim
        return trim($js);
    }
}
