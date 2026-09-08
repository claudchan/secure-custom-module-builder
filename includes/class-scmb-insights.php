<?php
/**
 * Module Insights admin functionality.
 *
 * Provides a sitewide usage overview for all custom modules built with SCMB,
 * listing where each module is inserted across public post types and pages.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SCMB_Insights {

    private static $instance = null;
    private $page_slug = 'scmb-insights';
    private $hook_suffix = '';

    /**
     * Get singleton instance.
     *
     * @return SCMB_Insights
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        add_action('admin_menu', [$this, 'register_admin_page'], 9);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    /**
     * Register the Module Insights submenu page under Module Builder.
     *
     * @return void
     */
    public function register_admin_page() {
        $this->hook_suffix = add_submenu_page(
            'edit.php?post_type=scmb_module',
            __('Module Insights', 'secure-custom-module-builder'),
            __('Module Insights', 'secure-custom-module-builder'),
            'manage_options',
            $this->page_slug,
            [$this, 'render_page']
        );
    }

    /**
     * Enqueue assets only on the Module Insights admin screen.
     *
     * @param string $hook The current admin page hook.
     * @return void
     */
    public function enqueue_assets($hook) {
        if ($hook !== $this->hook_suffix) {
            return;
        }

        $css_file = SCMB_PLUGIN_DIR . 'assets/css/insights.css';
        $js_file  = SCMB_PLUGIN_DIR . 'assets/js/insights.js';

        $css_version = file_exists($css_file) ? filemtime($css_file) : SCMB_VERSION;
        $js_version  = file_exists($js_file) ? filemtime($js_file) : SCMB_VERSION;

        wp_enqueue_style(
            'scmb-insights',
            SCMB_PLUGIN_URL . 'assets/css/insights.css',
            ['dashicons'],
            $css_version
        );

        wp_enqueue_script(
            'scmb-insights',
            SCMB_PLUGIN_URL . 'assets/js/insights.js',
            [],
            $js_version,
            true
        );
    }

    /**
     * Get stable module key matching SCMB block registration.
     *
     * @param WP_Post $module Module post.
     * @return string
     */
    private function get_module_key($module) {
        $module_id = $module->ID;
        $module_key = '';

        if (function_exists('get_field')) {
            $module_key = get_field('module_key', $module_id);
        }

        if (empty($module_key)) {
            $module_key = get_post_meta($module_id, 'module_key', true);
        }

        $module_key = sanitize_title((string) $module_key);

        if (empty($module_key)) {
            $module_key = sanitize_title($module->post_title);
        }

        return !empty($module_key) ? $module_key : 'module-' . (int) $module_id;
    }

    /**
     * Recursively count block instances across nested inner blocks.
     *
     * @param array  $blocks Parsed blocks array.
     * @param string $target_block_name Primary block name (e.g. acf/hero-banner).
     * @param string $fallback_name Fallback block name without prefix.
     * @return int
     */
    private function count_block_instances(array $blocks, $target_block_name, $fallback_name = '') {
        $count = 0;

        foreach ($blocks as $block) {
            if (!empty($block['blockName'])) {
                if ($block['blockName'] === $target_block_name || (!empty($fallback_name) && $block['blockName'] === $fallback_name)) {
                    $count++;
                }
            }

            if (!empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
                $count += $this->count_block_instances($block['innerBlocks'], $target_block_name, $fallback_name);
            }
        }

        return $count;
    }

    /**
     * Retrieve all module posts and their sitewide usage statistics.
     *
     * @return array
     */
    public function get_insights_data() {
        global $wpdb;

        // 1. Fetch all modules
        $modules = get_posts([
            'post_type'      => 'scmb_module',
            'post_status'    => ['publish', 'draft', 'pending', 'private', 'future'],
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        if (empty($modules)) {
            return [];
        }

        // 2. Fetch public post types to inspect
        $post_types = get_post_types(['public' => true], 'names');
        $post_types = array_values(array_diff($post_types, ['attachment', 'scmb_module']));

        $post_type_objects = get_post_types(['public' => true], 'objects');
        $post_type_labels  = [];
        foreach ($post_type_objects as $pt_name => $pt_obj) {
            $post_type_labels[$pt_name] = !empty($pt_obj->labels->singular_name) ? $pt_obj->labels->singular_name : $pt_name;
        }

        $post_statuses = ['publish', 'draft', 'pending', 'future', 'private'];

        // 3. Query candidate posts containing block comments
        $candidate_posts = [];
        if (!empty($post_types)) {
            $type_placeholders   = implode(',', array_fill(0, count($post_types), '%s'));
            $status_placeholders = implode(',', array_fill(0, count($post_statuses), '%s'));

            $sql = "
                SELECT ID, post_title, post_type, post_status, post_content
                FROM {$wpdb->posts}
                WHERE post_type IN ($type_placeholders)
                  AND post_status IN ($status_placeholders)
                  AND (post_content LIKE %s OR post_content LIKE %s)
            ";

            $query_params = array_merge(
                $post_types,
                $post_statuses,
                [
                    '%' . $wpdb->esc_like('<!-- wp:acf/') . '%',
                    '%' . $wpdb->esc_like('<!-- wp:') . '%',
                ]
            );

            $candidate_posts = $wpdb->get_results(
                $wpdb->prepare($sql, $query_params)
            );
        }

        // 4. Cache parsed blocks per post ID
        $parsed_blocks_cache = [];
        $modules_data = [];

        foreach ($modules as $module) {
            $mod_id   = (int) $module->ID;
            $mod_key  = $this->get_module_key($module);
            $target_block_name = 'acf/' . $mod_key;
            $fallback_name     = $mod_key;

            $total_instances = 0;
            $module_posts    = [];

            if (!empty($candidate_posts)) {
                foreach ($candidate_posts as $post_row) {
                    $content = $post_row->post_content;

                    // Fast substring check to avoid unnecessary block parsing
                    if (strpos($content, 'wp:acf/' . $mod_key) === false && strpos($content, 'wp:' . $mod_key) === false) {
                        continue;
                    }

                    $pid = (int) $post_row->ID;
                    if (!isset($parsed_blocks_cache[$pid])) {
                        $parsed_blocks_cache[$pid] = parse_blocks($content);
                    }

                    $instances = $this->count_block_instances(
                        $parsed_blocks_cache[$pid],
                        $target_block_name,
                        $fallback_name
                    );

                    if ($instances > 0) {
                        $total_instances += $instances;
                        $title = !empty($post_row->post_title) ? $post_row->post_title : sprintf(__('(Post #%d - No Title)', 'secure-custom-module-builder'), $pid);

                        $module_posts[] = [
                            'id'        => $pid,
                            'title'     => $title,
                            'type'      => $post_row->post_type,
                            'type_name' => isset($post_type_labels[$post_row->post_type]) ? $post_type_labels[$post_row->post_type] : $post_row->post_type,
                            'status'    => $post_row->post_status,
                            'instances' => $instances,
                            'edit_url'  => admin_url('post.php?post=' . $pid . '&action=edit'),
                            'view_url'  => get_permalink($pid),
                        ];
                    }
                }
            }

            // Retrieve module display label
            $module_label = '';
            if (function_exists('get_field')) {
                $module_label = get_field('module_label', $mod_id);
            }
            if (empty($module_label)) {
                $module_label = $module->post_title;
            }

            $modules_data[] = [
                'id'              => $mod_id,
                'title'           => $module->post_title,
                'label'           => $module_label,
                'key'             => $mod_key,
                'total_instances' => $total_instances,
                'posts'           => $module_posts,
            ];
        }

        // 5. Sort: total usage count (descending), then alphabetically by title (ascending)
        usort($modules_data, function($a, $b) {
            if ($b['total_instances'] !== $a['total_instances']) {
                return $b['total_instances'] <=> $a['total_instances'];
            }
            return strcasecmp($a['title'], $b['title']);
        });

        return $modules_data;
    }

    /**
     * Render the Module Insights admin page.
     *
     * @return void
     */
    public function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'secure-custom-module-builder'));
        }

        $modules_data = $this->get_insights_data();

        // Determine initially selected module
        $selected_id = isset($_GET['module_id']) ? absint($_GET['module_id']) : 0;
        $valid_ids   = array_column($modules_data, 'id');

        if (!in_array($selected_id, $valid_ids, true) && !empty($modules_data)) {
            $selected_id = $modules_data[0]['id'];
        }

        ?>
        <div class="wrap scmb-insights-wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Module Insights', 'secure-custom-module-builder'); ?></h1>
            <hr class="wp-header-end">

            <?php wp_nonce_field('scmb_insights_action', 'scmb_insights_nonce'); ?>

            <?php if (empty($modules_data)) : ?>
                <div class="scmb-insights-empty-state">
                    <span class="dashicons dashicons-editor-table"></span>
                    <h3><?php esc_html_e('No Modules Found', 'secure-custom-module-builder'); ?></h3>
                    <p>
                        <?php
                        printf(
                            /* translators: %s: Link to add a new module */
                            esc_html__('You haven\'t created any modules yet. %s to get started.', 'secure-custom-module-builder'),
                            '<a href="' . esc_url(admin_url('post-new.php?post_type=scmb_module')) . '">' . esc_html__('Create your first module', 'secure-custom-module-builder') . '</a>'
                        );
                        ?>
                    </p>
                </div>
            <?php else : ?>
                <div class="scmb-insights-layout">
                    <!-- Left Column: Module Selector -->
                    <div class="scmb-insights-sidebar">
                        <div class="scmb-insights-sidebar-header">
                            <h2><?php esc_html_e('Custom Modules', 'secure-custom-module-builder'); ?></h2>
                            <span class="scmb-insights-module-count">
                                <?php
                                printf(
                                    /* translators: %d: Total number of modules */
                                    esc_html(_n('%d module', '%d modules', count($modules_data), 'secure-custom-module-builder')),
                                    count($modules_data)
                                );
                                ?>
                            </span>
                        </div>
                        <ul class="scmb-insights-module-list" role="tablist">
                            <?php foreach ($modules_data as $mod) :
                                $is_active = ((int) $mod['id'] === $selected_id);
                                $item_url = add_query_arg([
                                    'post_type' => 'scmb_module',
                                    'page'      => $this->page_slug,
                                    'module_id' => $mod['id'],
                                ], admin_url('edit.php'));
                            ?>
                                <li class="scmb-insights-module-item <?php echo $is_active ? 'is-active' : ''; ?>">
                                    <a href="<?php echo esc_url($item_url); ?>"
                                       class="scmb-insights-module-link"
                                       data-module-id="<?php echo esc_attr($mod['id']); ?>"
                                       role="tab"
                                       aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>">
                                        <div class="scmb-insights-module-info">
                                            <span class="scmb-insights-module-title" title="<?php echo esc_attr($mod['title']); ?>">
                                                <?php echo esc_html($mod['title']); ?>
                                            </span>
                                            <span class="scmb-insights-module-slug">
                                                acf/<?php echo esc_html($mod['key']); ?>
                                            </span>
                                        </div>
                                        <span class="scmb-insights-badge <?php echo $mod['total_instances'] > 0 ? 'has-uses' : ''; ?>"
                                              title="<?php echo esc_attr(sprintf(_n('%d instance', '%d instances', $mod['total_instances'], 'secure-custom-module-builder'), $mod['total_instances'])); ?>">
                                            <?php echo esc_html($mod['total_instances']); ?>
                                        </span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <!-- Right Column: Usage Details -->
                    <div class="scmb-insights-content">
                        <?php foreach ($modules_data as $mod) :
                            $is_active = ((int) $mod['id'] === $selected_id);
                            $posts_count = count($mod['posts']);
                        ?>
                            <div class="scmb-insights-panel <?php echo $is_active ? 'is-active' : ''; ?>"
                                 data-module-id="<?php echo esc_attr($mod['id']); ?>"
                                 role="tabpanel">

                                <!-- Panel Header -->
                                <div class="scmb-insights-header">
                                    <div class="scmb-insights-header-left">
                                        <div class="scmb-insights-title-row">
                                            <h2><?php echo esc_html($mod['title']); ?></h2>
                                            <span class="scmb-insights-usage-pill <?php echo $mod['total_instances'] > 0 ? 'has-uses' : 'has-no-uses'; ?>">
                                                <?php
                                                if ($mod['total_instances'] > 0) {
                                                    printf(
                                                        /* translators: 1: Number of block instances, 2: Number of posts/pages */
                                                        esc_html(_n('%1$d sitewide use across %2$d post', '%1$d sitewide uses across %2$d posts', $posts_count, 'secure-custom-module-builder')),
                                                        $mod['total_instances'],
                                                        $posts_count
                                                    );
                                                } else {
                                                    esc_html_e('0 sitewide uses', 'secure-custom-module-builder');
                                                }
                                                ?>
                                            </span>
                                        </div>
                                        <div class="scmb-insights-meta-row">
                                            <span class="scmb-insights-meta-item">
                                                <?php esc_html_e('Block Slug:', 'secure-custom-module-builder'); ?>
                                                <code>acf/<?php echo esc_html($mod['key']); ?></code>
                                            </span>
                                            <span class="scmb-insights-meta-item">
                                                <a href="<?php echo esc_url(admin_url('post.php?post=' . $mod['id'] . '&action=edit')); ?>" class="scmb-insights-action-link">
                                                    <span class="dashicons dashicons-edit"></span>
                                                    <span class="scmb-insights-action-text"><?php esc_html_e('Edit Module', 'secure-custom-module-builder'); ?></span>
                                                </a>
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <?php if ($mod['total_instances'] === 0) : ?>
                                    <!-- Empty state for module with 0 uses -->
                                    <div class="scmb-insights-empty-state">
                                        <span class="dashicons dashicons-info-outline"></span>
                                        <h3><?php esc_html_e('No Sitewide Usage Found', 'secure-custom-module-builder'); ?></h3>
                                        <p><?php esc_html_e('This module has not been inserted into any published or draft posts or pages yet.', 'secure-custom-module-builder'); ?></p>
                                    </div>
                                <?php else : ?>
                                    <!-- Controls: Live Search -->
                                    <div class="scmb-insights-controls">
                                        <div class="scmb-insights-search-wrapper">
                                            <span class="dashicons dashicons-search"></span>
                                            <input type="search"
                                                   class="scmb-insights-search-input"
                                                   placeholder="<?php esc_attr_e('Filter by post/page title...', 'secure-custom-module-builder'); ?>"
                                                   aria-label="<?php esc_attr_e('Filter posts by title', 'secure-custom-module-builder'); ?>" />
                                        </div>
                                    </div>

                                    <!-- Sortable Table -->
                                    <table class="wp-list-table widefat fixed striped scmb-insights-table">
                                        <thead>
                                            <tr>
                                                <th scope="col" class="sortable is-sorted-asc" data-sort="title" aria-sort="ascending">
                                                    <?php esc_html_e('Title', 'secure-custom-module-builder'); ?>
                                                    <span class="sort-indicator dashicons dashicons-arrow-up-alt2"></span>
                                                </th>
                                                <th scope="col" class="sortable" data-sort="type" aria-sort="none" style="width: 140px;">
                                                    <?php esc_html_e('Type', 'secure-custom-module-builder'); ?>
                                                    <span class="sort-indicator dashicons dashicons-sort"></span>
                                                </th>
                                                <th scope="col" class="sortable" data-sort="instances" aria-sort="none" style="width: 120px; text-align: center;">
                                                    <?php esc_html_e('Instances', 'secure-custom-module-builder'); ?>
                                                    <span class="sort-indicator dashicons dashicons-sort"></span>
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($mod['posts'] as $post_item) : ?>
                                                <tr class="scmb-insights-row"
                                                    data-title="<?php echo esc_attr($post_item['title']); ?>"
                                                    data-type="<?php echo esc_attr($post_item['type_name']); ?>"
                                                    data-instances="<?php echo esc_attr($post_item['instances']); ?>">
                                                    <td class="scmb-insights-title-cell">
                                                        <div class="scmb-insights-post-title-row">
                                                            <span class="scmb-insights-post-title">
                                                                <?php echo esc_html($post_item['title']); ?>
                                                            </span>
                                                            <?php if ('publish' !== $post_item['status']) : ?>
                                                                <span class="scmb-insights-status-badge status-<?php echo esc_attr($post_item['status']); ?>">
                                                                    <?php echo esc_html(ucfirst($post_item['status'])); ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="scmb-insights-row-actions">
                                                            <a href="<?php echo esc_url($post_item['view_url']); ?>"
                                                               target="_blank"
                                                               rel="noopener noreferrer"
                                                               class="scmb-insights-action-link scmb-view-link">
                                                                <span class="scmb-insights-action-text"><?php esc_html_e('View', 'secure-custom-module-builder'); ?></span>
                                                                <span class="dashicons dashicons-external"></span>
                                                            </a>
                                                            <span class="scmb-action-sep">|</span>
                                                            <a href="<?php echo esc_url($post_item['edit_url']); ?>"
                                                               class="scmb-insights-action-link scmb-edit-link">
                                                                <span class="scmb-insights-action-text"><?php esc_html_e('Edit', 'secure-custom-module-builder'); ?></span>
                                                                <span class="dashicons dashicons-edit"></span>
                                                            </a>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="scmb-insights-type-badge">
                                                            <?php echo esc_html($post_item['type_name']); ?>
                                                        </span>
                                                    </td>
                                                    <td style="text-align: center;">
                                                        <span class="scmb-insights-instances-count">
                                                            <?php echo esc_html($post_item['instances']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <tr class="scmb-insights-no-results" style="display: none;">
                                                <td colspan="3">
                                                    <?php esc_html_e('No matching posts or pages found.', 'secure-custom-module-builder'); ?>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}

