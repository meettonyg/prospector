<?php
/**
 * Search Result Cache Class
 *
 * @package Interview_Finder
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Interview_Finder_Search_Cache
 *
 * Caches search results to reduce API calls.
 */
class Interview_Finder_Search_Cache {

    /**
     * Cache prefix.
     *
     * @var string
     */
    private const CACHE_PREFIX = 'if_search_';

    /**
     * Default cache duration (5 minutes).
     *
     * @var int
     */
    private const DEFAULT_TTL = 300;

    /**
     * Logger instance.
     *
     * @var Interview_Finder_Logger|null
     */
    private ?Interview_Finder_Logger $logger;

    /**
     * Constructor.
     *
     * @param Interview_Finder_Logger|null $logger Logger instance.
     */
    public function __construct( ?Interview_Finder_Logger $logger = null ) {
        $this->logger = $logger;
    }

    /**
     * Generate cache key for search params.
     *
     * @param string $api    API identifier.
     * @param array  $params Search parameters.
     * @return string
     */
    public function generate_key( string $api, array $params ): string {
        // Sort params for consistent key generation
        ksort( $params );
        $hash = md5( $api . wp_json_encode( $params ) );
        return self::CACHE_PREFIX . $hash;
    }

    /**
     * Get cached results.
     *
     * @param string $key Cache key.
     * @return array|null Cached data or null if not found.
     */
    public function get( string $key ): ?array {
        $data = get_transient( $key );

        if ( false === $data ) {
            $this->log_debug( 'Cache miss', [ 'key' => $key ] );
            return null;
        }

        $this->log_debug( 'Cache hit', [ 'key' => $key ] );
        return $data;
    }

    /**
     * Store results in cache.
     *
     * @param string $key  Cache key.
     * @param array  $data Data to cache.
     * @param int    $ttl  Time to live in seconds.
     * @return bool
     */
    public function set( string $key, array $data, int $ttl = self::DEFAULT_TTL ): bool {
        $result = set_transient( $key, $data, $ttl );
        $this->log_debug( 'Cache set', [ 'key' => $key, 'ttl' => $ttl ] );
        return $result;
    }

    /**
     * Delete cached results.
     *
     * @param string $key Cache key.
     * @return bool
     */
    public function delete( string $key ): bool {
        return delete_transient( $key );
    }

    /**
     * Clear all search caches.
     *
     * @return int Number of caches cleared.
     */
    public function clear_all(): int {
        global $wpdb;

        $transients = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like( '_transient_' . self::CACHE_PREFIX ) . '%'
            )
        );

        $count = 0;
        foreach ( $transients as $transient ) {
            $key = str_replace( '_transient_', '', $transient );
            if ( delete_transient( $key ) ) {
                $count++;
            }
        }

        $this->log_debug( 'Cleared all search caches', [ 'count' => $count ] );
        return $count;
    }

    /**
     * Get or set cached value (memoization helper).
     *
     * @param string   $key      Cache key.
     * @param callable $callback Callback to generate value if not cached.
     * @param int      $ttl      Time to live.
     * @return mixed
     */
    public function remember( string $key, callable $callback, int $ttl = self::DEFAULT_TTL ) {
        $cached = $this->get( $key );

        if ( null !== $cached ) {
            return $cached;
        }

        $value = $callback();

        if ( is_array( $value ) && ! isset( $value['error'] ) ) {
            $this->set( $key, $value, $ttl );
        }

        return $value;
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
            $this->logger->debug( '[Search Cache] ' . $message, $context );
        }
    }
}
