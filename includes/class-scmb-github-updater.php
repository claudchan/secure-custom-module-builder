<?php
/**
 * GitHub updater for the plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SCMB_GitHub_Updater {

    /**
     * Plugin slug.
     *
     * @var string
     */
    private $plugin_slug;

    /**
     * Plugin file basename.
     *
     * @var string
     */
    private $plugin_basename;

    /**
     * Plugin version.
     *
     * @var string
     */
    private $plugin_version;

    /**
     * GitHub repository owner.
     *
     * @var string
     */
    private $repo_owner;

    /**
     * GitHub repository name.
     *
     * @var string
     */
    private $repo_name;

    /**
     * Releases API URL.
     *
     * @var string
     */
    private $api_url;

    /**
     * Cache transient key.
     *
     * @var string
     */
    private $cache_key;

    /**
     * Constructor.
     *
     * @param array $args Updater arguments.
     */
    public function __construct( $args ) {
        $this->plugin_slug     = $args['plugin_slug'];
        $this->plugin_basename = plugin_basename( $args['plugin_file'] );
        $this->plugin_version  = $args['plugin_version'];
        $this->repo_owner      = $args['repo_owner'];
        $this->repo_name       = $args['repo_name'];
        $this->api_url         = sprintf(
            'https://api.github.com/repos/%1$s/%2$s/releases/latest',
            $this->repo_owner,
            $this->repo_name
        );
        $this->cache_key       = 'scmb_github_release_' . md5( $this->api_url );

        add_filter( 'site_transient_update_plugins', array( $this, 'check_for_update' ) );
        add_filter( 'upgrader_post_install', array( $this, 'rename_github_folder' ), 10, 3 );
    }

    /**
     * Check GitHub for a newer plugin release.
     *
     * @param object $transient The existing plugin update transient.
     * @return object
     */
    public function check_for_update( $transient ) {
        if ( ! is_object( $transient ) || empty( $transient->checked ) ) {
            return $transient;
        }

        $release = $this->get_latest_release();

        if ( empty( $release['version'] ) || empty( $release['package'] ) ) {
            return $transient;
        }

        if ( version_compare( $release['version'], $this->plugin_version, '<=' ) ) {
            return $transient;
        }

        $transient->response[ $this->plugin_basename ] = (object) array(
            'slug'        => $this->plugin_slug,
            'plugin'      => $this->plugin_basename,
            'new_version' => $release['version'],
            'package'     => $release['package'],
            'url'         => $release['url'],
        );

        return $transient;
    }

    /**
     * Rename the extracted GitHub release folder back to the plugin slug.
     *
     * @param bool  $response   Installation response.
     * @param array $hook_extra Extra hook arguments.
     * @param array $result     Installation result data.
     * @return array|bool|WP_Error
     */
    public function rename_github_folder( $response, $hook_extra, $result ) {
        if ( empty( $hook_extra['plugin'] ) || $this->plugin_basename !== $hook_extra['plugin'] ) {
            return $response;
        }

        if ( empty( $result['destination'] ) || empty( $result['local_destination'] ) ) {
            return $response;
        }

        global $wp_filesystem;

        if ( ! $wp_filesystem ) {
            WP_Filesystem();
        }

        $proper_destination = trailingslashit( $result['local_destination'] ) . $this->plugin_slug;
        $current_destination = untrailingslashit( $result['destination'] );

        if ( $current_destination === $proper_destination ) {
            $result['destination'] = $proper_destination;
            return $result;
        }

        if ( $wp_filesystem->is_dir( $proper_destination ) ) {
            $wp_filesystem->delete( $proper_destination, true );
        }

        if ( ! $wp_filesystem->move( $current_destination, $proper_destination, true ) ) {
            return new WP_Error(
                'scmb_updater_rename_failed',
                __( 'Could not rename the GitHub release folder to the plugin slug.', 'scmb' )
            );
        }

        $result['destination']      = $proper_destination;
        $result['destination_name'] = $this->plugin_slug;

        if ( ! empty( $result['remote_destination'] ) ) {
            $result['remote_destination'] = trailingslashit( dirname( $result['remote_destination'] ) ) . $this->plugin_slug;
        }

        return $result;
    }

    /**
     * Get the latest GitHub release and cache it for 12 hours.
     *
     * @return array
     */
    private function get_latest_release() {
        $cached_release = get_transient( $this->cache_key );

        if ( false !== $cached_release && is_array( $cached_release ) ) {
            return $cached_release;
        }

        $response = wp_remote_get(
            $this->api_url,
            array(
                'timeout' => 15,
                'headers' => array(
                    'Accept'     => 'application/vnd.github+json',
                    'User-Agent' => $this->repo_name . '-wordpress-updater',
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return array();
        }

        $code = wp_remote_retrieve_response_code( $response );

        if ( 200 !== (int) $code ) {
            return array();
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $body['tag_name'] ) || empty( $body['zipball_url'] ) ) {
            return array();
        }

        $release = array(
            'version' => ltrim( $body['tag_name'], 'vV' ),
            'package' => $body['zipball_url'],
            'url'     => ! empty( $body['html_url'] ) ? $body['html_url'] : $body['zipball_url'],
        );

        set_transient( $this->cache_key, $release, 12 * HOUR_IN_SECONDS );

        return $release;
    }
}
