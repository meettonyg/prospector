<?php
/**
 * Agency Bridge for Podcast Prospector
 *
 * Integrates Podcast Prospector with the Guestify Core agency system.
 * Handles scoping, transfer hooks, search quota management, and export data.
 *
 * @package Podcast_Prospector
 * @since 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Podcast_Prospector_Agency_Bridge
 *
 * Bridges Podcast Prospector with the agency management system.
 */
class Podcast_Prospector_Agency_Bridge {

    /**
     * Instance.
     *
     * @var Podcast_Prospector_Agency_Bridge|null
     */
    private static ?Podcast_Prospector_Agency_Bridge $instance = null;

    /**
     * Get instance.
     *
     * @return Podcast_Prospector_Agency_Bridge
     */
    public static function get_instance(): Podcast_Prospector_Agency_Bridge {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks(): void {
        // Transfer handler - update prospector data ownership when client is transferred
        add_action('gfy_client_ownership_transferred', [$this, 'handle_client_transfer'], 10, 3);

        // Export data handler - include prospector data in client exports
        add_filter('gfy_client_export_data', [$this, 'add_export_data'], 10, 3);

        // Search quota integration with agency limits
        add_filter('podcast_prospector_can_search', [$this, 'check_agency_search_quota'], 10, 2);
        add_action('podcast_prospector_search_performed', [$this, 'record_agency_search_usage']);

        // Override user ID for search tracking
        add_filter('podcast_prospector_get_user_id', [$this, 'get_scoped_user_id']);

        // Query scoping
        add_filter('podcast_prospector_search_results_query', [$this, 'scope_search_results']);

        // Stamp search activity with agency context
        add_action('podcast_prospector_user_data_created', [$this, 'stamp_user_data_agency_context']);

        // REST API context injection
        add_filter('podcast_prospector_rest_response', [$this, 'add_agency_context_to_response']);
    }

    /**
     * Handle client transfer - update prospector data ownership.
     *
     * @param int $client_id      Client ID being transferred
     * @param int $target_user_id User receiving the data
     * @param int $agency_id      Former agency ID
     */
    public function handle_client_transfer(int $client_id, int $target_user_id, int $agency_id): void {
        global $wpdb;

        $table = $wpdb->prefix . 'podcast_prospector';

        // Check if table has agency columns
        $has_agency = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'agency_id'",
            DB_NAME,
            $table
        ));

        if ($has_agency) {
            // Update records matching agency_id and client_id
            $updated = $wpdb->query($wpdb->prepare(
                "UPDATE {$table}
                 SET wp_user_id = %d, agency_id = NULL, client_id = NULL
                 WHERE agency_id = %d AND client_id = %d",
                $target_user_id,
                $agency_id,
                $client_id
            ));

            if ($updated > 0) {
                if ( class_exists( 'Podcast_Prospector_Logger' ) ) {
                    Podcast_Prospector_Logger::get_instance()->info( "Agency Bridge: Transferred {$updated} records from client {$client_id} to user {$target_user_id}" );
                }
            }
        }

        // Also transfer any saved searches from user_meta if applicable
        $client_users = $this->get_client_user_ids($agency_id, $client_id);
        foreach ($client_users as $user_id) {
            $saved_searches = get_user_meta($user_id, 'podcast_prospector_saved_searches', true);
            if ($saved_searches) {
                // Merge saved searches to target user
                $target_searches = get_user_meta($target_user_id, 'podcast_prospector_saved_searches', true);
                $target_searches = is_array($target_searches) ? $target_searches : [];
                $merged = array_merge($target_searches, (array) $saved_searches);
                update_user_meta($target_user_id, 'podcast_prospector_saved_searches', $merged);
            }
        }
    }

    /**
     * Get user IDs associated with a client.
     *
     * @param int $agency_id Agency ID
     * @param int $client_id Client ID
     * @return array User IDs
     */
    private function get_client_user_ids(int $agency_id, int $client_id): array {
        global $wpdb;

        if (!class_exists('GFY_Agency_Client_Manager')) {
            return [];
        }

        $client = GFY_Agency_Client_Manager::get($client_id);
        if ($client && !empty($client->user_id)) {
            return [(int) $client->user_id];
        }

        return [];
    }

    /**
     * Add prospector data to client export.
     *
     * @param array $export_data Current export data
     * @param int   $client_id   Client ID
     * @param int   $agency_id   Agency ID
     * @return array Modified export data
     */
    public function add_export_data(array $export_data, int $client_id, int $agency_id): array {
        global $wpdb;

        $export_data['podcast_prospector'] = [
            'search_stats' => null,
            'saved_searches' => [],
            'search_history' => [],
        ];

        // Export search stats
        $table = $wpdb->prefix . 'podcast_prospector';
        $has_agency = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'agency_id'",
            DB_NAME,
            $table
        ));

        if ($has_agency) {
            $export_data['podcast_prospector']['search_stats'] = $wpdb->get_row($wpdb->prepare(
                "SELECT search_count, total_searches, last_searched, last_reset_date, created_at
                 FROM {$table}
                 WHERE agency_id = %d AND client_id = %d",
                $agency_id,
                $client_id
            ), ARRAY_A);
        }

        // Export saved searches from user meta
        $client_users = $this->get_client_user_ids($agency_id, $client_id);
        foreach ($client_users as $user_id) {
            $saved = get_user_meta($user_id, 'podcast_prospector_saved_searches', true);
            if ($saved) {
                $export_data['podcast_prospector']['saved_searches'] = array_merge(
                    $export_data['podcast_prospector']['saved_searches'],
                    (array) $saved
                );
            }
        }

        return $export_data;
    }

    /**
     * Check agency search quota before allowing search.
     *
     * @param bool $can_search Current permission
     * @param int  $user_id    User ID
     * @return bool
     */
    public function check_agency_search_quota(bool $can_search, int $user_id): bool {
        if (!function_exists('gfy_is_agency_context') || !gfy_is_agency_context()) {
            return $can_search;
        }

        if (!class_exists('GFY_Agency_Limits')) {
            return $can_search;
        }

        $agency_id = GFY_Agency_Context::get_agency_id();
        $client_id = GFY_Agency_Context::get_client_id();

        // Use the 3-tier quota check from guestify-core
        $quota_check = GFY_Agency_Limits::can_search($agency_id, $user_id, $client_id);

        if (!$quota_check['allowed']) {
            // Store reason for display
            set_transient(
                'prospector_quota_error_' . $user_id,
                $quota_check['reason'],
                60
            );
        }

        return $quota_check['allowed'];
    }

    /**
     * Record search usage against agency quota.
     *
     * @param array $search_data Search data
     */
    public function record_agency_search_usage(array $search_data): void {
        if (!function_exists('gfy_is_agency_context') || !gfy_is_agency_context()) {
            return;
        }

        if (!class_exists('GFY_Agency_Limits')) {
            return;
        }

        $agency_id = GFY_Agency_Context::get_agency_id();
        $user_id = get_current_user_id();
        $client_id = GFY_Agency_Context::get_client_id();

        // Record the search against agency quota
        GFY_Agency_Limits::record_search($agency_id, $user_id, $client_id);

        // Also record to agency activity log
        if (class_exists('GFY_Agency_Activity_Logger')) {
            GFY_Agency_Activity_Logger::log(
                $agency_id,
                $user_id,
                'podcast_search',
                sprintf('Searched for: %s', $search_data['query'] ?? 'unknown'),
                [
                    'client_id' => $client_id,
                    'results_count' => $search_data['results_count'] ?? 0,
                ]
            );
        }
    }

    /**
     * Get scoped user ID based on agency context.
     *
     * @param int $user_id Current user ID
     * @return int Scoped user ID
     */
    public function get_scoped_user_id(int $user_id): int {
        // In agency context, we still use the actual user ID
        // but the quota is managed at the agency level
        return $user_id;
    }

    /**
     * Scope search results based on agency context.
     *
     * Note: Search results themselves are not scoped (podcasts are global),
     * but we may want to filter based on agency preferences.
     *
     * @param array $args Query args
     * @return array Modified args
     */
    public function scope_search_results(array $args): array {
        if (!function_exists('gfy_is_agency_context') || !gfy_is_agency_context()) {
            return $args;
        }

        // Could add agency-specific filters here (e.g., exclude certain categories)
        $agency_id = GFY_Agency_Context::get_agency_id();

        // Get agency search preferences if any
        $agency_prefs = get_option("gfy_agency_{$agency_id}_search_prefs", []);
        if (!empty($agency_prefs['excluded_categories'])) {
            $args['exclude_categories'] = $agency_prefs['excluded_categories'];
        }

        return $args;
    }

    /**
     * Stamp user data with agency context.
     *
     * @param int $record_id Record ID
     */
    public function stamp_user_data_agency_context(int $record_id): void {
        global $wpdb;

        if (!function_exists('gfy_is_agency_context') || !gfy_is_agency_context()) {
            return;
        }

        $table = $wpdb->prefix . 'podcast_prospector';

        // Check if columns exist
        $has_agency = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'agency_id'",
            DB_NAME,
            $table
        ));

        if ($has_agency) {
            $agency_id = GFY_Agency_Context::get_agency_id();
            $client_id = GFY_Agency_Context::get_client_id();

            $wpdb->update(
                $table,
                [
                    'agency_id' => $agency_id,
                    'client_id' => $client_id,
                ],
                ['id' => $record_id],
                ['%d', '%d'],
                ['%d']
            );
        }
    }

    /**
     * Add agency context to REST API responses.
     *
     * @param array $response Response data
     * @return array Modified response
     */
    public function add_agency_context_to_response(array $response): array {
        if (!function_exists('gfy_is_agency_context')) {
            return $response;
        }

        $response['agency_context'] = [
            'is_agency' => gfy_is_agency_context(),
            'agency_id' => gfy_is_agency_context() ? GFY_Agency_Context::get_agency_id() : null,
            'client_id' => gfy_is_agency_context() ? GFY_Agency_Context::get_client_id() : null,
        ];

        // Add quota info if in agency context
        if (gfy_is_agency_context() && class_exists('GFY_Agency_Limits')) {
            $agency_id = GFY_Agency_Context::get_agency_id();
            $user_id = get_current_user_id();
            $client_id = GFY_Agency_Context::get_client_id();

            $quota = GFY_Agency_Limits::get_search_quota($agency_id, $user_id, $client_id);
            $response['search_quota'] = $quota;
        }

        return $response;
    }

    /**
     * Get search count by agency.
     *
     * @param int $agency_id Agency ID
     * @return int Count
     */
    public static function count_searches_by_agency(int $agency_id): int {
        global $wpdb;

        $table = $wpdb->prefix . 'podcast_prospector';
        $has_agency = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'agency_id'",
            DB_NAME,
            $table
        ));

        if (!$has_agency) {
            return 0;
        }

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(search_count), 0) FROM {$table} WHERE agency_id = %d",
            $agency_id
        ));
    }

    /**
     * Get search count by client.
     *
     * @param int $client_id Client ID
     * @return int Count
     */
    public static function count_searches_by_client(int $client_id): int {
        global $wpdb;

        $table = $wpdb->prefix . 'podcast_prospector';
        $has_agency = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'client_id'",
            DB_NAME,
            $table
        ));

        if (!$has_agency) {
            return 0;
        }

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(search_count), 0) FROM {$table} WHERE client_id = %d",
            $client_id
        ));
    }

    /**
     * Get total searches by agency (all time).
     *
     * @param int $agency_id Agency ID
     * @return int Count
     */
    public static function count_total_searches_by_agency(int $agency_id): int {
        global $wpdb;

        $table = $wpdb->prefix . 'podcast_prospector';
        $has_agency = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'agency_id'",
            DB_NAME,
            $table
        ));

        if (!$has_agency) {
            return 0;
        }

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(total_searches), 0) FROM {$table} WHERE agency_id = %d",
            $agency_id
        ));
    }

    /**
     * Reset search quota for all agency members.
     *
     * Called during agency subscription renewal.
     *
     * @param int $agency_id Agency ID
     * @return int Number of records reset
     */
    public static function reset_agency_search_quota(int $agency_id): int {
        global $wpdb;

        $table = $wpdb->prefix . 'podcast_prospector';
        $has_agency = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'agency_id'",
            DB_NAME,
            $table
        ));

        if (!$has_agency) {
            return 0;
        }

        return (int) $wpdb->query($wpdb->prepare(
            "UPDATE {$table}
             SET search_count = 0, last_reset_date = %s
             WHERE agency_id = %d",
            current_time('mysql'),
            $agency_id
        ));
    }
}

// Initialize on plugins_loaded if guestify-core is active
add_action('plugins_loaded', function() {
    if (class_exists('Guestify_Core')) {
        Podcast_Prospector_Agency_Bridge::get_instance();
    }
}, 20);
