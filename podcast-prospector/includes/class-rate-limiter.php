<?php
/**
 * Rate Limiter Class
 *
 * @package Podcast_Prospector
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Podcast_Prospector_Rate_Limiter
 *
 * Manages rate limiting for external API calls.
 */
class Podcast_Prospector_Rate_Limiter {

    /**
     * Transient prefix.
     *
     * @var string
     */
    private const TRANSIENT_PREFIX = 'if_rate_';

    /**
     * Rate limit configurations per API.
     *
     * @var array
     */
    private array $limits = [
        'podcastindex' => [
            'requests'    => 10,
            'window'      => 60, // seconds
            'retry_after' => 60,
        ],
        'taddy' => [
            'requests'    => 20,
            'window'      => 60,
            'retry_after' => 30,
        ],
        'rss' => [
            'requests'    => 50,
            'window'      => 60,
            'retry_after' => 10,
        ],
    ];

    /**
     * Logger instance.
     *
     * @var Podcast_Prospector_Logger|null
     */
    private ?Podcast_Prospector_Logger $logger;

    /**
     * Constructor.
     *
     * @param Podcast_Prospector_Logger|null $logger Logger instance.
     */
    public function __construct( ?Podcast_Prospector_Logger $logger = null ) {
        $this->logger = $logger;
    }

    /**
     * Check if a request can be made.
     *
     * @param string $api API identifier.
     * @return bool
     */
    public function can_make_request( string $api ): bool {
        $config = $this->get_config( $api );
        $data = $this->get_rate_data( $api );

        $current_time = time();
        $window_start = $current_time - $config['window'];

        // Remove old requests outside the window
        $data['requests'] = array_filter( $data['requests'], function( $timestamp ) use ( $window_start ) {
            return $timestamp > $window_start;
        } );

        // Check if we're at the limit
        if ( count( $data['requests'] ) >= $config['requests'] ) {
            $this->log_warning( "Rate limit reached for {$api}", [
                'requests' => count( $data['requests'] ),
                'limit'    => $config['requests'],
            ] );
            return false;
        }

        return true;
    }

    /**
     * Record a request.
     *
     * @param string $api API identifier.
     * @return void
     */
    public function record_request( string $api ): void {
        $config = $this->get_config( $api );
        $data = $this->get_rate_data( $api );

        $current_time = time();
        $window_start = $current_time - $config['window'];

        // Clean old requests
        $data['requests'] = array_filter( $data['requests'], function( $timestamp ) use ( $window_start ) {
            return $timestamp > $window_start;
        } );

        // Add new request
        $data['requests'][] = $current_time;
        $data['last_request'] = $current_time;

        $this->save_rate_data( $api, $data );

        $this->log_debug( "Recorded request for {$api}", [
            'count' => count( $data['requests'] ),
            'limit' => $config['requests'],
        ] );
    }

    /**
     * Get retry after time in seconds.
     *
     * @param string $api API identifier.
     * @return int Seconds to wait.
     */
    public function get_retry_after( string $api ): int {
        $config = $this->get_config( $api );
        $data = $this->get_rate_data( $api );

        if ( empty( $data['requests'] ) ) {
            return 0;
        }

        $oldest_request = min( $data['requests'] );
        $window_end = $oldest_request + $config['window'];
        $wait_time = max( 0, $window_end - time() );

        return $wait_time ?: $config['retry_after'];
    }

    /**
     * Get remaining requests in current window.
     *
     * @param string $api API identifier.
     * @return int
     */
    public function get_remaining( string $api ): int {
        $config = $this->get_config( $api );
        $data = $this->get_rate_data( $api );

        $current_time = time();
        $window_start = $current_time - $config['window'];

        $recent_requests = array_filter( $data['requests'], function( $timestamp ) use ( $window_start ) {
            return $timestamp > $window_start;
        } );

        return max( 0, $config['requests'] - count( $recent_requests ) );
    }

    /**
     * Reset rate limit for an API (for testing).
     *
     * @param string $api API identifier.
     * @return void
     */
    public function reset( string $api ): void {
        delete_transient( self::TRANSIENT_PREFIX . $api );
    }

    /**
     * Check if a request slot is available, return immediately if not.
     *
     * Non-blocking replacement for the old wait_for_slot() loop.
     * Returns true if allowed, or a WP_Error with 429 status and retry_after.
     *
     * @param string $api API identifier.
     * @return true|WP_Error True if can proceed, WP_Error if rate limited.
     */
    public function wait_for_slot( string $api, int $max_wait = 30 ) {
        if ( $this->can_make_request( $api ) ) {
            return true;
        }

        $retry_after = $this->get_retry_after( $api );

        return new WP_Error(
            'rate_limited',
            __( 'Rate limit exceeded. Please try again later.', 'podcast-prospector' ),
            [
                'status'      => 429,
                'retry_after' => $retry_after,
            ]
        );
    }

    /**
     * Get configuration for an API.
     *
     * @param string $api API identifier.
     * @return array
     */
    private function get_config( string $api ): array {
        return $this->limits[ $api ] ?? [
            'requests'    => 10,
            'window'      => 60,
            'retry_after' => 60,
        ];
    }

    /**
     * Get rate data from transient.
     *
     * @param string $api API identifier.
     * @return array
     */
    private function get_rate_data( string $api ): array {
        $data = get_transient( self::TRANSIENT_PREFIX . $api );

        if ( false === $data || ! is_array( $data ) ) {
            return [
                'requests'     => [],
                'last_request' => 0,
            ];
        }

        return $data;
    }

    /**
     * Save rate data to transient.
     *
     * @param string $api  API identifier.
     * @param array  $data Rate data.
     * @return void
     */
    private function save_rate_data( string $api, array $data ): void {
        $config = $this->get_config( $api );
        set_transient( self::TRANSIENT_PREFIX . $api, $data, $config['window'] * 2 );
    }

    /**
     * Log debug message.
     *
     * @param string $message Message.
     * @param array  $context Context.
     * @return void
     */
    private function log_debug( string $message, array $context = [] ): void {
        if ( $this->logger ) {
            $this->logger->debug( '[Rate Limiter] ' . $message, $context );
        }
    }

    /**
     * Log warning message.
     *
     * @param string $message Message.
     * @param array  $context Context.
     * @return void
     */
    private function log_warning( string $message, array $context = [] ): void {
        if ( $this->logger ) {
            $this->logger->warning( '[Rate Limiter] ' . $message, $context );
        }
    }
}
