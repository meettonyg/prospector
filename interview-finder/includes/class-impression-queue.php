<?php
/**
 * Impression Queue Class
 *
 * Handles queued/batched impression and click tracking for scalability.
 * Uses WordPress transients for queue storage with periodic batch processing.
 *
 * @package Interview_Finder
 * @since 2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Interview_Finder_Impression_Queue
 *
 * Queues impressions and clicks for batch processing to reduce database load.
 */
class Interview_Finder_Impression_Queue {

    /**
     * Queue transient key for impressions.
     *
     * @var string
     */
    const IMPRESSION_QUEUE_KEY = 'if_impression_queue';

    /**
     * Queue transient key for clicks.
     *
     * @var string
     */
    const CLICK_QUEUE_KEY = 'if_click_queue';

    /**
     * Lock transient key.
     *
     * @var string
     */
    const LOCK_KEY = 'if_queue_processing_lock';

    /**
     * Batch size for processing.
     *
     * @var int
     */
    const BATCH_SIZE = 100;

    /**
     * Queue flush threshold (process when queue reaches this size).
     *
     * @var int
     */
    const FLUSH_THRESHOLD = 50;

    /**
     * Singleton instance.
     *
     * @var Interview_Finder_Impression_Queue|null
     */
    private static ?Interview_Finder_Impression_Queue $instance = null;

    /**
     * WordPress database object.
     *
     * @var wpdb
     */
    private wpdb $wpdb;

    /**
     * Logger instance.
     *
     * @var Interview_Finder_Logger|null
     */
    private ?Interview_Finder_Logger $logger = null;

    /**
     * In-memory queue for current request.
     *
     * @var array
     */
    private array $memory_queue = [
        'impressions' => [],
        'clicks'      => [],
    ];

    /**
     * Get singleton instance.
     *
     * @return Interview_Finder_Impression_Queue
     */
    public static function get_instance(): Interview_Finder_Impression_Queue {
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

        if ( class_exists( 'Interview_Finder_Logger' ) ) {
            $this->logger = Interview_Finder_Logger::get_instance();
        }

        // Register shutdown handler to flush memory queue
        register_shutdown_function( [ $this, 'flush_memory_queue' ] );

        // Schedule cron job for batch processing
        add_action( 'if_process_impression_queue', [ $this, 'process_queue' ] );

        if ( ! wp_next_scheduled( 'if_process_impression_queue' ) ) {
            wp_schedule_event( time(), 'every_minute', 'if_process_impression_queue' );
        }
    }

    /**
     * Queue an impression for batch processing.
     *
     * @param int $sponsored_id Sponsored listing ID.
     * @return void
     */
    public function queue_impression( int $sponsored_id ): void {
        $key = (string) $sponsored_id;

        if ( ! isset( $this->memory_queue['impressions'][ $key ] ) ) {
            $this->memory_queue['impressions'][ $key ] = 0;
        }

        $this->memory_queue['impressions'][ $key ]++;
    }

    /**
     * Queue a click for batch processing.
     *
     * @param int $sponsored_id Sponsored listing ID.
     * @return void
     */
    public function queue_click( int $sponsored_id ): void {
        $key = (string) $sponsored_id;

        if ( ! isset( $this->memory_queue['clicks'][ $key ] ) ) {
            $this->memory_queue['clicks'][ $key ] = 0;
        }

        $this->memory_queue['clicks'][ $key ]++;
    }

    /**
     * Flush memory queue to persistent storage (called on shutdown).
     *
     * @return void
     */
    public function flush_memory_queue(): void {
        // Flush impressions
        if ( ! empty( $this->memory_queue['impressions'] ) ) {
            $this->add_to_persistent_queue( self::IMPRESSION_QUEUE_KEY, $this->memory_queue['impressions'] );
            $this->memory_queue['impressions'] = [];
        }

        // Flush clicks
        if ( ! empty( $this->memory_queue['clicks'] ) ) {
            $this->add_to_persistent_queue( self::CLICK_QUEUE_KEY, $this->memory_queue['clicks'] );
            $this->memory_queue['clicks'] = [];
        }

        // Check if we should trigger immediate processing
        $this->maybe_trigger_processing();
    }

    /**
     * Add items to persistent queue (transient-based).
     *
     * @param string $queue_key Queue transient key.
     * @param array  $items     Items to add (id => count).
     * @return void
     */
    private function add_to_persistent_queue( string $queue_key, array $items ): void {
        // Use object cache if available, otherwise transient
        $queue = $this->get_cache( $queue_key );

        if ( ! is_array( $queue ) ) {
            $queue = [];
        }

        // Merge counts
        foreach ( $items as $id => $count ) {
            if ( ! isset( $queue[ $id ] ) ) {
                $queue[ $id ] = 0;
            }
            $queue[ $id ] += $count;
        }

        // Save back to cache (5 minute expiry as safety)
        $this->set_cache( $queue_key, $queue, 300 );
    }

    /**
     * Check if queue is large enough to trigger immediate processing.
     *
     * @return void
     */
    private function maybe_trigger_processing(): void {
        $impression_queue = $this->get_cache( self::IMPRESSION_QUEUE_KEY );
        $click_queue = $this->get_cache( self::CLICK_QUEUE_KEY );

        $total_items = count( $impression_queue ?? [] ) + count( $click_queue ?? [] );

        if ( $total_items >= self::FLUSH_THRESHOLD ) {
            // Spawn async processing
            $this->spawn_async_processing();
        }
    }

    /**
     * Spawn async processing via wp_remote_post to self.
     *
     * @return void
     */
    private function spawn_async_processing(): void {
        // Use WP Cron to trigger processing
        wp_schedule_single_event( time(), 'if_process_impression_queue' );
    }

    /**
     * Process queued impressions and clicks (called by cron).
     *
     * @return void
     */
    public function process_queue(): void {
        // Acquire lock to prevent concurrent processing
        if ( ! $this->acquire_lock() ) {
            $this->log_debug( 'Queue processing skipped - lock held' );
            return;
        }

        try {
            $this->process_impressions();
            $this->process_clicks();
        } finally {
            $this->release_lock();
        }
    }

    /**
     * Process queued impressions.
     *
     * @return void
     */
    private function process_impressions(): void {
        $queue = $this->get_cache( self::IMPRESSION_QUEUE_KEY );

        if ( empty( $queue ) ) {
            return;
        }

        $table = $this->wpdb->prefix . 'interview_finder_sponsored';
        $stats_table = $this->wpdb->prefix . 'interview_finder_sponsored_stats';
        $today = current_time( 'Y-m-d' );
        $processed = 0;

        foreach ( $queue as $sponsored_id => $count ) {
            if ( $processed >= self::BATCH_SIZE ) {
                break;
            }

            // Update total impressions
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $this->wpdb->query(
                $this->wpdb->prepare(
                    "UPDATE {$table} SET total_impressions = total_impressions + %d WHERE id = %d",
                    $count,
                    $sponsored_id
                )
            );

            // Update daily stats using INSERT ... ON DUPLICATE KEY UPDATE
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $this->wpdb->query(
                $this->wpdb->prepare(
                    "INSERT INTO {$stats_table} (sponsored_id, stat_date, impressions)
                     VALUES (%d, %s, %d)
                     ON DUPLICATE KEY UPDATE impressions = impressions + %d",
                    $sponsored_id,
                    $today,
                    $count,
                    $count
                )
            );

            unset( $queue[ $sponsored_id ] );
            $processed++;
        }

        // Save remaining queue
        if ( empty( $queue ) ) {
            $this->delete_cache( self::IMPRESSION_QUEUE_KEY );
        } else {
            $this->set_cache( self::IMPRESSION_QUEUE_KEY, $queue, 300 );
        }

        $this->log_debug( 'Processed impressions', [ 'count' => $processed ] );
    }

    /**
     * Process queued clicks.
     *
     * @return void
     */
    private function process_clicks(): void {
        $queue = $this->get_cache( self::CLICK_QUEUE_KEY );

        if ( empty( $queue ) ) {
            return;
        }

        $table = $this->wpdb->prefix . 'interview_finder_sponsored';
        $stats_table = $this->wpdb->prefix . 'interview_finder_sponsored_stats';
        $today = current_time( 'Y-m-d' );
        $processed = 0;

        foreach ( $queue as $sponsored_id => $count ) {
            if ( $processed >= self::BATCH_SIZE ) {
                break;
            }

            // Update total clicks
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $this->wpdb->query(
                $this->wpdb->prepare(
                    "UPDATE {$table} SET total_clicks = total_clicks + %d WHERE id = %d",
                    $count,
                    $sponsored_id
                )
            );

            // Update daily stats
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $this->wpdb->query(
                $this->wpdb->prepare(
                    "INSERT INTO {$stats_table} (sponsored_id, stat_date, clicks)
                     VALUES (%d, %s, %d)
                     ON DUPLICATE KEY UPDATE clicks = clicks + %d",
                    $sponsored_id,
                    $today,
                    $count,
                    $count
                )
            );

            unset( $queue[ $sponsored_id ] );
            $processed++;
        }

        // Save remaining queue
        if ( empty( $queue ) ) {
            $this->delete_cache( self::CLICK_QUEUE_KEY );
        } else {
            $this->set_cache( self::CLICK_QUEUE_KEY, $queue, 300 );
        }

        $this->log_debug( 'Processed clicks', [ 'count' => $processed ] );
    }

    /**
     * Acquire processing lock.
     *
     * @return bool
     */
    private function acquire_lock(): bool {
        $lock = $this->get_cache( self::LOCK_KEY );

        if ( $lock ) {
            return false;
        }

        $this->set_cache( self::LOCK_KEY, time(), 60 ); // 60 second lock
        return true;
    }

    /**
     * Release processing lock.
     *
     * @return void
     */
    private function release_lock(): void {
        $this->delete_cache( self::LOCK_KEY );
    }

    /**
     * Get value from cache (object cache or transient).
     *
     * @param string $key Cache key.
     * @return mixed
     */
    private function get_cache( string $key ) {
        if ( wp_using_ext_object_cache() ) {
            return wp_cache_get( $key, 'interview_finder' );
        }
        return get_transient( $key );
    }

    /**
     * Set value in cache.
     *
     * @param string $key        Cache key.
     * @param mixed  $value      Value to cache.
     * @param int    $expiration Expiration in seconds.
     * @return void
     */
    private function set_cache( string $key, $value, int $expiration = 300 ): void {
        if ( wp_using_ext_object_cache() ) {
            wp_cache_set( $key, $value, 'interview_finder', $expiration );
        } else {
            set_transient( $key, $value, $expiration );
        }
    }

    /**
     * Delete value from cache.
     *
     * @param string $key Cache key.
     * @return void
     */
    private function delete_cache( string $key ): void {
        if ( wp_using_ext_object_cache() ) {
            wp_cache_delete( $key, 'interview_finder' );
        } else {
            delete_transient( $key );
        }
    }

    /**
     * Get queue statistics.
     *
     * @return array
     */
    public function get_stats(): array {
        $impression_queue = $this->get_cache( self::IMPRESSION_QUEUE_KEY ) ?? [];
        $click_queue = $this->get_cache( self::CLICK_QUEUE_KEY ) ?? [];

        return [
            'impressions_queued' => array_sum( $impression_queue ),
            'clicks_queued'      => array_sum( $click_queue ),
            'unique_listings'    => count( $impression_queue ) + count( $click_queue ),
            'using_object_cache' => wp_using_ext_object_cache(),
        ];
    }

    /**
     * Force immediate queue processing (for testing/admin).
     *
     * @return array Processing results.
     */
    public function force_process(): array {
        $before = $this->get_stats();
        $this->flush_memory_queue();
        $this->process_queue();
        $after = $this->get_stats();

        return [
            'before' => $before,
            'after'  => $after,
        ];
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
            $this->logger->debug( '[ImpressionQueue] ' . $message, $context );
        }
    }
}

// Register custom cron interval
add_filter( 'cron_schedules', function ( $schedules ) {
    $schedules['every_minute'] = [
        'interval' => 60,
        'display'  => __( 'Every Minute', 'interview-finder' ),
    ];
    return $schedules;
} );
