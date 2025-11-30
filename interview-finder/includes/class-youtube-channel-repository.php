<?php
/**
 * YouTube Channel Repository Class
 *
 * Handles lookups against the wp_pit_podcasts table for YouTube channel deduplication.
 *
 * @package Interview_Finder
 * @since 2.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Interview_Finder_YouTube_Channel_Repository
 *
 * Provides methods to check for existing YouTube channels in the database
 * to support deduplication when importing YouTube videos.
 */
class Interview_Finder_YouTube_Channel_Repository {

    /**
     * Table name without prefix.
     *
     * @var string
     */
    const TABLE_NAME = 'pit_podcasts';

    /**
     * YouTube channel column name.
     *
     * @var string
     */
    const CHANNEL_COLUMN = 'youtube_channel_id';

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
     * Channel cache for current request.
     *
     * @var array<string, bool>
     */
    private array $cache = [];

    /**
     * Whether the youtube_channel_id column exists.
     *
     * @var bool|null
     */
    private ?bool $column_exists = null;

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
     * Check if YouTube deduplication is available.
     *
     * @return bool
     */
    public function is_available(): bool {
        return $this->table_exists() && $this->column_exists();
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
     * Check if the youtube_channel_id column exists.
     *
     * @return bool
     */
    public function column_exists(): bool {
        if ( null !== $this->column_exists ) {
            return $this->column_exists;
        }

        if ( ! $this->table_exists() ) {
            $this->column_exists = false;
            return false;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $columns = $this->wpdb->get_results(
            "SHOW COLUMNS FROM {$this->table_name} LIKE '" . self::CHANNEL_COLUMN . "'"
        );

        $this->column_exists = ! empty( $columns );

        if ( ! $this->column_exists ) {
            $this->log_debug( 'YouTube channel column does not exist in table' );
        }

        return $this->column_exists;
    }

    /**
     * Check if a YouTube channel ID already exists in the database.
     *
     * @param string $channel_id YouTube channel ID.
     * @return bool True if channel exists, false otherwise.
     */
    public function channel_exists( string $channel_id ): bool {
        if ( empty( $channel_id ) || ! $this->is_available() ) {
            return false;
        }

        // Check cache first
        if ( array_key_exists( $channel_id, $this->cache ) ) {
            return $this->cache[ $channel_id ];
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $exists = (bool) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT 1 FROM {$this->table_name} WHERE " . self::CHANNEL_COLUMN . " = %s LIMIT 1",
                $channel_id
            )
        );

        $this->cache[ $channel_id ] = $exists;

        return $exists;
    }

    /**
     * Check multiple YouTube channel IDs for existence (batch lookup).
     *
     * @param array $channel_ids Array of YouTube channel IDs.
     * @return array<string, bool> Map of channel ID to existence status.
     */
    public function channels_exist( array $channel_ids ): array {
        if ( empty( $channel_ids ) || ! $this->is_available() ) {
            // Return all as false (not existing) if dedup not available
            return array_fill_keys( $channel_ids, false );
        }

        $results = [];
        $ids_to_fetch = [];

        // Check cache first
        foreach ( $channel_ids as $channel_id ) {
            if ( array_key_exists( $channel_id, $this->cache ) ) {
                $results[ $channel_id ] = $this->cache[ $channel_id ];
            } else {
                $ids_to_fetch[] = $channel_id;
            }
        }

        // Fetch remaining from database
        if ( ! empty( $ids_to_fetch ) ) {
            $placeholders = implode( ', ', array_fill( 0, count( $ids_to_fetch ), '%s' ) );

            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $found = $this->wpdb->get_col(
                $this->wpdb->prepare(
                    "SELECT " . self::CHANNEL_COLUMN . " FROM {$this->table_name}
                     WHERE " . self::CHANNEL_COLUMN . " IN ({$placeholders})",
                    ...$ids_to_fetch
                )
            );

            $found_set = array_flip( $found ?? [] );

            // Update results and cache
            foreach ( $ids_to_fetch as $channel_id ) {
                $exists = isset( $found_set[ $channel_id ] );
                $results[ $channel_id ] = $exists;
                $this->cache[ $channel_id ] = $exists;
            }
        }

        $this->log_debug( 'Batch channel lookup', [
            'requested' => count( $channel_ids ),
            'existing'  => count( array_filter( $results ) ),
        ] );

        return $results;
    }

    /**
     * Get podcast data for a YouTube channel ID.
     *
     * @param string $channel_id YouTube channel ID.
     * @return array|null Podcast data or null if not found.
     */
    public function get_podcast_by_channel( string $channel_id ): ?array {
        if ( empty( $channel_id ) || ! $this->is_available() ) {
            return null;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE " . self::CHANNEL_COLUMN . " = %s LIMIT 1",
                $channel_id
            ),
            ARRAY_A
        );

        return $result ?: null;
    }

    /**
     * Mark YouTube search results with deduplication info.
     *
     * Adds 'is_duplicate' and 'existing_podcast' fields to each item.
     *
     * @param array $items YouTube search result items.
     * @return array Items with deduplication info added.
     */
    public function mark_duplicates( array $items ): array {
        if ( empty( $items ) ) {
            return $items;
        }

        // Extract channel IDs
        $channel_ids = [];
        foreach ( $items as $item ) {
            if ( ! empty( $item['channelId'] ) ) {
                $channel_ids[] = $item['channelId'];
            }
        }

        // Batch check for existing channels
        $existing = $this->channels_exist( array_unique( $channel_ids ) );

        // Mark items
        foreach ( $items as &$item ) {
            $channel_id = $item['channelId'] ?? '';
            $item['is_duplicate'] = $existing[ $channel_id ] ?? false;
        }

        return $items;
    }

    /**
     * Get statistics about YouTube channels in database.
     *
     * @return array Statistics.
     */
    public function get_stats(): array {
        if ( ! $this->is_available() ) {
            return [
                'available'      => false,
                'table_exists'   => $this->table_exists(),
                'column_exists'  => $this->column_exists(),
                'total_channels' => 0,
            ];
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $total = (int) $this->wpdb->get_var(
            "SELECT COUNT(DISTINCT " . self::CHANNEL_COLUMN . ") FROM {$this->table_name}
             WHERE " . self::CHANNEL_COLUMN . " IS NOT NULL AND " . self::CHANNEL_COLUMN . " != ''"
        );

        return [
            'available'      => true,
            'table_exists'   => true,
            'column_exists'  => true,
            'total_channels' => $total,
        ];
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
            $this->logger->debug( '[YouTubeChannel] ' . $message, $context );
        }
    }
}
