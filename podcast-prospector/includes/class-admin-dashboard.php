<?php
/**
 * Admin Dashboard Widget Class
 *
 * @package Podcast_Prospector
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Podcast_Prospector_Admin_Dashboard
 *
 * Provides admin dashboard widget with usage statistics.
 */
class Podcast_Prospector_Admin_Dashboard {

    /**
     * Database instance.
     *
     * @var Podcast_Prospector_Database
     */
    private Podcast_Prospector_Database $database;

    /**
     * Constructor.
     *
     * @param Podcast_Prospector_Database|null $database Database instance.
     */
    public function __construct( ?Podcast_Prospector_Database $database = null ) {
        $this->database = $database ?? Podcast_Prospector_Database::get_instance();
    }

    /**
     * Initialize dashboard widget.
     *
     * @return void
     */
    public function init(): void {
        add_action( 'wp_dashboard_setup', [ $this, 'register_widget' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    /**
     * Register dashboard widget.
     *
     * @return void
     */
    public function register_widget(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        wp_add_dashboard_widget(
            'interview_finder_stats',
            __( 'Interview Finder Statistics', 'interview-finder' ),
            [ $this, 'render_widget' ],
            [ $this, 'render_widget_config' ]
        );
    }

    /**
     * Enqueue dashboard assets.
     *
     * @param string $hook Current admin page hook.
     * @return void
     */
    public function enqueue_assets( string $hook ): void {
        if ( 'index.php' !== $hook ) {
            return;
        }

        wp_add_inline_style( 'dashboard', $this->get_widget_styles() );
    }

    /**
     * Render dashboard widget.
     *
     * @return void
     */
    public function render_widget(): void {
        $stats = $this->get_statistics();
        ?>
        <div class="if-dashboard-widget">
            <div class="if-stats-grid">
                <div class="if-stat-card">
                    <span class="if-stat-value"><?php echo esc_html( number_format_i18n( $stats['total_searches'] ) ); ?></span>
                    <span class="if-stat-label"><?php esc_html_e( 'Total Searches', 'interview-finder' ); ?></span>
                </div>

                <div class="if-stat-card">
                    <span class="if-stat-value"><?php echo esc_html( number_format_i18n( $stats['active_users'] ) ); ?></span>
                    <span class="if-stat-label"><?php esc_html_e( 'Active Users (30 days)', 'interview-finder' ); ?></span>
                </div>

                <div class="if-stat-card">
                    <span class="if-stat-value"><?php echo esc_html( number_format_i18n( $stats['searches_today'] ) ); ?></span>
                    <span class="if-stat-label"><?php esc_html_e( 'Searches Today', 'interview-finder' ); ?></span>
                </div>

                <div class="if-stat-card">
                    <span class="if-stat-value"><?php echo esc_html( number_format_i18n( $stats['imports_total'] ) ); ?></span>
                    <span class="if-stat-label"><?php esc_html_e( 'Total Imports', 'interview-finder' ); ?></span>
                </div>
            </div>

            <?php if ( ! empty( $stats['top_users'] ) ) : ?>
            <div class="if-top-users">
                <h4><?php esc_html_e( 'Top Searchers This Month', 'interview-finder' ); ?></h4>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'User', 'interview-finder' ); ?></th>
                            <th><?php esc_html_e( 'Searches', 'interview-finder' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $stats['top_users'] as $user ) : ?>
                        <tr>
                            <td><?php echo esc_html( $user['display_name'] ); ?></td>
                            <td><?php echo esc_html( number_format_i18n( $user['search_count'] ) ); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <div class="if-widget-actions">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=interview-finder' ) ); ?>" class="button">
                    <?php esc_html_e( 'View Settings', 'interview-finder' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=interview-finder-logs' ) ); ?>" class="button">
                    <?php esc_html_e( 'View Logs', 'interview-finder' ); ?>
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Render widget configuration form.
     *
     * @return void
     */
    public function render_widget_config(): void {
        $options = get_option( 'interview_finder_dashboard_widget', [] );
        $show_top_users = $options['show_top_users'] ?? true;
        $top_users_count = $options['top_users_count'] ?? 5;

        if ( isset( $_POST['interview_finder_widget_nonce'] ) &&
             wp_verify_nonce( $_POST['interview_finder_widget_nonce'], 'interview_finder_widget_config' ) ) {

            $show_top_users = isset( $_POST['if_show_top_users'] );
            $top_users_count = absint( $_POST['if_top_users_count'] ?? 5 );

            update_option( 'interview_finder_dashboard_widget', [
                'show_top_users'  => $show_top_users,
                'top_users_count' => min( 10, max( 1, $top_users_count ) ),
            ] );
        }
        ?>
        <p>
            <label>
                <input type="checkbox" name="if_show_top_users" <?php checked( $show_top_users ); ?>>
                <?php esc_html_e( 'Show top searchers', 'interview-finder' ); ?>
            </label>
        </p>
        <p>
            <label for="if_top_users_count"><?php esc_html_e( 'Number of top users to show:', 'interview-finder' ); ?></label>
            <input type="number" id="if_top_users_count" name="if_top_users_count"
                   value="<?php echo esc_attr( $top_users_count ); ?>" min="1" max="10" class="small-text">
        </p>
        <?php wp_nonce_field( 'interview_finder_widget_config', 'interview_finder_widget_nonce' ); ?>
        <?php
    }

    /**
     * Get statistics for dashboard.
     *
     * @return array
     */
    private function get_statistics(): array {
        global $wpdb;

        $table = $wpdb->prefix . 'interview_finder_users';
        $options = get_option( 'interview_finder_dashboard_widget', [] );

        // Total searches
        $total_searches = $wpdb->get_var(
            "SELECT SUM(total_searches) FROM {$table}"
        ) ?: 0;

        // Active users in last 30 days
        $thirty_days_ago = gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) );
        $active_users = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT user_id) FROM {$table} WHERE last_searched >= %s",
                $thirty_days_ago
            )
        ) ?: 0;

        // Searches today
        $today = gmdate( 'Y-m-d' );
        $searches_today = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(search_count) FROM {$table} WHERE DATE(last_searched) = %s",
                $today
            )
        ) ?: 0;

        // Total imports from Formidable
        $imports_total = 0;
        if ( class_exists( 'FrmEntry' ) ) {
            $form_id = get_option( 'interview_finder_form_id', 0 );
            if ( $form_id ) {
                $imports_total = FrmEntry::getRecordCount( [ 'form_id' => $form_id ] );
            }
        }

        // Top users
        $top_users = [];
        $show_top_users = $options['show_top_users'] ?? true;

        if ( $show_top_users ) {
            $limit = $options['top_users_count'] ?? 5;
            $month_start = gmdate( 'Y-m-01' );

            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT user_id, search_count FROM {$table}
                     WHERE last_searched >= %s
                     ORDER BY search_count DESC
                     LIMIT %d",
                    $month_start,
                    $limit
                )
            );

            foreach ( $results as $row ) {
                $user = get_userdata( $row->user_id );
                if ( $user ) {
                    $top_users[] = [
                        'user_id'      => $row->user_id,
                        'display_name' => $user->display_name,
                        'search_count' => $row->search_count,
                    ];
                }
            }
        }

        return [
            'total_searches' => (int) $total_searches,
            'active_users'   => (int) $active_users,
            'searches_today' => (int) $searches_today,
            'imports_total'  => (int) $imports_total,
            'top_users'      => $top_users,
        ];
    }

    /**
     * Get widget CSS styles.
     *
     * @return string
     */
    private function get_widget_styles(): string {
        return '
            .if-dashboard-widget { padding: 0; }
            .if-stats-grid {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
                margin-bottom: 16px;
            }
            .if-stat-card {
                background: #f6f7f7;
                padding: 12px;
                border-radius: 4px;
                text-align: center;
            }
            .if-stat-value {
                display: block;
                font-size: 24px;
                font-weight: 600;
                color: #1d2327;
            }
            .if-stat-label {
                display: block;
                font-size: 12px;
                color: #646970;
                margin-top: 4px;
            }
            .if-top-users {
                margin-bottom: 16px;
            }
            .if-top-users h4 {
                margin: 0 0 8px 0;
                font-size: 13px;
            }
            .if-widget-actions {
                display: flex;
                gap: 8px;
            }
        ';
    }
}
