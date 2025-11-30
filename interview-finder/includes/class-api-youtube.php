<?php
/**
 * YouTube API Class
 *
 * Handles all interactions with the YouTube Data API v3.
 *
 * @package Interview_Finder
 * @since 2.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Interview_Finder_API_YouTube
 *
 * Provides methods to search YouTube videos via YouTube Data API v3.
 */
class Interview_Finder_API_YouTube {

    /**
     * API base URL.
     *
     * @var string
     */
    const API_BASE_URL = 'https://www.googleapis.com/youtube/v3';

    /**
     * API Key.
     *
     * @var string
     */
    private string $api_key;

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
     * Rate limiter instance.
     *
     * @var Interview_Finder_Rate_Limiter|null
     */
    private ?Interview_Finder_Rate_Limiter $rate_limiter;

    /**
     * Constructor.
     *
     * @param Interview_Finder_Settings         $settings     Settings instance.
     * @param Interview_Finder_Logger|null      $logger       Logger instance.
     * @param Interview_Finder_Rate_Limiter|null $rate_limiter Rate limiter instance.
     */
    public function __construct(
        Interview_Finder_Settings $settings,
        ?Interview_Finder_Logger $logger = null,
        ?Interview_Finder_Rate_Limiter $rate_limiter = null
    ) {
        $this->settings = $settings;
        $this->logger = $logger;
        $this->rate_limiter = $rate_limiter;
        $this->api_key = $settings->get( 'youtube_api_key', '' );
    }

    /**
     * Check if API is configured.
     *
     * @return bool
     */
    public function is_configured(): bool {
        return ! empty( $this->api_key );
    }

    /**
     * Check if YouTube features are enabled.
     *
     * @return bool
     */
    public function is_enabled(): bool {
        return (bool) $this->settings->get( 'youtube_features_enabled', false ) && $this->is_configured();
    }

    /**
     * Search for videos.
     *
     * @param array $params Search parameters.
     * @return array Search results.
     */
    public function search_videos( array $params ): array {
        if ( ! $this->is_enabled() ) {
            return $this->error_response( 'YouTube features are not enabled or API key is missing.' );
        }

        // Check rate limit
        if ( $this->rate_limiter && ! $this->rate_limiter->check( 'youtube_search' ) ) {
            return $this->error_response( 'Rate limit exceeded. Please try again later.' );
        }

        $search_term = sanitize_text_field( $params['search_term'] ?? '' );
        $max_results = max( 5, min( 50, (int) ( $params['results_per_page'] ?? 10 ) ) );
        $page_token = sanitize_text_field( $params['page_token'] ?? '' );
        $order = $this->validate_order( $params['order'] ?? 'relevance' );
        $duration = $this->validate_duration( $params['duration'] ?? 'any' );
        $published_after = $this->validate_date( $params['published_after'] ?? '' );

        if ( empty( $search_term ) ) {
            return $this->error_response( 'Search term is required.' );
        }

        // Build search query parameters
        $query_params = [
            'part'       => 'snippet',
            'type'       => 'video',
            'q'          => $search_term,
            'maxResults' => $max_results,
            'order'      => $order,
            'key'        => $this->api_key,
        ];

        if ( ! empty( $page_token ) ) {
            $query_params['pageToken'] = $page_token;
        }

        if ( 'any' !== $duration ) {
            $query_params['videoDuration'] = $duration;
        }

        if ( ! empty( $published_after ) ) {
            $query_params['publishedAfter'] = $published_after . 'T00:00:00Z';
        }

        // Make search request
        $search_url = self::API_BASE_URL . '/search?' . http_build_query( $query_params );
        $search_response = $this->make_request( $search_url );

        if ( isset( $search_response['error'] ) ) {
            return $search_response;
        }

        // Extract video IDs for detailed info
        $video_ids = [];
        foreach ( $search_response['items'] ?? [] as $item ) {
            if ( isset( $item['id']['videoId'] ) ) {
                $video_ids[] = $item['id']['videoId'];
            }
        }

        if ( empty( $video_ids ) ) {
            return [
                'success' => true,
                'data'    => [
                    'items'         => [],
                    'totalResults'  => 0,
                    'nextPageToken' => null,
                    'prevPageToken' => null,
                ],
            ];
        }

        // Get video details (statistics, duration, etc.)
        $videos = $this->get_video_details( $video_ids );

        if ( isset( $videos['error'] ) ) {
            // Fall back to search results without details
            $this->log_warning( 'Failed to get video details, using search results only' );
            $videos = $this->format_search_results( $search_response['items'] );
        }

        return [
            'success' => true,
            'data'    => [
                'items'         => $videos,
                'totalResults'  => $search_response['pageInfo']['totalResults'] ?? count( $videos ),
                'resultsPerPage' => $search_response['pageInfo']['resultsPerPage'] ?? $max_results,
                'nextPageToken' => $search_response['nextPageToken'] ?? null,
                'prevPageToken' => $search_response['prevPageToken'] ?? null,
            ],
        ];
    }

    /**
     * Get detailed video information.
     *
     * @param array $video_ids Array of video IDs.
     * @return array Video details.
     */
    public function get_video_details( array $video_ids ): array {
        if ( empty( $video_ids ) ) {
            return [];
        }

        $query_params = [
            'part' => 'snippet,statistics,contentDetails',
            'id'   => implode( ',', $video_ids ),
            'key'  => $this->api_key,
        ];

        $url = self::API_BASE_URL . '/videos?' . http_build_query( $query_params );
        $response = $this->make_request( $url );

        if ( isset( $response['error'] ) ) {
            return $response;
        }

        $videos = [];
        foreach ( $response['items'] ?? [] as $item ) {
            $videos[] = $this->format_video( $item );
        }

        return $videos;
    }

    /**
     * Format a video item for display.
     *
     * @param array $item Raw video item from API.
     * @return array Formatted video data.
     */
    private function format_video( array $item ): array {
        $snippet = $item['snippet'] ?? [];
        $statistics = $item['statistics'] ?? [];
        $content_details = $item['contentDetails'] ?? [];

        // Parse ISO 8601 duration to seconds
        $duration_iso = $content_details['duration'] ?? 'PT0S';
        $duration_seconds = $this->parse_duration( $duration_iso );

        return [
            'id'              => $item['id'] ?? '',
            'title'           => $snippet['title'] ?? '',
            'description'     => $snippet['description'] ?? '',
            'publishedAt'     => $snippet['publishedAt'] ?? '',
            'channelId'       => $snippet['channelId'] ?? '',
            'channelTitle'    => $snippet['channelTitle'] ?? '',
            'thumbnails'      => $snippet['thumbnails'] ?? [],
            'thumbnailUrl'    => $snippet['thumbnails']['high']['url'] ?? $snippet['thumbnails']['medium']['url'] ?? $snippet['thumbnails']['default']['url'] ?? '',
            'viewCount'       => (int) ( $statistics['viewCount'] ?? 0 ),
            'likeCount'       => (int) ( $statistics['likeCount'] ?? 0 ),
            'commentCount'    => (int) ( $statistics['commentCount'] ?? 0 ),
            'duration'        => $duration_seconds,
            'durationFormatted' => $this->format_duration( $duration_seconds ),
            'durationIso'     => $duration_iso,
            'videoUrl'        => 'https://www.youtube.com/watch?v=' . ( $item['id'] ?? '' ),
            'channelUrl'      => 'https://www.youtube.com/channel/' . ( $snippet['channelId'] ?? '' ),
            'embedUrl'        => 'https://www.youtube.com/embed/' . ( $item['id'] ?? '' ),
            'source'          => 'youtube',
        ];
    }

    /**
     * Format search results without detailed stats.
     *
     * @param array $items Search result items.
     * @return array Formatted items.
     */
    private function format_search_results( array $items ): array {
        $videos = [];
        foreach ( $items as $item ) {
            $snippet = $item['snippet'] ?? [];
            $video_id = $item['id']['videoId'] ?? '';

            $videos[] = [
                'id'              => $video_id,
                'title'           => $snippet['title'] ?? '',
                'description'     => $snippet['description'] ?? '',
                'publishedAt'     => $snippet['publishedAt'] ?? '',
                'channelId'       => $snippet['channelId'] ?? '',
                'channelTitle'    => $snippet['channelTitle'] ?? '',
                'thumbnails'      => $snippet['thumbnails'] ?? [],
                'thumbnailUrl'    => $snippet['thumbnails']['high']['url'] ?? $snippet['thumbnails']['medium']['url'] ?? $snippet['thumbnails']['default']['url'] ?? '',
                'viewCount'       => null, // Not available in search results
                'likeCount'       => null,
                'commentCount'    => null,
                'duration'        => null,
                'durationFormatted' => null,
                'videoUrl'        => 'https://www.youtube.com/watch?v=' . $video_id,
                'channelUrl'      => 'https://www.youtube.com/channel/' . ( $snippet['channelId'] ?? '' ),
                'embedUrl'        => 'https://www.youtube.com/embed/' . $video_id,
                'source'          => 'youtube',
            ];
        }
        return $videos;
    }

    /**
     * Parse ISO 8601 duration to seconds.
     *
     * @param string $duration ISO 8601 duration string (e.g., PT1H30M45S).
     * @return int Duration in seconds.
     */
    private function parse_duration( string $duration ): int {
        $interval = new DateInterval( $duration );
        return ( $interval->h * 3600 ) + ( $interval->i * 60 ) + $interval->s;
    }

    /**
     * Format duration in seconds to human-readable string.
     *
     * @param int $seconds Duration in seconds.
     * @return string Formatted duration (e.g., "1:30:45" or "30:45").
     */
    private function format_duration( int $seconds ): string {
        $hours = floor( $seconds / 3600 );
        $minutes = floor( ( $seconds % 3600 ) / 60 );
        $secs = $seconds % 60;

        if ( $hours > 0 ) {
            return sprintf( '%d:%02d:%02d', $hours, $minutes, $secs );
        }

        return sprintf( '%d:%02d', $minutes, $secs );
    }

    /**
     * Validate order parameter.
     *
     * @param string $order Order value.
     * @return string Valid order.
     */
    private function validate_order( string $order ): string {
        $valid = [ 'relevance', 'date', 'viewCount', 'rating' ];
        return in_array( $order, $valid, true ) ? $order : 'relevance';
    }

    /**
     * Validate duration parameter.
     *
     * @param string $duration Duration value.
     * @return string Valid duration.
     */
    private function validate_duration( string $duration ): string {
        $valid = [ 'any', 'short', 'medium', 'long' ];
        return in_array( $duration, $valid, true ) ? $duration : 'any';
    }

    /**
     * Validate date parameter.
     *
     * @param string $date Date string.
     * @return string Valid date (Y-m-d) or empty.
     */
    private function validate_date( string $date ): string {
        if ( empty( $date ) ) {
            return '';
        }

        $timestamp = strtotime( $date );
        if ( false === $timestamp ) {
            return '';
        }

        return gmdate( 'Y-m-d', $timestamp );
    }

    /**
     * Make an API request.
     *
     * @param string $url Request URL.
     * @return array Response data.
     */
    private function make_request( string $url ): array {
        $this->log_debug( 'YouTube API request', [ 'url' => preg_replace( '/key=[^&]+/', 'key=***', $url ) ] );

        $response = wp_remote_get( $url, [
            'timeout' => 15,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ] );

        if ( is_wp_error( $response ) ) {
            $this->log_error( 'YouTube API request failed', [
                'error' => $response->get_error_message(),
            ] );
            return $this->error_response( 'API request failed: ' . $response->get_error_message() );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( $status_code !== 200 ) {
            $error_message = $data['error']['message'] ?? 'Unknown error';
            $error_reason = $data['error']['errors'][0]['reason'] ?? 'unknown';

            $this->log_error( 'YouTube API error', [
                'status'  => $status_code,
                'message' => $error_message,
                'reason'  => $error_reason,
            ] );

            // Check for quota exceeded
            if ( $error_reason === 'quotaExceeded' ) {
                return $this->error_response( 'YouTube API quota exceeded. Please try again tomorrow or increase your quota in Google Cloud Console.' );
            }

            return $this->error_response( 'YouTube API error: ' . $error_message );
        }

        $this->log_debug( 'YouTube API response', [
            'results' => count( $data['items'] ?? [] ),
        ] );

        return $data;
    }

    /**
     * Create an error response.
     *
     * @param string $message Error message.
     * @return array Error response.
     */
    private function error_response( string $message ): array {
        return [
            'success' => false,
            'error'   => $message,
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
            $this->logger->debug( '[YouTube API] ' . $message, $context );
        }
    }

    /**
     * Log warning message.
     *
     * @param string $message Log message.
     * @param array  $context Additional context.
     * @return void
     */
    private function log_warning( string $message, array $context = [] ): void {
        if ( $this->logger ) {
            $this->logger->warning( '[YouTube API] ' . $message, $context );
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
            $this->logger->error( '[YouTube API] ' . $message, $context );
        }
    }
}
