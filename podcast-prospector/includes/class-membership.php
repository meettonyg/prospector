<?php
/**
 * Interview Finder Membership Class
 *
 * Handles membership tiers and feature access control.
 *
 * @package Podcast_Prospector
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Podcast_Prospector_Membership
 *
 * Manages membership levels and associated permissions/limits.
 */
class Podcast_Prospector_Membership {

    /**
     * Membership tier constants.
     */
    const TIER_ZENITH      = 'ZENITH';
    const TIER_VELOCITY    = 'VELOCITY';
    const TIER_ACCELERATOR = 'ACCELERATOR';

    /**
     * Singleton instance.
     *
     * @var Podcast_Prospector_Membership|null
     */
    private static ?Podcast_Prospector_Membership $instance = null;

    /**
     * Membership configuration.
     *
     * @var array
     */
    private array $config;

    /**
     * Default settings for unknown membership.
     *
     * @var array
     */
    private array $defaults;

    /**
     * Get singleton instance.
     *
     * @return Podcast_Prospector_Membership
     */
    public static function get_instance(): Podcast_Prospector_Membership {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor.
     */
    private function __construct() {
        $this->init_config();
    }

    /**
     * Initialize membership configuration.
     *
     * @return void
     */
    private function init_config(): void {
        $this->config = [
            self::TIER_ZENITH => [
                // Taddy pagination
                'max_pages'                      => 20,
                'max_results_per_page'           => 25,

                // PodcastIndex
                'podcastindex_max'               => 50,

                // Feature toggles
                'can_filter_country'             => true,
                'can_filter_language'            => true,
                'can_filter_genre'               => true,
                'can_filter_date'                => true,

                // Sort options
                'sort_by_date_published_options' => [ 'LATEST', 'OLDEST' ],

                // Safe mode
                'safe_mode_forced'               => false,

                // PodcastIndex sorting
                'podcastindex_sort_options'      => [ 'LATEST', 'OLDEST' ],
            ],

            self::TIER_VELOCITY => [
                'max_pages'                      => 10,
                'max_results_per_page'           => 15,
                'podcastindex_max'               => 25,

                'can_filter_country'             => true,
                'can_filter_language'            => true,
                'can_filter_genre'               => true,
                'can_filter_date'                => false,

                'sort_by_date_published_options' => [ 'LATEST' ],
                'safe_mode_forced'               => false,
                'podcastindex_sort_options'      => [ 'LATEST' ],
            ],

            self::TIER_ACCELERATOR => [
                'max_pages'                      => 5,
                'max_results_per_page'           => 10,
                'podcastindex_max'               => 10,

                'can_filter_country'             => false,
                'can_filter_language'            => false,
                'can_filter_genre'               => false,
                'can_filter_date'                => false,

                'sort_by_date_published_options' => [],
                'safe_mode_forced'               => true,
                'podcastindex_sort_options'      => [],
            ],
        ];

        // Default settings for unknown/unassigned membership
        $this->defaults = [
            'max_pages'                      => 5,
            'max_results_per_page'           => 10,
            'podcastindex_max'               => 5,

            'can_filter_country'             => false,
            'can_filter_language'            => false,
            'can_filter_genre'               => false,
            'can_filter_date'                => false,

            'sort_by_date_published_options' => [],
            'safe_mode_forced'               => true,
            'podcastindex_sort_options'      => [],
        ];
    }

    /**
     * Get membership settings for a user.
     *
     * @param int $user_id WordPress user ID.
     * @return array Membership settings.
     */
    public function get_user_settings( int $user_id ): array {
        $membership_level = $this->get_user_membership_level( $user_id );
        return $this->get_settings_for_tier( $membership_level );
    }

    /**
     * Get user's membership level.
     *
     * @param int $user_id WordPress user ID.
     * @return string Membership level.
     */
    public function get_user_membership_level( int $user_id ): string {
        $level = get_user_meta( $user_id, 'guestify_membership', true );
        return strtoupper( trim( (string) $level ) );
    }

    /**
     * Get settings for a specific tier.
     *
     * @param string $tier Membership tier.
     * @return array Settings array.
     */
    public function get_settings_for_tier( string $tier ): array {
        $tier = strtoupper( $tier );
        return $this->config[ $tier ] ?? $this->defaults;
    }

    /**
     * Check if user can use a specific filter.
     *
     * @param int    $user_id     WordPress user ID.
     * @param string $filter_name Filter name (country, language, genre, date).
     * @return bool
     */
    public function can_use_filter( int $user_id, string $filter_name ): bool {
        $settings = $this->get_user_settings( $user_id );
        $key = 'can_filter_' . $filter_name;
        return ! empty( $settings[ $key ] );
    }

    /**
     * Check if user can use a specific sort option.
     *
     * @param int    $user_id     WordPress user ID.
     * @param string $sort_option Sort option (LATEST, OLDEST).
     * @return bool
     */
    public function can_use_sort_option( int $user_id, string $sort_option ): bool {
        $settings = $this->get_user_settings( $user_id );
        $allowed = $settings['sort_by_date_published_options'] ?? [];
        return in_array( strtoupper( $sort_option ), $allowed, true );
    }

    /**
     * Get maximum results for PodcastIndex.
     *
     * @param int $user_id WordPress user ID.
     * @return int
     */
    public function get_podcastindex_max( int $user_id ): int {
        $settings = $this->get_user_settings( $user_id );
        return (int) ( $settings['podcastindex_max'] ?? 10 );
    }

    /**
     * Get maximum results per page for Taddy.
     *
     * @param int $user_id WordPress user ID.
     * @return int
     */
    public function get_max_results_per_page( int $user_id ): int {
        $settings = $this->get_user_settings( $user_id );
        return (int) ( $settings['max_results_per_page'] ?? 10 );
    }

    /**
     * Get maximum pages for Taddy pagination.
     *
     * @param int $user_id WordPress user ID.
     * @return int
     */
    public function get_max_pages( int $user_id ): int {
        $settings = $this->get_user_settings( $user_id );
        return (int) ( $settings['max_pages'] ?? 5 );
    }

    /**
     * Check if safe mode is forced for user.
     *
     * @param int $user_id WordPress user ID.
     * @return bool
     */
    public function is_safe_mode_forced( int $user_id ): bool {
        $settings = $this->get_user_settings( $user_id );
        return ! empty( $settings['safe_mode_forced'] );
    }

    /**
     * Get user's search cap.
     *
     * @param int $user_id WordPress user ID.
     * @return int Search cap (0 = unlimited).
     */
    public function get_search_cap( int $user_id ): int {
        $cap = get_user_meta( $user_id, 'podcast_prospector_search_cap', true );
        return (int) $cap;
    }

    /**
     * Get user's subscription date.
     *
     * @param int $user_id WordPress user ID.
     * @return string
     */
    public function get_subscription_date( int $user_id ): string {
        return get_user_meta( $user_id, 'guestify_subscription_date', true ) ?: '';
    }

    /**
     * Get user's GHL contact ID.
     *
     * @param int $user_id WordPress user ID.
     * @return string
     */
    public function get_ghl_id( int $user_id ): string {
        return get_user_meta( $user_id, 'highlevel_contact_id', true ) ?: '';
    }

    /**
     * Get all available tiers.
     *
     * @return array
     */
    public function get_available_tiers(): array {
        return [
            self::TIER_ZENITH,
            self::TIER_VELOCITY,
            self::TIER_ACCELERATOR,
        ];
    }

    /**
     * Validate and constrain results per page value.
     *
     * @param int $requested  Requested value.
     * @param int $user_id    WordPress user ID.
     * @return int Constrained value.
     */
    public function constrain_results_per_page( int $requested, int $user_id ): int {
        $max = $this->get_max_results_per_page( $user_id );
        return max( 5, min( $requested, $max ) );
    }

    /**
     * Validate and constrain page number.
     *
     * @param int $requested Requested page.
     * @param int $user_id   WordPress user ID.
     * @return int Constrained page number.
     */
    public function constrain_page_number( int $requested, int $user_id ): int {
        $max = $this->get_max_pages( $user_id );
        return max( 1, min( $requested, $max ) );
    }
}
