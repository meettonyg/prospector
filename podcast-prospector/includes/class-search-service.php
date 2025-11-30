<?php
/**
 * Search Service Class
 *
 * @package Podcast_Prospector
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Podcast_Prospector_Search_Service
 *
 * Orchestrates search operations with caching, rate limiting, and error handling.
 */
class Podcast_Prospector_Search_Service {

    /**
     * PodcastIndex API.
     *
     * @var Podcast_Prospector_API_PodcastIndex
     */
    private Podcast_Prospector_API_PodcastIndex $podcastindex_api;

    /**
     * Taddy API.
     *
     * @var Podcast_Prospector_API_Taddy
     */
    private Podcast_Prospector_API_Taddy $taddy_api;

    /**
     * Search cache.
     *
     * @var Podcast_Prospector_Search_Cache
     */
    private Podcast_Prospector_Search_Cache $cache;

    /**
     * Membership service.
     *
     * @var Podcast_Prospector_Membership
     */
    private Podcast_Prospector_Membership $membership;

    /**
     * Logger.
     *
     * @var Podcast_Prospector_Logger|null
     */
    private ?Podcast_Prospector_Logger $logger;

    /**
     * YouTube API.
     *
     * @var Podcast_Prospector_API_YouTube|null
     */
    private ?Podcast_Prospector_API_YouTube $youtube_api = null;

    /**
     * YouTube Channel Repository (for deduplication).
     *
     * @var Podcast_Prospector_YouTube_Channel_Repository|null
     */
    private ?Podcast_Prospector_YouTube_Channel_Repository $youtube_channel_repo = null;

    /**
     * Sponsored listings service.
     *
     * @var Podcast_Prospector_Sponsored_Listings|null
     */
    private ?Podcast_Prospector_Sponsored_Listings $sponsored_listings = null;

    /**
     * Constructor.
     *
     * @param Podcast_Prospector_API_PodcastIndex $podcastindex_api PodcastIndex API.
     * @param Podcast_Prospector_API_Taddy        $taddy_api        Taddy API.
     * @param Podcast_Prospector_Search_Cache     $cache            Search cache.
     * @param Podcast_Prospector_Membership       $membership       Membership service.
     * @param Podcast_Prospector_Logger|null      $logger           Logger.
     */
    public function __construct(
        Podcast_Prospector_API_PodcastIndex $podcastindex_api,
        Podcast_Prospector_API_Taddy $taddy_api,
        Podcast_Prospector_Search_Cache $cache,
        Podcast_Prospector_Membership $membership,
        ?Podcast_Prospector_Logger $logger = null
    ) {
        $this->podcastindex_api = $podcastindex_api;
        $this->taddy_api = $taddy_api;
        $this->cache = $cache;
        $this->membership = $membership;
        $this->logger = $logger;

        // Initialize YouTube API if available
        if ( class_exists( 'Podcast_Prospector_API_YouTube' ) ) {
            $container = Podcast_Prospector_Container::get_instance();
            if ( $container->has( 'api.youtube' ) ) {
                $this->youtube_api = $container->get( 'api.youtube' );
            }
        }

        // Initialize YouTube Channel Repository for deduplication
        if ( class_exists( 'Podcast_Prospector_YouTube_Channel_Repository' ) ) {
            $container = Podcast_Prospector_Container::get_instance();
            if ( $container->has( 'youtube_channel' ) ) {
                $this->youtube_channel_repo = $container->get( 'youtube_channel' );
            }
        }

        // Initialize sponsored listings
        if ( class_exists( 'Podcast_Prospector_Sponsored_Listings' ) ) {
            $this->sponsored_listings = Podcast_Prospector_Sponsored_Listings::get_instance();
        }
    }

    /**
     * Perform search based on type.
     *
     * @param array $params   Validated search parameters.
     * @param int   $user_id  User ID for membership checks.
     * @return Podcast_Prospector_Search_Result
     */
    public function search( array $params, int $user_id ): Podcast_Prospector_Search_Result {
        $search_type = $params['search_type'] ?? 'byperson';
        $settings = $this->membership->get_user_settings( $user_id );

        // Apply membership constraints
        $params = $this->apply_membership_constraints( $params, $settings, $user_id );

        // Force safe mode if required
        if ( $this->membership->is_safe_mode_forced( $user_id ) ) {
            $params['is_safe_mode'] = true;
        }

        // Generate cache key
        $cache_key = $this->cache->generate_key( $search_type, $params );

        // Try cache first
        $cached = $this->cache->get( $cache_key );
        if ( null !== $cached ) {
            return new Podcast_Prospector_Search_Result( $cached, true, $search_type );
        }

        // Perform the search
        try {
            $result = $this->perform_search( $search_type, $params );

            if ( ! is_wp_error( $result ) ) {
                $this->cache->set( $cache_key, $result );
                return new Podcast_Prospector_Search_Result( $result, false, $search_type );
            }

            return new Podcast_Prospector_Search_Result( null, false, $search_type, $result->get_error_message() );

        } catch ( Exception $e ) {
            $this->log_error( 'Search failed', [
                'type'  => $search_type,
                'error' => $e->getMessage(),
            ] );

            return new Podcast_Prospector_Search_Result( null, false, $search_type, $e->getMessage() );
        }
    }

    /**
     * Search with fallback - try multiple sources.
     *
     * @param string $term    Search term.
     * @param int    $user_id User ID.
     * @return array Results from available sources.
     */
    public function search_with_fallback( string $term, int $user_id ): array {
        $results = [
            'podcastindex' => null,
            'taddy'        => null,
            'errors'       => [],
        ];

        // Try PodcastIndex
        try {
            $max = $this->membership->get_podcastindex_max( $user_id );
            $pi_result = $this->podcastindex_api->search_by_person( $term, $max );

            if ( ! is_wp_error( $pi_result ) ) {
                $results['podcastindex'] = $pi_result;
            } else {
                $results['errors']['podcastindex'] = $pi_result->get_error_message();
            }
        } catch ( Exception $e ) {
            $results['errors']['podcastindex'] = $e->getMessage();
        }

        // Try Taddy
        try {
            $settings = $this->membership->get_user_settings( $user_id );
            $taddy_result = $this->taddy_api->search_episodes( [
                'search_term'      => $term,
                'results_per_page' => $settings['max_results_per_page'],
                'page'             => 1,
                'is_safe_mode'     => $settings['safe_mode_forced'],
            ] );

            if ( ! is_wp_error( $taddy_result ) ) {
                $results['taddy'] = $taddy_result;
            } else {
                $results['errors']['taddy'] = $taddy_result->get_error_message();
            }
        } catch ( Exception $e ) {
            $results['errors']['taddy'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Apply membership constraints to parameters.
     *
     * @param array $params   Search parameters.
     * @param array $settings Membership settings.
     * @param int   $user_id  User ID.
     * @return array Modified parameters.
     */
    private function apply_membership_constraints( array $params, array $settings, int $user_id ): array {
        // Constrain pagination
        $params['page'] = $this->membership->constrain_page_number(
            $params['page'] ?? 1,
            $user_id
        );

        $params['results_per_page'] = $this->membership->constrain_results_per_page(
            $params['results_per_page'] ?? 10,
            $user_id
        );

        // Reset locked filters
        if ( ! $settings['can_filter_language'] ) {
            $params['language'] = 'ALL';
        }
        if ( ! $settings['can_filter_country'] ) {
            $params['country'] = 'ALL';
        }
        if ( ! $settings['can_filter_genre'] ) {
            $params['genre'] = 'ALL';
        }
        if ( ! $settings['can_filter_date'] ) {
            $params['after_date'] = '';
            $params['before_date'] = '';
        }

        // Validate sort option
        $allowed_sorts = $settings['sort_by_date_published_options'] ?? [];
        if ( ! empty( $params['sort_order'] ) && 'BEST_MATCH' !== $params['sort_order'] ) {
            if ( ! in_array( $params['sort_order'], $allowed_sorts, true ) ) {
                $params['sort_order'] = 'BEST_MATCH';
            }
        }

        return $params;
    }

    /**
     * Perform the actual search.
     *
     * @param string $type   Search type.
     * @param array  $params Parameters.
     * @return array|WP_Error
     */
    private function perform_search( string $type, array $params ) {
        switch ( $type ) {
            case 'byadvancedepisode':
                return $this->taddy_api->search_episodes( $params );

            case 'byadvancedpodcast':
                return $this->taddy_api->search_podcasts( $params );

            case 'byyoutube':
                return $this->search_youtube( $params );

            case 'bytitle':
                return $this->podcastindex_api->search_by_term(
                    $params['search_term'],
                    $params['results_per_page'] ?? 10
                );

            case 'byperson':
            default:
                return $this->podcastindex_api->search_by_person(
                    $params['search_term'],
                    $params['results_per_page'] ?? 10
                );
        }
    }

    /**
     * Perform YouTube search.
     *
     * @param array $params Search parameters.
     * @return array|WP_Error
     */
    private function search_youtube( array $params ) {
        if ( ! $this->youtube_api ) {
            return new WP_Error( 'youtube_unavailable', __( 'YouTube API is not available.', 'interview-finder' ) );
        }

        if ( ! $this->youtube_api->is_enabled() ) {
            return new WP_Error( 'youtube_disabled', __( 'YouTube search is not enabled.', 'interview-finder' ) );
        }

        $result = $this->youtube_api->search_videos( $params );

        if ( ! $result['success'] ) {
            return new WP_Error( 'youtube_error', $result['error'] ?? __( 'YouTube search failed.', 'interview-finder' ) );
        }

        // Apply channel deduplication if available
        if ( $this->youtube_channel_repo && ! empty( $result['data']['items'] ) ) {
            $result['data']['items'] = $this->youtube_channel_repo->mark_duplicates( $result['data']['items'] );
            $result['data']['deduplication_available'] = $this->youtube_channel_repo->is_available();
        } else {
            $result['data']['deduplication_available'] = false;
        }

        return $result;
    }

    /**
     * Get matching sponsored listings for a search.
     *
     * @param array $params Search parameters.
     * @param int   $limit  Maximum listings to return.
     * @return array Sponsored listings formatted for display.
     */
    public function get_sponsored_listings( array $params, int $limit = 3 ): array {
        if ( ! $this->sponsored_listings ) {
            return [];
        }

        // Extract categories from search params
        $categories = [];

        // Try to get categories from params
        if ( ! empty( $params['categories'] ) ) {
            $categories = $params['categories'];
        } elseif ( ! empty( $params['category'] ) ) {
            $categories = [ $params['category'] ];
        }

        // Get matching listings
        $listings = $this->sponsored_listings->get_matching(
            [ 'categories' => $categories ],
            $limit
        );

        // Record impressions for each listing shown
        foreach ( $listings as $listing ) {
            $this->sponsored_listings->record_impression( (int) $listing['id'] );
        }

        // Format for display (match podcast result format)
        return array_map( [ $this, 'format_sponsored_listing' ], $listings );
    }

    /**
     * Format a sponsored listing to match podcast result format.
     *
     * @param array $listing Sponsored listing data.
     * @return array Formatted listing.
     */
    private function format_sponsored_listing( array $listing ): array {
        return [
            'id'                => 'sponsored_' . $listing['id'],
            'sponsored_id'      => (int) $listing['id'],
            'uuid'              => $listing['podcast_uuid'],
            'name'              => $listing['podcast_title'],
            'title'             => $listing['podcast_title'],
            'description'       => $listing['podcast_description'],
            'imageUrl'          => $listing['podcast_image_url'],
            'itunesId'          => $listing['podcast_itunes_id'],
            'websiteUrl'        => $listing['podcast_url'],
            'rssUrl'            => $listing['podcast_rss_url'],
            'categories'        => $listing['categories_array'] ?? [],
            'is_sponsored'      => true,
            'sponsored_name'    => $listing['name'],
            'source'            => 'sponsored',
        ];
    }

    /**
     * Record a click on a sponsored listing.
     *
     * @param int $sponsored_id Sponsored listing ID.
     * @return bool
     */
    public function record_sponsored_click( int $sponsored_id ): bool {
        if ( ! $this->sponsored_listings ) {
            return false;
        }

        return $this->sponsored_listings->record_click( $sponsored_id );
    }

    /**
     * Log error.
     *
     * @param string $message Message.
     * @param array  $context Context.
     * @return void
     */
    private function log_error( string $message, array $context = [] ): void {
        if ( $this->logger ) {
            $this->logger->error( '[Search Service] ' . $message, $context );
        }
    }
}

/**
 * Class Podcast_Prospector_Search_Result
 *
 * Encapsulates search results.
 */
class Podcast_Prospector_Search_Result {

    /**
     * Result data.
     *
     * @var array|null
     */
    private ?array $data;

    /**
     * Whether result was from cache.
     *
     * @var bool
     */
    private bool $from_cache;

    /**
     * Search type used.
     *
     * @var string
     */
    private string $search_type;

    /**
     * Error message if failed.
     *
     * @var string|null
     */
    private ?string $error;

    /**
     * Constructor.
     *
     * @param array|null  $data        Result data.
     * @param bool        $from_cache  Whether from cache.
     * @param string      $search_type Search type.
     * @param string|null $error       Error message.
     */
    public function __construct( ?array $data, bool $from_cache, string $search_type, ?string $error = null ) {
        $this->data = $data;
        $this->from_cache = $from_cache;
        $this->search_type = $search_type;
        $this->error = $error;
    }

    /**
     * Check if search was successful.
     *
     * @return bool
     */
    public function is_success(): bool {
        return null !== $this->data && null === $this->error;
    }

    /**
     * Get result data.
     *
     * @return array|null
     */
    public function get_data(): ?array {
        return $this->data;
    }

    /**
     * Check if from cache.
     *
     * @return bool
     */
    public function is_from_cache(): bool {
        return $this->from_cache;
    }

    /**
     * Get search type.
     *
     * @return string
     */
    public function get_search_type(): string {
        return $this->search_type;
    }

    /**
     * Get error message.
     *
     * @return string|null
     */
    public function get_error(): ?string {
        return $this->error;
    }

    /**
     * Get result count.
     *
     * @return int
     */
    public function get_count(): int {
        if ( ! $this->data ) {
            return 0;
        }

        // Handle different response structures
        if ( isset( $this->data['items'] ) ) {
            return count( $this->data['items'] );
        }
        if ( isset( $this->data['feeds'] ) ) {
            return count( $this->data['feeds'] );
        }

        // YouTube format
        if ( isset( $this->data['data']['items'] ) ) {
            return count( $this->data['data']['items'] );
        }

        // Taddy - new API format (search)
        if ( isset( $this->data['data']['search']['podcastEpisodes'] ) ) {
            return count( $this->data['data']['search']['podcastEpisodes'] );
        }
        if ( isset( $this->data['data']['search']['podcastSeries'] ) ) {
            return count( $this->data['data']['search']['podcastSeries'] );
        }

        // Taddy - legacy API format (searchForTerm)
        if ( isset( $this->data['data']['searchForTerm']['podcastEpisodes'] ) ) {
            return count( $this->data['data']['searchForTerm']['podcastEpisodes'] );
        }
        if ( isset( $this->data['data']['searchForTerm']['podcastSeries'] ) ) {
            return count( $this->data['data']['searchForTerm']['podcastSeries'] );
        }

        return 0;
    }

    /**
     * Get ranking details from Taddy API response.
     *
     * @return array
     */
    public function get_ranking_details(): array {
        if ( ! $this->data ) {
            return [];
        }

        if ( isset( $this->data['data']['search']['rankingDetails'] ) ) {
            return $this->data['data']['search']['rankingDetails'];
        }

        return [];
    }

    /**
     * Get response details (pagination info) from Taddy API response.
     *
     * @return array
     */
    public function get_response_details(): array {
        if ( ! $this->data ) {
            return [];
        }

        if ( isset( $this->data['data']['search']['responseDetails'] ) ) {
            return $this->data['data']['search']['responseDetails'];
        }

        return [];
    }
}
