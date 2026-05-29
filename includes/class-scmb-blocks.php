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
    private static $editor_block_labels = [];
    private const TEMPLATE_NAME_PATTERN = '[a-zA-Z_][a-zA-Z0-9_]*';
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('acf/init', [$this, 'register_blocks']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_block_editor_assets']);
        add_action('enqueue_block_assets', [$this, 'enqueue_block_editor_assets']);
    }

    /**
     * Enqueue editor-only styles for SCMB block previews.
     *
     * @return void
     */
    public function enqueue_block_editor_assets() {
        if ( ! is_admin() ) {
            return;
        }

        $editor_css_path = SCMB_PLUGIN_DIR . 'assets/css/block-editor.css';
        $editor_js_path  = SCMB_PLUGIN_DIR . 'assets/js/block-editor.js';

        if ( ! file_exists( $editor_css_path ) ) {
            return;
        }

        wp_enqueue_style(
            'scmb-block-editor',
            SCMB_PLUGIN_URL . 'assets/css/block-editor.css',
            [],
            filemtime( $editor_css_path )
        );

        if ( file_exists( $editor_js_path ) ) {
            wp_enqueue_script(
                'scmb-block-editor',
                SCMB_PLUGIN_URL . 'assets/js/block-editor.js',
                [ 'wp-data', 'wp-dom-ready' ],
                filemtime( $editor_js_path ),
                true
            );

            wp_add_inline_script(
                'scmb-block-editor',
                'window.scmbBlockLabels = ' . wp_json_encode( self::$editor_block_labels ) . ';',
                'before'
            );
        }
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
        $block_slug = $this->get_module_key($module_id, $module->post_title);
        
        // Get module configuration
        $label = get_field('module_label', $module_id) ?: $module->post_title;
        $description = get_field('module_description', $module_id) ?: '';
        $category = get_field('module_category', $module_id) ?: 'custom';
        $icon = get_field('module_icon', $module_id) ?: 'admin-post';
        $preview_image = $this->get_module_preview_image( $module_id );
        $single_instance = (bool) get_field('module_single_instance', $module_id);
        $fields = get_field('module_fields', $module_id) ?: [];
        
        // Register ACF field group for this block FIRST
        $this->register_block_fields($block_slug, $module_id, $label, $fields);
        $this->register_block_editor_label($block_slug, $label, $icon);
        
        // Register the block type
        $block_args = [
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
                'multiple' => !$single_instance,
            ],
            'render_callback' => function($block, $content = '', $is_preview = false, $post_id = 0) use ($module_id) {
                $this->render_block($block, $module_id, $is_preview, $post_id);
            },
            'enqueue_style' => null,
            'enqueue_script' => null,
            'enqueue_assets' => function() use ($module_id) {
                $this->enqueue_block_assets($module_id);
            },
        ];

        if ( ! empty( $preview_image['url'] ) ) {
            $block_args['example'] = [
                'attributes' => [
                    'mode' => 'preview',
                    'data' => [
                        'scmb_is_inserter_preview' => 1,
                        'scmb_preview_image_url' => $preview_image['url'],
                        'scmb_preview_image_alt' => $preview_image['alt'],
                    ],
                ],
            ];
        }

        acf_register_block_type( $block_args );
    }

    /**
     * Get the uploaded inserter preview image for a module.
     *
     * @param int $module_id Module post ID.
     * @return array{url:string,alt:string}
     */
    private function get_module_preview_image( $module_id ) {
        $image_value = get_field( 'module_preview_image', $module_id );
        $image_id = 0;
        $image_url = '';

        if ( is_array( $image_value ) ) {
            $image_id = absint( $image_value['ID'] ?? $image_value['id'] ?? 0 );
            $image_url = ! empty( $image_value['url'] ) ? esc_url_raw( $image_value['url'] ) : '';
        } elseif ( is_numeric( $image_value ) ) {
            $image_id = absint( $image_value );
        } elseif ( is_string( $image_value ) ) {
            $image_url = esc_url_raw( $image_value );
        }

        if ( $image_id ) {
            $image_url = wp_get_attachment_image_url( $image_id, 'medium_large' );
        }

        if ( ! $image_url ) {
            return [
                'url' => '',
                'alt' => '',
            ];
        }

        $image_alt = $image_id ? get_post_meta( $image_id, '_wp_attachment_image_alt', true ) : '';

        return [
            'url' => esc_url_raw( $image_url ),
            'alt' => wp_strip_all_tags( $image_alt ),
        ];
    }

    /**
     * Share block labels with the editor script.
     *
     * @param string $block_slug Block slug without the acf/ prefix.
     * @param string $label      Block label.
     * @param string $icon       Dashicon name.
     * @return void
     */
    private function register_block_editor_label( $block_slug, $label, $icon ) {
        $safe_block_slug = sanitize_title( $block_slug );

        if ( empty( $safe_block_slug ) ) {
            return;
        }

        self::$editor_block_labels[ 'acf/' . $safe_block_slug ] = [
            'slug'  => $safe_block_slug,
            'name'  => 'acf/' . $safe_block_slug,
            'label' => wp_strip_all_tags( $label ),
            'icon'  => sanitize_key( $icon ),
        ];
    }
    
    /**
     * Register ACF fields for a block
     */
    private function register_block_fields($block_slug, $module_id, $label, $fields) {
        if (empty($fields) || !is_array($fields)) {
            return;
        }
        
        $acf_fields = [];
        $module_key = get_field('module_key', $module_id);
        $field_key_prefix = !empty($module_key) ? str_replace('-', '_', sanitize_title($module_key)) : (string) $module_id;
        
        foreach ($fields as $field) {
            if (empty($field['field_name']) || empty($field['field_type'])) {
                continue;
            }

            $field_name = $this->normalize_template_field_name( $field['field_name'] );

            if ( empty( $field_name ) ) {
                continue;
            }
            
            $field_key = 'scmb_' . $field_key_prefix . '_' . $field_name;
            
            $acf_field = [
                'key' => $field_key,
                'label' => $field['field_label'] ?: ucfirst($field_name),
                'name' => $field_name,
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

            if ($field['field_type'] === 'select') {
                $acf_field['choices'] = $this->parse_select_choices( isset( $field['field_choices'] ) ? $field['field_choices'] : '' );
                $acf_field['ui'] = 1;
                $acf_field['return_format'] = 'value';
                $acf_field['allow_null'] = ! empty( $field['field_allow_null'] ) ? 1 : 0;
            }
            
            // Special settings for Repeater
            if ($field['field_type'] === 'repeater') {
                $acf_field['layout'] = 'row';
                $acf_field['button_label'] = 'Add Item';
                $acf_field['sub_fields'] = $this->parse_repeater_sub_fields(
                    isset( $field['field_sub_fields'] ) ? $field['field_sub_fields'] : '',
                    $field_key
                );

                $repeater_max = isset( $field['field_repeater_max'] ) ? absint( $field['field_repeater_max'] ) : 0;

                if ( $repeater_max > 0 ) {
                    $acf_field['max'] = $repeater_max;
                }

                $this->apply_repeater_display_settings( $acf_field );
            }
            
            $acf_fields[] = $acf_field;
        }
        
        // Register field group
        if (!empty($acf_fields)) {
            acf_add_local_field_group([
                'key' => 'group_scmb_' . $field_key_prefix,
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
     * Get a stable module key for block registration.
     *
     * @param int    $module_id Module post ID.
     * @param string $fallback  Fallback title.
     * @return string
     */
    private function get_module_key($module_id, $fallback = '') {
        $module_key = get_field('module_key', $module_id);
        $module_key = sanitize_title($module_key);

        if (empty($module_key)) {
            $module_key = sanitize_title($fallback);
        }

        return !empty($module_key) ? $module_key : 'module-' . (int) $module_id;
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
        if ( $is_preview && ! empty( $block['data']['scmb_is_inserter_preview'] ) && ! empty( $block['data']['scmb_preview_image_url'] ) ) {
            printf(
                '<div class="scmb-inserter-preview"><img src="%s" alt="%s"></div>',
                esc_url( $block['data']['scmb_preview_image_url'] ),
                esc_attr( $block['data']['scmb_preview_image_alt'] ?? get_the_title( $module_id ) )
            );
            return;
        }

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
                    esc_html__( 'No HTML template defined for this module.', 'secure-custom-module-builder' )
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
        echo wp_kses( $output, $this->get_allowed_template_html() );

        if ( $is_preview ) {
            echo '</div>';
        }
    }

    /**
     * Get allowed HTML markup for rendered module templates.
     *
     * @return array
     */
    private function get_allowed_template_html() {
        $allowed_html = wp_kses_allowed_html( 'post' );

        foreach ( $allowed_html as $tag => $attributes ) {
            if ( is_array( $attributes ) ) {
                $allowed_html[ $tag ]['uk-icon'] = true;
            }
        }

        $allowed_html['canvas'] = [
            'id'           => true,
            'class'        => true,
            'style'        => true,
            'width'        => true,
            'height'       => true,
            'role'         => true,
            'aria-label'   => true,
            'aria-hidden'  => true,
            'data-module'  => true,
            'data-scmb'    => true,
            'data-context' => true,
        ];

        return $allowed_html;
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

            $field_name = $this->normalize_template_field_name( $field['field_name'] );

            if ( empty( $field_name ) ) {
                continue;
            }

            $value = $this->get_acf_field_value( $field_name, $field['field_name'] );
            $value = ( false !== $value ) ? $value : '';

            $field['field_name'] = $field_name;
            $prepared_value = $this->prepare_field_value( $field, $value );
            $context[ $field_name ] = $prepared_value;

            if ( 'repeater' === $field['field_type'] ) {
                $this->add_repeater_template_helpers( $context, $field_name, $prepared_value );
            }
        }

        return $context;
    }

    /**
     * Add count helpers for a repeater field.
     *
     * @param array  $context Rendering context.
     * @param string $field_name Repeater field name.
     * @param mixed  $rows Prepared repeater rows.
     * @return void
     */
    private function add_repeater_template_helpers( &$context, $field_name, $rows ) {
        $count = is_array( $rows ) ? count( $rows ) : 0;

        $context[ $field_name . '__count' ]        = $count;
        $context[ $field_name . '__has_multiple' ] = $count > 1;
    }

    /**
     * Normalize template/ACF field names to lowercase snake_case.
     *
     * @param string $field_name Raw field name.
     * @return string
     */
    private function normalize_template_field_name( $field_name ) {
        $field_name = strtolower( (string) $field_name );
        $field_name = preg_replace( '/[^a-z0-9_]+/', '_', $field_name );
        $field_name = preg_replace( '/_+/', '_', $field_name );
        $field_name = trim( $field_name, '_' );

        if ( '' !== $field_name && ! preg_match( '/^[a-z_]/', $field_name ) ) {
            $field_name = 'field_' . $field_name;
        }

        return $field_name;
    }

    /**
     * Parse comma-separated select choices.
     *
     * Supports `value:Label`, `value=Label`, or `value`.
     *
     * @param string $raw_choices Raw choices string.
     * @return array
     */
    private function parse_select_choices( $raw_choices ) {
        $choices = [];
        $items   = array_filter( array_map( 'trim', preg_split( '/[\r\n,]+/', (string) $raw_choices ) ) );

        foreach ( $items as $item ) {
            $parts = preg_split( '/[:=]/', $item, 2 );

            if ( false === $parts || empty( $parts[0] ) ) {
                continue;
            }

            $value = $this->normalize_template_field_name( $parts[0] );

            if ( empty( $value ) ) {
                continue;
            }

            $label = isset( $parts[1] ) && '' !== trim( $parts[1] )
                ? trim( $parts[1] )
                : $value;

            $choices[ $value ] = $label;
        }

        return $choices;
    }

    /**
     * Parse a boolean option from sub-field config text.
     *
     * @param mixed $value Raw option value.
     * @return bool
     */
    private function parse_boolean_option( $value ) {
        if ( is_bool( $value ) ) {
            return $value;
        }

        return in_array( strtolower( trim( (string) $value ) ), [ '1', 'true', 'yes', 'y', 'allow_null' ], true );
    }

    /**
     * Parse repeater sub-field lines into ACF fields.
     *
     * Nested repeaters are represented by indenting child lines below a
     * repeater parent:
     * item_tags|Tags|repeater|max=4
     *   tag_label|Tag Label|text
     *
     * @param string $raw_lines  Raw sub-field configuration.
     * @param string $parent_key Parent ACF field key.
     * @return array
     */
    private function parse_repeater_sub_fields( $raw_lines, $parent_key ) {
        $lines    = $this->parse_repeater_sub_field_lines( $raw_lines );
        $position = 0;

        return $this->parse_repeater_sub_field_level( $lines, $position, -1, $parent_key );
    }

    /**
     * Convert raw repeater config text into parsed line metadata.
     *
     * @param string $raw_lines Raw sub-field configuration.
     * @return array
     */
    private function parse_repeater_sub_field_lines( $raw_lines ) {
        $raw_lines = preg_split( '/\r\n|\r|\n/', (string) $raw_lines );

        if ( false === $raw_lines ) {
            return [];
        }

        $lines = [];

        foreach ( $raw_lines as $raw_line ) {
            if ( '' === trim( $raw_line ) ) {
                continue;
            }

            preg_match( '/^(\s*)(.*)$/', $raw_line, $matches );

            $indent = strlen( str_replace( "\t", '    ', isset( $matches[1] ) ? $matches[1] : '' ) );
            $text   = isset( $matches[2] ) ? trim( $matches[2] ) : '';

            if ( '' === $text ) {
                continue;
            }

            $lines[] = [
                'indent' => $indent,
                'text'   => $text,
            ];
        }

        return $lines;
    }

    /**
     * Recursively parse one indentation level of repeater sub-fields.
     *
     * @param array  $lines      Parsed line metadata.
     * @param int    $position   Current parser position.
     * @param int    $parent_indent Parent indentation level.
     * @param string $parent_key Parent ACF field key.
     * @return array
     */
    private function parse_repeater_sub_field_level( $lines, &$position, $parent_indent, $parent_key ) {
        $sub_fields = [];
        $total      = count( $lines );

        while ( $position < $total ) {
            $line = $lines[ $position ];

            if ( $line['indent'] <= $parent_indent ) {
                break;
            }

            $sub_field = $this->build_repeater_sub_field_from_line( $line['text'], $parent_key );
            ++$position;

            if ( empty( $sub_field ) ) {
                continue;
            }

            if ( 'repeater' === $sub_field['type'] ) {
                $sub_field['sub_fields'] = $this->parse_repeater_sub_field_level( $lines, $position, $line['indent'], $sub_field['key'] );
                $this->apply_repeater_display_settings( $sub_field );
            } else {
                $this->skip_nested_repeater_lines( $lines, $position, $line['indent'] );
            }

            $sub_fields[] = $sub_field;
        }

        return $sub_fields;
    }

    /**
     * Build one ACF sub-field from a config line.
     *
     * @param string $line       Config line without indentation.
     * @param string $parent_key Parent ACF field key.
     * @return array
     */
    private function build_repeater_sub_field_from_line( $line, $parent_key ) {
        $parts          = array_map( 'trim', explode( '|', $line ) );
        $sub_field_name = isset( $parts[0] ) ? $this->normalize_template_field_name( $parts[0] ) : '';

        if ( empty( $sub_field_name ) ) {
            return [];
        }

        $sub_field_type = isset( $parts[2] ) && '' !== $parts[2] ? $parts[2] : 'text';
        $sub_field      = [
            'key'   => $parent_key . '_' . $sub_field_name,
            'label' => isset( $parts[1] ) && '' !== $parts[1] ? $parts[1] : ucfirst( $sub_field_name ),
            'name'  => $sub_field_name,
            'type'  => $sub_field_type,
        ];

        if ( 'wysiwyg' === $sub_field_type ) {
            $sub_field['tabs']         = 'all';
            $sub_field['toolbar']      = 'full';
            $sub_field['media_upload'] = 1;
        }

        if ( 'select' === $sub_field_type ) {
            $sub_field['choices']       = $this->parse_select_choices( isset( $parts[3] ) ? $parts[3] : '' );
            $sub_field['ui']            = 1;
            $sub_field['return_format'] = 'value';
            $sub_field['allow_null']    = $this->parse_boolean_option( isset( $parts[4] ) ? $parts[4] : false ) ? 1 : 0;
        }

        if ( 'repeater' === $sub_field_type ) {
            $sub_field['layout']       = 'row';
            $sub_field['button_label'] = 'Add Item';

            $max_rows = $this->parse_repeater_max_option( array_slice( $parts, 3 ) );

            if ( $max_rows > 0 ) {
                $sub_field['max'] = $max_rows;
            }
        }

        return $sub_field;
    }

    /**
     * Skip indented child lines under a non-repeater field.
     *
     * @param array $lines Parsed line metadata.
     * @param int   $position Current parser position.
     * @param int   $indent Parent indentation.
     * @return void
     */
    private function skip_nested_repeater_lines( $lines, &$position, $indent ) {
        $total = count( $lines );

        while ( $position < $total && $lines[ $position ]['indent'] > $indent ) {
            ++$position;
        }
    }

    /**
     * Parse max rows from nested repeater options.
     *
     * @param array $options Raw config options after the field type.
     * @return int
     */
    private function parse_repeater_max_option( $options ) {
        foreach ( $options as $option ) {
            $option = trim( (string) $option );

            if ( preg_match( '/^max\s*=\s*(\d+)$/', $option, $matches ) ) {
                return absint( $matches[1] );
            }
        }

        return 0;
    }

    /**
     * Apply compact display settings to an ACF repeater field.
     *
     * @param array $repeater_field ACF repeater field.
     * @return void
     */
    private function apply_repeater_display_settings( &$repeater_field ) {
        $compact_repeater_types = [
            'text',
            'email',
            'url',
            'number',
            'range',
            'select',
            'checkbox',
            'radio',
            'button_group',
            'true_false',
            'color_picker',
            'date_picker',
            'time_picker',
            'date_time_picker',
        ];

        $has_only_compact_sub_fields = ! empty( $repeater_field['sub_fields'] );

        foreach ( $repeater_field['sub_fields'] as $sub_field ) {
            if ( empty( $sub_field['type'] ) || ! in_array( $sub_field['type'], $compact_repeater_types, true ) ) {
                $has_only_compact_sub_fields = false;
                break;
            }
        }

        if ( $has_only_compact_sub_fields && count( $repeater_field['sub_fields'] ) <= 3 ) {
            $repeater_field['layout'] = 'table';
        }

        if ( ! empty( $repeater_field['sub_fields'][0]['key'] ) ) {
            $repeater_field['collapsed'] = $repeater_field['sub_fields'][0]['key'];
        }
    }

    /**
     * Read an ACF field value, allowing older hyphenated saved names as fallbacks.
     *
     * @param string $field_name     Normalized field name.
     * @param string $original_name  Original configured field name.
     * @return mixed
     */
    private function get_acf_field_value( $field_name, $original_name = '' ) {
        $field_names = array_unique(
            array_filter(
                [
                    $field_name,
                    $original_name,
                ],
                'strlen'
            )
        );
        $empty_value = false;

        foreach ( $field_names as $candidate_name ) {
            $value = get_field( $candidate_name );

            if ( false === $value || null === $value || '' === $value ) {
                $empty_value = $value;
                continue;
            }

            return $value;
        }

        return $empty_value;
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

        $prepared_rows    = [];
        $sub_field_schema = $this->get_repeater_sub_field_schema( $field );

        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }

            $prepared_row = [];

            foreach ( $row as $sub_field_name => $sub_field_value ) {
                $sub_field_name = $this->normalize_template_field_name( $sub_field_name );

                if ( empty( $sub_field_name ) ) {
                    continue;
                }

                $sub_field = isset( $sub_field_schema[ $sub_field_name ] )
                    ? $sub_field_schema[ $sub_field_name ]
                    : [
                        'type'       => 'text',
                        'sub_fields' => [],
                    ];

                $prepared_row[ $sub_field_name ] = $this->prepare_sub_field_value( $sub_field, $sub_field_value );
            }

            $prepared_rows[] = $prepared_row;
        }

        return $prepared_rows;
    }

    /**
     * Get the configured schema for repeater sub-fields.
     *
     * @param array $field Repeater field definition.
     * @return array
     */
    private function get_repeater_sub_field_schema( $field ) {
        if ( empty( $field['field_sub_fields'] ) ) {
            return [];
        }

        return $this->build_repeater_sub_field_schema(
            $this->parse_repeater_sub_fields( $field['field_sub_fields'], 'schema' )
        );
    }

    /**
     * Build a lookup schema from parsed ACF sub-fields.
     *
     * @param array $sub_fields Parsed ACF sub-fields.
     * @return array
     */
    private function build_repeater_sub_field_schema( $sub_fields ) {
        $schema = [];

        foreach ( $sub_fields as $sub_field ) {
            if ( empty( $sub_field['name'] ) || empty( $sub_field['type'] ) ) {
                continue;
            }

            $schema[ $sub_field['name'] ] = [
                'type'       => $sub_field['type'],
                'sub_fields' => ! empty( $sub_field['sub_fields'] )
                    ? $this->build_repeater_sub_field_schema( $sub_field['sub_fields'] )
                    : [],
            ];
        }

        return $schema;
    }

    /**
     * Prepare a repeater sub-field value for template rendering.
     *
     * @param array $sub_field Sub-field schema.
     * @param mixed $value     Raw sub-field value.
     * @return mixed
     */
    private function prepare_sub_field_value( $sub_field, $value ) {
        $field_type = isset( $sub_field['type'] ) ? $sub_field['type'] : 'text';

        switch ( $field_type ) {
            case 'wysiwyg':
                return wp_kses_post( $value );

            case 'url':
                return esc_url( $value );

            case 'image':
                return ! empty( $value ) ? (int) $value : '';

            case 'true_false':
                return ! empty( $value );

            case 'repeater':
                return $this->prepare_repeater_rows_with_schema(
                    $value,
                    isset( $sub_field['sub_fields'] ) ? $sub_field['sub_fields'] : []
                );

            default:
                return is_scalar( $value ) ? esc_html( (string) $value ) : '';
        }
    }

    /**
     * Prepare nested repeater rows using an already-built schema.
     *
     * @param mixed $rows   Raw repeater rows.
     * @param array $schema Nested sub-field schema.
     * @return array
     */
    private function prepare_repeater_rows_with_schema( $rows, $schema ) {
        if ( ! is_array( $rows ) ) {
            return [];
        }

        $prepared_rows = [];

        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }

            $prepared_row = [];

            foreach ( $row as $sub_field_name => $sub_field_value ) {
                $sub_field_name = $this->normalize_template_field_name( $sub_field_name );

                if ( empty( $sub_field_name ) ) {
                    continue;
                }

                $sub_field = isset( $schema[ $sub_field_name ] )
                    ? $schema[ $sub_field_name ]
                    : [
                        'type'       => 'text',
                        'sub_fields' => [],
                    ];

                $prepared_row[ $sub_field_name ] = $this->prepare_sub_field_value( $sub_field, $sub_field_value );
            }

            $prepared_rows[] = $prepared_row;
        }

        return $prepared_rows;
    }

    /**
     * Render a template string using the supplied context.
     *
     * Supported syntax:
     * - <!-- editor-only comments -->
     * - {{field_name}}
     * - {{#field_name}} ... {{/field_name}}
     * - {{#repeater_name}} {{#if __first}}...{{/if}} {{/repeater_name}}
     * - {{#if field_name}} ... {{else}} ... {{/if}}
     * - {{#if title && content}} ... {{/if}}
     * - {{#if title || content}} ... {{/if}}
     * - {{#if !title}} ... {{/if}}
     * - {{#if repeater_name__count > 1}} ... {{/if}}
     *
     * @param string $template Template markup.
     * @param array  $context  Rendering context.
     * @return string
     */
    private function render_template( $template, $context ) {
        if ( ! is_string( $template ) || empty( $template ) ) {
            return '';
        }

        $template = $this->strip_template_comments( $template );
        $template = $this->render_template_sections( $template, $context );
        $template = $this->replace_template_variables( $template, $context );

        // Remove any unsupported or unresolved tags from the final output.
        return preg_replace( '/\{\{[^}]+\}\}/', '', $template );
    }

    /**
     * Remove HTML comments before macro rendering.
     *
     * Comments are intended as editor notes only. Stripping them first prevents
     * commented-out template tags or markup from being parsed into partial
     * frontend output.
     *
     * @param string $template Template markup.
     * @return string
     */
    private function strip_template_comments( $template ) {
        $template = preg_replace( '/<!--[\s\S]*?-->/', '', $template );

        if ( false === $template ) {
            return '';
        }

        return preg_replace( '/<!--[\s\S]*$/', '', $template );
    }

    /**
     * Render template control structures such as sections and conditionals.
     *
     * @param string $template Template markup.
     * @param array  $context  Rendering context.
     * @return string
     */
    private function render_template_sections( $template, $context ) {
        $name_pattern    = self::TEMPLATE_NAME_PATTERN;
        $opening_pattern = '/\{\{\s*#(?:(if)\s+([^}]+?)|(' . $name_pattern . '))\s*\}\}/';

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
        $name_pattern  = self::TEMPLATE_NAME_PATTERN;
        $pattern       = '/\{\{\s*(#if\s+[^}]+|#' . $name_pattern . '|\/if|\/' . $name_pattern . '|else)\s*\}\}/';
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

        $total_rows = count( $section_value );

        foreach ( $section_value as $row_index => $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }

            $row_context = $this->build_child_template_context(
                $context,
                array_merge(
                    $row,
                    [
                        '__index'    => $row_index,
                        '__position' => $row_index + 1,
                        '__first'    => 0 === $row_index,
                        '__last'     => $row_index === $total_rows - 1,
                    ]
                )
            );
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
        $child_context = array_merge( $context, $row );

        foreach ( $row as $field_name => $value ) {
            if ( is_array( $value ) ) {
                $this->add_repeater_template_helpers( $child_context, $field_name, $value );
            }
        }

        return $child_context;
    }

    /**
     * Get a value from the template context.
     *
     * @param array  $context    Rendering context.
     * @param string $field_name Context key.
     * @return mixed
     */
    private function get_template_context_value( $context, $field_name ) {
        if ( isset( $context[ $field_name ] ) ) {
            return $context[ $field_name ];
        }

        $underscore_alias = str_replace( '-', '_', $field_name );

        if ( $underscore_alias !== $field_name && isset( $context[ $underscore_alias ] ) ) {
            return $context[ $underscore_alias ];
        }

        $hyphen_alias = str_replace( '_', '-', $field_name );

        if ( $hyphen_alias !== $field_name && isset( $context[ $hyphen_alias ] ) ) {
            return $context[ $hyphen_alias ];
        }

        return null;
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
     * - >, >=, <, <=, ==, !=
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

        $comparison_result = $this->evaluate_comparison_operand( $operand, $context );

        if ( null !== $comparison_result ) {
            return $negated ? ! $comparison_result : $comparison_result;
        }

        if ( ! preg_match( '/^' . self::TEMPLATE_NAME_PATTERN . '$/', $operand ) ) {
            return false;
        }

        $value  = $this->get_template_context_value( $context, $operand );
        $result = $this->is_template_value_truthy( $value );

        return $negated ? ! $result : $result;
    }

    /**
     * Evaluate a numeric comparison operand.
     *
     * @param string $operand Conditional operand.
     * @param array  $context Rendering context.
     * @return bool|null Null when the operand is not a comparison.
     */
    private function evaluate_comparison_operand( $operand, $context ) {
        $pattern = '/^(' . self::TEMPLATE_NAME_PATTERN . ')\s*(>=|<=|==|!=|>|<)\s*(-?\d+(?:\.\d+)?)$/';

        if ( ! preg_match( $pattern, $operand, $matches ) ) {
            return null;
        }

        $left_value = $this->get_template_context_value( $context, $matches[1] );

        if ( ! is_numeric( $left_value ) ) {
            return false;
        }

        $left     = (float) $left_value;
        $operator = $matches[2];
        $right    = (float) $matches[3];

        switch ( $operator ) {
            case '>=':
                return $left >= $right;

            case '<=':
                return $left <= $right;

            case '==':
                return $left === $right;

            case '!=':
                return $left !== $right;

            case '>':
                return $left > $right;

            case '<':
                return $left < $right;
        }

        return false;
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
            '/\{\{\s*(' . self::TEMPLATE_NAME_PATTERN . ')\s*\}\}/',
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
            wp_register_style($handle, false, [], SCMB_VERSION);
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
            
            // Wrap the JS to execute once without duplicating the module code.
            // The initializer is either called immediately or attached to DOMContentLoaded.
            $wrapped_js = sprintf(
                "(function(){\n" .
                "\"use strict\";\n" .
                "function scmbInitModule(){\n%s\n}\n" .
                "if(document.readyState===\"loading\"){\n" .
                "document.addEventListener(\"DOMContentLoaded\",scmbInitModule,{once:true});\n" .
                "}else{\n" .
                "scmbInitModule();\n" .
                "}\n" .
                "})();",
                $cleaned_js
            );
            
            $script_handle = 'scmb-module-' . $module_id . '-script';

            wp_register_script($script_handle, false, [], SCMB_VERSION, true);
            wp_enqueue_script($script_handle);
            wp_add_inline_script($script_handle, $wrapped_js, 'after');
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
                return '';
            }
        }
        
        // Additional safety check: ensure code is valid JavaScript
        // This is a basic check - full validation would require a JS parser
        if (!$this->is_valid_javascript_syntax($js)) {
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
        // Match common DOM-ready wrappers, including arrow functions. The wrapper
        // body is extracted with brace matching so trailing "});" tokens are not
        // left behind in the generated frontend script.
        $patterns = [
            '/document\s*\.\s*addEventListener\s*\(\s*[\'"]DOMContentLoaded[\'"]\s*,\s*(?:function\s*\([^)]*\)|\([^)]*\)\s*=>|[a-zA-Z_$][a-zA-Z0-9_$]*\s*=>)\s*\{/i',
            '/\$\s*\(\s*document\s*\)\s*\.\s*ready\s*\(\s*(?:function\s*\([^)]*\)|\([^)]*\)\s*=>|[a-zA-Z_$][a-zA-Z0-9_$]*\s*=>)\s*\{/i',
            '/\$\s*\(\s*(?:function\s*\([^)]*\)|\([^)]*\)\s*=>|[a-zA-Z_$][a-zA-Z0-9_$]*\s*=>)\s*\{/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $js, $matches, PREG_OFFSET_CAPTURE)) {
                $match_start = $matches[0][1];
                $match_text  = $matches[0][0];
                $open_brace  = $match_start + strlen($match_text) - 1;
                $close_brace = $this->find_matching_javascript_brace($js, $open_brace);

                if (false === $close_brace) {
                    continue;
                }

                $statement_end = $this->find_javascript_statement_end($js, $close_brace + 1);
                $before        = substr($js, 0, $match_start);
                $body          = substr($js, $open_brace + 1, $close_brace - $open_brace - 1);
                $after         = substr($js, $statement_end);

                return trim($before . "\n" . $body . "\n" . $after);
            }
        }
        
        // If no wrapper found, return original JS
        return $js;
    }

    /**
     * Find the closing brace for a JavaScript block.
     *
     * @param string $js JavaScript code.
     * @param int    $open_brace Position of the opening brace.
     * @return int|false Closing brace position, or false when not found.
     */
    private function find_matching_javascript_brace($js, $open_brace) {
        $length = strlen($js);
        $depth  = 0;
        $quote  = null;
        $line_comment = false;
        $block_comment = false;

        for ($i = $open_brace; $i < $length; $i++) {
            $char = $js[$i];
            $next = ($i + 1 < $length) ? $js[$i + 1] : '';

            if ($line_comment) {
                if ("\n" === $char || "\r" === $char) {
                    $line_comment = false;
                }
                continue;
            }

            if ($block_comment) {
                if ('*' === $char && '/' === $next) {
                    $block_comment = false;
                    $i++;
                }
                continue;
            }

            if (null !== $quote) {
                if ('\\' === $char) {
                    $i++;
                    continue;
                }

                if ($quote === $char) {
                    $quote = null;
                }
                continue;
            }

            if ('/' === $char && '/' === $next) {
                $line_comment = true;
                $i++;
                continue;
            }

            if ('/' === $char && '*' === $next) {
                $block_comment = true;
                $i++;
                continue;
            }

            if ('"' === $char || "'" === $char || '`' === $char) {
                $quote = $char;
                continue;
            }

            if ('{' === $char) {
                $depth++;
                continue;
            }

            if ('}' === $char) {
                $depth--;

                if (0 === $depth) {
                    return $i;
                }
            }
        }

        return false;
    }

    /**
     * Find the end of the JavaScript wrapper call after a callback body.
     *
     * @param string $js JavaScript code.
     * @param int    $start Position immediately after the callback closing brace.
     * @return int Position immediately after the wrapper statement.
     */
    private function find_javascript_statement_end($js, $start) {
        $length = strlen($js);

        for ($i = $start; $i < $length; $i++) {
            if (';' === $js[$i]) {
                return $i + 1;
            }
        }

        return $length;
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
