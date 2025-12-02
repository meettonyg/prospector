<?php
/**
 * Multisite Support Class
 *
 * @package Podcast_Prospector
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Podcast_Prospector_Multisite
 *
 * Provides WordPress Multisite support for the plugin.
 */
class Podcast_Prospector_Multisite {

    /**
     * Singleton instance.
     *
     * @var Podcast_Prospector_Multisite|null
     */
    private static ?Podcast_Prospector_Multisite $instance = null;

    /**
     * Network-wide options.
     *
     * @var array
     */
    private const NETWORK_OPTIONS = [
        'podcast_prospector_network_enabled',
        'podcast_prospector_network_api_keys',
        'podcast_prospector_network_settings',
    ];

    /**
     * Get singleton instance.
     *
     * @return Podcast_Prospector_Multisite
     */
    public static function get_instance(): Podcast_Prospector_Multisite {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Check if multisite is active.
     *
     * @return bool
     */
    public static function is_multisite(): bool {
        return is_multisite();
    }

    /**
     * Check if network activated.
     *
     * @return bool
     */
    public function is_network_activated(): bool {
        if ( ! self::is_multisite() ) {
            return false;
        }

        if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
            require_once ABSPATH . '/wp-admin/includes/plugin.php';
        }

        return is_plugin_active_for_network( plugin_basename( PODCAST_PROSPECTOR_FILE ) );
    }

    /**
     * Initialize multisite support.
     *
     * @return void
     */
    public function init(): void {
        if ( ! self::is_multisite() ) {
            return;
        }

        // Network admin hooks
        add_action( 'network_admin_menu', [ $this, 'add_network_menu' ] );
        add_action( 'network_admin_edit_podcast_prospector_network', [ $this, 'save_network_settings' ] );

        // Site activation/deactivation
        add_action( 'wp_initialize_site', [ $this, 'on_site_created' ], 10, 2 );
        add_action( 'wp_uninitialize_site', [ $this, 'on_site_deleted' ], 10, 1 );

        // Filter options for network settings
        add_filter( 'pre_option_podcast_prospector_podcastindex_key', [ $this, 'maybe_use_network_key' ], 10, 3 );
        add_filter( 'pre_option_podcast_prospector_podcastindex_secret', [ $this, 'maybe_use_network_secret' ], 10, 3 );
        add_filter( 'pre_option_podcast_prospector_taddy_api_key', [ $this, 'maybe_use_network_taddy_key' ], 10, 3 );
        add_filter( 'pre_option_podcast_prospector_taddy_user_id', [ $this, 'maybe_use_network_taddy_user' ], 10, 3 );
    }

    /**
     * Add network admin menu.
     *
     * @return void
     */
    public function add_network_menu(): void {
        add_submenu_page(
            'settings.php',
            __( 'Interview Finder Network Settings', 'interview-finder' ),
            __( 'Interview Finder', 'interview-finder' ),
            'manage_network_options',
            'interview-finder-network',
            [ $this, 'render_network_settings' ]
        );
    }

    /**
     * Render network settings page.
     *
     * @return void
     */
    public function render_network_settings(): void {
        if ( ! current_user_can( 'manage_network_options' ) ) {
            wp_die( __( 'You do not have permission to access this page.', 'interview-finder' ) );
        }

        $settings = $this->get_network_settings();
        $api_keys = $this->get_network_api_keys();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Interview Finder Network Settings', 'interview-finder' ); ?></h1>

            <?php if ( isset( $_GET['updated'] ) ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e( 'Settings saved.', 'interview-finder' ); ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url( network_admin_url( 'edit.php?action=podcast_prospector_network' ) ); ?>">
                <?php wp_nonce_field( 'podcast_prospector_network_settings', 'if_network_nonce' ); ?>

                <h2><?php esc_html_e( 'Network-wide API Keys', 'interview-finder' ); ?></h2>
                <p class="description">
                    <?php esc_html_e( 'These API keys will be used across all sites in the network unless overridden at the site level.', 'interview-finder' ); ?>
                </p>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="podcastindex_key"><?php esc_html_e( 'PodcastIndex API Key', 'interview-finder' ); ?></label>
                        </th>
                        <td>
                            <input type="text" id="podcastindex_key" name="podcastindex_key"
                                   value="<?php echo esc_attr( $api_keys['podcastindex_key'] ?? '' ); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="podcastindex_secret"><?php esc_html_e( 'PodcastIndex API Secret', 'interview-finder' ); ?></label>
                        </th>
                        <td>
                            <input type="password" id="podcastindex_secret" name="podcastindex_secret"
                                   value="<?php echo esc_attr( $api_keys['podcastindex_secret'] ?? '' ); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="taddy_user_id"><?php esc_html_e( 'Taddy User ID', 'interview-finder' ); ?></label>
                        </th>
                        <td>
                            <input type="text" id="taddy_user_id" name="taddy_user_id"
                                   value="<?php echo esc_attr( $api_keys['taddy_user_id'] ?? '' ); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="taddy_api_key"><?php esc_html_e( 'Taddy API Key', 'interview-finder' ); ?></label>
                        </th>
                        <td>
                            <input type="text" id="taddy_api_key" name="taddy_api_key"
                                   value="<?php echo esc_attr( $api_keys['taddy_api_key'] ?? '' ); ?>" class="regular-text">
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e( 'Network Settings', 'interview-finder' ); ?></h2>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Use Network Keys', 'interview-finder' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="use_network_keys" value="1"
                                    <?php checked( $settings['use_network_keys'] ?? false ); ?>>
                                <?php esc_html_e( 'Force all sites to use network-wide API keys', 'interview-finder' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Allow Site Overrides', 'interview-finder' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="allow_site_overrides" value="1"
                                    <?php checked( $settings['allow_site_overrides'] ?? true ); ?>>
                                <?php esc_html_e( 'Allow individual sites to override network settings', 'interview-finder' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="default_log_level"><?php esc_html_e( 'Default Log Level', 'interview-finder' ); ?></label>
                        </th>
                        <td>
                            <select id="default_log_level" name="default_log_level">
                                <option value="none" <?php selected( $settings['default_log_level'] ?? 'warning', 'none' ); ?>>
                                    <?php esc_html_e( 'None', 'interview-finder' ); ?>
                                </option>
                                <option value="error" <?php selected( $settings['default_log_level'] ?? 'warning', 'error' ); ?>>
                                    <?php esc_html_e( 'Error', 'interview-finder' ); ?>
                                </option>
                                <option value="warning" <?php selected( $settings['default_log_level'] ?? 'warning', 'warning' ); ?>>
                                    <?php esc_html_e( 'Warning', 'interview-finder' ); ?>
                                </option>
                                <option value="info" <?php selected( $settings['default_log_level'] ?? 'warning', 'info' ); ?>>
                                    <?php esc_html_e( 'Info', 'interview-finder' ); ?>
                                </option>
                                <option value="debug" <?php selected( $settings['default_log_level'] ?? 'warning', 'debug' ); ?>>
                                    <?php esc_html_e( 'Debug', 'interview-finder' ); ?>
                                </option>
                            </select>
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e( 'Site Statistics', 'interview-finder' ); ?></h2>
                <?php $this->render_network_stats(); ?>

                <?php submit_button( __( 'Save Network Settings', 'interview-finder' ) ); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Save network settings.
     *
     * @return void
     */
    public function save_network_settings(): void {
        check_admin_referer( 'podcast_prospector_network_settings', 'if_network_nonce' );

        if ( ! current_user_can( 'manage_network_options' ) ) {
            wp_die( __( 'You do not have permission to access this page.', 'interview-finder' ) );
        }

        // Save API keys
        $api_keys = [
            'podcastindex_key'    => sanitize_text_field( $_POST['podcastindex_key'] ?? '' ),
            'podcastindex_secret' => sanitize_text_field( $_POST['podcastindex_secret'] ?? '' ),
            'taddy_user_id'       => sanitize_text_field( $_POST['taddy_user_id'] ?? '' ),
            'taddy_api_key'       => sanitize_text_field( $_POST['taddy_api_key'] ?? '' ),
        ];
        update_site_option( 'podcast_prospector_network_api_keys', $api_keys );

        // Save settings
        $settings = [
            'use_network_keys'     => ! empty( $_POST['use_network_keys'] ),
            'allow_site_overrides' => ! empty( $_POST['allow_site_overrides'] ),
            'default_log_level'    => sanitize_text_field( $_POST['default_log_level'] ?? 'warning' ),
        ];
        update_site_option( 'podcast_prospector_network_settings', $settings );

        wp_redirect( add_query_arg( [
            'page'    => 'interview-finder-network',
            'updated' => 'true',
        ], network_admin_url( 'settings.php' ) ) );
        exit;
    }

    /**
     * Render network statistics.
     *
     * @return void
     */
    private function render_network_stats(): void {
        $sites = get_sites( [ 'number' => 100 ] );
        $stats = [];

        foreach ( $sites as $site ) {
            switch_to_blog( $site->blog_id );

            global $wpdb;
            $table = $wpdb->prefix . 'podcast_prospector_users';
            $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table;

            $site_stats = [
                'blog_id'         => $site->blog_id,
                'name'            => get_bloginfo( 'name' ),
                'url'             => get_site_url(),
                'total_searches'  => 0,
                'active_users'    => 0,
            ];

            if ( $table_exists ) {
                $site_stats['total_searches'] = $wpdb->get_var( "SELECT SUM(total_searches) FROM {$table}" ) ?: 0;
                $site_stats['active_users'] = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(DISTINCT user_id) FROM {$table} WHERE last_searched >= %s",
                        gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) )
                    )
                ) ?: 0;
            }

            $stats[] = $site_stats;

            restore_current_blog();
        }

        if ( empty( $stats ) ) {
            echo '<p>' . esc_html__( 'No sites found.', 'interview-finder' ) . '</p>';
            return;
        }
        ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Site', 'interview-finder' ); ?></th>
                    <th><?php esc_html_e( 'Total Searches', 'interview-finder' ); ?></th>
                    <th><?php esc_html_e( 'Active Users (30 days)', 'interview-finder' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $stats as $site_stat ) : ?>
                <tr>
                    <td>
                        <a href="<?php echo esc_url( $site_stat['url'] ); ?>" target="_blank">
                            <?php echo esc_html( $site_stat['name'] ); ?>
                        </a>
                    </td>
                    <td><?php echo esc_html( number_format_i18n( $site_stat['total_searches'] ) ); ?></td>
                    <td><?php echo esc_html( number_format_i18n( $site_stat['active_users'] ) ); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th><?php esc_html_e( 'Total', 'interview-finder' ); ?></th>
                    <th><?php echo esc_html( number_format_i18n( array_sum( array_column( $stats, 'total_searches' ) ) ) ); ?></th>
                    <th><?php echo esc_html( number_format_i18n( array_sum( array_column( $stats, 'active_users' ) ) ) ); ?></th>
                </tr>
            </tfoot>
        </table>
        <?php
    }

    /**
     * Get network settings.
     *
     * @return array
     */
    public function get_network_settings(): array {
        $defaults = [
            'use_network_keys'     => false,
            'allow_site_overrides' => true,
            'default_log_level'    => 'warning',
        ];

        return wp_parse_args( get_site_option( 'podcast_prospector_network_settings', [] ), $defaults );
    }

    /**
     * Get network API keys.
     *
     * @return array
     */
    public function get_network_api_keys(): array {
        return get_site_option( 'podcast_prospector_network_api_keys', [] );
    }

    /**
     * Filter: Maybe use network PodcastIndex key.
     *
     * @param mixed  $pre_option Pre-filter value.
     * @param string $option     Option name.
     * @param mixed  $default    Default value.
     * @return mixed
     */
    public function maybe_use_network_key( $pre_option, $option, $default ) {
        return $this->maybe_use_network_value( 'podcastindex_key', $pre_option, $default );
    }

    /**
     * Filter: Maybe use network PodcastIndex secret.
     *
     * @param mixed  $pre_option Pre-filter value.
     * @param string $option     Option name.
     * @param mixed  $default    Default value.
     * @return mixed
     */
    public function maybe_use_network_secret( $pre_option, $option, $default ) {
        return $this->maybe_use_network_value( 'podcastindex_secret', $pre_option, $default );
    }

    /**
     * Filter: Maybe use network Taddy API key.
     *
     * @param mixed  $pre_option Pre-filter value.
     * @param string $option     Option name.
     * @param mixed  $default    Default value.
     * @return mixed
     */
    public function maybe_use_network_taddy_key( $pre_option, $option, $default ) {
        return $this->maybe_use_network_value( 'taddy_api_key', $pre_option, $default );
    }

    /**
     * Filter: Maybe use network Taddy user ID.
     *
     * @param mixed  $pre_option Pre-filter value.
     * @param string $option     Option name.
     * @param mixed  $default    Default value.
     * @return mixed
     */
    public function maybe_use_network_taddy_user( $pre_option, $option, $default ) {
        return $this->maybe_use_network_value( 'taddy_user_id', $pre_option, $default );
    }

    /**
     * Maybe use network value for an option.
     *
     * @param string $key        API key name.
     * @param mixed  $pre_option Pre-filter value.
     * @param mixed  $default    Default value.
     * @return mixed
     */
    private function maybe_use_network_value( string $key, $pre_option, $default ) {
        if ( ! self::is_multisite() ) {
            return $pre_option;
        }

        $settings = $this->get_network_settings();
        $api_keys = $this->get_network_api_keys();

        // If forcing network keys, use them
        if ( ! empty( $settings['use_network_keys'] ) && ! empty( $api_keys[ $key ] ) ) {
            return $api_keys[ $key ];
        }

        // If allowing overrides, let site option through
        if ( ! empty( $settings['allow_site_overrides'] ) ) {
            return $pre_option;
        }

        // Fall back to network key if available
        if ( ! empty( $api_keys[ $key ] ) ) {
            return $api_keys[ $key ];
        }

        return $pre_option;
    }

    /**
     * Handle new site creation.
     *
     * @param WP_Site $new_site New site object.
     * @param array   $args     Site creation arguments.
     * @return void
     */
    public function on_site_created( WP_Site $new_site, array $args ): void {
        if ( ! $this->is_network_activated() ) {
            return;
        }

        switch_to_blog( $new_site->blog_id );

        // Run activation for the new site
        if ( class_exists( 'Podcast_Prospector_Database' ) ) {
            Podcast_Prospector_Database::get_instance()->create_tables();
        }

        // Set default options from network
        $settings = $this->get_network_settings();
        update_option( 'podcast_prospector_log_level', $settings['default_log_level'] );

        restore_current_blog();
    }

    /**
     * Handle site deletion.
     *
     * @param WP_Site $old_site Deleted site object.
     * @return void
     */
    public function on_site_deleted( WP_Site $old_site ): void {
        switch_to_blog( $old_site->blog_id );

        // Clean up plugin data
        global $wpdb;
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}podcast_prospector_users" );

        // Delete options
        delete_option( 'podcast_prospector_podcastindex_key' );
        delete_option( 'podcast_prospector_podcastindex_secret' );
        delete_option( 'podcast_prospector_taddy_api_key' );
        delete_option( 'podcast_prospector_taddy_user_id' );
        delete_option( 'podcast_prospector_form_id' );
        delete_option( 'podcast_prospector_log_level' );

        restore_current_blog();
    }

    /**
     * Network activate plugin on all sites.
     *
     * @return void
     */
    public function network_activate(): void {
        if ( ! self::is_multisite() ) {
            return;
        }

        $sites = get_sites( [ 'number' => 0 ] );

        foreach ( $sites as $site ) {
            switch_to_blog( $site->blog_id );

            if ( class_exists( 'Podcast_Prospector_Database' ) ) {
                Podcast_Prospector_Database::get_instance()->create_tables();
            }

            restore_current_blog();
        }
    }
}
