<?php
/**
 * Plugin Name: Guestify Podcast Prospector
 * Plugin URI: https://example.com/podcast-prospector
 * Description: Search and display podcast episodes from multiple podcast databases. Import podcasts to Guest Intelligence tracker.
 * Version: 2.3.0
 * Author: Podcast Prospector Team
 * Author URI: https://example.com
 * Text Domain: podcast-prospector
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package Podcast_Prospector
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Plugin version.
 */
define( 'PODCAST_PROSPECTOR_VERSION', '2.3.0' );

/**
 * Plugin file path.
 */
define( 'PODCAST_PROSPECTOR_FILE', __FILE__ );

/**
 * Plugin directory path.
 */
define( 'PODCAST_PROSPECTOR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL.
 */
define( 'PODCAST_PROSPECTOR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Plugin basename.
 */
define( 'PODCAST_PROSPECTOR_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Class Podcast_Prospector
 *
 * Main plugin class that bootstraps the plugin.
 */
final class Podcast_Prospector {

    /**
     * Plugin instance.
     *
     * @var Podcast_Prospector|null
     */
    private static ?Podcast_Prospector $instance = null;

    /**
     * Settings instance.
     *
     * @var Podcast_Prospector_Settings|null
     */
    public ?Podcast_Prospector_Settings $settings = null;

    /**
     * Logger instance.
     *
     * @var Podcast_Prospector_Logger|null
     */
    public ?Podcast_Prospector_Logger $logger = null;

    /**
     * Get plugin instance.
     *
     * @return Podcast_Prospector
     */
    public static function get_instance(): Podcast_Prospector {
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
     * Container instance.
     *
     * @var Podcast_Prospector_Container|null
     */
    public ?Podcast_Prospector_Container $container = null;

    /**
     * Load required files.
     *
     * @return void
     */
    private function load_dependencies(): void {
        $includes_dir = PODCAST_PROSPECTOR_PLUGIN_DIR . 'includes/';

        // Core classes (order matters for dependencies)
        require_once $includes_dir . 'class-settings.php';
        require_once $includes_dir . 'class-logger.php';
        require_once $includes_dir . 'class-database.php';
        require_once $includes_dir . 'class-membership.php';
        require_once $includes_dir . 'class-rss-cache.php';
        require_once $includes_dir . 'class-api-podcastindex.php';
        require_once $includes_dir . 'class-api-taddy.php';
        require_once $includes_dir . 'class-renderer.php';
        require_once $includes_dir . 'class-guest-intel-import-handler.php';
        require_once $includes_dir . 'class-ajax-handler.php';
        require_once $includes_dir . 'class-shortcode.php';

        // New enhanced classes
        require_once $includes_dir . 'class-container.php';
        require_once $includes_dir . 'class-validator.php';
        require_once $includes_dir . 'class-rate-limiter.php';
        require_once $includes_dir . 'class-search-cache.php';
        require_once $includes_dir . 'class-search-service.php';
        require_once $includes_dir . 'class-rest-api.php';
        require_once $includes_dir . 'class-template-loader.php';
        require_once $includes_dir . 'class-error-handler.php';
        require_once $includes_dir . 'class-admin-dashboard.php';
        require_once $includes_dir . 'class-webhooks.php';
        require_once $includes_dir . 'class-multisite.php';
        require_once $includes_dir . 'class-podcast-location-repository.php';
        require_once $includes_dir . 'class-api-youtube.php';
        require_once $includes_dir . 'class-youtube-channel-repository.php';
        require_once $includes_dir . 'class-sponsored-listings.php';
        require_once $includes_dir . 'class-sponsored-listings-admin.php';
        require_once $includes_dir . 'class-impression-queue.php';
        require_once $includes_dir . 'class-vue-assets.php';
        require_once $includes_dir . 'class-user-shortcodes.php';
        require_once $includes_dir . 'class-home-widget-shortcode.php';

        // Initialize core instances
        $this->settings = Podcast_Prospector_Settings::get_instance();
        $this->logger = Podcast_Prospector_Logger::get_instance();

        // Initialize dependency injection container
        $this->container = Podcast_Prospector_Container::get_instance();
        $this->register_services();
    }

    /**
     * Register services in the container.
     *
     * @return void
     */
    private function register_services(): void {
        // Register logger
        $this->container->singleton( Podcast_Prospector_Logger::class, function() {
            return $this->logger;
        } );

        // Register validator
        $this->container->singleton( Podcast_Prospector_Validator::class, function() {
            return Podcast_Prospector_Validator::get_instance();
        } );

        // Register rate limiter
        $this->container->singleton( Podcast_Prospector_Rate_Limiter::class, function() {
            return new Podcast_Prospector_Rate_Limiter( $this->logger );
        } );

        // Register search cache
        $this->container->singleton( Podcast_Prospector_Search_Cache::class, function() {
            return new Podcast_Prospector_Search_Cache( $this->logger );
        } );

        // Register membership
        $this->container->singleton( Podcast_Prospector_Membership::class, function() {
            return Podcast_Prospector_Membership::get_instance();
        } );

        // Register PodcastIndex API
        $this->container->singleton( Podcast_Prospector_API_PodcastIndex::class, function() {
            return Podcast_Prospector_API_PodcastIndex::get_instance();
        } );

        // Register Taddy API
        $this->container->singleton( Podcast_Prospector_API_Taddy::class, function() {
            return Podcast_Prospector_API_Taddy::get_instance();
        } );

        // Register search service
        $this->container->singleton( Podcast_Prospector_Search_Service::class, function() {
            return new Podcast_Prospector_Search_Service(
                $this->container->get( Podcast_Prospector_API_PodcastIndex::class ),
                $this->container->get( Podcast_Prospector_API_Taddy::class ),
                $this->container->get( Podcast_Prospector_Search_Cache::class ),
                $this->container->get( Podcast_Prospector_Membership::class ),
                $this->logger
            );
        } );

        // Register Guest Intel import handler
        $this->container->singleton( Podcast_Prospector_Guest_Intel_Import_Handler::class, function() {
            return Podcast_Prospector_Guest_Intel_Import_Handler::get_instance();
        } );

        // Register REST API
        $this->container->singleton( Podcast_Prospector_REST_API::class, function() {
            return new Podcast_Prospector_REST_API(
                $this->container->get( Podcast_Prospector_Search_Service::class ),
                $this->container->get( Podcast_Prospector_Guest_Intel_Import_Handler::class ),
                $this->container->get( Podcast_Prospector_Validator::class ),
                $this->container->get( Podcast_Prospector_Membership::class ),
                $this->logger
            );
        } );

        // Register template loader
        $this->container->singleton( Podcast_Prospector_Template_Loader::class, function() {
            return new Podcast_Prospector_Template_Loader();
        } );

        // Register error handler
        $this->container->singleton( Podcast_Prospector_Error_Handler::class, function() {
            $handler = Podcast_Prospector_Error_Handler::get_instance();
            $handler->set_logger( $this->logger );
            return $handler;
        } );
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

        // REST API hooks
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

        // Multisite support
        if ( Podcast_Prospector_Multisite::is_multisite() ) {
            Podcast_Prospector_Multisite::get_instance()->init();
        }
    }

    /**
     * Register REST API routes.
     *
     * @return void
     */
    public function register_rest_routes(): void {
        $rest_api = $this->container->get( Podcast_Prospector_REST_API::class );
        $rest_api->register_routes();
    }

    /**
     * Plugin activation.
     *
     * @return void
     */
    public function activate(): void {
        // Create database table
        $database = Podcast_Prospector_Database::get_instance();
        $database->create_table();

        // Create sponsored listings tables
        $sponsored = Podcast_Prospector_Sponsored_Listings::get_instance();
        $sponsored->create_tables();

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
        load_plugin_textdomain( 'podcast-prospector', false, dirname( PODCAST_PROSPECTOR_PLUGIN_BASENAME ) . '/languages' );

        // Initialize settings admin page
        $this->settings->init();

        // Initialize AJAX handlers
        $ajax_handler = Podcast_Prospector_Ajax_Handler::get_instance();
        $ajax_handler->init();

        // Initialize shortcode
        $shortcode = Podcast_Prospector_Shortcode::get_instance();
        $shortcode->init();

        // Initialize user shortcodes
        $user_shortcodes = Podcast_Prospector_User_Shortcodes::get_instance();
        $user_shortcodes->init();

        // Initialize admin dashboard widget
        $dashboard = new Podcast_Prospector_Admin_Dashboard();
        $dashboard->init();

        // Initialize webhooks
        Podcast_Prospector_Webhooks::get_instance()->init();

        // Initialize sponsored listings admin
        $sponsored_listings = Podcast_Prospector_Sponsored_Listings::get_instance();
        $sponsored_admin = new Podcast_Prospector_Sponsored_Listings_Admin( $sponsored_listings );
        $sponsored_admin->init();
    }

    /**
     * Admin initialization.
     *
     * @return void
     */
    public function admin_init(): void {
        // Check for database updates
        $this->maybe_update_database();
        $this->maybe_update_sponsored_database();
    }

    /**
     * Enqueue frontend scripts and styles.
     *
     * Vue assets are loaded via the shortcode (class-vue-assets.php).
     * This hook is kept for potential future use or extensions.
     *
     * @return void
     */
    public function enqueue_scripts(): void {
        // Vue assets are enqueued by the shortcode via Podcast_Prospector_Vue_Assets
        // No legacy assets needed - Vue handles all frontend rendering
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
        $current_version = get_option( 'podcast_prospector_db_version', '1.0.0' );

        if ( version_compare( $current_version, Podcast_Prospector_Database::DB_VERSION, '<' ) ) {
            $database = Podcast_Prospector_Database::get_instance();
            $database->create_table();
        }
    }

    /**
     * Check and run sponsored listings database updates if needed.
     *
     * @return void
     */
    private function maybe_update_sponsored_database(): void {
        $current_version = get_option( 'podcast_prospector_sponsored_db_version', '1.0.0' );

        if ( version_compare( $current_version, Podcast_Prospector_Sponsored_Listings::DB_VERSION, '<' ) ) {
            $sponsored = Podcast_Prospector_Sponsored_Listings::get_instance();
            $sponsored->create_tables();
        }
    }

    /**
     * Get plugin settings.
     *
     * @return Podcast_Prospector_Settings
     */
    public function get_settings(): Podcast_Prospector_Settings {
        return $this->settings;
    }

    /**
     * Get logger.
     *
     * @return Podcast_Prospector_Logger
     */
    public function get_logger(): Podcast_Prospector_Logger {
        return $this->logger;
    }

    /**
     * Get service from container.
     *
     * @param string $service Service class name.
     * @return mixed
     */
    public function get( string $service ) {
        return $this->container->get( $service );
    }

    /**
     * Get template loader.
     *
     * @return Podcast_Prospector_Template_Loader
     */
    public function get_template_loader(): Podcast_Prospector_Template_Loader {
        return $this->container->get( Podcast_Prospector_Template_Loader::class );
    }

    /**
     * Get error handler.
     *
     * @return Podcast_Prospector_Error_Handler
     */
    public function get_error_handler(): Podcast_Prospector_Error_Handler {
        return $this->container->get( Podcast_Prospector_Error_Handler::class );
    }

    /**
     * Get search service.
     *
     * @return Podcast_Prospector_Search_Service
     */
    public function get_search_service(): Podcast_Prospector_Search_Service {
        return $this->container->get( Podcast_Prospector_Search_Service::class );
    }
}

/**
 * Initialize the plugin.
 *
 * @return Podcast_Prospector
 */
function podcast_prospector(): Podcast_Prospector {
    return Podcast_Prospector::get_instance();
}

// Start the plugin
podcast_prospector();
