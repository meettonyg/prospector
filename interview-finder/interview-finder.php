<?php
/**
 * Plugin Name: Interview Finder
 * Plugin URI: https://example.com/interview-finder
 * Description: Search and display podcast episodes from multiple podcast databases. Import podcasts to Formidable Forms tracker.
 * Version: 2.0.0
 * Author: Interview Finder Team
 * Author URI: https://example.com
 * Text Domain: interview-finder
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package Interview_Finder
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Plugin version.
 */
define( 'INTERVIEW_FINDER_VERSION', '2.0.0' );

/**
 * Plugin directory path.
 */
define( 'INTERVIEW_FINDER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL.
 */
define( 'INTERVIEW_FINDER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Plugin basename.
 */
define( 'INTERVIEW_FINDER_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Class Interview_Finder
 *
 * Main plugin class that bootstraps the plugin.
 */
final class Interview_Finder {

    /**
     * Plugin instance.
     *
     * @var Interview_Finder|null
     */
    private static ?Interview_Finder $instance = null;

    /**
     * Settings instance.
     *
     * @var Interview_Finder_Settings|null
     */
    public ?Interview_Finder_Settings $settings = null;

    /**
     * Logger instance.
     *
     * @var Interview_Finder_Logger|null
     */
    public ?Interview_Finder_Logger $logger = null;

    /**
     * Get plugin instance.
     *
     * @return Interview_Finder
     */
    public static function get_instance(): Interview_Finder {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor.
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load required files.
     *
     * @return void
     */
    private function load_dependencies(): void {
        $includes_dir = INTERVIEW_FINDER_PLUGIN_DIR . 'includes/';

        // Core classes (order matters for dependencies)
        require_once $includes_dir . 'class-settings.php';
        require_once $includes_dir . 'class-logger.php';
        require_once $includes_dir . 'class-database.php';
        require_once $includes_dir . 'class-membership.php';
        require_once $includes_dir . 'class-rss-cache.php';
        require_once $includes_dir . 'class-api-podcastindex.php';
        require_once $includes_dir . 'class-api-taddy.php';
        require_once $includes_dir . 'class-renderer.php';
        require_once $includes_dir . 'class-form-handler.php';
        require_once $includes_dir . 'class-ajax-handler.php';
        require_once $includes_dir . 'class-shortcode.php';

        // Initialize core instances
        $this->settings = Interview_Finder_Settings::get_instance();
        $this->logger = Interview_Finder_Logger::get_instance();
    }

    /**
     * Initialize WordPress hooks.
     *
     * @return void
     */
    private function init_hooks(): void {
        // Activation/deactivation hooks
        register_activation_hook( __FILE__, [ $this, 'activate' ] );
        register_deactivation_hook( __FILE__, [ $this, 'deactivate' ] );

        // Plugin initialization
        add_action( 'plugins_loaded', [ $this, 'init' ] );

        // Admin hooks
        add_action( 'admin_init', [ $this, 'admin_init' ] );

        // Frontend hooks
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
    }

    /**
     * Plugin activation.
     *
     * @return void
     */
    public function activate(): void {
        // Create database table
        $database = Interview_Finder_Database::get_instance();
        $database->create_table();

        // Migrate legacy settings if needed
        $this->migrate_legacy_settings();

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation.
     *
     * @return void
     */
    public function deactivate(): void {
        // Clear scheduled events if any
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Initialize plugin components.
     *
     * @return void
     */
    public function init(): void {
        // Load text domain
        load_plugin_textdomain( 'interview-finder', false, dirname( INTERVIEW_FINDER_PLUGIN_BASENAME ) . '/languages' );

        // Initialize settings admin page
        $this->settings->init();

        // Initialize AJAX handlers
        $ajax_handler = Interview_Finder_Ajax_Handler::get_instance();
        $ajax_handler->init();

        // Initialize shortcode
        $shortcode = Interview_Finder_Shortcode::get_instance();
        $shortcode->init();
    }

    /**
     * Admin initialization.
     *
     * @return void
     */
    public function admin_init(): void {
        // Check for database updates
        $this->maybe_update_database();
    }

    /**
     * Enqueue frontend scripts and styles.
     *
     * @return void
     */
    public function enqueue_scripts(): void {
        // Check if we should load assets
        if ( ! $this->settings->is_search_page() ) {
            return;
        }

        // Styles
        wp_enqueue_style(
            'interview-finder-styles',
            INTERVIEW_FINDER_PLUGIN_URL . 'assets/styles.css',
            [],
            INTERVIEW_FINDER_VERSION
        );

        // Scripts
        wp_enqueue_script( 'jquery' );

        wp_enqueue_script(
            'interview-finder-scripts',
            INTERVIEW_FINDER_PLUGIN_URL . 'assets/podcast-selection.js',
            [ 'jquery' ],
            INTERVIEW_FINDER_VERSION,
            true
        );

        // Localize script with AJAX data and nonces
        wp_localize_script( 'interview-finder-scripts', 'interviewFinderData', [
            'ajaxurl'      => admin_url( 'admin-ajax.php' ),
            'searchNonce'  => Interview_Finder_Ajax_Handler::create_search_nonce(),
            'importNonce'  => Interview_Finder_Ajax_Handler::create_import_nonce(),
            'i18n'         => [
                'selectAtLeastOne' => __( 'Please select at least one podcast/episode using the checkboxes.', 'interview-finder' ),
                'importing'        => __( 'Importing...', 'interview-finder' ),
                'imported'         => __( 'Imported!', 'interview-finder' ),
                'importSelected'   => __( 'Import Selected', 'interview-finder' ),
                'importToTracker'  => __( 'Import to Tracker', 'interview-finder' ),
                'invalidData'      => __( 'Error: Invalid data format received for this item.', 'interview-finder' ),
                'communicationError' => __( 'A communication error occurred. Please try again.', 'interview-finder' ),
                'unexpectedResponse' => __( 'Received an unexpected response from the server.', 'interview-finder' ),
            ],
        ] );
    }

    /**
     * Migrate legacy hardcoded settings.
     *
     * @return void
     */
    private function migrate_legacy_settings(): void {
        // Only migrate if settings are empty
        $current = $this->settings->get_all();

        // Check if we need to migrate (API keys not set)
        if ( empty( $current['podcastindex_api_key'] ) && empty( $current['taddy_api_key'] ) ) {
            // Legacy hardcoded values - user should update these via admin
            $legacy = [
                'search_page_id' => 43072, // Legacy hardcoded page ID
                // Note: API keys should be re-entered via admin for security
            ];

            $this->settings->migrate_legacy_settings( $legacy );

            // Log migration notice
            if ( $this->logger ) {
                $this->logger->info( 'Legacy settings migration completed. Please configure API credentials in Settings > Interview Finder.' );
            }
        }
    }

    /**
     * Check and run database updates if needed.
     *
     * @return void
     */
    private function maybe_update_database(): void {
        $current_version = get_option( 'interview_finder_db_version', '1.0.0' );

        if ( version_compare( $current_version, Interview_Finder_Database::DB_VERSION, '<' ) ) {
            $database = Interview_Finder_Database::get_instance();
            $database->create_table();
        }
    }

    /**
     * Get plugin settings.
     *
     * @return Interview_Finder_Settings
     */
    public function get_settings(): Interview_Finder_Settings {
        return $this->settings;
    }

    /**
     * Get logger.
     *
     * @return Interview_Finder_Logger
     */
    public function get_logger(): Interview_Finder_Logger {
        return $this->logger;
    }
}

/**
 * Initialize the plugin.
 *
 * @return Interview_Finder
 */
function interview_finder(): Interview_Finder {
    return Interview_Finder::get_instance();
}

// Start the plugin
interview_finder();
