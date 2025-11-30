<?php
/**
 * Podcast Location Repository Class
 *
 * Handles lookups against the wp_pit_podcasts table for location data.
 *
 * @package Interview_Finder
 * @since 2.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Interview_Finder_Podcast_Location_Repository
 *
 * Provides methods to query podcast location data from the pit_podcasts table.
 */
class Interview_Finder_Podcast_Location_Repository {

    /**
     * Table name without prefix.
     *
     * @var string
     */
    const TABLE_NAME = 'pit_podcasts';

    /**
     * WordPress database object.
     *
     * @var wpdb
     */
    private wpdb $wpdb;

    /**
     * Full table name with prefix.
     *
     * @var string
     */
    private string $table_name;

    /**
     * Settings instance.
     *
     * @var Interview_Finder_Settings
     */
    private Interview_Finder_Settings $settings;

    /**
     * Logger instance.
     *
     * @var Interview_Finder_Logger|null
     */
    private ?Interview_Finder_Logger $logger;

    /**
     * Location cache for current request.
     *
     * @var array<string, array|null>
     */
    private array $cache = [];

    /**
     * Constructor.
     *
     * @param Interview_Finder_Settings    $settings Settings instance.
     * @param Interview_Finder_Logger|null $logger   Logger instance.
     */
    public function __construct( Interview_Finder_Settings $settings, ?Interview_Finder_Logger $logger = null ) {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . self::TABLE_NAME;
        $this->settings = $settings;
        $this->logger = $logger;
    }

    /**
     * Check if location features are enabled.
     *
     * @return bool
     */
    public function is_enabled(): bool {
        return (bool) $this->settings->get( 'location_features_enabled', false );
    }

    /**
     * Check if the pit_podcasts table exists.
     *
     * @return bool
     */
    public function table_exists(): bool {
        static $exists = null;

        if ( null === $exists ) {
            $exists = $this->wpdb->get_var(
                $this->wpdb->prepare(
                    'SHOW TABLES LIKE %s',
                    $this->table_name
                )
            ) === $this->table_name;
        }

        return $exists;
    }

    /**
     * Get location data for a single podcast by iTunes ID.
     *
     * @param string $itunes_id iTunes ID of the podcast.
     * @return array|null Location data or null if not found.
     */
    public function get_location_by_itunes_id( string $itunes_id ): ?array {
        if ( ! $this->is_enabled() || empty( $itunes_id ) ) {
            return null;
        }

        // Check cache first
        $cache_key = 'itunes_' . $itunes_id;
        if ( array_key_exists( $cache_key, $this->cache ) ) {
            return $this->cache[ $cache_key ];
        }

        if ( ! $this->table_exists() ) {
            $this->log_debug( 'Location table does not exist' );
            return null;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT city, state_region, country, timezone FROM {$this->table_name} WHERE itunes_id = %s LIMIT 1",
                $itunes_id
            ),
            ARRAY_A
        );

        $location = $result ? $this->format_location( $result ) : null;
        $this->cache[ $cache_key ] = $location;

        return $location;
    }

    /**
     * Get location data for multiple podcasts by iTunes IDs (batch lookup).
     *
     * @param array $itunes_ids Array of iTunes IDs.
     * @return array<string, array> Map of iTunes ID to location data.
     */
    public function get_locations_by_itunes_ids( array $itunes_ids ): array {
        if ( ! $this->is_enabled() || empty( $itunes_ids ) ) {
            return [];
        }

        if ( ! $this->table_exists() ) {
            $this->log_debug( 'Location table does not exist' );
            return [];
        }

        $locations = [];
        $ids_to_fetch = [];

        // Check cache first
        foreach ( $itunes_ids as $itunes_id ) {
            $cache_key = 'itunes_' . $itunes_id;
            if ( array_key_exists( $cache_key, $this->cache ) ) {
                if ( null !== $this->cache[ $cache_key ] ) {
                    $locations[ $itunes_id ] = $this->cache[ $cache_key ];
                }
            } else {
                $ids_to_fetch[] = $itunes_id;
            }
        }

        // Fetch remaining from database
        if ( ! empty( $ids_to_fetch ) ) {
            $placeholders = implode( ', ', array_fill( 0, count( $ids_to_fetch ), '%s' ) );

            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $results = $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT itunes_id, city, state_region, country, timezone
                     FROM {$this->table_name}
                     WHERE itunes_id IN ({$placeholders})",
                    ...$ids_to_fetch
                ),
                ARRAY_A
            );

            // Index results by iTunes ID
            $found_ids = [];
            if ( $results ) {
                foreach ( $results as $row ) {
                    $itunes_id = $row['itunes_id'];
                    $found_ids[] = $itunes_id;
                    $location = $this->format_location( $row );
                    $locations[ $itunes_id ] = $location;
                    $this->cache[ 'itunes_' . $itunes_id ] = $location;
                }
            }

            // Cache misses as null
            foreach ( $ids_to_fetch as $itunes_id ) {
                if ( ! in_array( $itunes_id, $found_ids, true ) ) {
                    $this->cache[ 'itunes_' . $itunes_id ] = null;
                }
            }
        }

        $this->log_debug( 'Batch location lookup', [
            'requested' => count( $itunes_ids ),
            'found'     => count( $locations ),
        ] );

        return $locations;
    }

    /**
     * Search for podcasts by location.
     *
     * @param array $params Location search parameters (city, state_region, country).
     * @return array Array of podcast data with location info.
     */
    public function search_by_location( array $params ): array {
        if ( ! $this->is_enabled() ) {
            return [];
        }

        if ( ! $this->table_exists() ) {
            $this->log_debug( 'Location table does not exist' );
            return [];
        }

        $where_clauses = [];
        $where_values = [];

        if ( ! empty( $params['city'] ) ) {
            $where_clauses[] = 'city LIKE %s';
            $where_values[] = '%' . $this->wpdb->esc_like( $params['city'] ) . '%';
        }

        if ( ! empty( $params['state_region'] ) ) {
            $where_clauses[] = 'state_region LIKE %s';
            $where_values[] = '%' . $this->wpdb->esc_like( $params['state_region'] ) . '%';
        }

        if ( ! empty( $params['country'] ) ) {
            $where_clauses[] = 'country LIKE %s';
            $where_values[] = '%' . $this->wpdb->esc_like( $params['country'] ) . '%';
        }

        if ( empty( $where_clauses ) ) {
            return [];
        }

        $where = implode( ' AND ', $where_clauses );
        $limit = min( 100, max( 1, (int) ( $params['limit'] ?? 50 ) ) );

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT itunes_id, rss_feed_url, spotify_id, podcast_index_id, podcast_index_guid,
                        slug, city, state_region, country, timezone
                 FROM {$this->table_name}
                 WHERE {$where}
                 LIMIT %d",
                array_merge( $where_values, [ $limit ] )
            ),
            ARRAY_A
        );

        $this->log_debug( 'Location search', [
            'params' => $params,
            'found'  => count( $results ?? [] ),
        ] );

        return $results ?? [];
    }

    /**
     * Get iTunes IDs for podcasts matching location criteria.
     *
     * Useful for filtering Taddy search results by location.
     *
     * @param array $params Location search parameters.
     * @return array Array of iTunes IDs.
     */
    public function get_itunes_ids_by_location( array $params ): array {
        $results = $this->search_by_location( $params );

        $itunes_ids = [];
        foreach ( $results as $row ) {
            if ( ! empty( $row['itunes_id'] ) ) {
                $itunes_ids[] = $row['itunes_id'];
            }
        }

        return $itunes_ids;
    }

    /**
     * Get distinct cities for autocomplete.
     *
     * @param string $search Optional search term to filter.
     * @param int    $limit  Maximum results.
     * @return array Array of city names.
     */
    public function get_distinct_cities( string $search = '', int $limit = 50 ): array {
        if ( ! $this->is_enabled() || ! $this->table_exists() ) {
            return [];
        }

        if ( ! empty( $search ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $results = $this->wpdb->get_col(
                $this->wpdb->prepare(
                    "SELECT DISTINCT city FROM {$this->table_name}
                     WHERE city LIKE %s AND city != ''
                     ORDER BY city ASC LIMIT %d",
                    '%' . $this->wpdb->esc_like( $search ) . '%',
                    $limit
                )
            );
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $results = $this->wpdb->get_col(
                $this->wpdb->prepare(
                    "SELECT DISTINCT city FROM {$this->table_name}
                     WHERE city != ''
                     ORDER BY city ASC LIMIT %d",
                    $limit
                )
            );
        }

        return $results ?? [];
    }

    /**
     * Get distinct states/regions for autocomplete.
     *
     * @param string $search Optional search term to filter.
     * @param int    $limit  Maximum results.
     * @return array Array of state/region names.
     */
    public function get_distinct_states( string $search = '', int $limit = 50 ): array {
        if ( ! $this->is_enabled() || ! $this->table_exists() ) {
            return [];
        }

        if ( ! empty( $search ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $results = $this->wpdb->get_col(
                $this->wpdb->prepare(
                    "SELECT DISTINCT state_region FROM {$this->table_name}
                     WHERE state_region LIKE %s AND state_region != ''
                     ORDER BY state_region ASC LIMIT %d",
                    '%' . $this->wpdb->esc_like( $search ) . '%',
                    $limit
                )
            );
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $results = $this->wpdb->get_col(
                $this->wpdb->prepare(
                    "SELECT DISTINCT state_region FROM {$this->table_name}
                     WHERE state_region != ''
                     ORDER BY state_region ASC LIMIT %d",
                    $limit
                )
            );
        }

        return $results ?? [];
    }

    /**
     * Get distinct countries for autocomplete.
     *
     * @param string $search Optional search term to filter.
     * @param int    $limit  Maximum results.
     * @return array Array of country names.
     */
    public function get_distinct_countries( string $search = '', int $limit = 50 ): array {
        if ( ! $this->is_enabled() || ! $this->table_exists() ) {
            return [];
        }

        if ( ! empty( $search ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $results = $this->wpdb->get_col(
                $this->wpdb->prepare(
                    "SELECT DISTINCT country FROM {$this->table_name}
                     WHERE country LIKE %s AND country != ''
                     ORDER BY country ASC LIMIT %d",
                    '%' . $this->wpdb->esc_like( $search ) . '%',
                    $limit
                )
            );
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $results = $this->wpdb->get_col(
                $this->wpdb->prepare(
                    "SELECT DISTINCT country FROM {$this->table_name}
                     WHERE country != ''
                     ORDER BY country ASC LIMIT %d",
                    $limit
                )
            );
        }

        return $results ?? [];
    }

    /**
     * Get location statistics.
     *
     * @return array Statistics about location data.
     */
    public function get_location_stats(): array {
        if ( ! $this->table_exists() ) {
            return [
                'table_exists'    => false,
                'total_podcasts'  => 0,
                'with_location'   => 0,
                'unique_cities'   => 0,
                'unique_states'   => 0,
                'unique_countries' => 0,
            ];
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $total = (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name}" );

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $with_location = (int) $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE city != '' OR state_region != '' OR country != ''"
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $unique_cities = (int) $this->wpdb->get_var(
            "SELECT COUNT(DISTINCT city) FROM {$this->table_name} WHERE city != ''"
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $unique_states = (int) $this->wpdb->get_var(
            "SELECT COUNT(DISTINCT state_region) FROM {$this->table_name} WHERE state_region != ''"
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $unique_countries = (int) $this->wpdb->get_var(
            "SELECT COUNT(DISTINCT country) FROM {$this->table_name} WHERE country != ''"
        );

        return [
            'table_exists'     => true,
            'total_podcasts'   => $total,
            'with_location'    => $with_location,
            'unique_cities'    => $unique_cities,
            'unique_states'    => $unique_states,
            'unique_countries' => $unique_countries,
        ];
    }

    /**
     * Format location data for display.
     *
     * @param array $row Database row.
     * @return array Formatted location data.
     */
    private function format_location( array $row ): array {
        $location = [
            'city'         => $row['city'] ?? '',
            'state_region' => $row['state_region'] ?? '',
            'country'      => $row['country'] ?? '',
            'timezone'     => $row['timezone'] ?? '',
        ];

        // Build formatted display string
        $parts = array_filter( [
            $location['city'],
            $location['state_region'],
            $location['country'],
        ] );

        $location['formatted'] = implode( ', ', $parts );
        $location['has_location'] = ! empty( $location['formatted'] );

        return $location;
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
            $this->logger->debug( '[PodcastLocation] ' . $message, $context );
        }
    }
}
