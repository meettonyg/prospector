<?php
/**
 * Interview Finder Database Class
 *
 * Handles all database operations for user search tracking.
 *
 * @package Podcast_Prospector
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Podcast_Prospector_Database
 *
 * Manages custom database table and user data operations.
 */
class Podcast_Prospector_Database {

    /**
     * Table name without prefix.
     *
     * @var string
     */
    const TABLE_NAME = 'podcast_prospector';

    /**
     * Database version for migrations.
     *
     * @var string
     */
    const DB_VERSION = '2.0.1';

    /**
     * Singleton instance.
     *
     * @var Podcast_Prospector_Database|null
     */
    private static ?Podcast_Prospector_Database $instance = null;

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
     * Logger instance.
     *
     * @var Podcast_Prospector_Logger|null
     */
    private ?Podcast_Prospector_Logger $logger = null;

    /**
     * Get singleton instance.
     *
     * @return Podcast_Prospector_Database
     */
    public static function get_instance(): Podcast_Prospector_Database {
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

        if ( class_exists( 'Podcast_Prospector_Logger' ) ) {
            $this->logger = Podcast_Prospector_Logger::get_instance();
        }
    }

    /**
     * Get the full table name.
     *
     * @return string
     */
    public function get_table_name(): string {
        return $this->table_name;
    }

    /**
     * Create or update the database table.
     *
     * @return bool
     */
    public function create_table(): bool {
        $charset_collate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            wp_user_id BIGINT(20) UNSIGNED NOT NULL,
            ghl_id VARCHAR(255) DEFAULT '',
            search_count INT(11) DEFAULT 0,
            total_searches INT(11) DEFAULT 0,
            last_searched DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_reset_date DATETIME DEFAULT '0000-00-00 00:00:00',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY wp_user_id (wp_user_id),
            KEY ghl_id (ghl_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        // Store database version
        update_option( 'podcast_prospector_db_version', self::DB_VERSION );

        $this->log_debug( 'Database table created/updated' );

        return true;
    }

    /**
     * Get user data by GHL ID or WordPress user ID.
     *
     * @param string|null $ghl_id     GoHighLevel contact ID.
     * @param int|null    $wp_user_id WordPress user ID.
     * @return object|null
     */
    public function get_user_data( ?string $ghl_id = null, ?int $wp_user_id = null ): ?object {
        if ( empty( $ghl_id ) && empty( $wp_user_id ) ) {
            return null;
        }

        $where_clauses = [];
        $params = [];

        if ( ! empty( $wp_user_id ) ) {
            $where_clauses[] = 'wp_user_id = %d';
            $params[] = $wp_user_id;
        }

        if ( ! empty( $ghl_id ) ) {
            $where_clauses[] = 'ghl_id = %s';
            $params[] = $ghl_id;
        }

        $where = implode( ' OR ', $where_clauses );

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE {$where}",
                ...$params
            )
        );

        $this->log_debug( 'Retrieved user data', [
            'ghl_id'     => $ghl_id,
            'wp_user_id' => $wp_user_id,
            'found'      => ! empty( $result ),
        ] );

        return $result;
    }

    /**
     * Get or create user data record.
     *
     * @param string|null $ghl_id     GoHighLevel contact ID.
     * @param int|null    $wp_user_id WordPress user ID.
     * @return object|null
     */
    public function get_or_create_user_data( ?string $ghl_id = null, ?int $wp_user_id = null ): ?object {
        // Check for existing record
        $result = $this->get_user_data( $ghl_id, $wp_user_id );

        if ( $result ) {
            return $result;
        }

        // Create new record
        if ( empty( $wp_user_id ) ) {
            $this->log_error( 'Cannot create user data without WordPress user ID' );
            return null;
        }

        $insert_result = $this->wpdb->insert(
            $this->table_name,
            [
                'ghl_id'        => $ghl_id ?? '',
                'wp_user_id'    => $wp_user_id,
                'search_count'  => 0,
                'total_searches' => 0,
                'last_searched' => current_time( 'mysql' ),
                'created_at'    => current_time( 'mysql' ),
            ],
            [ '%s', '%d', '%d', '%d', '%s', '%s' ]
        );

        if ( false === $insert_result ) {
            $this->log_error( 'Failed to create user data', [
                'error' => $this->wpdb->last_error,
            ] );
            return null;
        }

        $this->log_debug( 'Created new user data record', [
            'wp_user_id' => $wp_user_id,
            'ghl_id'     => $ghl_id,
        ] );

        return $this->get_user_data( $ghl_id, $wp_user_id );
    }

    /**
     * Increment user search count.
     *
     * @param string|null $ghl_id     GoHighLevel contact ID.
     * @param int|null    $wp_user_id WordPress user ID.
     * @return array{search_count: int, total_searches: int, milestone_reached: int|null}|null
     */
    public function increment_search_count( ?string $ghl_id = null, ?int $wp_user_id = null ): ?array {
        $user_data = $this->get_or_create_user_data( $ghl_id, $wp_user_id );

        if ( ! $user_data ) {
            return null;
        }

        $new_search_count = (int) $user_data->search_count + 1;
        $new_total = (int) ( $user_data->total_searches ?? 0 ) + 1;

        $update_result = $this->wpdb->update(
            $this->table_name,
            [
                'search_count'   => $new_search_count,
                'total_searches' => $new_total,
                'last_searched'  => current_time( 'mysql' ),
            ],
            [ 'id' => $user_data->id ],
            [ '%d', '%d', '%s' ],
            [ '%d' ]
        );

        if ( false === $update_result ) {
            $this->log_error( 'Failed to increment search count', [
                'error' => $this->wpdb->last_error,
            ] );
            return null;
        }

        // Update user meta for external sync and check for milestone
        $milestone_reached = null;
        if ( $wp_user_id ) {
            update_user_meta( $wp_user_id, 'guestify_total_searches', $new_total );
            $milestone_reached = $this->sync_milestones( $wp_user_id, $new_total );
        }

        $this->log_debug( 'Incremented search count', [
            'wp_user_id'        => $wp_user_id,
            'search_count'      => $new_search_count,
            'total_searches'    => $new_total,
            'milestone_reached' => $milestone_reached,
        ] );

        return [
            'search_count'      => $new_search_count,
            'total_searches'    => $new_total,
            'milestone_reached' => $milestone_reached,
        ];
    }

    /**
     * Check if a milestone was reached and sync to external CRM.
     *
     * @param int $wp_user_id    WordPress user ID.
     * @param int $total_searches Total search count.
     * @return int|null The milestone reached, or null if no milestone.
     */
    private function sync_milestones( int $wp_user_id, int $total_searches ): ?int {
        $milestones = $this->get_celebration_milestones();

        if ( in_array( $total_searches, $milestones, true ) ) {
            if ( function_exists( 'wp_fusion' ) ) {
                wp_fusion()->user->push_user_meta( $wp_user_id, [
                    'guestify_total_searches' => $total_searches,
                ] );
            }

            $this->log_info( 'Milestone reached', [
                'wp_user_id'     => $wp_user_id,
                'total_searches' => $total_searches,
            ] );

            return $total_searches;
        }

        return null;
    }

    /**
     * Get celebration milestones from settings.
     *
     * @return int[]
     */
    private function get_celebration_milestones(): array {
        if ( class_exists( 'Podcast_Prospector_Settings' ) ) {
            return Podcast_Prospector_Settings::get_instance()->get_celebration_milestones();
        }
        // Fallback to defaults if settings class not available
        return [ 5, 100, 250, 500 ];
    }

    /**
     * Reset search count if subscription has renewed.
     *
     * @param string|null $ghl_id     GoHighLevel contact ID.
     * @param int|null    $wp_user_id WordPress user ID.
     * @return bool Whether reset occurred.
     */
    public function reset_search_cap_if_needed( ?string $ghl_id = null, ?int $wp_user_id = null ): bool {
        $user_data = $this->get_user_data( $ghl_id, $wp_user_id );

        if ( ! $user_data ) {
            return false;
        }

        // Ensure we have a user ID
        $wp_user_id = $wp_user_id ?: (int) $user_data->wp_user_id;
        if ( ! $wp_user_id ) {
            return false;
        }

        // Get renewal info from user meta
        $last_renewal_date = get_user_meta( $wp_user_id, 'guestify_last_renewal_date', true );
        $last_reset_date = $user_data->last_reset_date;

        if ( empty( $last_renewal_date ) ) {
            return false;
        }

        // Compare dates (YYYY-MM-DD format)
        $renewal_date_fmt = gmdate( 'Y-m-d', strtotime( $last_renewal_date ) );
        $reset_date_fmt = gmdate( 'Y-m-d', strtotime( $last_reset_date ) );

        // Handle initial setup or different renewal date
        $should_reset = empty( $last_reset_date )
            || '0000-00-00' === substr( $last_reset_date, 0, 10 )
            || $renewal_date_fmt !== $reset_date_fmt;

        if ( $should_reset ) {
            $new_reset_datetime = $renewal_date_fmt . ' 00:00:00';

            $this->wpdb->update(
                $this->table_name,
                [
                    'search_count'    => 0,
                    'last_reset_date' => $new_reset_datetime,
                ],
                [ 'id' => $user_data->id ],
                [ '%d', '%s' ],
                [ '%d' ]
            );

            $this->log_info( 'Search cap reset for subscription renewal', [
                'wp_user_id'    => $wp_user_id,
                'renewal_date'  => $renewal_date_fmt,
            ] );

            return true;
        }

        return false;
    }

    /**
     * Update GHL ID for a user.
     *
     * @param int    $wp_user_id WordPress user ID.
     * @param string $ghl_id     GoHighLevel contact ID.
     * @return bool
     */
    public function update_ghl_id( int $wp_user_id, string $ghl_id ): bool {
        $result = $this->wpdb->update(
            $this->table_name,
            [ 'ghl_id' => $ghl_id ],
            [ 'wp_user_id' => $wp_user_id ],
            [ '%s' ],
            [ '%d' ]
        );

        return false !== $result;
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
            $this->logger->debug( '[Database] ' . $message, $context );
        }
    }

    /**
     * Log info message.
     *
     * @param string $message Log message.
     * @param array  $context Additional context.
     * @return void
     */
    private function log_info( string $message, array $context = [] ): void {
        if ( $this->logger ) {
            $this->logger->info( '[Database] ' . $message, $context );
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
            $this->logger->error( '[Database] ' . $message, $context );
        }
    }
}
