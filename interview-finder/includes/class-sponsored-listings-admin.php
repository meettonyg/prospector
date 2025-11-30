<?php
/**
 * Sponsored Listings Admin Class
 *
 * Handles the WordPress admin interface for managing sponsored listings.
 *
 * @package Interview_Finder
 * @since 2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Interview_Finder_Sponsored_Listings_Admin
 *
 * Admin interface for sponsored listings management.
 */
class Interview_Finder_Sponsored_Listings_Admin {

    /**
     * Sponsored listings instance.
     *
     * @var Interview_Finder_Sponsored_Listings
     */
    private Interview_Finder_Sponsored_Listings $listings;

    /**
     * Admin page hook.
     *
     * @var string
     */
    private string $page_hook = '';

    /**
     * Constructor.
     *
     * @param Interview_Finder_Sponsored_Listings $listings Listings instance.
     */
    public function __construct( Interview_Finder_Sponsored_Listings $listings ) {
        $this->listings = $listings;
    }

    /**
     * Initialize admin hooks.
     *
     * @return void
     */
    public function init(): void {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'handle_actions' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
    }

    /**
     * Add admin menu page.
     *
     * @return void
     */
    public function add_admin_menu(): void {
        $this->page_hook = add_submenu_page(
            'interview-finder',
            __( 'Sponsored Listings', 'interview-finder' ),
            __( 'Sponsored Listings', 'interview-finder' ),
            'manage_options',
            'interview-finder-sponsored',
            [ $this, 'render_page' ]
        );
    }

    /**
     * Enqueue admin scripts.
     *
     * @param string $hook Current admin page hook.
     * @return void
     */
    public function enqueue_scripts( string $hook ): void {
        if ( $hook !== $this->page_hook ) {
            return;
        }

        wp_enqueue_style(
            'interview-finder-sponsored-admin',
            INTERVIEW_FINDER_PLUGIN_URL . 'assets/css/sponsored-admin.css',
            [],
            INTERVIEW_FINDER_VERSION
        );
    }

    /**
     * Handle form actions.
     *
     * @return void
     */
    public function handle_actions(): void {
        if ( ! isset( $_GET['page'] ) || 'interview-finder-sponsored' !== $_GET['page'] ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Handle create/update
        if ( isset( $_POST['sponsored_listing_submit'] ) && check_admin_referer( 'sponsored_listing_save', 'sponsored_nonce' ) ) {
            $this->save_listing();
        }

        // Handle delete
        if ( isset( $_GET['action'] ) && 'delete' === $_GET['action'] && isset( $_GET['id'] ) ) {
            if ( check_admin_referer( 'delete_sponsored_' . intval( $_GET['id'] ) ) ) {
                $this->listings->delete( intval( $_GET['id'] ) );
                wp_safe_redirect( admin_url( 'admin.php?page=interview-finder-sponsored&deleted=1' ) );
                exit;
            }
        }

        // Handle status change
        if ( isset( $_GET['action'] ) && in_array( $_GET['action'], [ 'activate', 'pause' ], true ) && isset( $_GET['id'] ) ) {
            if ( check_admin_referer( $_GET['action'] . '_sponsored_' . intval( $_GET['id'] ) ) ) {
                $new_status = 'activate' === $_GET['action'] ? 'active' : 'paused';
                $this->listings->update( intval( $_GET['id'] ), [ 'status' => $new_status ] );
                wp_safe_redirect( admin_url( 'admin.php?page=interview-finder-sponsored&updated=1' ) );
                exit;
            }
        }
    }

    /**
     * Save listing from form submission.
     *
     * @return void
     */
    private function save_listing(): void {
        $data = [
            'name'                => sanitize_text_field( $_POST['name'] ?? '' ),
            'podcast_title'       => sanitize_text_field( $_POST['podcast_title'] ?? '' ),
            'podcast_uuid'        => sanitize_text_field( $_POST['podcast_uuid'] ?? '' ),
            'podcast_itunes_id'   => sanitize_text_field( $_POST['podcast_itunes_id'] ?? '' ),
            'podcast_image_url'   => esc_url_raw( $_POST['podcast_image_url'] ?? '' ),
            'podcast_description' => sanitize_textarea_field( $_POST['podcast_description'] ?? '' ),
            'podcast_url'         => esc_url_raw( $_POST['podcast_url'] ?? '' ),
            'podcast_rss_url'     => esc_url_raw( $_POST['podcast_rss_url'] ?? '' ),
            'categories'          => sanitize_text_field( $_POST['categories'] ?? '' ),
            'priority'            => intval( $_POST['priority'] ?? 0 ),
            'status'              => sanitize_text_field( $_POST['status'] ?? 'active' ),
            'start_date'          => ! empty( $_POST['start_date'] ) ? sanitize_text_field( $_POST['start_date'] ) : null,
            'end_date'            => ! empty( $_POST['end_date'] ) ? sanitize_text_field( $_POST['end_date'] ) : null,
            'impression_limit'    => intval( $_POST['impression_limit'] ?? 0 ),
            'click_limit'         => intval( $_POST['click_limit'] ?? 0 ),
        ];

        $id = isset( $_POST['listing_id'] ) ? intval( $_POST['listing_id'] ) : 0;

        if ( $id > 0 ) {
            $this->listings->update( $id, $data );
            $redirect = admin_url( 'admin.php?page=interview-finder-sponsored&updated=1' );
        } else {
            $this->listings->create( $data );
            $redirect = admin_url( 'admin.php?page=interview-finder-sponsored&created=1' );
        }

        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Render admin page.
     *
     * @return void
     */
    public function render_page(): void {
        // Ensure tables exist
        $this->listings->create_tables();

        $action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';
        $id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">' . esc_html__( 'Sponsored Listings', 'interview-finder' ) . '</h1>';

        // Show notices
        $this->show_notices();

        if ( 'new' === $action || 'edit' === $action ) {
            $this->render_form( $id );
        } elseif ( 'stats' === $action && $id > 0 ) {
            $this->render_stats( $id );
        } else {
            $this->render_list();
        }

        echo '</div>';
    }

    /**
     * Show admin notices.
     *
     * @return void
     */
    private function show_notices(): void {
        if ( isset( $_GET['created'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Sponsored listing created successfully.', 'interview-finder' ) . '</p></div>';
        }
        if ( isset( $_GET['updated'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Sponsored listing updated successfully.', 'interview-finder' ) . '</p></div>';
        }
        if ( isset( $_GET['deleted'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Sponsored listing deleted successfully.', 'interview-finder' ) . '</p></div>';
        }
    }

    /**
     * Render listings list.
     *
     * @return void
     */
    private function render_list(): void {
        $listings = $this->listings->get_all();
        $stats = $this->listings->get_aggregate_stats();

        echo '<a href="' . esc_url( admin_url( 'admin.php?page=interview-finder-sponsored&action=new' ) ) . '" class="page-title-action">' . esc_html__( 'Add New', 'interview-finder' ) . '</a>';
        echo '<hr class="wp-header-end">';

        // Stats summary
        echo '<div class="sponsored-stats-summary" style="background: #fff; padding: 15px; margin: 20px 0; border: 1px solid #ccd0d4; display: flex; gap: 30px;">';
        echo '<div><strong>' . esc_html__( 'Total Listings:', 'interview-finder' ) . '</strong> ' . esc_html( $stats['total_listings'] ) . '</div>';
        echo '<div><strong>' . esc_html__( 'Active:', 'interview-finder' ) . '</strong> ' . esc_html( $stats['active_listings'] ) . '</div>';
        echo '<div><strong>' . esc_html__( 'Total Impressions:', 'interview-finder' ) . '</strong> ' . esc_html( number_format( $stats['total_impressions'] ) ) . '</div>';
        echo '<div><strong>' . esc_html__( 'Total Clicks:', 'interview-finder' ) . '</strong> ' . esc_html( number_format( $stats['total_clicks'] ) ) . '</div>';
        echo '<div><strong>' . esc_html__( 'Avg CTR:', 'interview-finder' ) . '</strong> ' . esc_html( $stats['avg_ctr'] ) . '%</div>';
        echo '</div>';

        if ( empty( $listings ) ) {
            echo '<p>' . esc_html__( 'No sponsored listings found. Create one to get started.', 'interview-finder' ) . '</p>';
            return;
        }

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . esc_html__( 'Name', 'interview-finder' ) . '</th>';
        echo '<th>' . esc_html__( 'Podcast', 'interview-finder' ) . '</th>';
        echo '<th>' . esc_html__( 'Categories', 'interview-finder' ) . '</th>';
        echo '<th>' . esc_html__( 'Priority', 'interview-finder' ) . '</th>';
        echo '<th>' . esc_html__( 'Status', 'interview-finder' ) . '</th>';
        echo '<th>' . esc_html__( 'Impressions', 'interview-finder' ) . '</th>';
        echo '<th>' . esc_html__( 'Clicks', 'interview-finder' ) . '</th>';
        echo '<th>' . esc_html__( 'CTR', 'interview-finder' ) . '</th>';
        echo '<th>' . esc_html__( 'Actions', 'interview-finder' ) . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ( $listings as $listing ) {
            $ctr = $listing['total_impressions'] > 0
                ? round( ( $listing['total_clicks'] / $listing['total_impressions'] ) * 100, 2 )
                : 0;

            $status_class = 'active' === $listing['status'] ? 'status-active' : ( 'paused' === $listing['status'] ? 'status-paused' : 'status-expired' );

            echo '<tr>';
            echo '<td><strong>' . esc_html( $listing['name'] ) . '</strong></td>';
            echo '<td>';
            if ( $listing['podcast_image_url'] ) {
                echo '<img src="' . esc_url( $listing['podcast_image_url'] ) . '" alt="" style="width:30px;height:30px;vertical-align:middle;margin-right:8px;">';
            }
            echo esc_html( $listing['podcast_title'] );
            echo '</td>';
            echo '<td>' . esc_html( $listing['categories'] ) . '</td>';
            echo '<td>' . esc_html( $listing['priority'] ) . '</td>';
            echo '<td><span class="' . esc_attr( $status_class ) . '" style="padding:3px 8px;border-radius:3px;font-size:12px;';
            echo 'active' === $listing['status'] ? 'background:#d4edda;color:#155724;' : ( 'paused' === $listing['status'] ? 'background:#fff3cd;color:#856404;' : 'background:#f8d7da;color:#721c24;' );
            echo '">' . esc_html( ucfirst( $listing['status'] ) ) . '</span></td>';
            echo '<td>' . esc_html( number_format( $listing['total_impressions'] ) );
            if ( $listing['impression_limit'] > 0 ) {
                echo ' / ' . esc_html( number_format( $listing['impression_limit'] ) );
            }
            echo '</td>';
            echo '<td>' . esc_html( number_format( $listing['total_clicks'] ) );
            if ( $listing['click_limit'] > 0 ) {
                echo ' / ' . esc_html( number_format( $listing['click_limit'] ) );
            }
            echo '</td>';
            echo '<td>' . esc_html( $ctr ) . '%</td>';
            echo '<td>';
            echo '<a href="' . esc_url( admin_url( 'admin.php?page=interview-finder-sponsored&action=edit&id=' . $listing['id'] ) ) . '">' . esc_html__( 'Edit', 'interview-finder' ) . '</a> | ';
            echo '<a href="' . esc_url( admin_url( 'admin.php?page=interview-finder-sponsored&action=stats&id=' . $listing['id'] ) ) . '">' . esc_html__( 'Stats', 'interview-finder' ) . '</a> | ';

            if ( 'active' === $listing['status'] ) {
                echo '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin.php?page=interview-finder-sponsored&action=pause&id=' . $listing['id'] ), 'pause_sponsored_' . $listing['id'] ) ) . '">' . esc_html__( 'Pause', 'interview-finder' ) . '</a> | ';
            } else {
                echo '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin.php?page=interview-finder-sponsored&action=activate&id=' . $listing['id'] ), 'activate_sponsored_' . $listing['id'] ) ) . '">' . esc_html__( 'Activate', 'interview-finder' ) . '</a> | ';
            }

            echo '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin.php?page=interview-finder-sponsored&action=delete&id=' . $listing['id'] ), 'delete_sponsored_' . $listing['id'] ) ) . '" onclick="return confirm(\'' . esc_js( __( 'Are you sure you want to delete this listing?', 'interview-finder' ) ) . '\');" style="color:#a00;">' . esc_html__( 'Delete', 'interview-finder' ) . '</a>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
    }

    /**
     * Render listing form.
     *
     * @param int $id Listing ID for edit, 0 for new.
     * @return void
     */
    private function render_form( int $id = 0 ): void {
        $listing = $id > 0 ? $this->listings->get( $id ) : null;
        $is_edit = null !== $listing;

        $title = $is_edit ? __( 'Edit Sponsored Listing', 'interview-finder' ) : __( 'Add New Sponsored Listing', 'interview-finder' );

        echo '<a href="' . esc_url( admin_url( 'admin.php?page=interview-finder-sponsored' ) ) . '" class="page-title-action">' . esc_html__( 'Back to List', 'interview-finder' ) . '</a>';
        echo '<hr class="wp-header-end">';

        echo '<h2>' . esc_html( $title ) . '</h2>';

        echo '<form method="post" action="">';
        wp_nonce_field( 'sponsored_listing_save', 'sponsored_nonce' );

        if ( $is_edit ) {
            echo '<input type="hidden" name="listing_id" value="' . esc_attr( $id ) . '">';
        }

        echo '<table class="form-table">';

        // Name
        echo '<tr>';
        echo '<th><label for="name">' . esc_html__( 'Campaign Name', 'interview-finder' ) . ' *</label></th>';
        echo '<td><input type="text" id="name" name="name" class="regular-text" required value="' . esc_attr( $listing['name'] ?? '' ) . '"></td>';
        echo '</tr>';

        // Podcast Title
        echo '<tr>';
        echo '<th><label for="podcast_title">' . esc_html__( 'Podcast Title', 'interview-finder' ) . ' *</label></th>';
        echo '<td><input type="text" id="podcast_title" name="podcast_title" class="regular-text" required value="' . esc_attr( $listing['podcast_title'] ?? '' ) . '"></td>';
        echo '</tr>';

        // Podcast UUID
        echo '<tr>';
        echo '<th><label for="podcast_uuid">' . esc_html__( 'Podcast UUID (Taddy)', 'interview-finder' ) . '</label></th>';
        echo '<td><input type="text" id="podcast_uuid" name="podcast_uuid" class="regular-text" value="' . esc_attr( $listing['podcast_uuid'] ?? '' ) . '">';
        echo '<p class="description">' . esc_html__( 'Optional: UUID from Taddy API for linking.', 'interview-finder' ) . '</p></td>';
        echo '</tr>';

        // iTunes ID
        echo '<tr>';
        echo '<th><label for="podcast_itunes_id">' . esc_html__( 'iTunes ID', 'interview-finder' ) . '</label></th>';
        echo '<td><input type="text" id="podcast_itunes_id" name="podcast_itunes_id" class="regular-text" value="' . esc_attr( $listing['podcast_itunes_id'] ?? '' ) . '"></td>';
        echo '</tr>';

        // Image URL
        echo '<tr>';
        echo '<th><label for="podcast_image_url">' . esc_html__( 'Podcast Image URL', 'interview-finder' ) . '</label></th>';
        echo '<td><input type="url" id="podcast_image_url" name="podcast_image_url" class="large-text" value="' . esc_attr( $listing['podcast_image_url'] ?? '' ) . '"></td>';
        echo '</tr>';

        // Description
        echo '<tr>';
        echo '<th><label for="podcast_description">' . esc_html__( 'Description', 'interview-finder' ) . '</label></th>';
        echo '<td><textarea id="podcast_description" name="podcast_description" rows="3" class="large-text">' . esc_textarea( $listing['podcast_description'] ?? '' ) . '</textarea></td>';
        echo '</tr>';

        // Podcast URL
        echo '<tr>';
        echo '<th><label for="podcast_url">' . esc_html__( 'Podcast URL', 'interview-finder' ) . '</label></th>';
        echo '<td><input type="url" id="podcast_url" name="podcast_url" class="large-text" value="' . esc_attr( $listing['podcast_url'] ?? '' ) . '"></td>';
        echo '</tr>';

        // RSS URL
        echo '<tr>';
        echo '<th><label for="podcast_rss_url">' . esc_html__( 'RSS Feed URL', 'interview-finder' ) . '</label></th>';
        echo '<td><input type="url" id="podcast_rss_url" name="podcast_rss_url" class="large-text" value="' . esc_attr( $listing['podcast_rss_url'] ?? '' ) . '"></td>';
        echo '</tr>';

        // Categories
        echo '<tr>';
        echo '<th><label for="categories">' . esc_html__( 'Categories (comma-separated)', 'interview-finder' ) . '</label></th>';
        echo '<td><input type="text" id="categories" name="categories" class="large-text" value="' . esc_attr( $listing['categories'] ?? '' ) . '">';
        echo '<p class="description">' . esc_html__( 'Enter categories to match against (e.g., "Business, Marketing, Entrepreneurship"). Listing will show when search matches these categories.', 'interview-finder' ) . '</p></td>';
        echo '</tr>';

        // Priority
        echo '<tr>';
        echo '<th><label for="priority">' . esc_html__( 'Priority', 'interview-finder' ) . '</label></th>';
        echo '<td><input type="number" id="priority" name="priority" min="0" max="100" value="' . esc_attr( $listing['priority'] ?? 0 ) . '">';
        echo '<p class="description">' . esc_html__( 'Higher priority listings appear first (0-100).', 'interview-finder' ) . '</p></td>';
        echo '</tr>';

        // Status
        echo '<tr>';
        echo '<th><label for="status">' . esc_html__( 'Status', 'interview-finder' ) . '</label></th>';
        echo '<td><select id="status" name="status">';
        $statuses = [ 'active' => __( 'Active', 'interview-finder' ), 'paused' => __( 'Paused', 'interview-finder' ) ];
        foreach ( $statuses as $value => $label ) {
            $selected = ( $listing['status'] ?? 'active' ) === $value ? ' selected' : '';
            echo '<option value="' . esc_attr( $value ) . '"' . $selected . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select></td>';
        echo '</tr>';

        // Start Date
        echo '<tr>';
        echo '<th><label for="start_date">' . esc_html__( 'Start Date', 'interview-finder' ) . '</label></th>';
        echo '<td><input type="datetime-local" id="start_date" name="start_date" value="' . esc_attr( $listing['start_date'] ? gmdate( 'Y-m-d\TH:i', strtotime( $listing['start_date'] ) ) : '' ) . '">';
        echo '<p class="description">' . esc_html__( 'Leave empty to start immediately.', 'interview-finder' ) . '</p></td>';
        echo '</tr>';

        // End Date
        echo '<tr>';
        echo '<th><label for="end_date">' . esc_html__( 'End Date', 'interview-finder' ) . '</label></th>';
        echo '<td><input type="datetime-local" id="end_date" name="end_date" value="' . esc_attr( $listing['end_date'] ? gmdate( 'Y-m-d\TH:i', strtotime( $listing['end_date'] ) ) : '' ) . '">';
        echo '<p class="description">' . esc_html__( 'Leave empty for no end date.', 'interview-finder' ) . '</p></td>';
        echo '</tr>';

        // Impression Limit
        echo '<tr>';
        echo '<th><label for="impression_limit">' . esc_html__( 'Impression Limit', 'interview-finder' ) . '</label></th>';
        echo '<td><input type="number" id="impression_limit" name="impression_limit" min="0" value="' . esc_attr( $listing['impression_limit'] ?? 0 ) . '">';
        echo '<p class="description">' . esc_html__( '0 = unlimited. Listing will pause when limit is reached.', 'interview-finder' ) . '</p></td>';
        echo '</tr>';

        // Click Limit
        echo '<tr>';
        echo '<th><label for="click_limit">' . esc_html__( 'Click Limit', 'interview-finder' ) . '</label></th>';
        echo '<td><input type="number" id="click_limit" name="click_limit" min="0" value="' . esc_attr( $listing['click_limit'] ?? 0 ) . '">';
        echo '<p class="description">' . esc_html__( '0 = unlimited. Listing will pause when limit is reached.', 'interview-finder' ) . '</p></td>';
        echo '</tr>';

        echo '</table>';

        echo '<p class="submit">';
        echo '<input type="submit" name="sponsored_listing_submit" class="button button-primary" value="' . esc_attr( $is_edit ? __( 'Update Listing', 'interview-finder' ) : __( 'Create Listing', 'interview-finder' ) ) . '">';
        echo '</p>';

        echo '</form>';
    }

    /**
     * Render stats page for a listing.
     *
     * @param int $id Listing ID.
     * @return void
     */
    private function render_stats( int $id ): void {
        $listing = $this->listings->get( $id );

        if ( ! $listing ) {
            echo '<p>' . esc_html__( 'Listing not found.', 'interview-finder' ) . '</p>';
            return;
        }

        $stats = $this->listings->get_stats( $id );

        echo '<a href="' . esc_url( admin_url( 'admin.php?page=interview-finder-sponsored' ) ) . '" class="page-title-action">' . esc_html__( 'Back to List', 'interview-finder' ) . '</a>';
        echo '<hr class="wp-header-end">';

        echo '<h2>' . sprintf( esc_html__( 'Stats for: %s', 'interview-finder' ), esc_html( $listing['name'] ) ) . '</h2>';

        // Summary
        $ctr = $listing['total_impressions'] > 0
            ? round( ( $listing['total_clicks'] / $listing['total_impressions'] ) * 100, 2 )
            : 0;

        echo '<div style="background:#fff;padding:20px;border:1px solid #ccd0d4;margin:20px 0;">';
        echo '<h3 style="margin-top:0;">' . esc_html__( 'Totals', 'interview-finder' ) . '</h3>';
        echo '<div style="display:flex;gap:40px;">';
        echo '<div><strong style="font-size:24px;">' . esc_html( number_format( $listing['total_impressions'] ) ) . '</strong><br>' . esc_html__( 'Impressions', 'interview-finder' ) . '</div>';
        echo '<div><strong style="font-size:24px;">' . esc_html( number_format( $listing['total_clicks'] ) ) . '</strong><br>' . esc_html__( 'Clicks', 'interview-finder' ) . '</div>';
        echo '<div><strong style="font-size:24px;">' . esc_html( $ctr ) . '%</strong><br>' . esc_html__( 'CTR', 'interview-finder' ) . '</div>';
        echo '</div>';
        echo '</div>';

        // Daily stats table
        if ( empty( $stats ) ) {
            echo '<p>' . esc_html__( 'No daily stats recorded yet.', 'interview-finder' ) . '</p>';
            return;
        }

        echo '<h3>' . esc_html__( 'Daily Stats (Last 30 Days)', 'interview-finder' ) . '</h3>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . esc_html__( 'Date', 'interview-finder' ) . '</th>';
        echo '<th>' . esc_html__( 'Impressions', 'interview-finder' ) . '</th>';
        echo '<th>' . esc_html__( 'Clicks', 'interview-finder' ) . '</th>';
        echo '<th>' . esc_html__( 'CTR', 'interview-finder' ) . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ( array_reverse( $stats ) as $day ) {
            $day_ctr = $day['impressions'] > 0
                ? round( ( $day['clicks'] / $day['impressions'] ) * 100, 2 )
                : 0;

            echo '<tr>';
            echo '<td>' . esc_html( gmdate( 'M j, Y', strtotime( $day['stat_date'] ) ) ) . '</td>';
            echo '<td>' . esc_html( number_format( $day['impressions'] ) ) . '</td>';
            echo '<td>' . esc_html( number_format( $day['clicks'] ) ) . '</td>';
            echo '<td>' . esc_html( $day_ctr ) . '%</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
    }
}
