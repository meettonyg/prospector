<?php
/**
 * Membership Tier Admin Class
 *
 * WordPress admin interface for managing membership tier capabilities.
 *
 * @package Podcast_Prospector
 * @since 2.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Podcast_Prospector_Membership_Admin
 *
 * Admin page for configuring membership tier settings.
 */
class Podcast_Prospector_Membership_Admin {

    /**
     * Membership instance.
     *
     * @var Podcast_Prospector_Membership
     */
    private Podcast_Prospector_Membership $membership;

    /**
     * Admin page hook suffix.
     *
     * @var string
     */
    private string $page_hook = '';

    /**
     * All tier keys including defaults.
     *
     * @var array
     */
    private array $all_tabs;

    /**
     * Constructor.
     *
     * @param Podcast_Prospector_Membership $membership Membership instance.
     */
    public function __construct( Podcast_Prospector_Membership $membership ) {
        $this->membership = $membership;
        $this->all_tabs = array_merge(
            $this->membership->get_available_tiers(),
            [ 'DEFAULTS' ]
        );
    }

    /**
     * Initialize admin hooks.
     *
     * @return void
     */
    public function init(): void {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ], 9 );
        add_action( 'admin_init', [ $this, 'handle_actions' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
    }

    /**
     * Register admin menu pages.
     *
     * Creates the top-level "Podcast Prospector" menu and adds
     * "Membership Tiers" as the first submenu item.
     *
     * @return void
     */
    public function add_admin_menu(): void {
        // Create top-level menu (also fixes orphaned Sponsored Listings submenu)
        add_menu_page(
            __( 'Podcast Prospector', 'podcast-prospector' ),
            __( 'Podcast Prospector', 'podcast-prospector' ),
            'manage_options',
            'podcast-prospector',
            [ $this, 'render_page' ],
            'dashicons-microphone',
            30
        );

        // Replace auto-generated submenu with "Membership Tiers"
        $this->page_hook = add_submenu_page(
            'podcast-prospector',
            __( 'Membership Tiers', 'podcast-prospector' ),
            __( 'Membership Tiers', 'podcast-prospector' ),
            'manage_options',
            'podcast-prospector',
            [ $this, 'render_page' ]
        );
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook Current admin page hook.
     * @return void
     */
    public function enqueue_scripts( string $hook ): void {
        if ( $hook !== $this->page_hook ) {
            return;
        }

        wp_enqueue_style(
            'podcast-prospector-membership-admin',
            PODCAST_PROSPECTOR_PLUGIN_URL . 'assets/css/membership-admin.css',
            [],
            PODCAST_PROSPECTOR_VERSION
        );
    }

    /**
     * Handle form submission.
     *
     * @return void
     */
    public function handle_actions(): void {
        if ( ! isset( $_POST['membership_tier_submit'] ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( ! check_admin_referer( 'membership_tier_save', 'membership_nonce' ) ) {
            return;
        }

        $this->save_config();

        $tab = isset( $_POST['active_tab'] ) ? sanitize_text_field( $_POST['active_tab'] ) : '';
        $redirect = add_query_arg( [
            'page'    => 'podcast-prospector',
            'tab'     => $tab,
            'updated' => '1',
        ], admin_url( 'admin.php' ) );

        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Save tier configuration from form submission.
     *
     * @return void
     */
    private function save_config(): void {
        $config = [
            'tiers'    => [],
            'defaults' => [],
        ];

        // Save each tier
        foreach ( $this->membership->get_available_tiers() as $tier ) {
            $tier_key = strtolower( $tier );
            $input = $_POST[ $tier_key ] ?? [];
            $config['tiers'][ $tier ] = $this->sanitize_tier_settings( $input );
        }

        // Save defaults
        $defaults_input = $_POST['defaults'] ?? [];
        $config['defaults'] = $this->sanitize_tier_settings( $defaults_input );

        update_option( Podcast_Prospector_Membership::OPTION_NAME, $config );
        $this->membership->reload_config();
    }

    /**
     * Sanitize a single tier's settings.
     *
     * @param array $input Raw input data.
     * @return array Sanitized settings.
     */
    private function sanitize_tier_settings( array $input ): array {
        $valid_sort_options = [ 'LATEST', 'OLDEST' ];

        return [
            'max_pages'                      => max( 1, min( 100, absint( $input['max_pages'] ?? 5 ) ) ),
            'max_results_per_page'           => max( 1, min( 100, absint( $input['max_results_per_page'] ?? 10 ) ) ),
            'podcastindex_max'               => max( 1, min( 200, absint( $input['podcastindex_max'] ?? 10 ) ) ),
            'default_search_cap'             => absint( $input['default_search_cap'] ?? 0 ),
            'can_filter_country'             => ! empty( $input['can_filter_country'] ),
            'can_filter_language'            => ! empty( $input['can_filter_language'] ),
            'can_filter_genre'               => ! empty( $input['can_filter_genre'] ),
            'can_filter_date'                => ! empty( $input['can_filter_date'] ),
            'sort_by_date_published_options' => array_values( array_intersect(
                $input['sort_by_date_published_options'] ?? [],
                $valid_sort_options
            ) ),
            'safe_mode_forced'               => ! empty( $input['safe_mode_forced'] ),
            'podcastindex_sort_options'      => array_values( array_intersect(
                $input['podcastindex_sort_options'] ?? [],
                $valid_sort_options
            ) ),
        ];
    }

    /**
     * Render the admin page.
     *
     * @return void
     */
    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $active_tab = isset( $_GET['tab'] ) ? strtoupper( sanitize_text_field( $_GET['tab'] ) ) : '';
        if ( ! in_array( $active_tab, $this->all_tabs, true ) ) {
            $active_tab = $this->all_tabs[0]; // Default to first tier
        }

        // Get current settings for the active tab
        if ( 'DEFAULTS' === $active_tab ) {
            $settings = $this->membership->get_defaults_config();
        } else {
            $config = $this->membership->get_config();
            $settings = $config[ $active_tab ] ?? $this->membership->get_defaults_config();
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Membership Tiers', 'podcast-prospector' ); ?></h1>

            <?php if ( isset( $_GET['updated'] ) ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e( 'Membership tier settings saved.', 'podcast-prospector' ); ?></p>
                </div>
            <?php endif; ?>

            <?php $this->render_tabs( $active_tab ); ?>

            <div class="membership-tier-form">
                <?php $this->render_tier_form( $active_tab, $settings ); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render tab navigation.
     *
     * @param string $active_tab Currently active tab.
     * @return void
     */
    private function render_tabs( string $active_tab ): void {
        $tab_labels = [
            'ZENITH'      => __( 'Zenith', 'podcast-prospector' ),
            'VELOCITY'    => __( 'Velocity', 'podcast-prospector' ),
            'ACCELERATOR' => __( 'Accelerator', 'podcast-prospector' ),
            'DEFAULTS'    => __( 'Defaults (Unknown)', 'podcast-prospector' ),
        ];

        echo '<nav class="nav-tab-wrapper">';
        foreach ( $this->all_tabs as $tab ) {
            $url = add_query_arg( [
                'page' => 'podcast-prospector',
                'tab'  => strtolower( $tab ),
            ], admin_url( 'admin.php' ) );

            $class = ( $tab === $active_tab ) ? 'nav-tab nav-tab-active' : 'nav-tab';
            printf(
                '<a href="%s" class="%s">%s</a>',
                esc_url( $url ),
                esc_attr( $class ),
                esc_html( $tab_labels[ $tab ] ?? $tab )
            );
        }
        echo '</nav>';
    }

    /**
     * Render the settings form for a tier.
     *
     * @param string $tier     Tier key (ZENITH, VELOCITY, ACCELERATOR, DEFAULTS).
     * @param array  $settings Current settings for this tier.
     * @return void
     */
    private function render_tier_form( string $tier, array $settings ): void {
        $field_prefix = strtolower( $tier );
        ?>
        <form method="post" action="">
            <?php wp_nonce_field( 'membership_tier_save', 'membership_nonce' ); ?>
            <input type="hidden" name="active_tab" value="<?php echo esc_attr( strtolower( $tier ) ); ?>">

            <table class="form-table">
                <tbody>
                    <!-- Result Limits -->
                    <tr>
                        <th scope="row">
                            <label for="<?php echo esc_attr( $field_prefix ); ?>_max_pages">
                                <?php esc_html_e( 'Max Pages (Taddy)', 'podcast-prospector' ); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number"
                                   id="<?php echo esc_attr( $field_prefix ); ?>_max_pages"
                                   name="<?php echo esc_attr( $field_prefix ); ?>[max_pages]"
                                   value="<?php echo esc_attr( $settings['max_pages'] ?? 5 ); ?>"
                                   min="1" max="100" class="small-text">
                            <p class="description"><?php esc_html_e( 'Maximum pages for Taddy pagination.', 'podcast-prospector' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="<?php echo esc_attr( $field_prefix ); ?>_max_results_per_page">
                                <?php esc_html_e( 'Max Results Per Page (Taddy)', 'podcast-prospector' ); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number"
                                   id="<?php echo esc_attr( $field_prefix ); ?>_max_results_per_page"
                                   name="<?php echo esc_attr( $field_prefix ); ?>[max_results_per_page]"
                                   value="<?php echo esc_attr( $settings['max_results_per_page'] ?? 10 ); ?>"
                                   min="1" max="100" class="small-text">
                            <p class="description"><?php esc_html_e( 'Maximum results per page for Taddy searches.', 'podcast-prospector' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="<?php echo esc_attr( $field_prefix ); ?>_podcastindex_max">
                                <?php esc_html_e( 'PodcastIndex Max Results', 'podcast-prospector' ); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number"
                                   id="<?php echo esc_attr( $field_prefix ); ?>_podcastindex_max"
                                   name="<?php echo esc_attr( $field_prefix ); ?>[podcastindex_max]"
                                   value="<?php echo esc_attr( $settings['podcastindex_max'] ?? 10 ); ?>"
                                   min="1" max="200" class="small-text">
                            <p class="description"><?php esc_html_e( 'Maximum results from PodcastIndex API.', 'podcast-prospector' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="<?php echo esc_attr( $field_prefix ); ?>_default_search_cap">
                                <?php esc_html_e( 'Default Search Cap', 'podcast-prospector' ); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number"
                                   id="<?php echo esc_attr( $field_prefix ); ?>_default_search_cap"
                                   name="<?php echo esc_attr( $field_prefix ); ?>[default_search_cap]"
                                   value="<?php echo esc_attr( $settings['default_search_cap'] ?? 0 ); ?>"
                                   min="0" class="small-text">
                            <p class="description"><?php esc_html_e( 'Default monthly search limit for this tier. 0 = unlimited. Per-user overrides take precedence.', 'podcast-prospector' ); ?></p>
                        </td>
                    </tr>

                    <!-- Filter Toggles -->
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Filter Permissions', 'podcast-prospector' ); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox"
                                           name="<?php echo esc_attr( $field_prefix ); ?>[can_filter_country]"
                                           value="1"
                                           <?php checked( ! empty( $settings['can_filter_country'] ) ); ?>>
                                    <?php esc_html_e( 'Country', 'podcast-prospector' ); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox"
                                           name="<?php echo esc_attr( $field_prefix ); ?>[can_filter_language]"
                                           value="1"
                                           <?php checked( ! empty( $settings['can_filter_language'] ) ); ?>>
                                    <?php esc_html_e( 'Language', 'podcast-prospector' ); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox"
                                           name="<?php echo esc_attr( $field_prefix ); ?>[can_filter_genre]"
                                           value="1"
                                           <?php checked( ! empty( $settings['can_filter_genre'] ) ); ?>>
                                    <?php esc_html_e( 'Genre', 'podcast-prospector' ); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox"
                                           name="<?php echo esc_attr( $field_prefix ); ?>[can_filter_date]"
                                           value="1"
                                           <?php checked( ! empty( $settings['can_filter_date'] ) ); ?>>
                                    <?php esc_html_e( 'Date', 'podcast-prospector' ); ?>
                                </label>
                            </fieldset>
                            <p class="description"><?php esc_html_e( 'Which search filters this tier can use.', 'podcast-prospector' ); ?></p>
                        </td>
                    </tr>

                    <!-- Sort Options -->
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Taddy Sort Options', 'podcast-prospector' ); ?></th>
                        <td>
                            <fieldset class="checkbox-group">
                                <?php
                                $sort_options = $settings['sort_by_date_published_options'] ?? [];
                                ?>
                                <label>
                                    <input type="checkbox"
                                           name="<?php echo esc_attr( $field_prefix ); ?>[sort_by_date_published_options][]"
                                           value="LATEST"
                                           <?php checked( in_array( 'LATEST', $sort_options, true ) ); ?>>
                                    <?php esc_html_e( 'Latest', 'podcast-prospector' ); ?>
                                </label>
                                <label>
                                    <input type="checkbox"
                                           name="<?php echo esc_attr( $field_prefix ); ?>[sort_by_date_published_options][]"
                                           value="OLDEST"
                                           <?php checked( in_array( 'OLDEST', $sort_options, true ) ); ?>>
                                    <?php esc_html_e( 'Oldest', 'podcast-prospector' ); ?>
                                </label>
                            </fieldset>
                            <p class="description"><?php esc_html_e( 'Which date sort options are available for Taddy searches.', 'podcast-prospector' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e( 'PodcastIndex Sort Options', 'podcast-prospector' ); ?></th>
                        <td>
                            <fieldset class="checkbox-group">
                                <?php
                                $pi_sort_options = $settings['podcastindex_sort_options'] ?? [];
                                ?>
                                <label>
                                    <input type="checkbox"
                                           name="<?php echo esc_attr( $field_prefix ); ?>[podcastindex_sort_options][]"
                                           value="LATEST"
                                           <?php checked( in_array( 'LATEST', $pi_sort_options, true ) ); ?>>
                                    <?php esc_html_e( 'Latest', 'podcast-prospector' ); ?>
                                </label>
                                <label>
                                    <input type="checkbox"
                                           name="<?php echo esc_attr( $field_prefix ); ?>[podcastindex_sort_options][]"
                                           value="OLDEST"
                                           <?php checked( in_array( 'OLDEST', $pi_sort_options, true ) ); ?>>
                                    <?php esc_html_e( 'Oldest', 'podcast-prospector' ); ?>
                                </label>
                            </fieldset>
                            <p class="description"><?php esc_html_e( 'Which date sort options are available for PodcastIndex searches.', 'podcast-prospector' ); ?></p>
                        </td>
                    </tr>

                    <!-- Safe Mode -->
                    <tr>
                        <th scope="row">
                            <label for="<?php echo esc_attr( $field_prefix ); ?>_safe_mode_forced">
                                <?php esc_html_e( 'Force Safe Mode', 'podcast-prospector' ); ?>
                            </label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       id="<?php echo esc_attr( $field_prefix ); ?>_safe_mode_forced"
                                       name="<?php echo esc_attr( $field_prefix ); ?>[safe_mode_forced]"
                                       value="1"
                                       <?php checked( ! empty( $settings['safe_mode_forced'] ) ); ?>>
                                <?php esc_html_e( 'Force safe mode for this tier (users cannot disable it).', 'podcast-prospector' ); ?>
                            </label>
                        </td>
                    </tr>
                </tbody>
            </table>

            <?php submit_button( __( 'Save Settings', 'podcast-prospector' ), 'primary', 'membership_tier_submit' ); ?>
        </form>
        <?php
    }
}
