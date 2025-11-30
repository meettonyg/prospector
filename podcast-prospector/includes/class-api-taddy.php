<?php
/**
 * Taddy API Class
 *
 * Handles all interactions with the Taddy GraphQL API.
 *
 * @package Podcast_Prospector
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Podcast_Prospector_API_Taddy
 *
 * Provides methods to search podcasts and episodes via Taddy GraphQL API.
 */
class Podcast_Prospector_API_Taddy {

    /**
     * API base URL.
     *
     * @var string
     */
    const API_BASE_URL = 'https://api.taddy.org';

    /**
     * Singleton instance.
     *
     * @var Podcast_Prospector_API_Taddy|null
     */
    private static ?Podcast_Prospector_API_Taddy $instance = null;

    /**
     * API Key.
     *
     * @var string
     */
    private string $api_key;

    /**
     * User ID.
     *
     * @var string
     */
    private string $user_id;

    /**
     * Logger instance.
     *
     * @var Podcast_Prospector_Logger|null
     */
    private ?Podcast_Prospector_Logger $logger = null;

    /**
     * Get singleton instance.
     *
     * @return Podcast_Prospector_API_Taddy
     */
    public static function get_instance(): Podcast_Prospector_API_Taddy {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor.
     */
    private function __construct() {
        $this->load_credentials();

        if ( class_exists( 'Podcast_Prospector_Logger' ) ) {
            $this->logger = Podcast_Prospector_Logger::get_instance();
        }
    }

    /**
     * Load API credentials from settings.
     *
     * @return void
     */
    private function load_credentials(): void {
        if ( class_exists( 'Podcast_Prospector_Settings' ) ) {
            $settings = Podcast_Prospector_Settings::get_instance();
            $credentials = $settings->get_taddy_credentials();
            $this->api_key = $credentials['api_key'] ?? '';
            $this->user_id = $credentials['user_id'] ?? '';
        } else {
            $this->api_key = '';
            $this->user_id = '';
        }
    }

    /**
     * Check if API is configured.
     *
     * @return bool
     */
    public function is_configured(): bool {
        return ! empty( $this->api_key ) && ! empty( $this->user_id );
    }

    /**
     * Escape a string for safe use in GraphQL queries.
     *
     * This prevents GraphQL injection attacks by escaping special characters.
     *
     * @param string $value Value to escape.
     * @return string Escaped value.
     */
    private function escape_graphql_string( string $value ): string {
        // Remove null bytes
        $value = str_replace( "\0", '', $value );

        // Escape special characters for GraphQL string literals
        $replacements = [
            '\\' => '\\\\',  // Backslash first
            '"'  => '\\"',   // Double quotes
            "\n" => '\\n',   // Newlines
            "\r" => '\\r',   // Carriage returns
            "\t" => '\\t',   // Tabs
        ];

        return str_replace(
            array_keys( $replacements ),
            array_values( $replacements ),
            $value
        );
    }

    /**
     * Make GraphQL API request using WordPress HTTP API.
     *
     * @param string $query GraphQL query.
     * @return array|WP_Error
     */
    private function request( string $query ) {
        if ( ! $this->is_configured() ) {
            $this->log_error( 'API not configured - missing credentials' );
            return new WP_Error( 'api_not_configured', __( 'Taddy API credentials not configured.', 'podcast-prospector' ) );
        }

        $this->log_debug( 'Making GraphQL request', [
            'query_length' => strlen( $query ),
        ] );

        $response = wp_remote_post( self::API_BASE_URL, [
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-USER-ID'    => $this->user_id,
                'X-API-KEY'    => $this->api_key,
            ],
            'body'    => wp_json_encode( [ 'query' => $query ] ),
        ] );

        if ( is_wp_error( $response ) ) {
            $this->log_error( 'API request failed', [
                'error' => $response->get_error_message(),
            ] );
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( $status_code !== 200 ) {
            $this->log_error( 'API returned non-200 status', [
                'status_code' => $status_code,
                'body'        => $body,
            ] );
            return new WP_Error(
                'api_error',
                sprintf( __( 'Taddy API returned status %d', 'podcast-prospector' ), $status_code ),
                [ 'status_code' => $status_code ]
            );
        }

        if ( null === $data ) {
            $this->log_error( 'Failed to parse API response' );
            return new WP_Error( 'parse_error', __( 'Failed to parse API response.', 'podcast-prospector' ) );
        }

        // Check for GraphQL errors
        if ( isset( $data['errors'] ) && ! empty( $data['errors'] ) ) {
            $error_message = $data['errors'][0]['message'] ?? 'Unknown GraphQL error';
            $this->log_error( 'GraphQL error', [
                'errors' => $data['errors'],
            ] );
            return new WP_Error( 'graphql_error', $error_message );
        }

        $this->log_debug( 'API request successful' );

        return $data;
    }

    /**
     * Build filter string for GraphQL query.
     *
     * @param array $filters Filter parameters.
     * @return string
     */
    private function build_filter_string( array $filters ): string {
        $filter_parts = array_filter( $filters, function( $value ) {
            return ! empty( $value );
        } );

        return implode( ', ', $filter_parts );
    }

    /**
     * Search for podcast episodes using latest Taddy API.
     *
     * @param array $params Search parameters.
     * @return array|WP_Error
     */
    public function search_episodes( array $params ) {
        $search_term = $this->escape_graphql_string( $params['search_term'] ?? '' );
        $page = max( 1, min( 20, (int) ( $params['page'] ?? 1 ) ) ); // Max page is 20
        $results_per_page = max( 5, min( 25, (int) ( $params['results_per_page'] ?? 10 ) ) );
        $language = $params['language'] ?? 'ALL';
        $country = $params['country'] ?? 'ALL';
        $genre = $params['genre'] ?? 'ALL';
        $after_date = $params['after_date'] ?? '';
        $before_date = $params['before_date'] ?? '';
        $is_safe_mode = (bool) ( $params['is_safe_mode'] ?? false );
        $sort_by = $params['sort_by'] ?? 'EXACTNESS'; // EXACTNESS or POPULARITY
        $match_by = $params['match_by'] ?? 'MOST_TERMS'; // MOST_TERMS, ALL_TERMS, EXACT_PHRASE

        // Build query parameters
        $query_params = $this->build_search_params( [
            'term'             => $search_term,
            'page'             => $page,
            'limitPerPage'     => $results_per_page,
            'filterForTypes'   => 'PODCASTEPISODE',
            'sortBy'           => $sort_by,
            'matchBy'          => $match_by,
            'isSafeMode'       => $is_safe_mode,
            'language'         => $language,
            'country'          => $country,
            'genre'            => $genre,
            'after_date'       => $after_date,
            'before_date'      => $before_date,
        ] );

        // Build GraphQL query using latest Taddy API
        $query = <<<GRAPHQL
{
    search({$query_params}) {
        searchId
        podcastEpisodes {
            uuid
            name
            guid
            audioUrl
            datePublished
            description
            duration
            podcastSeries {
                uuid
                name
                authorName
                description
                imageUrl
                genres
                itunesId
                language
                isExplicitContent
                rssUrl
                websiteUrl
            }
        }
        rankingDetails {
            id
            uuid
            rankingScore
        }
        responseDetails {
            totalResults
            totalPages
            currentPage
        }
    }
}
GRAPHQL;

        return $this->request( $query );
    }

    /**
     * Build search parameters string for GraphQL query.
     *
     * @param array $params Parameters to build.
     * @return string
     */
    private function build_search_params( array $params ): string {
        $parts = [];

        // Required term
        $parts[] = sprintf( 'term: "%s"', $params['term'] );

        // Pagination
        $parts[] = sprintf( 'page: %d', $params['page'] );
        $parts[] = sprintf( 'limitPerPage: %d', $params['limitPerPage'] );

        // Filter type
        $parts[] = sprintf( 'filterForTypes: %s', $params['filterForTypes'] );

        // Sorting - EXACTNESS (default) or POPULARITY
        if ( ! empty( $params['sortBy'] ) && in_array( $params['sortBy'], [ 'EXACTNESS', 'POPULARITY' ], true ) ) {
            $parts[] = sprintf( 'sortBy: %s', $params['sortBy'] );
        }

        // Matching - MOST_TERMS (default), ALL_TERMS, EXACT_PHRASE
        if ( ! empty( $params['matchBy'] ) && in_array( $params['matchBy'], [ 'MOST_TERMS', 'ALL_TERMS', 'EXACT_PHRASE' ], true ) ) {
            $parts[] = sprintf( 'matchBy: %s', $params['matchBy'] );
        }

        // Safe mode
        $parts[] = sprintf( 'isSafeMode: %s', $params['isSafeMode'] ? 'true' : 'false' );

        // Language filter
        if ( ! empty( $params['language'] ) && 'ALL' !== $params['language'] ) {
            $parts[] = sprintf( 'filterForLanguages: [%s]', esc_attr( $params['language'] ) );
        }

        // Country filter
        if ( ! empty( $params['country'] ) && 'ALL' !== $params['country'] ) {
            $parts[] = sprintf( 'filterForCountries: [%s]', esc_attr( $params['country'] ) );
        }

        // Genre filter
        if ( ! empty( $params['genre'] ) && 'ALL' !== $params['genre'] ) {
            $parts[] = sprintf( 'filterForGenres: [%s]', esc_attr( $params['genre'] ) );
        }

        // Date filters
        if ( ! empty( $params['after_date'] ) ) {
            $timestamp = strtotime( $params['after_date'] . ' UTC' );
            if ( $timestamp ) {
                $parts[] = sprintf( 'filterForPublishedAfter: %d', $timestamp );
            }
        }

        if ( ! empty( $params['before_date'] ) ) {
            $timestamp = strtotime( $params['before_date'] . ' 23:59:59 UTC' );
            if ( $timestamp ) {
                $parts[] = sprintf( 'filterForPublishedBefore: %d', $timestamp );
            }
        }

        return implode( ', ', $parts );
    }

    /**
     * Search for podcast series using latest Taddy API.
     *
     * @param array $params Search parameters.
     * @return array|WP_Error
     */
    public function search_podcasts( array $params ) {
        $search_term = $this->escape_graphql_string( $params['search_term'] ?? '' );
        $page = max( 1, min( 20, (int) ( $params['page'] ?? 1 ) ) ); // Max page is 20
        $results_per_page = max( 5, min( 25, (int) ( $params['results_per_page'] ?? 10 ) ) );
        $language = $params['language'] ?? 'ALL';
        $country = $params['country'] ?? 'ALL';
        $genre = $params['genre'] ?? 'ALL';
        $after_date = $params['after_date'] ?? '';
        $before_date = $params['before_date'] ?? '';
        $is_safe_mode = (bool) ( $params['is_safe_mode'] ?? false );
        $sort_by = $params['sort_by'] ?? 'EXACTNESS'; // EXACTNESS or POPULARITY
        $match_by = $params['match_by'] ?? 'MOST_TERMS'; // MOST_TERMS, ALL_TERMS, EXACT_PHRASE

        // Build query parameters
        $query_params = $this->build_search_params( [
            'term'             => $search_term,
            'page'             => $page,
            'limitPerPage'     => $results_per_page,
            'filterForTypes'   => 'PODCASTSERIES',
            'sortBy'           => $sort_by,
            'matchBy'          => $match_by,
            'isSafeMode'       => $is_safe_mode,
            'language'         => $language,
            'country'          => $country,
            'genre'            => $genre,
            'after_date'       => $after_date,
            'before_date'      => $before_date,
        ] );

        // Build GraphQL query using latest Taddy API
        $query = <<<GRAPHQL
{
    search({$query_params}) {
        searchId
        podcastSeries {
            uuid
            name
            authorName
            description
            imageUrl
            genres
            totalEpisodesCount
            itunesId
            language
            isExplicitContent
            rssUrl
            websiteUrl
        }
        rankingDetails {
            id
            uuid
            rankingScore
        }
        responseDetails {
            totalResults
            totalPages
            currentPage
        }
    }
}
GRAPHQL;

        return $this->request( $query );
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
            $this->logger->debug( '[Taddy API] ' . $message, $context );
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
            $this->logger->error( '[Taddy API] ' . $message, $context );
        }
    }
}
