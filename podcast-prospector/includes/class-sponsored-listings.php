<?php
/**
 * Sponsored Listings Class
 *
 * Handles sponsored/promoted podcast listings that appear at the top of search results.
 *
 * @package Podcast_Prospector
 * @since 2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Podcast_Prospector_Sponsored_Listings
 *
 * Manages sponsored listings database operations, matching, and tracking.
 */
class Podcast_Prospector_Sponsored_Listings {

    /**
     * Table name without prefix.
     *
     * @var string
     */
    const TABLE_NAME = 'podcast_prospector_sponsored';

    /**
     * Stats table name without prefix.
     *
     * @var string
     */
    const STATS_TABLE_NAME = 'podcast_prospector_sponsored_stats';

    /**
     * Database version for migrations.
     *
     * @var string
     */
    const DB_VERSION = '1.0.1';

    /**
     * Singleton instance.
     *
     * @var Podcast_Prospector_Sponsored_Listings|null
     */
    private static ?Podcast_Prospector_Sponsored_Listings $instance = null;

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
     * Full stats table name with prefix.
     *
     * @var string
     */
    private string $stats_table_name;

    /**
     * Logger instance.
     *
     * @var Podcast_Prospector_Logger|null
     */
    private ?Podcast_Prospector_Logger $logger = null;

    /**
     * Settings instance.
     *
     * @var Podcast_Prospector_Settings|null
     */
    private ?Podcast_Prospector_Settings $settings = null;

    /**
     * Get singleton instance.
     *
     * @return Podcast_Prospector_Sponsored_Listings
     */
    public static function get_instance(): Podcast_Prospector_Sponsored_Listings {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor.
     */
    private function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . self::TABLE_NAME;
        $this->stats_table_name = $wpdb->prefix . self::STATS_TABLE_NAME;

        if ( class_exists( 'Podcast_Prospector_Logger' ) ) {
            $this->logger = Podcast_Prospector_Logger::get_instance();
        }

        if ( class_exists( 'Podcast_Prospector_Settings' ) ) {
            $this->settings = Podcast_Prospector_Settings::get_instance();
        }
    }

    /**
     * Create database tables.
     *
     * @return bool
     */
    public function create_tables(): bool {
        $charset_collate = $this->wpdb->get_charset_collate();

        // Main sponsored listings table with optimized indexes
        $sql_listings = "CREATE TABLE {$this->table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            podcast_title VARCHAR(255) NOT NULL,
            podcast_uuid VARCHAR(255) DEFAULT '',
            podcast_itunes_id VARCHAR(50) DEFAULT '',
            podcast_image_url VARCHAR(500) DEFAULT '',
            podcast_description TEXT,
            podcast_url VARCHAR(500) DEFAULT '',
            podcast_rss_url VARCHAR(500) DEFAULT '',
            categories VARCHAR(500) DEFAULT '',
            priority INT(11) DEFAULT 0,
            status ENUM('active', 'paused', 'expired') DEFAULT 'active',
            start_date DATETIME DEFAULT NULL,
            end_date DATETIME DEFAULT NULL,
            impression_limit INT(11) DEFAULT 0,
            click_limit INT(11) DEFAULT 0,
            total_impressions INT(11) DEFAULT 0,
            total_clicks INT(11) DEFAULT 0,
            created_by BIGINT(20) UNSIGNED NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_status_priority (status, priority DESC),
            KEY idx_active_lookup (status, start_date, end_date, impression_limit, click_limit),
            KEY idx_categories (categories(100)),
            KEY idx_created_at (created_at)
        ) $charset_collate;";

        // Daily stats table for reporting
        $sql_stats = "CREATE TABLE {$this->stats_table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            sponsored_id BIGINT(20) UNSIGNED NOT NULL,
            stat_date DATE NOT NULL,
            impressions INT(11) DEFAULT 0,
            clicks INT(11) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY sponsored_date (sponsored_id, stat_date),
            KEY sponsored_id (sponsored_id),
            KEY stat_date (stat_date)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql_listings );
        dbDelta( $sql_stats );

        update_option( 'podcast_prospector_sponsored_db_version', self::DB_VERSION );

        $this->log_debug( 'Sponsored listings tables created/updated' );

        return true;
    }

    /**
     * Check if tables exist.
     *
     * @return bool
     */
    public function tables_exist(): bool {
        $table_exists = $this->wpdb->get_var(
            $this->wpdb->prepare(
                'SHOW TABLES LIKE %s',
                $this->table_name
            )
        ) === $this->table_name;

        return $table_exists;
    }

    /**
     * Get all sponsored listings.
     *
     * @param array $args Query arguments.
     * @return array
     */
    public function get_all( array $args = [] ): array {
        $defaults = [
            'status'   => null,
            'order_by' => 'priority',
            'order'    => 'DESC',
            'limit'    => 50,
            'offset'   => 0,
        ];

        $args = wp_parse_args( $args, $defaults );

        $where = '1=1';
        $params = [];

        if ( $args['status'] ) {
            $where .= ' AND status = %s';
            $params[] = $args['status'];
        }

        $order_by = in_array( $args['order_by'], [ 'priority', 'name', 'created_at', 'total_impressions' ], true )
            ? $args['order_by']
            : 'priority';
        $order = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

        $sql = "SELECT * FROM {$this->table_name} WHERE {$where} ORDER BY {$order_by} {$order} LIMIT %d OFFSET %d";
        $params[] = $args['limit'];
        $params[] = $args['offset'];

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare( $sql, ...$params ),
            ARRAY_A
        );

        return $results ?? [];
    }

    /**
     * Get a single sponsored listing by ID.
     *
     * @param int $id Listing ID.
     * @return array|null
     */
    public function get( int $id ): ?array {
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d",
                $id
            ),
            ARRAY_A
        );

        return $result ?: null;
    }

    /**
     * Create a new sponsored listing.
     *
     * @param array $data Listing data.
     * @return int|false Insert ID or false on failure.
     */
    public function create( array $data ) {
        $defaults = [
            'name'                => '',
            'podcast_title'       => '',
            'podcast_uuid'        => '',
            'podcast_itunes_id'   => '',
            'podcast_image_url'   => '',
            'podcast_description' => '',
            'podcast_url'         => '',
            'podcast_rss_url'     => '',
            'categories'          => '',
            'priority'            => 0,
            'status'              => 'active',
            'start_date'          => null,
            'end_date'            => null,
            'impression_limit'    => 0,
            'click_limit'         => 0,
            'created_by'          => get_current_user_id(),
        ];

        $data = wp_parse_args( $data, $defaults );

        // Serialize categories if array
        if ( is_array( $data['categories'] ) ) {
            $data['categories'] = implode( ',', array_map( 'sanitize_text_field', $data['categories'] ) );
        }

        $result = $this->wpdb->insert(
            $this->table_name,
            [
                'name'                => sanitize_text_field( $data['name'] ),
                'podcast_title'       => sanitize_text_field( $data['podcast_title'] ),
                'podcast_uuid'        => sanitize_text_field( $data['podcast_uuid'] ),
                'podcast_itunes_id'   => sanitize_text_field( $data['podcast_itunes_id'] ),
                'podcast_image_url'   => esc_url_raw( $data['podcast_image_url'] ),
                'podcast_description' => sanitize_textarea_field( $data['podcast_description'] ),
                'podcast_url'         => esc_url_raw( $data['podcast_url'] ),
                'podcast_rss_url'     => esc_url_raw( $data['podcast_rss_url'] ),
                'categories'          => sanitize_text_field( $data['categories'] ),
                'priority'            => (int) $data['priority'],
                'status'              => in_array( $data['status'], [ 'active', 'paused', 'expired' ], true ) ? $data['status'] : 'active',
                'start_date'          => $data['start_date'],
                'end_date'            => $data['end_date'],
                'impression_limit'    => (int) $data['impression_limit'],
                'click_limit'         => (int) $data['click_limit'],
                'created_by'          => (int) $data['created_by'],
            ],
            [ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%d', '%d' ]
        );

        if ( $result ) {
            $this->log_debug( 'Sponsored listing created', [ 'id' => $this->wpdb->insert_id ] );
            return $this->wpdb->insert_id;
        }

        $this->log_error( 'Failed to create sponsored listing', [ 'error' => $this->wpdb->last_error ] );
        return false;
    }

    /**
     * Update a sponsored listing.
     *
     * @param int   $id   Listing ID.
     * @param array $data Data to update.
     * @return bool
     */
    public function update( int $id, array $data ): bool {
        // Serialize categories if array
        if ( isset( $data['categories'] ) && is_array( $data['categories'] ) ) {
            $data['categories'] = implode( ',', array_map( 'sanitize_text_field', $data['categories'] ) );
        }

        $update_data = [];
        $format = [];

        $allowed_fields = [
            'name', 'podcast_title', 'podcast_uuid', 'podcast_itunes_id',
            'podcast_image_url', 'podcast_description', 'podcast_url', 'podcast_rss_url',
            'categories', 'priority', 'status', 'start_date', 'end_date',
            'impression_limit', 'click_limit',
        ];

        foreach ( $allowed_fields as $field ) {
            if ( isset( $data[ $field ] ) ) {
                if ( in_array( $field, [ 'priority', 'impression_limit', 'click_limit' ], true ) ) {
                    $update_data[ $field ] = (int) $data[ $field ];
                    $format[] = '%d';
                } elseif ( in_array( $field, [ 'podcast_image_url', 'podcast_url', 'podcast_rss_url' ], true ) ) {
                    $update_data[ $field ] = esc_url_raw( $data[ $field ] );
                    $format[] = '%s';
                } elseif ( $field === 'podcast_description' ) {
                    $update_data[ $field ] = sanitize_textarea_field( $data[ $field ] );
                    $format[] = '%s';
                } elseif ( $field === 'status' ) {
                    $update_data[ $field ] = in_array( $data[ $field ], [ 'active', 'paused', 'expired' ], true )
                        ? $data[ $field ]
                        : 'active';
                    $format[] = '%s';
                } else {
                    $update_data[ $field ] = sanitize_text_field( $data[ $field ] );
                    $format[] = '%s';
                }
            }
        }

        if ( empty( $update_data ) ) {
            return false;
        }

        $result = $this->wpdb->update(
            $this->table_name,
            $update_data,
            [ 'id' => $id ],
            $format,
            [ '%d' ]
        );

        if ( false !== $result ) {
            $this->log_debug( 'Sponsored listing updated', [ 'id' => $id ] );
            return true;
        }

        $this->log_error( 'Failed to update sponsored listing', [ 'id' => $id, 'error' => $this->wpdb->last_error ] );
        return false;
    }

    /**
     * Delete a sponsored listing.
     *
     * @param int $id Listing ID.
     * @return bool
     */
    public function delete( int $id ): bool {
        // Delete stats first
        $this->wpdb->delete(
            $this->stats_table_name,
            [ 'sponsored_id' => $id ],
            [ '%d' ]
        );

        // Delete listing
        $result = $this->wpdb->delete(
            $this->table_name,
            [ 'id' => $id ],
            [ '%d' ]
        );

        if ( $result ) {
            $this->log_debug( 'Sponsored listing deleted', [ 'id' => $id ] );
            return true;
        }

        return false;
    }

    /**
     * Get matching sponsored listings for a search.
     *
     * @param array $params Search parameters (categories, search_type, etc.).
     * @param int   $limit  Maximum listings to return.
     * @return array
     */
    public function get_matching( array $params, int $limit = 3 ): array {
        if ( ! $this->tables_exist() ) {
            return [];
        }

        $now = current_time( 'mysql' );

        // Build query for active, non-expired, within date range, under limits
        $sql = "SELECT * FROM {$this->table_name}
                WHERE status = 'active'
                AND (start_date IS NULL OR start_date <= %s)
                AND (end_date IS NULL OR end_date >= %s)
                AND (impression_limit = 0 OR total_impressions < impression_limit)
                AND (click_limit = 0 OR total_clicks < click_limit)";

        $query_params = [ $now, $now ];

        // Category matching
        $categories = $params['categories'] ?? [];
        if ( ! empty( $categories ) ) {
            if ( is_string( $categories ) ) {
                $categories = array_map( 'trim', explode( ',', $categories ) );
            }

            // Build LIKE conditions for category matching
            $category_conditions = [];
            foreach ( $categories as $category ) {
                $category_conditions[] = 'categories LIKE %s';
                $query_params[] = '%' . $this->wpdb->esc_like( $category ) . '%';
            }

            if ( ! empty( $category_conditions ) ) {
                $sql .= ' AND (' . implode( ' OR ', $category_conditions ) . ')';
            }
        }

        $sql .= ' ORDER BY priority DESC, RAND() LIMIT %d';
        $query_params[] = $limit;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare( $sql, ...$query_params ),
            ARRAY_A
        );

        $listings = $results ?? [];

        // Format for display
        foreach ( $listings as &$listing ) {
            $listing['categories_array'] = ! empty( $listing['categories'] )
                ? array_map( 'trim', explode( ',', $listing['categories'] ) )
                : [];
            $listing['is_sponsored'] = true;
        }

        return $listings;
    }

    /**
     * Record an impression for a sponsored listing.
     *
     * Uses queue for batch processing at scale.
     *
     * @param int  $id     Listing ID.
     * @param bool $direct Whether to write directly to DB (for low traffic/testing).
     * @return bool
     */
    public function record_impression( int $id, bool $direct = false ): bool {
        if ( ! $this->tables_exist() ) {
            return false;
        }

        // Use queue for scalability (if available)
        if ( ! $direct && class_exists( 'Podcast_Prospector_Impression_Queue' ) ) {
            $queue = Podcast_Prospector_Impression_Queue::get_instance();
            $queue->queue_impression( $id );
            return true;
        }

        // Direct write fallback
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE {$this->table_name} SET total_impressions = total_impressions + 1 WHERE id = %d",
                $id
            )
        );

        // Update daily stats
        $today = current_time( 'Y-m-d' );

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $this->wpdb->query(
            $this->wpdb->prepare(
                "INSERT INTO {$this->stats_table_name} (sponsored_id, stat_date, impressions)
                 VALUES (%d, %s, 1)
                 ON DUPLICATE KEY UPDATE impressions = impressions + 1",
                $id,
                $today
            )
        );

        // Check if limit reached
        $this->check_limits( $id );

        return true;
    }

    /**
     * Record a click for a sponsored listing.
     *
     * Uses queue for batch processing at scale.
     *
     * @param int  $id     Listing ID.
     * @param bool $direct Whether to write directly to DB (for low traffic/testing).
     * @return bool
     */
    public function record_click( int $id, bool $direct = false ): bool {
        if ( ! $this->tables_exist() ) {
            return false;
        }

        // Use queue for scalability (if available)
        if ( ! $direct && class_exists( 'Podcast_Prospector_Impression_Queue' ) ) {
            $queue = Podcast_Prospector_Impression_Queue::get_instance();
            $queue->queue_click( $id );
            return true;
        }

        // Direct write fallback
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE {$this->table_name} SET total_clicks = total_clicks + 1 WHERE id = %d",
                $id
            )
        );

        // Update daily stats
        $today = current_time( 'Y-m-d' );

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $this->wpdb->query(
            $this->wpdb->prepare(
                "INSERT INTO {$this->stats_table_name} (sponsored_id, stat_date, clicks)
                 VALUES (%d, %s, 1)
                 ON DUPLICATE KEY UPDATE clicks = clicks + 1",
                $id,
                $today
            )
        );

        // Check if limit reached
        $this->check_limits( $id );

        $this->log_debug( 'Sponsored click recorded', [ 'id' => $id ] );

        return true;
    }

    /**
     * Check if listing has reached its limits and update status.
     *
     * @param int $id Listing ID.
     * @return void
     */
    private function check_limits( int $id ): void {
        $listing = $this->get( $id );

        if ( ! $listing ) {
            return;
        }

        $expired = false;

        // Check impression limit
        if ( $listing['impression_limit'] > 0 && $listing['total_impressions'] >= $listing['impression_limit'] ) {
            $expired = true;
        }

        // Check click limit
        if ( $listing['click_limit'] > 0 && $listing['total_clicks'] >= $listing['click_limit'] ) {
            $expired = true;
        }

        if ( $expired ) {
            $this->update( $id, [ 'status' => 'expired' ] );
            $this->log_debug( 'Sponsored listing expired due to limits', [ 'id' => $id ] );
        }
    }

    /**
     * Get statistics for a sponsored listing.
     *
     * @param int    $id         Listing ID.
     * @param string $start_date Start date (Y-m-d).
     * @param string $end_date   End date (Y-m-d).
     * @return array
     */
    public function get_stats( int $id, string $start_date = '', string $end_date = '' ): array {
        if ( ! $this->tables_exist() ) {
            return [];
        }

        if ( empty( $start_date ) ) {
            $start_date = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
        }

        if ( empty( $end_date ) ) {
            $end_date = current_time( 'Y-m-d' );
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT stat_date, impressions, clicks
                 FROM {$this->stats_table_name}
                 WHERE sponsored_id = %d
                 AND stat_date BETWEEN %s AND %s
                 ORDER BY stat_date ASC",
                $id,
                $start_date,
                $end_date
            ),
            ARRAY_A
        );

        return $results ?? [];
    }

    /**
     * Get aggregate stats for all listings.
     *
     * @return array
     */
    public function get_aggregate_stats(): array {
        if ( ! $this->tables_exist() ) {
            return [
                'total_listings'    => 0,
                'active_listings'   => 0,
                'total_impressions' => 0,
                'total_clicks'      => 0,
                'avg_ctr'           => 0,
            ];
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $stats = $this->wpdb->get_row(
            "SELECT
                COUNT(*) as total_listings,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_listings,
                SUM(total_impressions) as total_impressions,
                SUM(total_clicks) as total_clicks
             FROM {$this->table_name}",
            ARRAY_A
        );

        $stats = $stats ?? [
            'total_listings'    => 0,
            'active_listings'   => 0,
            'total_impressions' => 0,
            'total_clicks'      => 0,
        ];

        // Calculate CTR
        $stats['avg_ctr'] = $stats['total_impressions'] > 0
            ? round( ( $stats['total_clicks'] / $stats['total_impressions'] ) * 100, 2 )
            : 0;

        return $stats;
    }

    /**
     * Get available categories from existing listings.
     *
     * @return array
     */
    public function get_categories(): array {
        if ( ! $this->tables_exist() ) {
            return [];
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $results = $this->wpdb->get_col(
            "SELECT DISTINCT categories FROM {$this->table_name} WHERE categories != ''"
        );

        $categories = [];
        foreach ( $results as $cat_string ) {
            $cats = array_map( 'trim', explode( ',', $cat_string ) );
            $categories = array_merge( $categories, $cats );
        }

        return array_unique( array_filter( $categories ) );
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
            $this->logger->debug( '[SponsoredListings] ' . $message, $context );
        }
    }

    /**
     * Log error message.
     *
     * @param string $message Message.
     * @param array  $context Context.
     * @return void
     */
    private function log_error( string $message, array $context = [] ): void {
        if ( $this->logger ) {
            $this->logger->error( '[SponsoredListings] ' . $message, $context );
        }
    }
}
