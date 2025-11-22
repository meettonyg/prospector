<?php
/**
 * PodcastIndex API Class
 *
 * Handles all interactions with the PodcastIndex API.
 *
 * @package Interview_Finder
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Interview_Finder_API_PodcastIndex
 *
 * Provides methods to search podcasts and episodes via PodcastIndex API.
 */
class Interview_Finder_API_PodcastIndex {

    /**
     * API base URL.
     *
     * @var string
     */
    const API_BASE_URL = 'https://api.podcastindex.org/api/1.0';

    /**
     * Singleton instance.
     *
     * @var Interview_Finder_API_PodcastIndex|null
     */
    private static ?Interview_Finder_API_PodcastIndex $instance = null;

    /**
     * API Key.
     *
     * @var string
     */
    private string $api_key;

    /**
     * API Secret.
     *
     * @var string
     */
    private string $api_secret;

    /**
     * Logger instance.
     *
     * @var Interview_Finder_Logger|null
     */
    private ?Interview_Finder_Logger $logger = null;

    /**
     * Get singleton instance.
     *
     * @return Interview_Finder_API_PodcastIndex
     */
    public static function get_instance(): Interview_Finder_API_PodcastIndex {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor.
     */
    private function __construct() {
        $this->load_credentials();

        if ( class_exists( 'Interview_Finder_Logger' ) ) {
            $this->logger = Interview_Finder_Logger::get_instance();
        }
    }

    /**
     * Load API credentials from settings.
     *
     * @return void
     */
    private function load_credentials(): void {
        if ( class_exists( 'Interview_Finder_Settings' ) ) {
            $settings = Interview_Finder_Settings::get_instance();
            $credentials = $settings->get_podcastindex_credentials();
            $this->api_key = $credentials['api_key'] ?? '';
            $this->api_secret = $credentials['api_secret'] ?? '';
        } else {
            $this->api_key = '';
            $this->api_secret = '';
        }
    }

    /**
     * Check if API is configured.
     *
     * @return bool
     */
    public function is_configured(): bool {
        return ! empty( $this->api_key ) && ! empty( $this->api_secret );
    }

    /**
     * Generate authentication headers.
     *
     * @return array
     */
    private function get_auth_headers(): array {
        $auth_time = time();
        $hash = sha1( $this->api_key . $this->api_secret . $auth_time );

        return [
            'User-Agent'    => 'InterviewFinder/2.0',
            'X-Auth-Key'    => $this->api_key,
            'X-Auth-Date'   => (string) $auth_time,
            'Authorization' => $hash,
        ];
    }

    /**
     * Make API request using WordPress HTTP API.
     *
     * @param string $endpoint API endpoint.
     * @param array  $params   Query parameters.
     * @return array|WP_Error
     */
    private function request( string $endpoint, array $params = [] ) {
        if ( ! $this->is_configured() ) {
            $this->log_error( 'API not configured - missing credentials' );
            return new WP_Error( 'api_not_configured', __( 'PodcastIndex API credentials not configured.', 'interview-finder' ) );
        }

        $url = self::API_BASE_URL . $endpoint;
        if ( ! empty( $params ) ) {
            $url = add_query_arg( $params, $url );
        }

        $this->log_debug( 'Making API request', [
            'endpoint' => $endpoint,
            'params'   => $params,
        ] );

        $response = wp_remote_get( $url, [
            'timeout' => 30,
            'headers' => $this->get_auth_headers(),
        ] );

        if ( is_wp_error( $response ) ) {
            $this->log_error( 'API request failed', [
                'error' => $response->get_error_message(),
            ] );
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( $status_code !== 200 ) {
            $this->log_error( 'API returned non-200 status', [
                'status_code' => $status_code,
                'body'        => $body,
            ] );
            return new WP_Error(
                'api_error',
                sprintf( __( 'API returned status %d', 'interview-finder' ), $status_code ),
                [ 'status_code' => $status_code ]
            );
        }

        if ( null === $data ) {
            $this->log_error( 'Failed to parse API response' );
            return new WP_Error( 'parse_error', __( 'Failed to parse API response.', 'interview-finder' ) );
        }

        $this->log_debug( 'API request successful', [
            'endpoint'     => $endpoint,
            'result_count' => isset( $data['items'] ) ? count( $data['items'] ) : ( isset( $data['feeds'] ) ? count( $data['feeds'] ) : 0 ),
        ] );

        return $data;
    }

    /**
     * Search episodes by person name.
     *
     * @param string $search_term Person name to search.
     * @param int    $max_results Maximum results to return.
     * @return array|WP_Error
     */
    public function search_by_person( string $search_term, int $max_results = 33 ) {
        $params = [
            'q'      => sanitize_text_field( $search_term ),
            'pretty' => 'true',
            'max'    => min( $max_results, 100 ), // API max is 100
        ];

        $response = $this->request( '/search/byperson', $params );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        // Process and normalize results
        if ( isset( $response['items'] ) && is_array( $response['items'] ) ) {
            $response['items'] = array_slice( $response['items'], 0, $max_results );
            $response['items'] = array_map( [ $this, 'normalize_episode_result' ], $response['items'] );
        }

        return $response;
    }

    /**
     * Search podcasts by title/term.
     *
     * @param string $search_term Search term.
     * @param int    $max_results Maximum results to return.
     * @return array|WP_Error
     */
    public function search_by_term( string $search_term, int $max_results = 33 ) {
        $params = [
            'q'      => sanitize_text_field( $search_term ),
            'pretty' => 'true',
            'max'    => min( $max_results, 100 ),
        ];

        $response = $this->request( '/search/byterm', $params );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        // Normalize feeds array
        if ( isset( $response['feeds'] ) && is_array( $response['feeds'] ) ) {
            $response['feeds'] = array_slice( $response['feeds'], 0, $max_results );
        }

        return $response;
    }

    /**
     * Normalize episode result data.
     *
     * @param array $item Raw episode data.
     * @return array Normalized data.
     */
    private function normalize_episode_result( array $item ): array {
        // Add convenience fields for consistent access
        $item['episodeTitle'] = $item['title'] ?? 'No title available';
        $item['episodeDescription'] = $item['description'] ?? 'No description available';
        $item['podcastName'] = $item['feedTitle'] ?? 'No podcast name available';

        return $item;
    }

    /**
     * Log debug message.
     *
     * @param string $message Log message.
     * @param array  $context Additional context.
     * @return void
     */
    private function log_debug( string $message, array $context = [] ): void {
        if ( $this->logger ) {
            $this->logger->debug( '[PodcastIndex API] ' . $message, $context );
        }
    }

    /**
     * Log error message.
     *
     * @param string $message Log message.
     * @param array  $context Additional context.
     * @return void
     */
    private function log_error( string $message, array $context = [] ): void {
        if ( $this->logger ) {
            $this->logger->error( '[PodcastIndex API] ' . $message, $context );
        }
    }
}
