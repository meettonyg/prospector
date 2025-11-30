<?php
/**
 * RSS Feed Cache Class
 *
 * Caches RSS feed data to improve performance and reduce external requests.
 *
 * @package Podcast_Prospector
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Podcast_Prospector_RSS_Cache
 *
 * Provides RSS feed parsing with transient-based caching.
 */
class Podcast_Prospector_RSS_Cache {

    /**
     * Cache expiration time in seconds (1 hour).
     *
     * @var int
     */
    const CACHE_EXPIRATION = HOUR_IN_SECONDS;

    /**
     * Cache key prefix.
     *
     * @var string
     */
    const CACHE_PREFIX = 'if_rss_';

    /**
     * Singleton instance.
     *
     * @var Podcast_Prospector_RSS_Cache|null
     */
    private static ?Podcast_Prospector_RSS_Cache $instance = null;

    /**
     * Logger instance.
     *
     * @var Podcast_Prospector_Logger|null
     */
    private ?Podcast_Prospector_Logger $logger = null;

    /**
     * Get singleton instance.
     *
     * @return Podcast_Prospector_RSS_Cache
     */
    public static function get_instance(): Podcast_Prospector_RSS_Cache {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor.
     */
    private function __construct() {
        if ( class_exists( 'Podcast_Prospector_Logger' ) ) {
            $this->logger = Podcast_Prospector_Logger::get_instance();
        }
    }

    /**
     * Generate cache key for a feed URL.
     *
     * @param string $feed_url Feed URL.
     * @return string Cache key.
     */
    private function get_cache_key( string $feed_url ): string {
        return self::CACHE_PREFIX . md5( $feed_url );
    }

    /**
     * Get full RSS data with caching.
     *
     * @param string $feed_url Feed URL.
     * @return array RSS data or empty array on failure.
     */
    public function get_feed_data( string $feed_url ): array {
        if ( empty( $feed_url ) ) {
            return [];
        }

        $cache_key = $this->get_cache_key( $feed_url );

        // Check cache first
        $cached_data = get_transient( $cache_key );
        if ( false !== $cached_data ) {
            $this->log_debug( 'RSS cache hit', [ 'url' => $feed_url ] );
            return $cached_data;
        }

        // Fetch and parse RSS
        $this->log_debug( 'RSS cache miss, fetching', [ 'url' => $feed_url ] );
        $data = $this->fetch_and_parse( $feed_url );

        // Cache the result (even if empty, to prevent repeated failed requests)
        if ( ! empty( $data ) ) {
            set_transient( $cache_key, $data, self::CACHE_EXPIRATION );
        } else {
            // Cache failures for a shorter time (5 minutes)
            set_transient( $cache_key, [], 5 * MINUTE_IN_SECONDS );
        }

        return $data;
    }

    /**
     * Get only the last episode data (lightweight).
     *
     * @param string $feed_url Feed URL.
     * @return array Last episode data or empty array.
     */
    public function get_last_episode_data( string $feed_url ): array {
        $feed_data = $this->get_feed_data( $feed_url );

        if ( empty( $feed_data ) ) {
            return [];
        }

        return [
            'lastEpisodeTitle'   => $feed_data['lastEpisodeTitle'] ?? '',
            'lastEpisodePubDate' => $feed_data['lastEpisodePubDate'] ?? '',
            'episodeCount'       => $feed_data['episodeCount'] ?? 0,
        ];
    }

    /**
     * Fetch and parse RSS feed.
     *
     * @param string $feed_url Feed URL.
     * @return array Parsed data.
     */
    private function fetch_and_parse( string $feed_url ): array {
        // Use WordPress HTTP API for better compatibility
        $response = wp_remote_get( $feed_url, [
            'timeout' => 15,
            'headers' => [
                'User-Agent' => 'InterviewFinder/2.0 (RSS Feed Reader)',
            ],
        ] );

        if ( is_wp_error( $response ) ) {
            $this->log_error( 'Failed to fetch RSS', [
                'url'   => $feed_url,
                'error' => $response->get_error_message(),
            ] );
            return [];
        }

        $body = wp_remote_retrieve_body( $response );

        if ( empty( $body ) ) {
            $this->log_error( 'Empty RSS response', [ 'url' => $feed_url ] );
            return [];
        }

        // Suppress errors and try to parse
        libxml_use_internal_errors( true );
        $rss = simplexml_load_string( $body, 'SimpleXMLElement', LIBXML_NOCDATA );

        if ( false === $rss ) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            $this->log_error( 'Failed to parse RSS XML', [
                'url'    => $feed_url,
                'errors' => array_map( function( $e ) {
                    return $e->message;
                }, $errors ),
            ] );
            return [];
        }

        return $this->extract_feed_data( $rss );
    }

    /**
     * Extract data from parsed RSS.
     *
     * @param SimpleXMLElement $rss Parsed RSS.
     * @return array Extracted data.
     */
    private function extract_feed_data( SimpleXMLElement $rss ): array {
        $data = [
            'description'        => '',
            'lastBuildDate'      => '',
            'link'               => '',
            'itunes:keywords'    => '',
            'itunes:name'        => '',
            'itunes:email'       => '',
            'itunes:categories'  => [],
            'episodeCount'       => 0,
            'lastEpisodeTitle'   => '',
            'lastEpisodePubDate' => '',
        ];

        // Basic channel info
        if ( isset( $rss->channel ) ) {
            $channel = $rss->channel;

            $data['description'] = (string) ( $channel->description ?? '' );
            $data['lastBuildDate'] = (string) ( $channel->lastBuildDate ?? '' );
            $data['link'] = (string) ( $channel->link ?? '' );

            // iTunes namespace data
            $itunes = $channel->children( 'itunes', true );
            if ( $itunes ) {
                $data['itunes:keywords'] = (string) ( $itunes->keywords ?? '' );

                if ( isset( $itunes->owner ) ) {
                    $data['itunes:name'] = (string) ( $itunes->owner->name ?? '' );
                    $data['itunes:email'] = (string) ( $itunes->owner->email ?? '' );
                }

                // Categories
                $categories = [];
                foreach ( $itunes->category as $category ) {
                    $cat_text = (string) $category->attributes()->text;
                    if ( ! empty( $cat_text ) ) {
                        $categories[] = $cat_text;
                    }
                }
                $data['itunes:categories'] = array_unique( $categories );
            }

            // Episode count and last episode
            $items = $channel->item;
            $data['episodeCount'] = count( $items );

            // Find most recent episode
            $latest_date = null;
            $latest_title = '';

            foreach ( $items as $episode ) {
                $pub_date_str = (string) ( $episode->pubDate ?? '' );
                if ( empty( $pub_date_str ) ) {
                    continue;
                }

                $pub_timestamp = strtotime( $pub_date_str );
                if ( null === $latest_date || $pub_timestamp > $latest_date ) {
                    $latest_date = $pub_timestamp;
                    $latest_title = (string) ( $episode->title ?? '' );
                    $data['lastEpisodePubDate'] = $pub_date_str;
                }
            }

            $data['lastEpisodeTitle'] = $latest_title;
        }

        return $data;
    }

    /**
     * Batch fetch multiple feeds.
     *
     * Useful for warming cache when displaying search results.
     *
     * @param array $feed_urls Array of feed URLs.
     * @return array Associative array of URL => data.
     */
    public function batch_fetch( array $feed_urls ): array {
        $results = [];

        foreach ( $feed_urls as $url ) {
            if ( ! empty( $url ) ) {
                $results[ $url ] = $this->get_feed_data( $url );
            }
        }

        return $results;
    }

    /**
     * Clear cache for a specific feed.
     *
     * @param string $feed_url Feed URL.
     * @return bool
     */
    public function clear_cache( string $feed_url ): bool {
        $cache_key = $this->get_cache_key( $feed_url );
        return delete_transient( $cache_key );
    }

    /**
     * Clear all RSS caches.
     *
     * @return int Number of caches cleared.
     */
    public function clear_all_caches(): int {
        global $wpdb;

        // Find all our transients
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

        $this->log_debug( 'Cleared all RSS caches', [ 'count' => $count ] );

        return $count;
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
            $this->logger->debug( '[RSS Cache] ' . $message, $context );
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
            $this->logger->error( '[RSS Cache] ' . $message, $context );
        }
    }
}
