<?php
/**
 * REST API Class
 *
 * @package Interview_Finder
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Interview_Finder_REST_API
 *
 * Provides REST API endpoints for search and import operations.
 */
class Interview_Finder_REST_API {

    /**
     * API namespace.
     *
     * @var string
     */
    private const NAMESPACE = 'interview-finder/v1';

    /**
     * Search service.
     *
     * @var Interview_Finder_Search_Service
     */
    private Interview_Finder_Search_Service $search_service;

    /**
     * Form handler.
     *
     * @var Interview_Finder_Form_Handler
     */
    private Interview_Finder_Form_Handler $form_handler;

    /**
     * Validator.
     *
     * @var Interview_Finder_Validator
     */
    private Interview_Finder_Validator $validator;

    /**
     * Membership.
     *
     * @var Interview_Finder_Membership
     */
    private Interview_Finder_Membership $membership;

    /**
     * Logger.
     *
     * @var Interview_Finder_Logger|null
     */
    private ?Interview_Finder_Logger $logger;

    /**
     * Constructor.
     *
     * @param Interview_Finder_Search_Service $search_service Search service.
     * @param Interview_Finder_Form_Handler   $form_handler   Form handler.
     * @param Interview_Finder_Validator      $validator      Validator.
     * @param Interview_Finder_Membership     $membership     Membership.
     * @param Interview_Finder_Logger|null    $logger         Logger.
     */
    public function __construct(
        Interview_Finder_Search_Service $search_service,
        Interview_Finder_Form_Handler $form_handler,
        Interview_Finder_Validator $validator,
        Interview_Finder_Membership $membership,
        ?Interview_Finder_Logger $logger = null
    ) {
        $this->search_service = $search_service;
        $this->form_handler = $form_handler;
        $this->validator = $validator;
        $this->membership = $membership;
        $this->logger = $logger;
    }

    /**
     * Register REST routes.
     *
     * @return void
     */
    public function register_routes(): void {
        // Search endpoint
        register_rest_route( self::NAMESPACE, '/search', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'handle_search' ],
            'permission_callback' => [ $this, 'check_search_permission' ],
            'args'                => $this->get_search_args(),
        ] );

        // Import endpoint
        register_rest_route( self::NAMESPACE, '/import', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'handle_import' ],
            'permission_callback' => [ $this, 'check_import_permission' ],
            'args'                => $this->get_import_args(),
        ] );

        // User stats endpoint
        register_rest_route( self::NAMESPACE, '/user/stats', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_user_stats' ],
            'permission_callback' => [ $this, 'check_user_permission' ],
        ] );

        // Clear cache endpoint (admin only)
        register_rest_route( self::NAMESPACE, '/cache/clear', [
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => [ $this, 'clear_cache' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ] );
    }

    /**
     * Handle search request.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function handle_search( WP_REST_Request $request ) {
        $user_id = get_current_user_id();

        // Validate input
        $validation = $this->validator->validate_search_request( $request->get_params() );
        if ( ! $validation->is_valid() ) {
            return new WP_Error(
                'validation_error',
                $validation->get_first_error(),
                [ 'status' => 400, 'errors' => $validation->get_errors() ]
            );
        }

        // Check search cap
        $database = Interview_Finder_Database::get_instance();
        $ghl_id = $this->membership->get_ghl_id( $user_id );
        $search_cap = $this->membership->get_search_cap( $user_id );

        $database->reset_search_cap_if_needed( $ghl_id, $user_id );
        $user_data = $database->get_user_data( $ghl_id, $user_id );
        $search_count = $user_data ? (int) $user_data->search_count : 0;

        if ( $search_cap > 0 && $search_count >= $search_cap ) {
            return new WP_Error(
                'search_cap_reached',
                __( 'You have reached the maximum number of searches allowed for your plan.', 'interview-finder' ),
                [ 'status' => 429 ]
            );
        }

        // Increment search count
        $database->increment_search_count( $ghl_id, $user_id );

        // Perform search
        $result = $this->search_service->search( $validation->get_all(), $user_id );

        if ( ! $result->is_success() ) {
            return new WP_Error(
                'search_error',
                $result->get_error() ?: __( 'Search failed.', 'interview-finder' ),
                [ 'status' => 500 ]
            );
        }

        // Get updated user stats
        $updated_data = $database->get_user_data( $ghl_id, $user_id );

        return new WP_REST_Response( [
            'success'     => true,
            'data'        => $result->get_data(),
            'from_cache'  => $result->is_from_cache(),
            'search_type' => $result->get_search_type(),
            'count'       => $result->get_count(),
            'user_stats'  => [
                'search_count'       => $updated_data ? (int) $updated_data->search_count : 0,
                'searches_remaining' => max( 0, $search_cap - ( $updated_data ? (int) $updated_data->search_count : 0 ) ),
                'search_cap'         => $search_cap,
            ],
        ], 200 );
    }

    /**
     * Handle import request.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function handle_import( WP_REST_Request $request ) {
        // Validate input
        $validation = $this->validator->validate_import_request( $request->get_params() );
        if ( ! $validation->is_valid() ) {
            return new WP_Error(
                'validation_error',
                $validation->get_first_error(),
                [ 'status' => 400, 'errors' => $validation->get_errors() ]
            );
        }

        // Perform import
        $result = $this->form_handler->create_entries(
            array_map( 'wp_json_encode', $validation->get( 'podcasts' ) ),
            [
                'search_term' => $validation->get( 'search_term' ),
                'search_type' => $validation->get( 'search_type' ),
            ]
        );

        $status = $result['fail_count'] === 0 ? 200 : 207; // 207 = Multi-Status for partial success

        return new WP_REST_Response( [
            'success'       => $result['fail_count'] === 0,
            'success_count' => $result['success_count'],
            'fail_count'    => $result['fail_count'],
            'message'       => $result['html'], // Contains formatted message
        ], $status );
    }

    /**
     * Get user stats.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_user_stats( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        $database = Interview_Finder_Database::get_instance();

        $ghl_id = $this->membership->get_ghl_id( $user_id );
        $user_data = $database->get_user_data( $ghl_id, $user_id );
        $settings = $this->membership->get_user_settings( $user_id );
        $search_cap = $this->membership->get_search_cap( $user_id );

        return new WP_REST_Response( [
            'user_id'            => $user_id,
            'membership_level'   => $this->membership->get_user_membership_level( $user_id ),
            'search_count'       => $user_data ? (int) $user_data->search_count : 0,
            'total_searches'     => $user_data ? (int) $user_data->total_searches : 0,
            'search_cap'         => $search_cap,
            'searches_remaining' => max( 0, $search_cap - ( $user_data ? (int) $user_data->search_count : 0 ) ),
            'last_searched'      => $user_data->last_searched ?? null,
            'settings'           => $settings,
        ], 200 );
    }

    /**
     * Clear search cache.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function clear_cache( WP_REST_Request $request ) {
        $search_cache = new Interview_Finder_Search_Cache();
        $rss_cache = Interview_Finder_RSS_Cache::get_instance();

        $search_cleared = $search_cache->clear_all();
        $rss_cleared = $rss_cache->clear_all_caches();

        return new WP_REST_Response( [
            'success'        => true,
            'search_cleared' => $search_cleared,
            'rss_cleared'    => $rss_cleared,
        ], 200 );
    }

    /**
     * Check search permission.
     *
     * @return bool|WP_Error
     */
    public function check_search_permission() {
        if ( ! is_user_logged_in() ) {
            return new WP_Error(
                'rest_not_logged_in',
                __( 'You must be logged in to search.', 'interview-finder' ),
                [ 'status' => 401 ]
            );
        }
        return true;
    }

    /**
     * Check import permission.
     *
     * @return bool|WP_Error
     */
    public function check_import_permission() {
        return $this->check_search_permission();
    }

    /**
     * Check user permission.
     *
     * @return bool|WP_Error
     */
    public function check_user_permission() {
        return $this->check_search_permission();
    }

    /**
     * Check admin permission.
     *
     * @return bool|WP_Error
     */
    public function check_admin_permission() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error(
                'rest_forbidden',
                __( 'You do not have permission to perform this action.', 'interview-finder' ),
                [ 'status' => 403 ]
            );
        }
        return true;
    }

    /**
     * Get search endpoint arguments.
     *
     * @return array
     */
    private function get_search_args(): array {
        return [
            'search_term' => [
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'search_type' => [
                'required' => false,
                'type'     => 'string',
                'default'  => 'byperson',
                'enum'     => [ 'byperson', 'bytitle', 'byadvancedpodcast', 'byadvancedepisode' ],
            ],
            'page' => [
                'required' => false,
                'type'     => 'integer',
                'default'  => 1,
                'minimum'  => 1,
            ],
            'results_per_page' => [
                'required' => false,
                'type'     => 'integer',
                'default'  => 10,
                'minimum'  => 5,
                'maximum'  => 25,
            ],
            'language' => [
                'required' => false,
                'type'     => 'string',
                'default'  => 'ALL',
            ],
            'country' => [
                'required' => false,
                'type'     => 'string',
                'default'  => 'ALL',
            ],
            'genre' => [
                'required' => false,
                'type'     => 'string',
                'default'  => 'ALL',
            ],
            'sort_order' => [
                'required' => false,
                'type'     => 'string',
                'default'  => 'BEST_MATCH',
                'enum'     => [ 'BEST_MATCH', 'LATEST', 'OLDEST' ],
            ],
        ];
    }

    /**
     * Get import endpoint arguments.
     *
     * @return array
     */
    private function get_import_args(): array {
        return [
            'podcasts' => [
                'required' => true,
                'type'     => 'array',
            ],
            'search_term' => [
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'search_type' => [
                'required' => false,
                'type'     => 'string',
                'default'  => 'byperson',
            ],
        ];
    }
}
