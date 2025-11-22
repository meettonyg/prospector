<?php
/**
 * Interview Finder Settings Class
 *
 * Handles plugin settings, admin page, and secure credential storage.
 *
 * @package Interview_Finder
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Interview_Finder_Settings
 *
 * Manages all plugin settings including API credentials, page IDs, and form field mappings.
 */
class Interview_Finder_Settings {

    /**
     * Option name for storing plugin settings.
     *
     * @var string
     */
    const OPTION_NAME = 'interview_finder_settings';

    /**
     * Settings page slug.
     *
     * @var string
     */
    const PAGE_SLUG = 'interview-finder-settings';

    /**
     * Singleton instance.
     *
     * @var Interview_Finder_Settings|null
     */
    private static ?Interview_Finder_Settings $instance = null;

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

        // Form Configuration (Formidable Forms)
        'form_id'                 => 518,
        'field_podcast_title'     => 8111,
        'field_feed_url'          => 9928,
        'field_itunes_id'         => 9929,
        'field_podcastindex_id'   => 9930,
        'field_podcast_guid'      => 9931,
        'field_original_search'   => 9932,
        'field_search_type'       => 9948,
        'field_status'            => 8113,
        'field_assigned_user'     => 8240,
        'field_episode_guid'      => 10392,
        'field_episode_title'     => 10393,
        'field_archive'           => 10402,

        // Debug Settings
        'debug_logging_enabled'   => false,
        'log_level'               => 'error', // error, warning, info, debug
    ];

    /**
     * Get singleton instance.
     *
     * @return Interview_Finder_Settings
     */
    public static function get_instance(): Interview_Finder_Settings {
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
     * Get form field mapping.
     *
     * @return array
     */
    public function get_field_map(): array {
        return [
            'podcast_title'    => (int) $this->get( 'field_podcast_title' ),
            'feed_url'         => (int) $this->get( 'field_feed_url' ),
            'itunes_id'        => (int) $this->get( 'field_itunes_id' ),
            'podcastindex_id'  => (int) $this->get( 'field_podcastindex_id' ),
            'podcast_guid'     => (int) $this->get( 'field_podcast_guid' ),
            'original_search'  => (int) $this->get( 'field_original_search' ),
            'search_type_used' => (int) $this->get( 'field_search_type' ),
            'status'           => (int) $this->get( 'field_status' ),
            'assigned_user'    => (int) $this->get( 'field_assigned_user' ),
            'episode_guid'     => (int) $this->get( 'field_episode_guid' ),
            'episode_title'    => (int) $this->get( 'field_episode_title' ),
            'archive'          => (int) $this->get( 'field_archive' ),
        ];
    }

    /**
     * Check if we should load assets on current page.
     *
     * @return bool
     */
    public function is_search_page(): bool {
        $page_id = (int) $this->get( 'search_page_id' );
        return $page_id > 0 && is_page( $page_id );
    }

    /**
     * Add admin menu page.
     *
     * @return void
     */
    public function add_admin_menu(): void {
        add_options_page(
            __( 'Interview Finder Settings', 'interview-finder' ),
            __( 'Interview Finder', 'interview-finder' ),
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
            'interview_finder_settings_group',
            self::OPTION_NAME,
            [ $this, 'sanitize_settings' ]
        );

        // API Credentials Section
        add_settings_section(
            'api_credentials_section',
            __( 'API Credentials', 'interview-finder' ),
            [ $this, 'render_api_section_description' ],
            self::PAGE_SLUG
        );

        $this->add_settings_field( 'podcastindex_api_key', __( 'PodcastIndex API Key', 'interview-finder' ), 'api_credentials_section' );
        $this->add_settings_field( 'podcastindex_api_secret', __( 'PodcastIndex API Secret', 'interview-finder' ), 'api_credentials_section', 'password' );
        $this->add_settings_field( 'taddy_api_key', __( 'Taddy API Key', 'interview-finder' ), 'api_credentials_section', 'password' );
        $this->add_settings_field( 'taddy_user_id', __( 'Taddy User ID', 'interview-finder' ), 'api_credentials_section' );

        // Page Configuration Section
        add_settings_section(
            'page_config_section',
            __( 'Page Configuration', 'interview-finder' ),
            [ $this, 'render_page_section_description' ],
            self::PAGE_SLUG
        );

        $this->add_settings_field( 'search_page_id', __( 'Search Page ID', 'interview-finder' ), 'page_config_section', 'number' );

        // Form Configuration Section
        add_settings_section(
            'form_config_section',
            __( 'Formidable Forms Configuration', 'interview-finder' ),
            [ $this, 'render_form_section_description' ],
            self::PAGE_SLUG
        );

        $this->add_settings_field( 'form_id', __( 'Form ID', 'interview-finder' ), 'form_config_section', 'number' );
        $this->add_settings_field( 'field_podcast_title', __( 'Podcast Title Field ID', 'interview-finder' ), 'form_config_section', 'number' );
        $this->add_settings_field( 'field_feed_url', __( 'Feed URL Field ID', 'interview-finder' ), 'form_config_section', 'number' );
        $this->add_settings_field( 'field_itunes_id', __( 'iTunes ID Field ID', 'interview-finder' ), 'form_config_section', 'number' );
        $this->add_settings_field( 'field_podcastindex_id', __( 'PodcastIndex ID Field ID', 'interview-finder' ), 'form_config_section', 'number' );
        $this->add_settings_field( 'field_podcast_guid', __( 'Podcast GUID Field ID', 'interview-finder' ), 'form_config_section', 'number' );
        $this->add_settings_field( 'field_original_search', __( 'Original Search Field ID', 'interview-finder' ), 'form_config_section', 'number' );
        $this->add_settings_field( 'field_search_type', __( 'Search Type Field ID', 'interview-finder' ), 'form_config_section', 'number' );
        $this->add_settings_field( 'field_status', __( 'Status Field ID', 'interview-finder' ), 'form_config_section', 'number' );
        $this->add_settings_field( 'field_assigned_user', __( 'Assigned User Field ID', 'interview-finder' ), 'form_config_section', 'number' );
        $this->add_settings_field( 'field_episode_guid', __( 'Episode GUID Field ID', 'interview-finder' ), 'form_config_section', 'number' );
        $this->add_settings_field( 'field_episode_title', __( 'Episode Title Field ID', 'interview-finder' ), 'form_config_section', 'number' );
        $this->add_settings_field( 'field_archive', __( 'Archive Field ID', 'interview-finder' ), 'form_config_section', 'number' );

        // Debug Settings Section
        add_settings_section(
            'debug_section',
            __( 'Debug Settings', 'interview-finder' ),
            [ $this, 'render_debug_section_description' ],
            self::PAGE_SLUG
        );

        $this->add_settings_field( 'debug_logging_enabled', __( 'Enable Debug Logging', 'interview-finder' ), 'debug_section', 'checkbox' );
        $this->add_settings_field( 'log_level', __( 'Log Level', 'interview-finder' ), 'debug_section', 'select', [
            'options' => [
                'error'   => __( 'Errors Only', 'interview-finder' ),
                'warning' => __( 'Warnings & Errors', 'interview-finder' ),
                'info'    => __( 'Info, Warnings & Errors', 'interview-finder' ),
                'debug'   => __( 'All (Debug)', 'interview-finder' ),
            ],
        ] );
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
        echo '<p>' . esc_html__( 'Enter your API credentials below. These are stored securely in the WordPress database.', 'interview-finder' ) . '</p>';
    }

    /**
     * Render page section description.
     *
     * @return void
     */
    public function render_page_section_description(): void {
        echo '<p>' . esc_html__( 'Configure which page displays the Interview Finder search interface.', 'interview-finder' ) . '</p>';
    }

    /**
     * Render form section description.
     *
     * @return void
     */
    public function render_form_section_description(): void {
        echo '<p>' . esc_html__( 'Map Formidable Forms field IDs for podcast import functionality.', 'interview-finder' ) . '</p>';
    }

    /**
     * Render debug section description.
     *
     * @return void
     */
    public function render_debug_section_description(): void {
        echo '<p>' . esc_html__( 'Configure logging and debug options.', 'interview-finder' ) . '</p>';
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
        $number_fields = [
            'search_page_id', 'form_id', 'field_podcast_title', 'field_feed_url',
            'field_itunes_id', 'field_podcastindex_id', 'field_podcast_guid',
            'field_original_search', 'field_search_type', 'field_status',
            'field_assigned_user', 'field_episode_guid', 'field_episode_title', 'field_archive',
        ];
        foreach ( $number_fields as $field ) {
            $sanitized[ $field ] = isset( $input[ $field ] ) ? absint( $input[ $field ] ) : 0;
        }

        // Boolean fields
        $sanitized['debug_logging_enabled'] = ! empty( $input['debug_logging_enabled'] );

        // Select fields
        $valid_log_levels = [ 'error', 'warning', 'info', 'debug' ];
        $sanitized['log_level'] = isset( $input['log_level'] ) && in_array( $input['log_level'], $valid_log_levels, true )
            ? $input['log_level']
            : 'error';

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
                settings_fields( 'interview_finder_settings_group' );
                do_settings_sections( self::PAGE_SLUG );
                submit_button( __( 'Save Settings', 'interview-finder' ) );
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
}
