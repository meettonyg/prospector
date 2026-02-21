<?php
/**
 * Guest Intel Import Handler Class
 *
 * Handles podcast imports to the Guest Intelligence database (pit_* tables)
 * instead of Formidable Forms entries.
 *
 * @package Podcast_Prospector
 * @since 2.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Podcast_Prospector_Guest_Intel_Import_Handler
 *
 * Manages podcast import to Guest Intelligence database tables.
 */
class Podcast_Prospector_Guest_Intel_Import_Handler {

    /**
     * Singleton instance.
     *
     * @var Podcast_Prospector_Guest_Intel_Import_Handler|null
     */
    private static ?Podcast_Prospector_Guest_Intel_Import_Handler $instance = null;

    /**
     * Logger instance.
     *
     * @var Podcast_Prospector_Logger|null
     */
    private ?Podcast_Prospector_Logger $logger = null;

    /**
     * Get singleton instance.
     *
     * @return Podcast_Prospector_Guest_Intel_Import_Handler
     */
    public static function get_instance(): Podcast_Prospector_Guest_Intel_Import_Handler {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor.
     */
    private function __construct() {
        if ( class_exists( 'Podcast_Prospector_Logger' ) ) {
            $this->logger = Podcast_Prospector_Logger::get_instance();
        }
    }

    /**
     * Import podcast items to Guest Intelligence database.
     *
     * @param array  $podcasts    Array of podcast JSON strings.
     * @param array  $post_data   Additional POST data (search_term, search_type).
     * @return array{success_count: int, fail_count: int, html: string, details: array}
     */
    public function import_items( array $podcasts, array $post_data ): array {
        $search_term = isset( $post_data['search_term'] ) ? sanitize_text_field( $post_data['search_term'] ) : 'N/A';
        $search_type = isset( $post_data['search_type'] ) ? sanitize_text_field( $post_data['search_type'] ) : 'byperson';
        $import_mode = isset( $post_data['import_mode'] ) ? sanitize_text_field( $post_data['import_mode'] ) : 'auto';
        $current_user = get_current_user_id();

        $total_to_import = count( $podcasts );
        $success_count = 0;
        $fail_count = 0;
        $failed_items = [];
        $details = [];

        foreach ( $podcasts as $item ) {
            $decoded = json_decode( stripslashes( $item ), true );

            if ( ! $decoded || ! is_array( $decoded ) ) {
                $fail_count++;
                $failed_items[] = __( 'Invalid data format received for an item.', 'podcast-prospector' );
                $this->log_error( 'Invalid JSON data for import', [ 'raw' => $item ] );
                continue;
            }

            // Extract podcast data based on search type
            $podcast_data = $this->extract_podcast_data( $decoded, $search_type );

            if ( empty( $podcast_data['rss_feed_url'] ) ) {
                $fail_count++;
                $failed_items[] = sprintf(
                    /* translators: %s: podcast name */
                    __( "Podcast '%s' couldn't be imported due to missing RSS feed.", 'podcast-prospector' ),
                    $podcast_data['title']
                );
                $this->log_error( 'Missing RSS feed URL', [ 'title' => $podcast_data['title'] ] );
                continue;
            }

            // Find or create podcast in pit_podcasts
            $podcast_id = $this->find_or_create_podcast( $podcast_data );

            if ( ! $podcast_id ) {
                $fail_count++;
                $failed_items[] = sprintf(
                    /* translators: %s: podcast name */
                    __( "Failed to create podcast record for '%s'.", 'podcast-prospector' ),
                    $podcast_data['title']
                );
                $this->log_error( 'Failed to create podcast', [ 'title' => $podcast_data['title'] ] );
                continue;
            }

            // Create opportunity in pit_opportunities
            $opportunity_id = $this->create_opportunity( $podcast_id, $current_user, $search_type, $search_term );

            if ( ! $opportunity_id ) {
                $fail_count++;
                $failed_items[] = sprintf(
                    /* translators: %s: podcast name */
                    __( "Failed to create opportunity for '%s'.", 'podcast-prospector' ),
                    $podcast_data['title']
                );
                $this->log_error( 'Failed to create opportunity', [
                    'podcast_id' => $podcast_id,
                    'title'      => $podcast_data['title'],
                ] );
                continue;
            }

            $success_count++;
            $detail = [
                'opportunity_id' => $opportunity_id,
                'podcast_id'     => $podcast_id,
                'podcast_name'   => $podcast_data['title'],
                'status'         => 'potential',
                'episode_linked' => false,
            ];

            // Auto-link episode if episode-level data is available and import mode allows it
            $should_link = 'aired' === $import_mode || ( 'auto' === $import_mode );
            if ( 'potential' !== $import_mode && $should_link ) {
                $episode_data = $this->extract_episode_data( $decoded, $search_type );
                if ( $episode_data ) {
                    $link_result = $this->link_episode_to_opportunity(
                        $opportunity_id,
                        $podcast_id,
                        $episode_data,
                        $current_user
                    );

                    if ( $link_result ) {
                        $detail['episode_linked']  = true;
                        $detail['engagement_id']   = $link_result['engagement_id'];
                        $detail['credit_id']       = $link_result['credit_id'];
                        $detail['status']          = 'aired';
                        $detail['episode_title']   = $episode_data['title'];
                    }
                }
            }

            $details[] = $detail;

            $this->log_info( 'Imported podcast to Guest Intel', [
                'podcast_id'     => $podcast_id,
                'opportunity_id' => $opportunity_id,
                'title'          => $podcast_data['title'],
                'episode_linked' => $detail['episode_linked'],
            ] );
        }

        // Fire webhook for Zapier/Make compatibility
        if ( $success_count > 0 ) {
            /**
             * Fires when interview finder import is completed.
             *
             * @since 2.1.0
             * @param int   $success_count Number of successfully imported items.
             * @param array $details       Details of imported items.
             */
            do_action( 'interview_finder_import_completed', $success_count, $details );
        }

        // Generate response HTML
        $html = $this->generate_response_html( $success_count, $fail_count, $total_to_import, $failed_items );

        return [
            'success_count' => $success_count,
            'fail_count'    => $fail_count,
            'html'          => $html,
            'details'       => $details,
        ];
    }

    /**
     * Find or create podcast with multi-key deduplication.
     *
     * Deduplication priority:
     * 1. RSS Feed URL (most reliable)
     * 2. iTunes ID
     * 3. PodcastIndex GUID
     *
     * @param array $data Podcast data.
     * @return int|false Podcast ID or false on failure.
     */
    public function find_or_create_podcast( array $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'pit_podcasts';

        // Priority 1: RSS Feed URL (most reliable)
        if ( ! empty( $data['rss_feed_url'] ) ) {
            $existing = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM $table WHERE rss_feed_url = %s",
                $data['rss_feed_url']
            ) );
            if ( $existing ) {
                $this->log_info( 'Found existing podcast by RSS URL', [
                    'podcast_id' => $existing,
                    'rss_url'    => $data['rss_feed_url'],
                ] );
                return (int) $existing;
            }
        }

        // Priority 2: iTunes ID
        if ( ! empty( $data['itunes_id'] ) ) {
            $existing = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM $table WHERE itunes_id = %s",
                $data['itunes_id']
            ) );
            if ( $existing ) {
                $this->log_info( 'Found existing podcast by iTunes ID', [
                    'podcast_id' => $existing,
                    'itunes_id'  => $data['itunes_id'],
                ] );
                return (int) $existing;
            }
        }

        // Priority 3: PodcastIndex GUID
        if ( ! empty( $data['podcast_index_guid'] ) ) {
            $existing = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM $table WHERE podcast_index_guid = %s",
                $data['podcast_index_guid']
            ) );
            if ( $existing ) {
                $this->log_info( 'Found existing podcast by PodcastIndex GUID', [
                    'podcast_id'         => $existing,
                    'podcast_index_guid' => $data['podcast_index_guid'],
                ] );
                return (int) $existing;
            }
        }

        // No match - create new podcast
        $insert_data = [
            'title'              => $data['title'] ?? 'Unknown Podcast',
            'rss_feed_url'       => $data['rss_feed_url'] ?? null,
            'itunes_id'          => $data['itunes_id'] ?? null,
            'podcast_index_id'   => $data['podcast_index_id'] ?? null,
            'podcast_index_guid' => $data['podcast_index_guid'] ?? null,
            'artwork_url'        => $data['artwork_url'] ?? null,
            'author'             => $data['author'] ?? null,
            'description'        => $data['description'] ?? null,
            'source'             => 'prospector',
            'created_at'         => current_time( 'mysql' ),
            'updated_at'         => current_time( 'mysql' ),
        ];

        // Generate slug
        if ( ! empty( $insert_data['title'] ) ) {
            $insert_data['slug'] = $this->generate_unique_slug( $insert_data['title'] );
        }

        $result = $wpdb->insert( $table, $insert_data );

        if ( false === $result ) {
            $this->log_error( 'Database insert failed for podcast', [
                'title' => $data['title'] ?? 'Unknown',
                'error' => $wpdb->last_error,
            ] );
            return false;
        }

        $this->log_info( 'Created new podcast', [
            'podcast_id' => $wpdb->insert_id,
            'title'      => $insert_data['title'],
        ] );

        return $wpdb->insert_id;
    }

    /**
     * Create opportunity record.
     *
     * Uses PIT_Opportunity_Repository for proper agency context stamping.
     *
     * @param int    $podcast_id  Podcast ID.
     * @param int    $user_id     User ID.
     * @param string $search_type Search type used (byperson, bytitle, etc.).
     * @param string $search_term Original search term.
     * @return int|false Opportunity ID or false on failure.
     */
    public function create_opportunity( int $podcast_id, int $user_id, string $search_type, string $search_term ) {
        $default_stage = $this->get_default_pipeline_stage( $user_id );

        $insert_data = [
            'user_id'     => $user_id,
            'podcast_id'  => $podcast_id,
            'status'      => $default_stage['stage_key'],
            'stage_id'    => $default_stage['id'],
            'priority'    => 'medium',
            'source'      => $search_type,
            'notes'       => sprintf(
                /* translators: 1: search type, 2: search term */
                __( 'Imported via Prospector %1$s search: "%2$s"', 'podcast-prospector' ),
                $search_type,
                $search_term
            ),
        ];

        $opportunity_id = PIT_Opportunity_Repository::create( $insert_data );

        if ( ! $opportunity_id ) {
            $this->log_error( 'Repository insert failed for opportunity', [
                'podcast_id' => $podcast_id,
            ] );
            return false;
        }

        return $opportunity_id;
    }

    /**
     * Get the default pipeline stage for new opportunities.
     *
     * Checks for user-specific stages first, falls back to system defaults.
     *
     * @param int $user_id User ID.
     * @return array{id: int, stage_key: string} Stage ID and key.
     */
    private function get_default_pipeline_stage( int $user_id ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'pit_pipeline_stages';

        // First try user-specific stage
        $stage = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, stage_key FROM $table 
             WHERE user_id = %d AND is_active = 1 
             ORDER BY sort_order ASC LIMIT 1",
            $user_id
        ), ARRAY_A );

        if ( $stage ) {
            return $stage;
        }

        // Fall back to system default (first by sort_order)
        $stage = $wpdb->get_row(
            "SELECT id, stage_key FROM $table 
             WHERE (user_id IS NULL OR is_system = 1) AND is_active = 1 
             ORDER BY sort_order ASC LIMIT 1",
            ARRAY_A
        );

        if ( $stage ) {
            return $stage;
        }

        // Ultimate fallback if no stages exist (should never happen)
        $this->log_warning( 'No pipeline stages found, using hardcoded fallback' );
        return [
            'id'        => 1,
            'stage_key' => 'potential',
        ];
    }

    /**
     * Extract podcast data from decoded search result.
     *
     * @param array  $decoded     Decoded podcast data.
     * @param string $search_type Search type used.
     * @return array Normalized podcast data.
     */
    private function extract_podcast_data( array $decoded, string $search_type ): array {
        $data = [
            'title'              => 'N/A',
            'rss_feed_url'       => '',
            'itunes_id'          => '',
            'podcast_index_id'   => '',
            'podcast_index_guid' => '',
            'artwork_url'        => '',
            'author'             => '',
            'description'        => '',
        ];

        if ( 'byadvancedepisode' === $search_type && isset( $decoded['podcastSeries'] ) ) {
            // Taddy Episode
            $data['title']              = $decoded['podcastSeries']['name'] ?? 'N/A';
            $data['rss_feed_url']       = $decoded['podcastSeries']['rssUrl'] ?? '';
            $data['itunes_id']          = $decoded['podcastSeries']['itunesId'] ?? '';
            $data['podcast_index_guid'] = $decoded['podcastSeries']['uuid'] ?? '';
            $data['artwork_url']        = $decoded['podcastSeries']['imageUrl'] ?? '';
            $data['author']             = $decoded['podcastSeries']['author'] ?? '';
            $data['description']        = $decoded['podcastSeries']['description'] ?? '';

        } elseif ( 'byadvancedpodcast' === $search_type ) {
            // Taddy Podcast
            $data['title']              = $decoded['name'] ?? 'N/A';
            $data['rss_feed_url']       = $decoded['rssUrl'] ?? '';
            $data['itunes_id']          = $decoded['itunesId'] ?? '';
            $data['podcast_index_guid'] = $decoded['uuid'] ?? '';
            $data['artwork_url']        = $decoded['imageUrl'] ?? '';
            $data['author']             = $decoded['author'] ?? '';
            $data['description']        = $decoded['description'] ?? '';

        } elseif ( 'byperson' === $search_type && isset( $decoded['feedTitle'] ) ) {
            // PodcastIndex Episode
            $data['title']            = $decoded['feedTitle'] ?? 'N/A';
            $data['rss_feed_url']     = $decoded['feedUrl'] ?? '';
            $data['itunes_id']        = $decoded['feedItunesId'] ?? '';
            $data['podcast_index_id'] = $decoded['feedId'] ?? '';
            $data['artwork_url']      = $decoded['feedImage'] ?? ( $decoded['image'] ?? '' );
            $data['author']           = $decoded['feedAuthor'] ?? '';

        } elseif ( 'bytitle' === $search_type && isset( $decoded['title'] ) && ! isset( $decoded['feedTitle'] ) ) {
            // PodcastIndex Feed
            $data['title']            = $decoded['title'] ?? 'N/A';
            $data['rss_feed_url']     = $decoded['url'] ?? '';
            $data['itunes_id']        = $decoded['itunesId'] ?? '';
            $data['podcast_index_id'] = $decoded['id'] ?? '';
            $data['artwork_url']      = $decoded['artwork'] ?? ( $decoded['image'] ?? '' );
            $data['author']           = $decoded['author'] ?? '';
            $data['description']      = $decoded['description'] ?? '';

        } else {
            // Fallback
            $data['title']              = $decoded['title'] ?? ( $decoded['feedTitle'] ?? ( $decoded['name'] ?? 'Unknown Title' ) );
            $data['rss_feed_url']       = $decoded['feedUrl'] ?? ( $decoded['url'] ?? ( $decoded['rssUrl'] ?? '' ) );
            $data['itunes_id']          = $decoded['itunesId'] ?? ( $decoded['feedItunesId'] ?? '' );
            $data['podcast_index_id']   = $decoded['id'] ?? ( $decoded['feedId'] ?? '' );
            $data['podcast_index_guid'] = $decoded['podcastGuid'] ?? ( $decoded['uuid'] ?? '' );
            $data['artwork_url']        = $decoded['artwork'] ?? ( $decoded['image'] ?? ( $decoded['feedImage'] ?? '' ) );
            $data['author']             = $decoded['author'] ?? ( $decoded['feedAuthor'] ?? '' );
            $data['description']        = $decoded['description'] ?? '';

            $this->log_warning( 'Could not determine exact result type, using fallback', [ 'decoded' => $decoded ] );
        }

        return $data;
    }

    /**
     * Generate unique slug for podcast.
     *
     * @param string $title Podcast title.
     * @return string Unique slug.
     */
    private function generate_unique_slug( string $title ): string {
        global $wpdb;
        $table = $wpdb->prefix . 'pit_podcasts';

        $base_slug = sanitize_title( $title );
        $slug = $base_slug;
        $counter = 1;

        while ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE slug = %s", $slug ) ) ) {
            $slug = $base_slug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Generate response HTML.
     *
     * @param int   $success_count Successful imports.
     * @param int   $fail_count    Failed imports.
     * @param int   $total         Total attempted.
     * @param array $failed_items  Failed item messages.
     * @return string HTML.
     */
    private function generate_response_html( int $success_count, int $fail_count, int $total, array $failed_items ): string {
        // Link to Interview Tracker (Guest Intelligence CRM)
        $tracker_link = home_url( '/app/interview/board/' );

        if ( $fail_count === 0 && $success_count > 0 ) {
            // All successful
            return sprintf(
                '<div class="import-message success" id="import-message-container-success">'
                . '<div class="message-content">'
                . '<i class="fas fa-check-circle message-icon"></i>'
                . '<span class="message-text">%s</span>'
                . '</div>'
                . '<div class="message-actions">'
                . '<a href="%s" class="view-tracker-link">%s <i class="fas fa-arrow-right"></i></a>'
                . '</div>'
                . '</div>',
                sprintf(
                    /* translators: 1: count, 2: singular/plural text */
                    esc_html__( 'Success! %1$d %2$s been added to your Interview Tracker.', 'podcast-prospector' ),
                    $success_count,
                    $success_count === 1 ? esc_html__( 'podcast has', 'podcast-prospector' ) : esc_html__( 'podcasts have', 'podcast-prospector' )
                ),
                esc_url( $tracker_link ),
                esc_html__( 'View in Interview Tracker', 'podcast-prospector' )
            );
        }

        if ( $fail_count > 0 ) {
            // Partial or total failure
            if ( $success_count > 0 ) {
                $error_summary = sprintf(
                    /* translators: 1: success count, 2: total count */
                    esc_html__( 'Partial success! %1$d of %2$d podcasts added to your Interview Tracker.', 'podcast-prospector' ),
                    $success_count,
                    $total
                );
            } else {
                $error_summary = sprintf(
                    /* translators: 1: count, 2: singular/plural text */
                    esc_html__( 'Error! %1$d %2$s could not be imported.', 'podcast-prospector' ),
                    $fail_count,
                    $fail_count === 1 ? esc_html__( 'podcast', 'podcast-prospector' ) : esc_html__( 'podcasts', 'podcast-prospector' )
                );
            }

            $error_details = '';
            if ( ! empty( $failed_items ) ) {
                $error_details = sprintf(
                    '<div class="error-details" id="import-error-details">%s%s</div>',
                    esc_html( $failed_items[0] ),
                    $fail_count > 1
                        ? sprintf( ' (%d total failures - check logs for details)', $fail_count )
                        : ''
                );
            }

            return sprintf(
                '<div class="import-message error" id="import-message-container-error">'
                . '<div class="message-content">'
                . '<i class="fas fa-exclamation-triangle message-icon"></i>'
                . '<span class="message-text">%s</span>'
                . '</div>'
                . '%s'
                . '<div class="message-actions">'
                . '<a href="%s" class="view-tracker-link">%s <i class="fas fa-arrow-right"></i></a>'
                . '</div>'
                . '</div>',
                $error_summary,
                $error_details,
                esc_url( $tracker_link ),
                esc_html__( 'View in Interview Tracker', 'podcast-prospector' )
            );
        }

        // No results
        return '<div class="import-message error" id="import-message-container-error">'
            . '<div class="message-content">'
            . '<i class="fas fa-exclamation-triangle message-icon"></i>'
            . '<span class="message-text">' . esc_html__( 'No podcasts were processed. Please check selection.', 'podcast-prospector' ) . '</span>'
            . '</div>'
            . '</div>';
    }

    /**
     * Extract episode-level data from decoded search result.
     *
     * Returns null if the result is podcast-level only (e.g., bytitle/byadvancedpodcast feeds).
     *
     * @param array  $decoded     Decoded search result data.
     * @param string $search_type Search type used.
     * @return array|null Normalized episode data or null if no episode data.
     */
    public function extract_episode_data( array $decoded, string $search_type ): ?array {
        $data = null;

        if ( 'byperson' === $search_type && isset( $decoded['feedTitle'] ) && isset( $decoded['title'] ) ) {
            // PodcastIndex episode result (has feedTitle for podcast + title for episode)
            $date_raw = $decoded['datePublished'] ?? null;
            $data = [
                'title'       => $decoded['title'] ?? '',
                'date'        => $date_raw ? gmdate( 'Y-m-d', (int) $date_raw ) : null,
                'duration'    => ! empty( $decoded['duration'] ) ? (int) $decoded['duration'] : null,
                'url'         => $decoded['link'] ?? '',
                'guid'        => $decoded['guid'] ?? ( $decoded['enclosureUrl'] ?? '' ),
                'description' => $decoded['description'] ?? '',
                'audio_url'   => $decoded['enclosureUrl'] ?? '',
                'thumbnail'   => $decoded['image'] ?? ( $decoded['feedImage'] ?? '' ),
            ];

        } elseif ( 'byadvancedepisode' === $search_type && isset( $decoded['podcastSeries'] ) ) {
            // Taddy episode result (has podcastSeries nested object)
            $date_raw = $decoded['datePublished'] ?? null;
            $data = [
                'title'       => $decoded['name'] ?? '',
                'date'        => $date_raw ? gmdate( 'Y-m-d', (int) $date_raw ) : null,
                'duration'    => ! empty( $decoded['duration'] ) ? (int) $decoded['duration'] : null,
                'url'         => $decoded['episodeUrl'] ?? ( $decoded['websiteUrl'] ?? '' ),
                'guid'        => $decoded['uuid'] ?? '',
                'description' => $decoded['description'] ?? '',
                'audio_url'   => $decoded['audioUrl'] ?? '',
                'thumbnail'   => $decoded['imageUrl'] ?? '',
            ];

        } elseif ( 'byyoutube' === $search_type && ! empty( $decoded['videoId'] ) ) {
            // YouTube video result
            $data = [
                'title'       => $decoded['title'] ?? '',
                'date'        => ! empty( $decoded['publishedAt'] ) ? gmdate( 'Y-m-d', strtotime( $decoded['publishedAt'] ) ) : null,
                'duration'    => ! empty( $decoded['duration'] ) ? (int) $decoded['duration'] : null,
                'url'         => $decoded['url'] ?? '',
                'guid'        => $decoded['videoId'] ?? '',
                'description' => $decoded['description'] ?? '',
                'audio_url'   => '',
                'thumbnail'   => $decoded['thumbnailUrl'] ?? '',
            ];
        }

        // For bytitle and byadvancedpodcast, data stays null (no episode info)

        // Validate: must have at least a title to be useful
        if ( $data && empty( $data['title'] ) ) {
            return null;
        }

        return $data;
    }

    /**
     * Link an episode to an existing opportunity.
     *
     * Creates engagement record, links to opportunity, sets status to aired,
     * creates guest profile and speaking credit for portfolio.
     *
     * @param int    $opportunity_id Opportunity ID.
     * @param int    $podcast_id     Podcast ID.
     * @param array  $episode_data   Normalized episode data from extract_episode_data().
     * @param int    $user_id        User ID.
     * @return array{engagement_id: int, credit_id: int|null}|false Result or false on failure.
     */
    public function link_episode_to_opportunity( int $opportunity_id, int $podcast_id, array $episode_data, int $user_id ) {
        // 1. Create or find engagement record
        $engagement_insert = [
            'podcast_id'            => $podcast_id,
            'engagement_type'       => 'podcast_interview',
            'title'                 => sanitize_text_field( $episode_data['title'] ),
            'engagement_date'       => $episode_data['date'] ?: null,
            'episode_url'           => esc_url_raw( $episode_data['url'] ?? '' ) ?: null,
            'episode_guid'          => sanitize_text_field( $episode_data['guid'] ?? '' ) ?: null,
            'duration_seconds'      => $episode_data['duration'] ?: null,
            'description'           => sanitize_textarea_field( $episode_data['description'] ?? '' ) ?: null,
            'audio_url'             => esc_url_raw( $episode_data['audio_url'] ?? '' ) ?: null,
            'thumbnail_url'         => esc_url_raw( $episode_data['thumbnail'] ?? '' ) ?: null,
            'discovery_source'      => 'prospector_import',
            'discovered_by_user_id' => $user_id,
            'created_at'            => current_time( 'mysql' ),
            'updated_at'            => current_time( 'mysql' ),
        ];

        $engagement_result = PIT_Engagement_Repository::upsert( $engagement_insert );
        $engagement_id = $engagement_result['id'] ?? null;

        if ( ! $engagement_id ) {
            $this->log_error( 'Failed to create engagement for episode', [
                'opportunity_id' => $opportunity_id,
                'episode_title'  => $episode_data['title'],
            ] );
            return false;
        }

        // 2. Link engagement to opportunity
        $linked = PIT_Opportunity_Repository::link_engagement( $opportunity_id, $engagement_id );

        if ( ! $linked ) {
            $this->log_error( 'Failed to link engagement to opportunity', [
                'opportunity_id' => $opportunity_id,
                'engagement_id'  => $engagement_id,
            ] );
            return false;
        }

        // 3. Update opportunity status to 'aired' and set air_date
        $update_data = [ 'status' => 'aired' ];
        if ( ! empty( $episode_data['date'] ) ) {
            $update_data['air_date'] = $episode_data['date'];
        }
        PIT_Opportunity_Repository::update( $opportunity_id, $update_data );

        // 4. Get or create user's guest profile
        $user = get_userdata( $user_id );
        $guest_id = null;
        $credit_id = null;

        if ( $user && class_exists( 'PIT_Guest_Repository' ) ) {
            $guest_id = PIT_Guest_Repository::upsert( [
                'full_name'  => $user->display_name,
                'email'      => $user->user_email,
                'first_name' => get_user_meta( $user_id, 'first_name', true ),
                'last_name'  => get_user_meta( $user_id, 'last_name', true ),
            ], $user_id );

            // 5. Create speaking credit (links guest to engagement for portfolio)
            if ( $guest_id && class_exists( 'PIT_Speaking_Credit_Repository' ) ) {
                $credit_id = PIT_Speaking_Credit_Repository::link( $guest_id, $engagement_id, 'guest' );
            }
        }

        $this->log_info( 'Linked episode to opportunity', [
            'opportunity_id' => $opportunity_id,
            'engagement_id'  => $engagement_id,
            'guest_id'       => $guest_id,
            'credit_id'      => $credit_id,
            'episode_title'  => $episode_data['title'],
        ] );

        return [
            'engagement_id' => $engagement_id,
            'credit_id'     => $credit_id,
        ];
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
            $this->logger->info( '[GuestIntel Import] ' . $message, $context );
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
            $this->logger->warning( '[GuestIntel Import] ' . $message, $context );
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
            $this->logger->error( '[GuestIntel Import] ' . $message, $context );
        }
    }
}
