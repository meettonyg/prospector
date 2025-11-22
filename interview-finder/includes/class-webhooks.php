<?php
/**
 * Webhooks Class
 *
 * @package Interview_Finder
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Interview_Finder_Webhooks
 *
 * Handles webhook notifications for plugin events.
 */
class Interview_Finder_Webhooks {

    /**
     * Singleton instance.
     *
     * @var Interview_Finder_Webhooks|null
     */
    private static ?Interview_Finder_Webhooks $instance = null;

    /**
     * Logger instance.
     *
     * @var Interview_Finder_Logger|null
     */
    private ?Interview_Finder_Logger $logger;

    /**
     * Registered webhooks.
     *
     * @var array
     */
    private array $webhooks = [];

    /**
     * Get singleton instance.
     *
     * @return Interview_Finder_Webhooks
     */
    public static function get_instance(): Interview_Finder_Webhooks {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->logger = class_exists( 'Interview_Finder_Logger' )
            ? Interview_Finder_Logger::get_instance()
            : null;
        $this->webhooks = get_option( 'interview_finder_webhooks', [] );
    }

    /**
     * Initialize webhooks.
     *
     * @return void
     */
    public function init(): void {
        // Register webhook triggers
        add_action( 'interview_finder_search_completed', [ $this, 'on_search_completed' ], 10, 3 );
        add_action( 'interview_finder_import_completed', [ $this, 'on_import_completed' ], 10, 2 );
        add_action( 'interview_finder_search_cap_reached', [ $this, 'on_search_cap_reached' ], 10, 2 );

        // Admin settings
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    /**
     * Register webhook settings.
     *
     * @return void
     */
    public function register_settings(): void {
        register_setting(
            'interview_finder_webhooks',
            'interview_finder_webhooks',
            [ $this, 'sanitize_webhooks' ]
        );

        add_settings_section(
            'interview_finder_webhooks_section',
            __( 'Webhook Endpoints', 'interview-finder' ),
            [ $this, 'render_section_description' ],
            'interview-finder-webhooks'
        );

        add_settings_field(
            'webhook_url',
            __( 'Webhook URL', 'interview-finder' ),
            [ $this, 'render_webhook_field' ],
            'interview-finder-webhooks',
            'interview_finder_webhooks_section'
        );
    }

    /**
     * Sanitize webhooks option.
     *
     * @param array $input Input data.
     * @return array
     */
    public function sanitize_webhooks( $input ): array {
        if ( ! is_array( $input ) ) {
            return [];
        }

        $sanitized = [];

        foreach ( $input as $key => $webhook ) {
            if ( empty( $webhook['url'] ) ) {
                continue;
            }

            $sanitized[ $key ] = [
                'url'     => esc_url_raw( $webhook['url'] ),
                'events'  => array_map( 'sanitize_text_field', $webhook['events'] ?? [] ),
                'secret'  => sanitize_text_field( $webhook['secret'] ?? '' ),
                'enabled' => ! empty( $webhook['enabled'] ),
            ];
        }

        return $sanitized;
    }

    /**
     * Render section description.
     *
     * @return void
     */
    public function render_section_description(): void {
        echo '<p>' . esc_html__( 'Configure webhook endpoints to receive notifications about plugin events.', 'interview-finder' ) . '</p>';
    }

    /**
     * Render webhook field.
     *
     * @return void
     */
    public function render_webhook_field(): void {
        $webhooks = $this->webhooks;
        ?>
        <div id="if-webhooks-container">
            <?php if ( empty( $webhooks ) ) : ?>
                <p class="description"><?php esc_html_e( 'No webhooks configured.', 'interview-finder' ); ?></p>
            <?php else : ?>
                <?php foreach ( $webhooks as $index => $webhook ) : ?>
                    <div class="if-webhook-row">
                        <input type="url" name="interview_finder_webhooks[<?php echo esc_attr( $index ); ?>][url]"
                               value="<?php echo esc_url( $webhook['url'] ); ?>" class="regular-text">
                        <label>
                            <input type="checkbox" name="interview_finder_webhooks[<?php echo esc_attr( $index ); ?>][enabled]"
                                   <?php checked( $webhook['enabled'] ?? false ); ?>>
                            <?php esc_html_e( 'Enabled', 'interview-finder' ); ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <button type="button" class="button" id="if-add-webhook">
            <?php esc_html_e( 'Add Webhook', 'interview-finder' ); ?>
        </button>
        <?php
    }

    /**
     * Add a webhook.
     *
     * @param string $url     Webhook URL.
     * @param array  $events  Events to trigger.
     * @param string $secret  Webhook secret.
     * @return bool
     */
    public function add_webhook( string $url, array $events = [], string $secret = '' ): bool {
        $this->webhooks[] = [
            'url'     => esc_url_raw( $url ),
            'events'  => $events,
            'secret'  => $secret,
            'enabled' => true,
        ];

        return update_option( 'interview_finder_webhooks', $this->webhooks );
    }

    /**
     * Send webhook notification.
     *
     * @param string $event Event name.
     * @param array  $data  Event data.
     * @return void
     */
    public function send( string $event, array $data ): void {
        foreach ( $this->webhooks as $webhook ) {
            if ( empty( $webhook['enabled'] ) || empty( $webhook['url'] ) ) {
                continue;
            }

            // Check if webhook is subscribed to this event
            if ( ! empty( $webhook['events'] ) && ! in_array( $event, $webhook['events'], true ) ) {
                continue;
            }

            $this->dispatch_webhook( $webhook, $event, $data );
        }
    }

    /**
     * Dispatch webhook request.
     *
     * @param array  $webhook Webhook configuration.
     * @param string $event   Event name.
     * @param array  $data    Event data.
     * @return void
     */
    private function dispatch_webhook( array $webhook, string $event, array $data ): void {
        $payload = [
            'event'     => $event,
            'timestamp' => current_time( 'c' ),
            'site_url'  => get_site_url(),
            'data'      => $data,
        ];

        $body = wp_json_encode( $payload );
        $signature = ! empty( $webhook['secret'] )
            ? hash_hmac( 'sha256', $body, $webhook['secret'] )
            : '';

        $args = [
            'body'        => $body,
            'headers'     => [
                'Content-Type'      => 'application/json',
                'X-IF-Event'        => $event,
                'X-IF-Signature'    => $signature,
                'X-IF-Delivery-ID'  => wp_generate_uuid4(),
            ],
            'timeout'     => 15,
            'blocking'    => false, // Non-blocking for performance
            'data_format' => 'body',
        ];

        $response = wp_remote_post( $webhook['url'], $args );

        if ( is_wp_error( $response ) ) {
            $this->log_error( 'Webhook delivery failed', [
                'url'   => $webhook['url'],
                'event' => $event,
                'error' => $response->get_error_message(),
            ] );
        }
    }

    /**
     * Handler: Search completed.
     *
     * @param string $search_term Search term.
     * @param int    $result_count Result count.
     * @param int    $user_id User ID.
     * @return void
     */
    public function on_search_completed( string $search_term, int $result_count, int $user_id ): void {
        $this->send( 'search.completed', [
            'search_term'  => $search_term,
            'result_count' => $result_count,
            'user_id'      => $user_id,
        ] );
    }

    /**
     * Handler: Import completed.
     *
     * @param int   $import_count Import count.
     * @param array $details      Import details.
     * @return void
     */
    public function on_import_completed( int $import_count, array $details ): void {
        $this->send( 'import.completed', [
            'import_count' => $import_count,
            'details'      => $details,
        ] );
    }

    /**
     * Handler: Search cap reached.
     *
     * @param int    $user_id User ID.
     * @param string $tier    Membership tier.
     * @return void
     */
    public function on_search_cap_reached( int $user_id, string $tier ): void {
        $this->send( 'search_cap.reached', [
            'user_id' => $user_id,
            'tier'    => $tier,
        ] );
    }

    /**
     * Log error.
     *
     * @param string $message Message.
     * @param array  $context Context.
     * @return void
     */
    private function log_error( string $message, array $context = [] ): void {
        if ( $this->logger ) {
            $this->logger->error( '[Webhooks] ' . $message, $context );
        }
    }
}

/**
 * Class Interview_Finder_Settings_Export
 *
 * Handles settings export and import.
 */
class Interview_Finder_Settings_Export {

    /**
     * Option keys to export.
     *
     * @var array
     */
    private const EXPORTABLE_OPTIONS = [
        'interview_finder_podcastindex_key',
        'interview_finder_podcastindex_secret',
        'interview_finder_taddy_user_id',
        'interview_finder_taddy_api_key',
        'interview_finder_form_id',
        'interview_finder_log_level',
        'interview_finder_cache_duration',
        'interview_finder_webhooks',
    ];

    /**
     * Export settings.
     *
     * @param bool $include_credentials Whether to include API credentials.
     * @return array
     */
    public function export( bool $include_credentials = false ): array {
        $export = [
            'version'    => INTERVIEW_FINDER_VERSION ?? '2.1.0',
            'exported'   => current_time( 'c' ),
            'site_url'   => get_site_url(),
            'settings'   => [],
        ];

        $skip_credentials = [
            'interview_finder_podcastindex_key',
            'interview_finder_podcastindex_secret',
            'interview_finder_taddy_user_id',
            'interview_finder_taddy_api_key',
        ];

        foreach ( self::EXPORTABLE_OPTIONS as $option ) {
            if ( ! $include_credentials && in_array( $option, $skip_credentials, true ) ) {
                continue;
            }

            $value = get_option( $option );
            if ( false !== $value ) {
                $export['settings'][ $option ] = $value;
            }
        }

        /**
         * Filter exported settings.
         *
         * @param array $export Export data.
         */
        return apply_filters( 'interview_finder_export_settings', $export );
    }

    /**
     * Import settings.
     *
     * @param array $data   Import data.
     * @param bool  $merge  Whether to merge with existing settings.
     * @return array Result with success/error counts.
     */
    public function import( array $data, bool $merge = true ): array {
        $result = [
            'success' => 0,
            'failed'  => 0,
            'skipped' => 0,
            'errors'  => [],
        ];

        if ( empty( $data['settings'] ) || ! is_array( $data['settings'] ) ) {
            $result['errors'][] = __( 'Invalid import data format.', 'interview-finder' );
            return $result;
        }

        foreach ( $data['settings'] as $option => $value ) {
            // Only import known options
            if ( ! in_array( $option, self::EXPORTABLE_OPTIONS, true ) ) {
                $result['skipped']++;
                continue;
            }

            // Skip if merge mode and option already exists
            if ( $merge && false !== get_option( $option ) ) {
                $result['skipped']++;
                continue;
            }

            if ( update_option( $option, $value ) ) {
                $result['success']++;
            } else {
                $result['failed']++;
            }
        }

        /**
         * Fires after settings import.
         *
         * @param array $result Import result.
         * @param array $data   Import data.
         */
        do_action( 'interview_finder_settings_imported', $result, $data );

        return $result;
    }

    /**
     * Export to JSON file.
     *
     * @param bool $include_credentials Whether to include credentials.
     * @return void
     */
    public function download_export( bool $include_credentials = false ): void {
        $data = $this->export( $include_credentials );
        $filename = 'interview-finder-settings-' . gmdate( 'Y-m-d' ) . '.json';

        header( 'Content-Type: application/json' );
        header( 'Content-Disposition: attachment; filename=' . $filename );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        echo wp_json_encode( $data, JSON_PRETTY_PRINT );
        exit;
    }

    /**
     * Import from uploaded file.
     *
     * @param array $file Uploaded file array ($_FILES).
     * @param bool  $merge Whether to merge settings.
     * @return array Import result.
     */
    public function import_from_file( array $file, bool $merge = true ): array {
        if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
            return [
                'success' => 0,
                'failed'  => 0,
                'errors'  => [ __( 'No file uploaded.', 'interview-finder' ) ],
            ];
        }

        $content = file_get_contents( $file['tmp_name'] );
        $data = json_decode( $content, true );

        if ( null === $data ) {
            return [
                'success' => 0,
                'failed'  => 0,
                'errors'  => [ __( 'Invalid JSON file.', 'interview-finder' ) ],
            ];
        }

        return $this->import( $data, $merge );
    }
}
