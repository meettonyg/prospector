<?php
/**
 * Interview Finder Settings Class
 *
 * Handles plugin settings, admin page, and secure credential storage.
 *
 * @package Podcast_Prospector
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Podcast_Prospector_Settings
 *
 * Manages all plugin settings including API credentials, page IDs, and form field mappings.
 */
class Podcast_Prospector_Settings {

    /**
     * Option name for storing plugin settings.
     *
     * @var string
     */
    const OPTION_NAME = 'podcast_prospector_settings';

    /**
     * Settings page slug.
     *
     * @var string
     */
    const PAGE_SLUG = 'podcast-prospector-settings';

    /**
     * Singleton instance.
     *
     * @var Podcast_Prospector_Settings|null
     */
    private static ?Podcast_Prospector_Settings $instance = null;

    /**
     * Cached settings array.
     *
     * @var array|null
     */
    private ?array $settings = null;

    /**
     * Default settings configuration.
     *
     * @var array
     */
    private array $defaults = [
        // API Credentials
        'podcastindex_api_key'    => '',
        'podcastindex_api_secret' => '',
        'taddy_api_key'           => '',
        'taddy_user_id'           => '',

        // Page Configuration
        'search_page_id'          => 0,

        // Debug Settings
        'debug_logging_enabled'   => false,
        'log_level'               => 'error', // error, warning, info, debug

        // Location Features
        'location_features_enabled' => false,
        'location_table_name'       => 'pit_podcasts', // Custom table name without prefix

        // YouTube Features
        'youtube_features_enabled'  => false,
        'youtube_api_key'           => '',
    ];

    /**
     * Get singleton instance.
     *
     * @return Podcast_Prospector_Settings
     */
    public static function get_instance(): Podcast_Prospector_Settings {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor for singleton pattern.
     */
    private function __construct() {
        $this->load_settings();
    }

    /**
     * Initialize hooks for admin page.
     *
     * @return void
     */
    public function init(): void {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    /**
     * Load settings from database.
     *
     * @return void
     */
    private function load_settings(): void {
        $saved_settings = get_option( self::OPTION_NAME, [] );
        $this->settings = wp_parse_args( $saved_settings, $this->defaults );
    }

    /**
     * Get a specific setting value.
     *
     * @param string $key     Setting key.
     * @param mixed  $default Default value if not found.
     * @return mixed
     */
    public function get( string $key, $default = null ) {
        if ( isset( $this->settings[ $key ] ) ) {
            return $this->settings[ $key ];
        }
        return $default ?? ( $this->defaults[ $key ] ?? null );
    }

    /**
     * Get all settings.
     *
     * @return array
     */
    public function get_all(): array {
        return $this->settings;
    }

    /**
     * Update a specific setting.
     *
     * @param string $key   Setting key.
     * @param mixed  $value Setting value.
     * @return bool
     */
    public function update( string $key, $value ): bool {
        $this->settings[ $key ] = $value;
        return update_option( self::OPTION_NAME, $this->settings );
    }

    /**
     * Get PodcastIndex API credentials.
     *
     * @return array{api_key: string, api_secret: string}
     */
    public function get_podcastindex_credentials(): array {
        return [
            'api_key'    => $this->get( 'podcastindex_api_key' ),
            'api_secret' => $this->get( 'podcastindex_api_secret' ),
        ];
    }

    /**
     * Get Taddy API credentials.
     *
     * @return array{api_key: string, user_id: string}
     */
    public function get_taddy_credentials(): array {
        return [
            'api_key' => $this->get( 'taddy_api_key' ),
            'user_id' => $this->get( 'taddy_user_id' ),
        ];
    }

    /**
     * Check if we should load assets on current page.
     *
     * Checks if current page is the configured search page OR contains the shortcode.
     *
     * @return bool
     */
    public function is_search_page(): bool {
        // Check if explicitly configured search page
        $page_id = (int) $this->get( 'search_page_id' );
        if ( $page_id > 0 && is_page( $page_id ) ) {
            return true;
        }

        // Check if current post/page contains the shortcode
        global $post;
        if ( $post instanceof WP_Post && has_shortcode( $post->post_content, 'podcast_prospector' ) ) {
            return true;
        }

        return false;
    }

    /**
     * Add admin menu page.
     *
     * @return void
     */
    public function add_admin_menu(): void {
        add_options_page(
            __( 'Interview Finder Settings', 'podcast-prospector' ),
            __( 'Interview Finder', 'podcast-prospector' ),
            'manage_options',
            self::PAGE_SLUG,
            [ $this, 'render_settings_page' ]
        );
    }

    /**
     * Register settings and fields.
     *
     * @return void
     */
    public function register_settings(): void {
        register_setting(
            'podcast_prospector_settings_group',
            self::OPTION_NAME,
            [ $this, 'sanitize_settings' ]
        );

        // API Credentials Section
        add_settings_section(
            'api_credentials_section',
            __( 'API Credentials', 'podcast-prospector' ),
            [ $this, 'render_api_section_description' ],
            self::PAGE_SLUG
        );

        $this->add_settings_field( 'podcastindex_api_key', __( 'PodcastIndex API Key', 'podcast-prospector' ), 'api_credentials_section' );
        $this->add_settings_field( 'podcastindex_api_secret', __( 'PodcastIndex API Secret', 'podcast-prospector' ), 'api_credentials_section', 'password' );
        $this->add_settings_field( 'taddy_api_key', __( 'Taddy API Key', 'podcast-prospector' ), 'api_credentials_section', 'password' );
        $this->add_settings_field( 'taddy_user_id', __( 'Taddy User ID', 'podcast-prospector' ), 'api_credentials_section' );

        // Page Configuration Section
        add_settings_section(
            'page_config_section',
            __( 'Page Configuration', 'podcast-prospector' ),
            [ $this, 'render_page_section_description' ],
            self::PAGE_SLUG
        );

        $this->add_settings_field( 'search_page_id', __( 'Search Page ID', 'podcast-prospector' ), 'page_config_section', 'number' );

        // Debug Settings Section
        add_settings_section(
            'debug_section',
            __( 'Debug Settings', 'podcast-prospector' ),
            [ $this, 'render_debug_section_description' ],
            self::PAGE_SLUG
        );

        $this->add_settings_field( 'debug_logging_enabled', __( 'Enable Debug Logging', 'podcast-prospector' ), 'debug_section', 'checkbox' );
        $this->add_settings_field( 'log_level', __( 'Log Level', 'podcast-prospector' ), 'debug_section', 'select', [
            'options' => [
                'error'   => __( 'Errors Only', 'podcast-prospector' ),
                'warning' => __( 'Warnings & Errors', 'podcast-prospector' ),
                'info'    => __( 'Info, Warnings & Errors', 'podcast-prospector' ),
                'debug'   => __( 'All (Debug)', 'podcast-prospector' ),
            ],
        ] );

        // Location Features Section
        add_settings_section(
            'location_section',
            __( 'Location Features', 'podcast-prospector' ),
            [ $this, 'render_location_section_description' ],
            self::PAGE_SLUG
        );

        $this->add_settings_field( 'location_features_enabled', __( 'Enable Location Features', 'podcast-prospector' ), 'location_section', 'checkbox' );
        $this->add_settings_field( 'location_table_name', __( 'Location Table Name', 'podcast-prospector' ), 'location_section', 'text' );

        // YouTube Features Section
        add_settings_section(
            'youtube_section',
            __( 'YouTube Features', 'podcast-prospector' ),
            [ $this, 'render_youtube_section_description' ],
            self::PAGE_SLUG
        );

        $this->add_settings_field( 'youtube_features_enabled', __( 'Enable YouTube Search', 'podcast-prospector' ), 'youtube_section', 'checkbox' );
        $this->add_settings_field( 'youtube_api_key', __( 'YouTube API Key', 'podcast-prospector' ), 'youtube_section', 'password' );
    }

    /**
     * Add a settings field helper.
     *
     * @param string $key     Field key.
     * @param string $label   Field label.
     * @param string $section Section ID.
     * @param string $type    Field type.
     * @param array  $args    Additional arguments.
     * @return void
     */
    private function add_settings_field( string $key, string $label, string $section, string $type = 'text', array $args = [] ): void {
        add_settings_field(
            $key,
            $label,
            [ $this, 'render_field' ],
            self::PAGE_SLUG,
            $section,
            array_merge( [
                'key'  => $key,
                'type' => $type,
            ], $args )
        );
    }

    /**
     * Render section descriptions.
     *
     * @return void
     */
    public function render_api_section_description(): void {
        echo '<p>' . esc_html__( 'Enter your API credentials below. These are stored securely in the WordPress database.', 'podcast-prospector' ) . '</p>';
    }

    /**
     * Render page section description.
     *
     * @return void
     */
    public function render_page_section_description(): void {
        echo '<p>' . esc_html__( 'Configure which page displays the Interview Finder search interface.', 'podcast-prospector' ) . '</p>';
    }

    /**
     * Render debug section description.
     *
     * @return void
     */
    public function render_debug_section_description(): void {
        echo '<p>' . esc_html__( 'Configure logging and debug options.', 'podcast-prospector' ) . '</p>';
    }

    /**
     * Render location section description.
     *
     * @return void
     */
    public function render_location_section_description(): void {
        echo '<p>' . esc_html__( 'Configure podcast location features. Enable this once your location database is populated.', 'podcast-prospector' ) . '</p>';

        // Show location stats if available
        if ( class_exists( 'Podcast_Prospector_Podcast_Location_Repository' ) ) {
            $container = Podcast_Prospector_Container::get_instance();
            if ( $container->has( 'podcast_location' ) ) {
                $location_repo = $container->get( 'podcast_location' );
                $stats = $location_repo->get_location_stats();

                if ( $stats['table_exists'] ) {
                    printf(
                        '<p><strong>%s</strong> %s | <strong>%s</strong> %s | <strong>%s</strong> %s</p>',
                        esc_html__( 'Podcasts with location:', 'podcast-prospector' ),
                        esc_html( number_format( $stats['with_location'] ) . ' / ' . number_format( $stats['total_podcasts'] ) ),
                        esc_html__( 'Unique cities:', 'podcast-prospector' ),
                        esc_html( number_format( $stats['unique_cities'] ) ),
                        esc_html__( 'Unique countries:', 'podcast-prospector' ),
                        esc_html( number_format( $stats['unique_countries'] ) )
                    );
                } else {
                    echo '<p class="notice notice-warning" style="padding: 10px;">' . esc_html__( 'Location table not found. Please ensure the pit_podcasts table exists.', 'podcast-prospector' ) . '</p>';
                }
            }
        }
    }

    /**
     * Render YouTube section description.
     *
     * @return void
     */
    public function render_youtube_section_description(): void {
        echo '<p>' . esc_html__( 'Configure YouTube video search. Requires a YouTube Data API v3 key from Google Cloud Console.', 'podcast-prospector' ) . '</p>';
        echo '<p><a href="https://console.cloud.google.com/apis/library/youtube.googleapis.com" target="_blank" rel="noopener">' . esc_html__( 'Get YouTube API Key', 'podcast-prospector' ) . '</a></p>';
        echo '<p class="description">' . esc_html__( 'Free quota: ~100 searches/day. Request increase in Google Cloud Console for more.', 'podcast-prospector' ) . '</p>';
    }

    /**
     * Render a settings field.
     *
     * @param array $args Field arguments.
     * @return void
     */
    public function render_field( array $args ): void {
        $key   = $args['key'];
        $type  = $args['type'];
        $value = $this->get( $key );
        $name  = self::OPTION_NAME . '[' . $key . ']';

        switch ( $type ) {
            case 'password':
                printf(
                    '<input type="password" id="%s" name="%s" value="%s" class="regular-text" autocomplete="off">',
                    esc_attr( $key ),
                    esc_attr( $name ),
                    esc_attr( $value )
                );
                break;

            case 'number':
                printf(
                    '<input type="number" id="%s" name="%s" value="%s" class="small-text">',
                    esc_attr( $key ),
                    esc_attr( $name ),
                    esc_attr( $value )
                );
                break;

            case 'checkbox':
                printf(
                    '<input type="checkbox" id="%s" name="%s" value="1" %s>',
                    esc_attr( $key ),
                    esc_attr( $name ),
                    checked( $value, true, false )
                );
                break;

            case 'select':
                printf( '<select id="%s" name="%s">', esc_attr( $key ), esc_attr( $name ) );
                foreach ( $args['options'] as $option_value => $option_label ) {
                    printf(
                        '<option value="%s" %s>%s</option>',
                        esc_attr( $option_value ),
                        selected( $value, $option_value, false ),
                        esc_html( $option_label )
                    );
                }
                echo '</select>';
                break;

            default:
                printf(
                    '<input type="text" id="%s" name="%s" value="%s" class="regular-text">',
                    esc_attr( $key ),
                    esc_attr( $name ),
                    esc_attr( $value )
                );
                break;
        }
    }

    /**
     * Sanitize settings before saving.
     *
     * @param array $input Raw input data.
     * @return array Sanitized data.
     */
    public function sanitize_settings( array $input ): array {
        $sanitized = [];

        // Text fields
        $text_fields = [ 'podcastindex_api_key', 'podcastindex_api_secret', 'taddy_api_key', 'taddy_user_id' ];
        foreach ( $text_fields as $field ) {
            $sanitized[ $field ] = isset( $input[ $field ] ) ? sanitize_text_field( $input[ $field ] ) : '';
        }

        // Number fields
        $number_fields = [ 'search_page_id' ];
        foreach ( $number_fields as $field ) {
            $sanitized[ $field ] = isset( $input[ $field ] ) ? absint( $input[ $field ] ) : 0;
        }

        // Boolean fields
        $sanitized['debug_logging_enabled'] = ! empty( $input['debug_logging_enabled'] );
        $sanitized['location_features_enabled'] = ! empty( $input['location_features_enabled'] );
        $sanitized['youtube_features_enabled'] = ! empty( $input['youtube_features_enabled'] );

        // Select fields
        $valid_log_levels = [ 'error', 'warning', 'info', 'debug' ];
        $sanitized['log_level'] = isset( $input['log_level'] ) && in_array( $input['log_level'], $valid_log_levels, true )
            ? $input['log_level']
            : 'error';

        // Location table name (sanitize as table name - alphanumeric and underscores only)
        $sanitized['location_table_name'] = isset( $input['location_table_name'] )
            ? preg_replace( '/[^a-zA-Z0-9_]/', '', $input['location_table_name'] )
            : 'pit_podcasts';

        // YouTube API key
        $sanitized['youtube_api_key'] = isset( $input['youtube_api_key'] )
            ? sanitize_text_field( $input['youtube_api_key'] )
            : '';

        return $sanitized;
    }

    /**
     * Render the settings page.
     *
     * @return void
     */
    public function render_settings_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields( 'podcast_prospector_settings_group' );
                do_settings_sections( self::PAGE_SLUG );
                submit_button( __( 'Save Settings', 'podcast-prospector' ) );
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Migration helper: Import legacy hardcoded values.
     *
     * @param array $legacy_values Array of legacy values to import.
     * @return bool
     */
    public function migrate_legacy_settings( array $legacy_values ): bool {
        $current = $this->get_all();
        $updated = false;

        foreach ( $legacy_values as $key => $value ) {
            if ( isset( $this->defaults[ $key ] ) && empty( $current[ $key ] ) && ! empty( $value ) ) {
                $current[ $key ] = $value;
                $updated = true;
            }
        }

        if ( $updated ) {
            return update_option( self::OPTION_NAME, $current );
        }

        return false;
    }

    /**
     * Get form field mapping (deprecated - kept for backward compatibility).
     *
     * @deprecated 2.2.0 Formidable integration removed
     * @return array
     */
    public function get_field_map(): array {
        return [];
    }
}
