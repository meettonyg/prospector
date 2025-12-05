<?php
/**
 * Interview Finder AJAX Handler Class
 *
 * Handles all AJAX requests with proper nonce verification.
 *
 * @package Podcast_Prospector
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Podcast_Prospector_Ajax_Handler
 *
 * Manages AJAX endpoints for search and import operations.
 */
class Podcast_Prospector_Ajax_Handler {

    /**
     * Nonce action for search.
     *
     * @var string
     */
    const NONCE_SEARCH = 'podcast_prospector_search';

    /**
     * Nonce action for import.
     *
     * @var string
     */
    const NONCE_IMPORT = 'podcast_prospector_import';

    /**
     * Singleton instance.
     *
     * @var Podcast_Prospector_Ajax_Handler|null
     */
    private static ?Podcast_Prospector_Ajax_Handler $instance = null;

    /**
     * Logger instance.
     *
     * @var Podcast_Prospector_Logger|null
     */
    private ?Podcast_Prospector_Logger $logger = null;

    /**
     * Get singleton instance.
     *
     * @return Podcast_Prospector_Ajax_Handler
     */
    public static function get_instance(): Podcast_Prospector_Ajax_Handler {
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
     * Register AJAX hooks.
     *
     * @return void
     */
    public function init(): void {
        // Search handlers
        add_action( 'wp_ajax_perform_search', [ $this, 'handle_search' ] );
        add_action( 'wp_ajax_nopriv_perform_search', [ $this, 'handle_search' ] );

        // Import handlers
        add_action( 'wp_ajax_add_podcasts_to_form', [ $this, 'handle_import' ] );
        add_action( 'wp_ajax_nopriv_add_podcasts_to_form', [ $this, 'handle_import' ] );
    }

    /**
     * Verify nonce for request.
     *
     * @param string $action Nonce action to verify.
     * @return bool
     */
    private function verify_nonce( string $action ): bool {
        $nonce = '';

        if ( isset( $_POST['_ajax_nonce'] ) ) {
            $nonce = sanitize_text_field( wp_unslash( $_POST['_ajax_nonce'] ) );
        } elseif ( isset( $_POST['nonce'] ) ) {
            $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );
        } elseif ( isset( $_REQUEST['_wpnonce'] ) ) {
            $nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );
        }

        if ( empty( $nonce ) ) {
            $this->log_warning( 'No nonce provided in request' );
            return false;
        }

        $valid = wp_verify_nonce( $nonce, $action );

        if ( ! $valid ) {
            $this->log_warning( 'Invalid nonce', [ 'action' => $action ] );
        }

        return (bool) $valid;
    }

    /**
     * Handle search AJAX request.
     *
     * @return void
     */
    public function handle_search(): void {
        // Verify nonce
        if ( ! $this->verify_nonce( self::NONCE_SEARCH ) ) {
            wp_send_json_error( [
                'message' => __( 'Security verification failed. Please refresh the page and try again.', 'podcast-prospector' ),
            ] );
            return;
        }

        // Validate required fields
        if ( ! isset( $_POST['search_term'] ) || ! isset( $_POST['search_type'] ) ) {
            wp_send_json_error( [
                'message' => __( 'Missing required parameters.', 'podcast-prospector' ),
            ] );
            return;
        }

        $user_id = get_current_user_id();
        $membership = Podcast_Prospector_Membership::get_instance();
        $database = Podcast_Prospector_Database::get_instance();

        $ghl_id = $membership->get_ghl_id( $user_id );
        $search_cap = $membership->get_search_cap( $user_id );
        $subscription_date = $membership->get_subscription_date( $user_id );

        // Verify user identity
        if ( ! $ghl_id && ! $user_id ) {
            wp_send_json_error( [
                'message' => __( 'Error: Required user data not found.', 'podcast-prospector' ),
            ] );
            return;
        }

        // Ensure user record exists
        $database->get_or_create_user_data( $ghl_id, $user_id );

        // Check for subscription renewal reset
        $database->reset_search_cap_if_needed( $ghl_id, $user_id );

        // Get current search count
        $user_data = $database->get_user_data( $ghl_id, $user_id );
        $search_count = $user_data ? (int) $user_data->search_count : 0;

        // Check search cap
        if ( $search_cap > 0 && $search_count >= $search_cap ) {
            wp_send_json_error( [
                'message' => __( 'You have reached the maximum number of searches allowed for your plan.', 'podcast-prospector' ),
            ] );
            return;
        }

        // Increment search count
        $database->increment_search_count( $ghl_id, $user_id );

        // Get updated counts
        $updated_data = $database->get_user_data( $ghl_id, $user_id );
        $new_search_count = $updated_data ? (int) $updated_data->search_count : 0;
        $searches_remaining = max( 0, $search_cap - $new_search_count );
        $last_searched = $updated_data->last_searched ?? 'N/A';

        // Sanitize inputs
        $search_term = sanitize_text_field( wp_unslash( $_POST['search_term'] ) );
        $search_type = sanitize_text_field( wp_unslash( $_POST['search_type'] ) );
        $language = isset( $_POST['language'] ) ? sanitize_text_field( wp_unslash( $_POST['language'] ) ) : 'ALL';
        $country = isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : 'ALL';
        $genre = isset( $_POST['genre'] ) ? sanitize_text_field( wp_unslash( $_POST['genre'] ) ) : 'ALL';
        $after_date = isset( $_POST['after_date'] ) ? sanitize_text_field( wp_unslash( $_POST['after_date'] ) ) : '';
        $before_date = isset( $_POST['before_date'] ) ? sanitize_text_field( wp_unslash( $_POST['before_date'] ) ) : '';
        $is_safe_mode = isset( $_POST['isSafeMode'] ) ? ! filter_var( wp_unslash( $_POST['isSafeMode'] ), FILTER_VALIDATE_BOOLEAN ) : true;
        $results_per_page = isset( $_POST['results_per_page'] ) ? (int) $_POST['results_per_page'] : 10;
        $page = isset( $_POST['page'] ) ? (int) $_POST['page'] : 1;
        $sort_order = isset( $_POST['sort_order'] ) ? sanitize_text_field( wp_unslash( $_POST['sort_order'] ) ) : 'BEST_MATCH';

        // Apply membership constraints
        $settings = $membership->get_user_settings( $user_id );
        $results_per_page = $membership->constrain_results_per_page( $results_per_page, $user_id );
        $page = $membership->constrain_page_number( $page, $user_id );

        // Force safe mode if required by membership
        if ( $membership->is_safe_mode_forced( $user_id ) ) {
            $is_safe_mode = true;
        }

        try {
            $renderer = Podcast_Prospector_Renderer::get_instance();
            $results_html = '';

            if ( 'byadvancedepisode' === $search_type ) {
                $taddy_api = Podcast_Prospector_API_Taddy::get_instance();
                
                // Check if Taddy API is configured
                if ( ! $taddy_api->is_configured() ) {
                    wp_send_json_error( [
                        'message' => __( 'Taddy API credentials not configured.', 'podcast-prospector' ),
                        'details' => __( 'Please configure Taddy API Key and User ID in Settings > Interview Finder.', 'podcast-prospector' ),
                    ] );
                    return;
                }
                $response = $taddy_api->search_episodes( [
                    'search_term'      => $search_term,
                    'language'         => $language,
                    'country'          => $country,
                    'genre'            => $genre,
                    'after_date'       => $after_date,
                    'before_date'      => $before_date,
                    'is_safe_mode'     => $is_safe_mode,
                    'results_per_page' => $results_per_page,
                    'page'             => $page,
                    'sort_order'       => $sort_order,
                ] );

                if ( is_wp_error( $response ) ) {
                    throw new Exception( $response->get_error_message() );
                }

                $results_html = $renderer->render_taddy_episode_results( $response, $search_term, $settings );

            } elseif ( 'byadvancedpodcast' === $search_type ) {
                $taddy_api = Podcast_Prospector_API_Taddy::get_instance();
                
                // Check if Taddy API is configured
                if ( ! $taddy_api->is_configured() ) {
                    wp_send_json_error( [
                        'message' => __( 'Taddy API credentials not configured.', 'podcast-prospector' ),
                        'details' => __( 'Please configure Taddy API Key and User ID in Settings > Interview Finder.', 'podcast-prospector' ),
                    ] );
                    return;
                }
                $response = $taddy_api->search_podcasts( [
                    'search_term'      => $search_term,
                    'language'         => $language,
                    'country'          => $country,
                    'genre'            => $genre,
                    'after_date'       => $after_date,
                    'before_date'      => $before_date,
                    'is_safe_mode'     => $is_safe_mode,
                    'results_per_page' => $results_per_page,
                    'page'             => $page,
                    'sort_order'       => $sort_order,
                ] );

                if ( is_wp_error( $response ) ) {
                    throw new Exception( $response->get_error_message() );
                }

                $results_html = $renderer->render_taddy_podcast_results( $response, $search_term, $settings );

            } else {
                // PodcastIndex search
                $pi_api = Podcast_Prospector_API_PodcastIndex::get_instance();
                
                // Check if PodcastIndex API is configured
                if ( ! $pi_api->is_configured() ) {
                    wp_send_json_error( [
                        'message' => __( 'PodcastIndex API credentials not configured.', 'podcast-prospector' ),
                        'details' => __( 'Please configure PodcastIndex API Key and Secret in Settings > Interview Finder.', 'podcast-prospector' ),
                    ] );
                    return;
                }
                
                $max_results = $membership->get_podcastindex_max( $user_id );

                if ( 'byperson' === $search_type ) {
                    $response = $pi_api->search_by_person( $search_term, $max_results );
                } else {
                    $response = $pi_api->search_by_term( $search_term, $max_results );
                }

                if ( is_wp_error( $response ) ) {
                    throw new Exception( $response->get_error_message() );
                }

                $results_html = $renderer->render_podcastindex_results( $response, $search_term );
            }

            $this->log_info( 'Search completed', [
                'user_id'     => $user_id,
                'search_type' => $search_type,
                'term'        => $search_term,
            ] );

            wp_send_json_success( [
                'html'      => $results_html,
                'user_data' => [
                    'search_count'       => $new_search_count,
                    'searches_remaining' => $searches_remaining,
                    'last_searched'      => $last_searched,
                    'subscription_date'  => $subscription_date,
                    'search_cap'         => $search_cap,
                ],
            ] );

        } catch ( Exception $e ) {
            $this->log_error( 'Search error', [
                'error'   => $e->getMessage(),
                'user_id' => $user_id,
            ] );

            wp_send_json_error( [
                'message' => __( 'An error occurred during the search.', 'podcast-prospector' ),
                'details' => $e->getMessage(),
            ] );
        }
    }

    /**
     * Handle import AJAX request.
     *
     * @return void
     */
    public function handle_import(): void {
        // Verify nonce
        if ( ! $this->verify_nonce( self::NONCE_IMPORT ) ) {
            $error_html = '<div class="import-message error" id="import-message-container-error">'
                . '<div class="message-content">'
                . '<i class="fas fa-exclamation-triangle message-icon"></i>'
                . '<span class="message-text">' . esc_html__( 'Security verification failed. Please refresh the page.', 'podcast-prospector' ) . '</span>'
                . '</div></div>';

            wp_send_json_error( [ 'html' => $error_html ] );
            return;
        }

        // Validate input
        if ( ! isset( $_POST['podcasts'] ) || ! is_array( $_POST['podcasts'] ) ) {
            $error_html = '<div class="import-message error" id="import-message-container-error">'
                . '<div class="message-content">'
                . '<i class="fas fa-exclamation-triangle message-icon"></i>'
                . '<span class="message-text">' . esc_html__( 'Error! No podcasts were selected for import.', 'podcast-prospector' ) . '</span>'
                . '</div></div>';

            wp_send_json_error( [ 'html' => $error_html ] );
            return;
        }

        // Import directly to Guest Intelligence database (pit_* tables)
        $handler = Podcast_Prospector_Guest_Intel_Import_Handler::get_instance();
        $result = $handler->import_items( $_POST['podcasts'], $_POST );

        if ( $result['success_count'] > 0 && $result['fail_count'] === 0 ) {
            wp_send_json_success( [ 'html' => $result['html'] ] );
        } else {
            wp_send_json_error( [ 'html' => $result['html'] ] );
        }
    }

    /**
     * Generate nonce for search.
     *
     * @return string
     */
    public static function create_search_nonce(): string {
        return wp_create_nonce( self::NONCE_SEARCH );
    }

    /**
     * Generate nonce for import.
     *
     * @return string
     */
    public static function create_import_nonce(): string {
        return wp_create_nonce( self::NONCE_IMPORT );
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
            $this->logger->info( '[AJAX] ' . $message, $context );
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
            $this->logger->warning( '[AJAX] ' . $message, $context );
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
            $this->logger->error( '[AJAX] ' . $message, $context );
        }
    }
}
