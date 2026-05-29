<?php
/**
 * Module import/export admin tools.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SCMB_Import_Export {

    private static $instance = null;
    private $page_slug = 'scmb-import-export';
    private $transient_prefix = 'scmb_import_package_';

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', [$this, 'register_admin_page']);
        add_action('admin_post_scmb_export_modules', [$this, 'handle_export']);
    }

    /**
     * Add the Import / Export page under Module Builder.
     */
    public function register_admin_page() {
        add_submenu_page(
            'edit.php?post_type=scmb_module',
            __('Import / Export Modules', 'secure-custom-module-builder'),
            __('Import / Export', 'secure-custom-module-builder'),
            'manage_options',
            $this->page_slug,
            [$this, 'render_page']
        );
    }

    /**
     * Render the admin page and process import preview/confirmation posts.
     */
    public function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to manage module imports.', 'secure-custom-module-builder'));
        }

        $notice = null;
        $preview = null;
        $result = null;
        $request_method = isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : '';

        if ('POST' === $request_method) {
            $has_import_nonce = isset($_POST['scmb_import_nonce'])
                && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['scmb_import_nonce'])), 'scmb_import_modules');
            $has_confirm_nonce = isset($_POST['scmb_confirm_nonce'])
                && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['scmb_confirm_nonce'])), 'scmb_confirm_import');

            if (!$has_import_nonce && !$has_confirm_nonce) {
                $notice = [
                    'type' => 'error',
                    'message' => __('Security check failed. Please try again.', 'secure-custom-module-builder'),
                ];
            } elseif ($has_import_nonce && isset($_POST['scmb_check_import'])) {
                [$notice, $preview] = $this->handle_import_preview();
            } elseif ($has_confirm_nonce && isset($_POST['scmb_confirm_import'])) {
                [$notice, $result] = $this->handle_import_confirm();
            }
        }

        $modules = $this->get_all_modules();
        ?>
        <div class="wrap scmb-import-export-wrap">
            <h1><?php esc_html_e('Module Builder: Import / Export', 'secure-custom-module-builder'); ?></h1>

            <?php $this->render_notice($notice); ?>
            <?php $this->render_result($result); ?>

            <div class="scmb-migration-grid">
                <div class="scmb-migration-panel">
                    <h2><?php esc_html_e('Export Modules', 'secure-custom-module-builder'); ?></h2>
                    <p><?php esc_html_e('Download a portable SCMB JSON package that can be imported into another WordPress site.', 'secure-custom-module-builder'); ?></p>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="scmb_export_modules">
                        <?php wp_nonce_field('scmb_export_modules', 'scmb_export_nonce'); ?>

                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><?php esc_html_e('Modules', 'secure-custom-module-builder'); ?></th>
                                <td>
                                    <fieldset>
                                        <label><input type="radio" name="module_scope" value="active" checked> <?php esc_html_e('All active modules', 'secure-custom-module-builder'); ?></label><br>
                                        <label><input type="radio" name="module_scope" value="all"> <?php esc_html_e('All modules', 'secure-custom-module-builder'); ?></label><br>
                                        <label><input type="radio" name="module_scope" value="specific"> <?php esc_html_e('Choose specific modules', 'secure-custom-module-builder'); ?></label>
                                    </fieldset>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Specific Modules', 'secure-custom-module-builder'); ?></th>
                                <td>
                                    <select name="module_ids[]" multiple size="8" class="scmb-module-select">
                                        <?php foreach ($modules as $module) : ?>
                                            <option value="<?php echo esc_attr($module->ID); ?>">
                                                <?php echo esc_html($module->post_title); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description"><?php esc_html_e('Used only when "Choose specific modules" is selected.', 'secure-custom-module-builder'); ?></p>
                                </td>
                            </tr>
                        </table>

                        <?php submit_button(__('Download Export File', 'secure-custom-module-builder'), 'primary', 'submit', false); ?>
                    </form>
                </div>

                <div class="scmb-migration-panel">
                    <h2><?php esc_html_e('Import Modules', 'secure-custom-module-builder'); ?></h2>
                    <p><?php esc_html_e('Upload an SCMB JSON package, review the preview, then confirm the import.', 'secure-custom-module-builder'); ?></p>

                    <form method="post" enctype="multipart/form-data">
                        <?php wp_nonce_field('scmb_import_modules', 'scmb_import_nonce'); ?>

                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><label for="scmb_import_file"><?php esc_html_e('Package File', 'secure-custom-module-builder'); ?></label></th>
                                <td><input type="file" id="scmb_import_file" name="scmb_import_file" accept="application/json,.json" required></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Import Mode', 'secure-custom-module-builder'); ?></th>
                                <td>
                                    <select name="import_mode">
                                        <option value="update_matching"><?php esc_html_e('Update matching modules and create new modules', 'secure-custom-module-builder'); ?></option>
                                        <option value="create_new"><?php esc_html_e('Create new modules only', 'secure-custom-module-builder'); ?></option>
                                        <option value="replace_matching"><?php esc_html_e('Replace matching modules', 'secure-custom-module-builder'); ?></option>
                                    </select>
                                    <p class="description"><?php esc_html_e('Updates match by stable module key. Replace mode also falls back to an exact module title match when the key is different or missing.', 'secure-custom-module-builder'); ?></p>
                                </td>
                            </tr>
                        </table>

                        <?php submit_button(__('Check Import', 'secure-custom-module-builder'), 'primary', 'scmb_check_import', false); ?>
                    </form>

                    <?php $this->render_preview($preview); ?>
                </div>
            </div>
        </div>

        <style>
            .scmb-migration-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 20px;
                margin-top: 20px;
            }
            .scmb-migration-panel {
                background: #fff;
                border: 1px solid #c3c4c7;
                padding: 20px;
            }
            .scmb-migration-panel h2 {
                margin-top: 0;
            }
            .scmb-module-select {
                width: 100%;
                max-width: 420px;
            }
            .scmb-import-preview {
                border-top: 1px solid #dcdcde;
                margin-top: 20px;
                padding-top: 20px;
            }
            .scmb-import-preview ul {
                list-style: disc;
                margin-left: 20px;
            }
            @media (max-width: 1100px) {
                .scmb-migration-grid {
                    grid-template-columns: 1fr;
                }
            }
        </style>
        <?php
    }

    /**
     * Download a JSON package.
     */
    public function handle_export() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to export modules.', 'secure-custom-module-builder'));
        }

        check_admin_referer('scmb_export_modules', 'scmb_export_nonce');

        $scope = isset($_POST['module_scope']) ? sanitize_key(wp_unslash($_POST['module_scope'])) : 'active';
        $selected_ids = isset($_POST['module_ids']) && is_array($_POST['module_ids'])
            ? array_map('absint', wp_unslash($_POST['module_ids']))
            : [];

        $modules = $this->get_modules_for_export($scope, $selected_ids);
        $package = $this->build_export_package($modules);
        $json = wp_json_encode($package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (false === $json) {
            wp_die(esc_html__('Could not encode the export package.', 'secure-custom-module-builder'));
        }

        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename=scmb-modules-' . gmdate('Y-m-d-His') . '.json');
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download is generated by wp_json_encode() and sent with an application/json content type.
        echo $json;
        exit;
    }

    /**
     * Build an import preview from an uploaded package.
     *
     * @return array
     */
    private function handle_import_preview() {
        check_admin_referer('scmb_import_modules', 'scmb_import_nonce');

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- File upload array is unslashed and sanitized immediately before use.
        $file = isset($_FILES['scmb_import_file']) && is_array($_FILES['scmb_import_file'])
            ? array_map('sanitize_text_field', wp_unslash($_FILES['scmb_import_file']))
            : [];

        if (empty($file) || !isset($file['tmp_name'])) {
            return [['type' => 'error', 'message' => __('No import file was uploaded.', 'secure-custom-module-builder')], null];
        }

        $tmp_name = isset($file['tmp_name']) ? $file['tmp_name'] : '';
        $file_name = isset($file['name']) ? sanitize_file_name($file['name']) : '';

        if (!empty($file['error'])) {
            return [['type' => 'error', 'message' => __('The import file could not be uploaded.', 'secure-custom-module-builder')], null];
        }

        if (empty($tmp_name) || !is_uploaded_file($tmp_name)) {
            return [['type' => 'error', 'message' => __('The import file could not be read.', 'secure-custom-module-builder')], null];
        }

        $file_type = wp_check_filetype(
            $file_name,
            [
                'json' => 'application/json',
            ]
        );

        if ('json' !== $file_type['ext'] || 'application/json' !== $file_type['type']) {
            return [['type' => 'error', 'message' => __('Please upload a valid JSON package file.', 'secure-custom-module-builder')], null];
        }

        $contents = file_get_contents($tmp_name);

        if (false === $contents) {
            return [['type' => 'error', 'message' => __('The import file could not be read.', 'secure-custom-module-builder')], null];
        }

        $package = json_decode($contents, true);

        if (!is_array($package) || empty($package['modules']) || !is_array($package['modules'])) {
            return [['type' => 'error', 'message' => __('The selected file is not a valid SCMB package.', 'secure-custom-module-builder')], null];
        }

        $mode = isset($_POST['import_mode']) ? sanitize_key(wp_unslash($_POST['import_mode'])) : 'update_matching';
        $mode = in_array($mode, ['update_matching', 'create_new', 'replace_matching'], true) ? $mode : 'update_matching';
        $preview = $this->analyze_import_package($package, $mode);
        $token = wp_generate_password(24, false, false);

        set_transient($this->get_import_transient_key($token), [
            'package' => $package,
            'mode' => $mode,
        ], 30 * MINUTE_IN_SECONDS);

        $preview['token'] = $token;

        return [['type' => 'success', 'message' => __('Import check complete. Review the preview before importing.', 'secure-custom-module-builder')], $preview];
    }

    /**
     * Confirm and perform a previously previewed import.
     *
     * @return array
     */
    private function handle_import_confirm() {
        check_admin_referer('scmb_confirm_import', 'scmb_confirm_nonce');

        $token = isset($_POST['import_token']) ? sanitize_text_field(wp_unslash($_POST['import_token'])) : '';
        $payload = get_transient($this->get_import_transient_key($token));

        if (empty($payload['package']) || empty($payload['mode'])) {
            return [['type' => 'error', 'message' => __('The import preview expired. Upload the package again.', 'secure-custom-module-builder')], null];
        }

        delete_transient($this->get_import_transient_key($token));
        $result = $this->import_package($payload['package'], $payload['mode']);

        return [['type' => 'success', 'message' => __('Import finished.', 'secure-custom-module-builder')], $result];
    }

    /**
     * Build export data.
     *
     * @param array $modules Module posts.
     * @return array
     */
    private function build_export_package($modules) {
        return [
            'schema_version' => '1.0',
            'plugin_version' => defined('SCMB_VERSION') ? SCMB_VERSION : '',
            'exported_at' => gmdate('c'),
            'modules' => array_map([$this, 'build_module_payload'], $modules),
        ];
    }

    /**
     * Build one portable module payload.
     *
     * @param WP_Post $module Module post.
     * @return array
     */
    private function build_module_payload($module) {
        $module_id = $module->ID;
        $module_key = $this->get_module_value($module_id, 'module_key');
        $module_key = $this->sanitize_module_key($module_key ?: $module->post_title);

        return [
            'module_key' => $module_key,
            'title' => $module->post_title,
            'post_status' => $module->post_status,
            'config' => [
                'module_label' => $this->get_module_value($module_id, 'module_label'),
                'module_category' => $this->get_module_value($module_id, 'module_category'),
                'module_icon' => $this->get_module_value($module_id, 'module_icon'),
                'module_description' => $this->get_module_value($module_id, 'module_description'),
                'module_preview_image' => $this->sanitize_attachment_id($this->get_module_value($module_id, 'module_preview_image')),
                'module_status' => (bool) $this->get_module_value($module_id, 'module_status'),
                'module_single_instance' => (bool) $this->get_module_value($module_id, 'module_single_instance'),
                'module_compact_code' => (bool) $this->get_module_value($module_id, 'module_compact_code'),
            ],
            'template' => [
                'module_html' => $this->get_module_value($module_id, 'module_html'),
                'module_css' => $this->get_module_value($module_id, 'module_css'),
                'module_js' => $this->get_module_value($module_id, 'module_js'),
            ],
            'fields' => $this->get_module_value($module_id, 'module_fields') ?: [],
        ];
    }

    /**
     * Analyze import impact.
     *
     * @param array  $package Import package.
     * @param string $mode Import mode.
     * @return array
     */
    private function analyze_import_package($package, $mode) {
        $preview = [
            'mode' => $mode,
            'total' => count($package['modules']),
            'create' => 0,
            'update' => 0,
            'duplicate' => 0,
            'warnings' => [],
        ];

        foreach ($package['modules'] as $module) {
            $module_key = $this->sanitize_module_key($module['module_key'] ?? ($module['title'] ?? 'module'));
            $match = $this->resolve_import_match($module, $module_key, $mode);
            $existing_id = $match['id'];

            if ($existing_id && 'create_new' === $mode) {
                $preview['duplicate']++;
            } elseif ($existing_id) {
                $preview['update']++;
            } else {
                $preview['create']++;
            }

            if (!$existing_id && !empty($module['title']) && $this->find_module_by_title($module['title'])) {
                $preview['warnings'][] = sprintf(
                    /* translators: %s: Module title. */
                    __('A module named "%s" already exists with a different key. It will not be overwritten.', 'secure-custom-module-builder'),
                    $module['title']
                );
            }

            if ($existing_id && 'title' === $match['matched_by']) {
                $preview['warnings'][] = sprintf(
                    /* translators: 1: Module title, 2: Module key. */
                    __('A module named "%1$s" has a different key. Replace mode will overwrite it and set its key to "%2$s".', 'secure-custom-module-builder'),
                    $module['title'],
                    $module_key
                );
            }
        }

        return $preview;
    }

    /**
     * Perform import.
     *
     * @param array  $package Import package.
     * @param string $mode Import mode.
     * @return array
     */
    private function import_package($package, $mode) {
        $result = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        foreach ($package['modules'] as $module) {
            $module_key = $this->sanitize_module_key($module['module_key'] ?? ($module['title'] ?? 'module'));

            if (empty($module_key) || empty($module['title'])) {
                $result['skipped']++;
                $result['errors'][] = __('A module was skipped because it is missing a title or module key.', 'secure-custom-module-builder');
                continue;
            }

            $match = $this->resolve_import_match($module, $module_key, $mode);
            $existing_id = $match['id'];
            $target_id = 0;

            if ($existing_id && 'create_new' !== $mode) {
                $target_id = $existing_id;
                wp_update_post([
                    'ID' => $target_id,
                    'post_title' => sanitize_text_field($module['title']),
                    'post_status' => $this->sanitize_post_status($module['post_status'] ?? 'publish'),
                ]);
                $result['updated']++;
            } else {
                if ($existing_id) {
                    $module_key = $this->make_unique_module_key($module_key);
                }

                $target_id = wp_insert_post([
                    'post_type' => 'scmb_module',
                    'post_title' => sanitize_text_field($module['title']),
                    'post_status' => $this->sanitize_post_status($module['post_status'] ?? 'publish'),
                ], true);

                if (is_wp_error($target_id)) {
                    $result['skipped']++;
                    $result['errors'][] = $target_id->get_error_message();
                    continue;
                }

                $result['created']++;
            }

            $this->update_module_from_payload((int) $target_id, $module, $module_key);
        }

        return $result;
    }

    /**
     * Update module meta from payload data.
     *
     * @param int    $post_id Module post ID.
     * @param array  $module Module payload.
     * @param string $module_key Stable module key.
     * @return void
     */
    private function update_module_from_payload($post_id, $module, $module_key) {
        $config = isset($module['config']) && is_array($module['config']) ? $module['config'] : [];
        $template = isset($module['template']) && is_array($module['template']) ? $module['template'] : [];

        $values = [
            'module_key' => $module_key,
            'module_label' => sanitize_text_field($config['module_label'] ?? ($module['title'] ?? '')),
            'module_category' => sanitize_key($config['module_category'] ?? 'custom'),
            'module_icon' => sanitize_text_field($config['module_icon'] ?? 'admin-post'),
            'module_description' => sanitize_textarea_field($config['module_description'] ?? ''),
            'module_preview_image' => $this->sanitize_attachment_id($config['module_preview_image'] ?? 0),
            'module_status' => !empty($config['module_status']) ? 1 : 0,
            'module_single_instance' => !empty($config['module_single_instance']) ? 1 : 0,
            'module_compact_code' => !empty($config['module_compact_code']) ? 1 : 0,
            'module_html' => isset($template['module_html']) ? (string) $template['module_html'] : '',
            'module_css' => isset($template['module_css']) ? (string) $template['module_css'] : '',
            'module_js' => isset($template['module_js']) ? (string) $template['module_js'] : '',
            'module_fields' => $this->sanitize_module_fields($module['fields'] ?? []),
        ];

        foreach ($values as $field_name => $value) {
            $this->update_module_value($post_id, $field_name, $value);
        }
    }

    /**
     * Sanitize module field definitions.
     *
     * @param mixed $fields Raw fields.
     * @return array
     */
    private function sanitize_module_fields($fields) {
        if (!is_array($fields)) {
            return [];
        }

        $allowed_types = ['text', 'textarea', 'wysiwyg', 'image', 'url', 'select', 'checkbox', 'true_false', 'repeater'];
        $sanitized = [];

        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }

            $field_type = sanitize_key($field['field_type'] ?? 'text');
            $sanitized[] = [
                'field_name' => $this->normalize_field_name($field['field_name'] ?? ''),
                'field_label' => sanitize_text_field($field['field_label'] ?? ''),
                'field_type' => in_array($field_type, $allowed_types, true) ? $field_type : 'text',
                'field_default' => sanitize_text_field($field['field_default'] ?? ''),
                'field_choices' => sanitize_textarea_field($field['field_choices'] ?? ''),
                'field_allow_null' => !empty($field['field_allow_null']) ? 1 : 0,
                'field_required' => !empty($field['field_required']) ? 1 : 0,
                'field_sub_fields' => $this->normalize_sub_field_lines(sanitize_textarea_field($field['field_sub_fields'] ?? '')),
                'field_repeater_max' => isset($field['field_repeater_max']) ? absint($field['field_repeater_max']) : '',
            ];
        }

        return array_values(array_filter($sanitized, function($field) {
            return !empty($field['field_name']);
        }));
    }

    private function normalize_sub_field_lines($value) {
        $lines = preg_split('/\r\n|\r|\n/', (string) $value);

        if (false === $lines) {
            return '';
        }

        $normalized_lines = [];

        foreach ($lines as $line) {
            $parts = explode('|', $line);

            if (!empty($parts[0])) {
                preg_match('/^(\s*)(.*)$/', $parts[0], $matches);

                $indent = $matches[1] ?? '';
                $name = $matches[2] ?? $parts[0];
                $parts[0] = $indent . $this->normalize_field_name($name);
            }

            $normalized_lines[] = implode('|', $parts);
        }

        return implode("\n", $normalized_lines);
    }

    private function normalize_field_name($value) {
        $value = strtolower((string) $value);
        $value = preg_replace('/[^a-z0-9_]+/', '_', $value);
        $value = preg_replace('/_+/', '_', $value);
        $value = trim($value, '_');

        if ('' !== $value && !preg_match('/^[a-z_]/', $value)) {
            $value = 'field_' . $value;
        }

        return $value;
    }

    private function sanitize_attachment_id($value) {
        if (is_array($value)) {
            return absint($value['ID'] ?? $value['id'] ?? 0);
        }

        return is_numeric($value) ? absint($value) : 0;
    }

    /**
     * Render import preview.
     *
     * @param array|null $preview Preview data.
     * @return void
     */
    private function render_preview($preview) {
        if (empty($preview)) {
            return;
        }
        ?>
        <div class="scmb-import-preview">
            <h3><?php esc_html_e('Import Preview', 'secure-custom-module-builder'); ?></h3>
            <ul>
                <li><?php
                    printf(
                        /* translators: %d: Number of modules. */
                        esc_html__('%d modules found', 'secure-custom-module-builder'),
                        (int) $preview['total']
                    );
                ?></li>
                <li><?php
                    printf(
                        /* translators: %d: Number of modules. */
                        esc_html__('%d will be created', 'secure-custom-module-builder'),
                        (int) $preview['create']
                    );
                ?></li>
                <li><?php
                    printf(
                        /* translators: %d: Number of modules. */
                        esc_html__('%d will be updated', 'secure-custom-module-builder'),
                        (int) $preview['update']
                    );
                ?></li>
                <li><?php
                    printf(
                        /* translators: %d: Number of modules. */
                        esc_html__('%d duplicate copies will be created', 'secure-custom-module-builder'),
                        (int) $preview['duplicate']
                    );
                ?></li>
            </ul>

            <?php if (!empty($preview['warnings'])) : ?>
                <h4><?php esc_html_e('Warnings', 'secure-custom-module-builder'); ?></h4>
                <ul>
                    <?php foreach ($preview['warnings'] as $warning) : ?>
                        <li><?php echo esc_html($warning); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <form method="post">
                <?php wp_nonce_field('scmb_confirm_import', 'scmb_confirm_nonce'); ?>
                <input type="hidden" name="import_token" value="<?php echo esc_attr($preview['token']); ?>">
                <?php submit_button(__('Import Modules', 'secure-custom-module-builder'), 'primary', 'scmb_confirm_import', false); ?>
            </form>
        </div>
        <?php
    }

    private function render_notice($notice) {
        if (empty($notice['message'])) {
            return;
        }

        $class = 'success' === $notice['type'] ? 'notice-success' : 'notice-error';
        echo '<div class="notice ' . esc_attr($class) . '"><p>' . esc_html($notice['message']) . '</p></div>';
    }

    private function render_result($result) {
        if (empty($result)) {
            return;
        }

        echo '<div class="notice notice-info"><p>';
        printf(
            /* translators: 1: Created module count, 2: Updated module count, 3: Skipped module count. */
            esc_html__('Created: %1$d. Updated: %2$d. Skipped: %3$d.', 'secure-custom-module-builder'),
            (int) $result['created'],
            (int) $result['updated'],
            (int) $result['skipped']
        );
        echo '</p></div>';

        if (!empty($result['errors'])) {
            echo '<div class="notice notice-warning"><ul>';
            foreach ($result['errors'] as $error) {
                echo '<li>' . esc_html($error) . '</li>';
            }
            echo '</ul></div>';
        }
    }

    private function get_modules_for_export($scope, $selected_ids) {
        $args = [
            'post_type' => 'scmb_module',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ];

        if ('specific' === $scope) {
            if (empty($selected_ids)) {
                return [];
            }

            $args['post__in'] = array_filter($selected_ids);
            $args['orderby'] = 'post__in';
        } elseif ('active' === $scope) {
            $args['meta_query'] = [
                [
                    'key' => 'module_status',
                    'value' => '1',
                    'compare' => '=',
                ],
            ];
        }

        return get_posts($args);
    }

    private function get_all_modules() {
        return get_posts([
            'post_type' => 'scmb_module',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
    }

    private function find_module_by_key($module_key) {
        $posts = get_posts([
            'post_type' => 'scmb_module',
            'post_status' => 'any',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => 'module_key',
                    'value' => $module_key,
                    'compare' => '=',
                ],
            ],
        ]);

        return !empty($posts) ? (int) $posts[0] : 0;
    }

    /**
     * Resolve the module an import should update.
     *
     * Stable keys are preferred. Replace mode additionally allows an exact title
     * fallback so modules from older/other sites can intentionally overwrite a
     * same-named local module whose key differs or is missing.
     *
     * @param array  $module Import module payload.
     * @param string $module_key Sanitized import module key.
     * @param string $mode Import mode.
     * @return array{id:int,matched_by:string}
     */
    private function resolve_import_match($module, $module_key, $mode) {
        $existing_id = $this->find_module_by_key($module_key);

        if ($existing_id) {
            return [
                'id' => $existing_id,
                'matched_by' => 'key',
            ];
        }

        if ('replace_matching' !== $mode || empty($module['title'])) {
            return [
                'id' => 0,
                'matched_by' => '',
            ];
        }

        $title_match_id = $this->find_module_by_title($module['title']);

        return [
            'id' => $title_match_id,
            'matched_by' => $title_match_id ? 'title' : '',
        ];
    }

    private function find_module_by_title($title) {
        $posts = get_posts([
            'post_type' => 'scmb_module',
            'post_status' => 'any',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'title' => sanitize_text_field($title),
        ]);

        return !empty($posts) ? (int) $posts[0] : 0;
    }

    private function get_module_value($post_id, $field_name) {
        if (function_exists('get_field')) {
            return get_field($field_name, $post_id);
        }

        return get_post_meta($post_id, $field_name, true);
    }

    private function update_module_value($post_id, $field_name, $value) {
        if (function_exists('update_field')) {
            update_field($field_name, $value, $post_id);
            return;
        }

        update_post_meta($post_id, $field_name, $value);
    }

    private function sanitize_module_key($key) {
        $key = sanitize_title((string) $key);
        return preg_replace('/[^a-z0-9-]/', '', $key);
    }

    private function make_unique_module_key($module_key) {
        $base = $this->sanitize_module_key($module_key);
        $candidate = $base;
        $suffix = 2;

        while ($this->find_module_by_key($candidate)) {
            $candidate = $base . '-' . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function sanitize_post_status($status) {
        $status = sanitize_key($status);
        return in_array($status, ['publish', 'draft', 'pending', 'private'], true) ? $status : 'publish';
    }

    private function get_import_transient_key($token) {
        return $this->transient_prefix . get_current_user_id() . '_' . $token;
    }
}
