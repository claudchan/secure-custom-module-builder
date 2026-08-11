<?php
/**
 * Link suggestion search for URL fields.
 *
 * Backs the autocomplete dropdown on Module URL fields (top-level and repeater
 * sub-fields) with matches across public post types, pages, and post type
 * archive pages.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SCMB_Url_Search {

    private static $instance = null;
    const NONCE_ACTION = 'scmb_url_search';
    const MIN_CHARS = 3;
    const MAX_RESULTS = 20;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_ajax_scmb_search_urls', [$this, 'handle_search']);
    }

    /**
     * AJAX handler: search posts/pages/CPTs and post type archives.
     *
     * @return void
     */
    public function handle_search() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('You are not allowed to search for links.', 'secure-custom-module-builder')], 403);
        }

        $search = isset($_GET['search']) ? sanitize_text_field(wp_unslash($_GET['search'])) : '';

        if (mb_strlen($search) < self::MIN_CHARS) {
            wp_send_json_success(['results' => []]);
        }

        $results = array_merge(
            $this->search_entries($search),
            $this->search_archives($search)
        );

        wp_send_json_success(['results' => array_slice($results, 0, self::MAX_RESULTS)]);
    }

    /**
     * Search published posts, pages, and custom post types.
     *
     * @param string $search Search term.
     * @return array
     */
    private function search_entries($search) {
        $post_types = get_post_types(
            [
                'public' => true,
                'exclude_from_search' => false,
            ],
            'names'
        );
        $post_types = array_values(array_diff($post_types, ['attachment']));

        if (empty($post_types)) {
            return [];
        }

        $query = new WP_Query(
            [
                's' => $search,
                'post_type' => $post_types,
                'post_status' => 'publish',
                'posts_per_page' => self::MAX_RESULTS,
                'no_found_rows' => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
                'ignore_sticky_posts' => true,
            ]
        );

        $results = [];

        foreach ($query->posts as $post) {
            $post_type_object = get_post_type_object($post->post_type);

            $results[] = [
                'title' => html_entity_decode(get_the_title($post), ENT_QUOTES),
                'url' => get_permalink($post),
                'type' => $post_type_object ? $post_type_object->labels->singular_name : $post->post_type,
            ];
        }

        return $results;
    }

    /**
     * Search post type archive pages by their label.
     *
     * @param string $search Search term.
     * @return array
     */
    private function search_archives($search) {
        $post_types = get_post_types(
            [
                'public' => true,
                'has_archive' => true,
            ],
            'objects'
        );

        $results = [];

        foreach ($post_types as $post_type) {
            $archive_label = $post_type->labels->name;

            if (false === stripos($archive_label, $search)) {
                continue;
            }

            $archive_link = get_post_type_archive_link($post_type->name);

            if (!$archive_link) {
                continue;
            }

            $results[] = [
                /* translators: %s: post type label, e.g. "Products". */
                'title' => sprintf(__('%s Archive', 'secure-custom-module-builder'), $archive_label),
                'url' => $archive_link,
                'type' => __('Archive', 'secure-custom-module-builder'),
            ];
        }

        return $results;
    }
}
