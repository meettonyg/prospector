<?php
/**
 * Search Result Cache Class
 *
 * Supports WordPress object cache (Redis/Memcached) for scalability.
 *
 * @package Podcast_Prospector
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Podcast_Prospector_Search_Cache
 *
 * Caches search results to reduce API calls.
 * Uses object cache when available (Redis/Memcached), falls back to transients.
 */
class Podcast_Prospector_Search_Cache {

    /**
     * Cache prefix.
     *
     * @var string
     */
    private const CACHE_PREFIX = 'if_search_';

    /**
     * Cache group for object cache.
     *
     * @var string
     */
    private const CACHE_GROUP = 'podcast_prospector';

    /**
     * Default cache duration (15 minutes - increased for scalability).
     *
     * @var int
     */
    private const DEFAULT_TTL = 900;

    /**
     * TTL for external API responses (30 minutes).
     *
     * @var int
     */
    private const API_TTL = 1800;

    /**
     * TTL for podcast data (1 hour - changes infrequently).
     *
     * @var int
     */
    private const PODCAST_TTL = 3600;

    /**
     * Logger instance.
     *
     * @var Podcast_Prospector_Logger|null
     */
    private ?Podcast_Prospector_Logger $logger;

    /**
     * Whether object cache is available.
     *
     * @var bool
     */
    private bool $use_object_cache;

    /**
     * Constructor.
     *
     * @param Podcast_Prospector_Logger|null $logger Logger instance.
     */
    public function __construct( ?Podcast_Prospector_Logger $logger = null ) {
        $this->logger = $logger;
        $cache_status            = function_exists( 'wp_using_ext_object_cache' ) ? wp_using_ext_object_cache() : null;

        if ( null === $cache_status ) {
            if ( $this->logger ) {
                $this->logger->warning( '[Search Cache] Object cache check unavailable; defaulting to transient cache' );
            }
        } elseif ( ! is_bool( $cache_status ) ) {
            if ( $this->logger ) {
                $this->logger->warning( '[Search Cache] Object cache check returned unexpected value; coercing to boolean', [ 'cache_status' => $cache_status ] );
            }
        }

        $this->use_object_cache = (bool) $cache_status;

        if ( $this->use_object_cache ) {
            $this->log_debug( 'Using external object cache (Redis/Memcached)' );
        }
    }

    /**
     * Check if external object cache is being used.
     *
     * @return bool
     */
    public function is_using_object_cache(): bool {
        return $this->use_object_cache;
    }

    /**
     * Get appropriate TTL based on search type.
     *
     * @param string $search_type Search type.
     * @return int TTL in seconds.
     */
    public function get_ttl_for_type( string $search_type ): int {
        switch ( $search_type ) {
            case 'byperson':
            case 'bytitle':
                return self::API_TTL; // 30 min - search results change moderately

            case 'byadvancedpodcast':
                return self::PODCAST_TTL; // 1 hour - podcast data is stable

            case 'byadvancedepisode':
                return self::API_TTL; // 30 min

            case 'byyoutube':
                return self::DEFAULT_TTL; // 15 min - YouTube data updates more frequently

            default:
                return self::DEFAULT_TTL;
        }
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
     * Uses object cache (Redis/Memcached) when available, falls back to transients.
     *
     * @param string $key Cache key.
     * @return array|null Cached data or null if not found.
     */
    public function get( string $key ): ?array {
        if ( $this->use_object_cache ) {
            $data = wp_cache_get( $key, self::CACHE_GROUP );
        } else {
            $data = get_transient( $key );
        }

        if ( false === $data ) {
            $this->log_debug( 'Cache miss', [ 'key' => $key, 'object_cache' => $this->use_object_cache ] );
            return null;
        }

        $this->log_debug( 'Cache hit', [ 'key' => $key, 'object_cache' => $this->use_object_cache ] );
        return $data;
    }

    /**
     * Store results in cache.
     *
     * Uses object cache (Redis/Memcached) when available, falls back to transients.
     *
     * @param string $key  Cache key.
     * @param array  $data Data to cache.
     * @param int    $ttl  Time to live in seconds.
     * @return bool
     */
    public function set( string $key, array $data, int $ttl = self::DEFAULT_TTL ): bool {
        if ( $this->use_object_cache ) {
            $result = wp_cache_set( $key, $data, self::CACHE_GROUP, $ttl );
        } else {
            $result = set_transient( $key, $data, $ttl );
        }

        $this->log_debug( 'Cache set', [ 'key' => $key, 'ttl' => $ttl, 'object_cache' => $this->use_object_cache ] );
        return $result;
    }

    /**
     * Delete cached results.
     *
     * @param string $key Cache key.
     * @return bool
     */
    public function delete( string $key ): bool {
        if ( $this->use_object_cache ) {
            return wp_cache_delete( $key, self::CACHE_GROUP );
        }
        return delete_transient( $key );
    }

    /**
     * Clear all search caches.
     *
     * @return int Number of caches cleared.
     */
    public function clear_all(): int {
        if ( $this->use_object_cache ) {
            // For object cache, flush the group if supported, otherwise can't reliably clear
            if ( function_exists( 'wp_cache_flush_group' ) ) {
                wp_cache_flush_group( self::CACHE_GROUP );
                $this->log_debug( 'Flushed object cache group' );
                return 1;
            }
            // Can't reliably clear object cache without group flush support
            $this->log_debug( 'Object cache clear not supported without group flush' );
            return 0;
        }

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
     * Get cache statistics.
     *
     * @return array
     */
    public function get_stats(): array {
        $stats = [
            'using_object_cache' => $this->use_object_cache,
            'cache_group'        => self::CACHE_GROUP,
            'default_ttl'        => self::DEFAULT_TTL,
            'api_ttl'            => self::API_TTL,
            'podcast_ttl'        => self::PODCAST_TTL,
        ];

        if ( ! $this->use_object_cache ) {
            global $wpdb;
            $stats['transient_count'] = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
                    $wpdb->esc_like( '_transient_' . self::CACHE_PREFIX ) . '%'
                )
            );
        }

        return $stats;
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
