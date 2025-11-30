<?php
/**
 * Interview Finder Form Handler Class
 *
 * Handles Formidable Forms entry creation for podcast imports.
 *
 * @package Podcast_Prospector
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Podcast_Prospector_Form_Handler
 *
 * Manages podcast import to Formidable Forms.
 */
class Podcast_Prospector_Form_Handler {

    /**
     * Singleton instance.
     *
     * @var Podcast_Prospector_Form_Handler|null
     */
    private static ?Podcast_Prospector_Form_Handler $instance = null;

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
     * @return Podcast_Prospector_Form_Handler
     */
    public static function get_instance(): Podcast_Prospector_Form_Handler {
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
        if ( class_exists( 'Podcast_Prospector_Settings' ) ) {
            $this->settings = Podcast_Prospector_Settings::get_instance();
        }
    }

    /**
     * Create form entries for selected podcasts.
     *
     * @param array $podcasts  Array of podcast JSON strings.
     * @param array $post_data Additional POST data.
     * @return array{success_count: int, fail_count: int, html: string}
     */
    public function create_entries( array $podcasts, array $post_data ): array {
        $search_term = isset( $post_data['search_term'] ) ? sanitize_text_field( $post_data['search_term'] ) : 'N/A';
        $search_type = isset( $post_data['search_type'] ) ? sanitize_text_field( $post_data['search_type'] ) : 'byperson';
        $current_user = get_current_user_id();

        $total_to_import = count( $podcasts );
        $success_count = 0;
        $fail_count = 0;
        $failed_items = [];

        $form_id = $this->settings ? (int) $this->settings->get( 'form_id' ) : 518;
        $field_map = $this->settings ? $this->settings->get_field_map() : $this->get_default_field_map();

        foreach ( $podcasts as $item ) {
            $decoded = json_decode( stripslashes( $item ), true );

            if ( ! $decoded || ! is_array( $decoded ) ) {
                $fail_count++;
                $failed_items[] = __( 'Invalid data format received for an item.', 'podcast-prospector' );
                $this->log_error( 'Invalid JSON data for import', [ 'raw' => $item ] );
                continue;
            }

            // Extract data based on search type
            $entry_data = $this->extract_entry_data( $decoded, $search_type );

            if ( empty( $entry_data['feed_url'] ) ) {
                $fail_count++;
                $failed_items[] = sprintf(
                    /* translators: %s: podcast name */
                    __( "Podcast '%s' couldn't be imported due to missing RSS feed.", 'podcast-prospector' ),
                    $entry_data['podcast_title']
                );
                $this->log_error( 'Missing RSS feed URL', [ 'title' => $entry_data['podcast_title'] ] );
                continue;
            }

            // Build form entry
            $item_meta = [
                $field_map['podcast_title']    => $entry_data['podcast_title'],
                $field_map['feed_url']         => $entry_data['feed_url'],
                $field_map['itunes_id']        => $entry_data['itunes_id'],
                $field_map['podcastindex_id']  => $entry_data['podcastindex_id'],
                $field_map['podcast_guid']     => $entry_data['podcast_guid'],
                $field_map['original_search']  => $search_term,
                $field_map['search_type_used'] => $search_type,
                $field_map['status']           => 'Potential',
                $field_map['assigned_user']    => $current_user,
                $field_map['archive']          => 0,
            ];

            // Add episode data if available
            if ( ! empty( $entry_data['episode_guid'] ) ) {
                $item_meta[ $field_map['episode_guid'] ] = $entry_data['episode_guid'];
            }
            if ( ! empty( $entry_data['episode_title'] ) ) {
                $item_meta[ $field_map['episode_title'] ] = $entry_data['episode_title'];
            }

            $new_entry = [
                'form_id'     => $form_id,
                'item_key'    => 'entry',
                'frm_user_id' => $current_user,
                'item_meta'   => $item_meta,
            ];

            // Create entry
            if ( class_exists( 'FrmEntry' ) ) {
                $entry_id = FrmEntry::create( $new_entry );

                if ( ! $entry_id || is_wp_error( $entry_id ) ) {
                    $fail_count++;
                    $error_msg = is_wp_error( $entry_id ) ? $entry_id->get_error_message() : 'Unknown error';
                    $failed_items[] = sprintf(
                        /* translators: 1: podcast name, 2: error message */
                        __( "Podcast '%1\$s' failed: %2\$s", 'podcast-prospector' ),
                        $entry_data['podcast_title'],
                        $error_msg
                    );
                    $this->log_error( 'Failed to create form entry', [
                        'title' => $entry_data['podcast_title'],
                        'error' => $error_msg,
                    ] );
                } else {
                    $success_count++;
                    $this->log_info( 'Created form entry', [
                        'entry_id' => $entry_id,
                        'title'    => $entry_data['podcast_title'],
                    ] );
                }
            } else {
                $fail_count++;
                $failed_items[] = sprintf(
                    /* translators: %s: podcast name */
                    __( "Formidable Forms not available. Could not import '%s'.", 'podcast-prospector' ),
                    $entry_data['podcast_title']
                );
                $this->log_error( 'FrmEntry class not found' );
            }
        }

        // Generate response HTML
        $html = $this->generate_response_html( $success_count, $fail_count, $total_to_import, $failed_items );

        return [
            'success_count' => $success_count,
            'fail_count'    => $fail_count,
            'html'          => $html,
        ];
    }

    /**
     * Extract entry data from decoded podcast data.
     *
     * @param array  $decoded     Decoded podcast data.
     * @param string $search_type Search type used.
     * @return array Entry data.
     */
    private function extract_entry_data( array $decoded, string $search_type ): array {
        $data = [
            'podcast_title'   => 'N/A',
            'episode_title'   => '',
            'episode_guid'    => '',
            'podcast_guid'    => '',
            'feed_url'        => '',
            'itunes_id'       => '',
            'podcastindex_id' => '',
        ];

        if ( 'byadvancedepisode' === $search_type && isset( $decoded['podcastSeries'] ) ) {
            // Taddy Episode
            $data['podcast_title'] = $decoded['podcastSeries']['name'] ?? 'N/A';
            $data['episode_title'] = $decoded['name'] ?? '';
            $data['episode_guid'] = $decoded['guid'] ?? '';
            $data['feed_url'] = $decoded['podcastSeries']['rssUrl'] ?? '';
            $data['itunes_id'] = $decoded['podcastSeries']['itunesId'] ?? '';
            $data['podcast_guid'] = $decoded['podcastSeries']['uuid'] ?? '';

        } elseif ( 'byadvancedpodcast' === $search_type ) {
            // Taddy Podcast
            $data['podcast_title'] = $decoded['name'] ?? 'N/A';
            $data['feed_url'] = $decoded['rssUrl'] ?? '';
            $data['itunes_id'] = $decoded['itunesId'] ?? '';
            $data['podcast_guid'] = $decoded['uuid'] ?? '';

        } elseif ( 'byperson' === $search_type && isset( $decoded['feedTitle'] ) ) {
            // PodcastIndex Episode
            $data['podcast_title'] = $decoded['feedTitle'] ?? 'N/A';
            $data['episode_title'] = $decoded['title'] ?? '';
            $data['episode_guid'] = $decoded['guid'] ?? '';
            $data['feed_url'] = $decoded['feedUrl'] ?? '';
            $data['itunes_id'] = $decoded['feedItunesId'] ?? '';
            $data['podcast_guid'] = $decoded['feedId'] ?? '';
            $data['podcastindex_id'] = $decoded['id'] ?? '';

        } elseif ( 'bytitle' === $search_type && isset( $decoded['title'] ) && ! isset( $decoded['feedTitle'] ) ) {
            // PodcastIndex Feed
            $data['podcast_title'] = $decoded['title'] ?? 'N/A';
            $data['feed_url'] = $decoded['url'] ?? '';
            $data['itunes_id'] = $decoded['itunesId'] ?? '';
            $data['podcast_guid'] = $decoded['id'] ?? '';
            $data['podcastindex_id'] = $decoded['id'] ?? '';

        } else {
            // Fallback
            $data['podcast_title'] = $decoded['title'] ?? ( $decoded['feedTitle'] ?? 'Unknown Title' );
            $data['episode_title'] = isset( $decoded['feedTitle'] ) ? ( $decoded['title'] ?? '' ) : '';
            $data['episode_guid'] = $decoded['guid'] ?? '';
            $data['feed_url'] = $decoded['feedUrl'] ?? ( $decoded['url'] ?? ( $decoded['rssUrl'] ?? '' ) );
            $data['itunes_id'] = $decoded['itunesId'] ?? ( $decoded['feedItunesId'] ?? '' );
            $data['podcastindex_id'] = $decoded['id'] ?? '';
            $data['podcast_guid'] = $decoded['feedId']
                ?? ( $decoded['uuid']
                    ?? ( $decoded['podcastSeries']['uuid']
                        ?? ( $decoded['id'] ?? '' ) ) );

            $this->log_warning( 'Could not determine exact result type', [ 'decoded' => $decoded ] );
        }

        return $data;
    }

    /**
     * Get default field map (fallback).
     *
     * @return array
     */
    private function get_default_field_map(): array {
        return [
            'podcast_title'    => 8111,
            'feed_url'         => 9928,
            'itunes_id'        => 9929,
            'podcastindex_id'  => 9930,
            'podcast_guid'     => 9931,
            'original_search'  => 9932,
            'search_type_used' => 9948,
            'status'           => 8113,
            'assigned_user'    => 8240,
            'episode_guid'     => 10392,
            'episode_title'    => 10393,
            'archive'          => 10402,
        ];
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
                    esc_html__( 'Success! %1$d %2$s been added to your collection.', 'podcast-prospector' ),
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
                    esc_html__( 'Partial success! %1$d of %2$d podcasts added to your collection.', 'podcast-prospector' ),
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
     * Log info message.
     *
     * @param string $message Log message.
     * @param array  $context Additional context.
     * @return void
     */
    private function log_info( string $message, array $context = [] ): void {
        if ( $this->logger ) {
            $this->logger->info( '[Form Handler] ' . $message, $context );
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
            $this->logger->warning( '[Form Handler] ' . $message, $context );
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
            $this->logger->error( '[Form Handler] ' . $message, $context );
        }
    }
}
