<?php
/**
 * Competitive Intel Bridge (Prospector Side)
 *
 * Exposes Taddy + PodcastIndex search capabilities to ShowAuthority
 * via WordPress filters. This allows ShowAuthority's competitive intel
 * system to discover competitor appearances without direct class coupling.
 *
 * @package Podcast_Prospector
 * @since 2.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Podcast_Prospector_Competitive_Intel_Bridge {

    /**
     * Singleton instance.
     *
     * @var Podcast_Prospector_Competitive_Intel_Bridge|null
     */
    private static ?Podcast_Prospector_Competitive_Intel_Bridge $instance = null;

    /**
     * Logger instance.
     *
     * @var Podcast_Prospector_Logger|null
     */
    private ?Podcast_Prospector_Logger $logger = null;

    /**
     * Get singleton instance.
     *
     * @return Podcast_Prospector_Competitive_Intel_Bridge
     */
    public static function get_instance(): Podcast_Prospector_Competitive_Intel_Bridge {
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

        $this->init_hooks();
    }

    /**
     * Register WordPress filter hooks.
     *
     * @return void
     */
    private function init_hooks(): void {
        add_filter( 'guestify_discover_competitor', [ $this, 'discover_competitor' ], 10, 2 );
        add_filter( 'guestify_prospector_is_available', '__return_true' );
    }

    /**
     * Discover competitor appearances across Taddy and PodcastIndex.
     *
     * @param array $results Default empty (filter convention).
     * @param array $args    {competitor_name: string, max_results: int, genre: string}
     * @return array {taddy_results: array, podcastindex_results: array, error: string|null}
     */
    public function discover_competitor( array $results, array $args ): array {
        $name        = $args['competitor_name'] ?? '';
        $max_results = (int) ( $args['max_results'] ?? 25 );
        $genre       = $args['genre'] ?? 'ALL';

        if ( empty( $name ) ) {
            return [
                'taddy_results'        => [],
                'podcastindex_results'  => [],
                'error'                 => 'Competitor name is required',
            ];
        }

        $this->log_info( 'Discovering competitor', [
            'name'        => $name,
            'max_results' => $max_results,
            'genre'       => $genre,
        ] );

        $taddy_results = [];
        $podcastindex_results = [];
        $errors = [];

        // 1. Search Taddy for episodes mentioning the competitor
        $taddy = Podcast_Prospector_API_Taddy::get_instance();
        if ( $taddy->is_configured() ) {
            $taddy_response = $taddy->search_episodes( [
                'search_term'      => $name,
                'results_per_page' => min( $max_results, 25 ),
                'page'             => 1,
                'genre'            => $genre,
            ] );

            if ( is_wp_error( $taddy_response ) ) {
                $errors[] = 'Taddy: ' . $taddy_response->get_error_message();
                $this->log_error( 'Taddy search failed', [
                    'error' => $taddy_response->get_error_message(),
                ] );
            } elseif ( isset( $taddy_response['data']['searchForTerm']['podcastEpisodes'] ) ) {
                $taddy_results = $taddy_response['data']['searchForTerm']['podcastEpisodes'];
            }
        } else {
            $this->log_info( 'Taddy API not configured, skipping' );
        }

        // 2. Search PodcastIndex by person name
        $podcastindex = Podcast_Prospector_API_PodcastIndex::get_instance();
        if ( $podcastindex->is_configured() ) {
            $pi_response = $podcastindex->search_by_person( $name, $max_results, $genre );

            if ( is_wp_error( $pi_response ) ) {
                $errors[] = 'PodcastIndex: ' . $pi_response->get_error_message();
                $this->log_error( 'PodcastIndex search failed', [
                    'error' => $pi_response->get_error_message(),
                ] );
            } elseif ( isset( $pi_response['items'] ) && is_array( $pi_response['items'] ) ) {
                $podcastindex_results = $pi_response['items'];
            }
        } else {
            $this->log_info( 'PodcastIndex API not configured, skipping' );
        }

        $this->log_info( 'Discovery complete', [
            'name'            => $name,
            'taddy_count'     => count( $taddy_results ),
            'pi_count'        => count( $podcastindex_results ),
            'errors'          => $errors,
        ] );

        return [
            'taddy_results'        => $taddy_results,
            'podcastindex_results' => $podcastindex_results,
            'error'                => ! empty( $errors ) ? implode( '; ', $errors ) : null,
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
            $this->logger->info( '[Competitive Intel Bridge] ' . $message, $context );
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
            $this->logger->error( '[Competitive Intel Bridge] ' . $message, $context );
        }
    }
}
