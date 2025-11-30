<?php
/**
 * Service Container for Dependency Injection
 *
 * @package Podcast_Prospector
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Podcast_Prospector_Container
 *
 * Simple dependency injection container for managing service instances.
 */
class Podcast_Prospector_Container {

    /**
     * Singleton instance.
     *
     * @var Podcast_Prospector_Container|null
     */
    private static ?Podcast_Prospector_Container $instance = null;

    /**
     * Registered services.
     *
     * @var array<string, callable>
     */
    private array $services = [];

    /**
     * Resolved instances.
     *
     * @var array<string, object>
     */
    private array $instances = [];

    /**
     * Service aliases.
     *
     * @var array<string, string>
     */
    private array $aliases = [];

    /**
     * Get singleton instance.
     *
     * @return Podcast_Prospector_Container
     */
    public static function get_instance(): Podcast_Prospector_Container {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor.
     */
    private function __construct() {
        $this->register_core_services();
    }

    /**
     * Register core plugin services.
     *
     * @return void
     */
    private function register_core_services(): void {
        // Settings
        $this->singleton( 'settings', function () {
            return Podcast_Prospector_Settings::get_instance();
        } );

        // Logger
        $this->singleton( 'logger', function ( $c ) {
            return Podcast_Prospector_Logger::get_instance();
        } );

        // Database
        $this->singleton( 'database', function ( $c ) {
            return Podcast_Prospector_Database::get_instance();
        } );

        // Membership
        $this->singleton( 'membership', function ( $c ) {
            return Podcast_Prospector_Membership::get_instance();
        } );

        // Validator
        $this->singleton( 'validator', function ( $c ) {
            return Podcast_Prospector_Validator::get_instance();
        } );

        // Rate Limiter
        $this->singleton( 'rate_limiter', function ( $c ) {
            return new Podcast_Prospector_Rate_Limiter( $c->get( 'logger' ) );
        } );

        // RSS Cache
        $this->singleton( 'rss_cache', function ( $c ) {
            return Podcast_Prospector_RSS_Cache::get_instance();
        } );

        // Search Cache
        $this->singleton( 'search_cache', function ( $c ) {
            return new Podcast_Prospector_Search_Cache( $c->get( 'logger' ) );
        } );

        // PodcastIndex API
        $this->singleton( 'api.podcastindex', function ( $c ) {
            return new Podcast_Prospector_API_PodcastIndex(
                $c->get( 'settings' ),
                $c->get( 'logger' ),
                $c->get( 'rate_limiter' )
            );
        } );

        // Taddy API
        $this->singleton( 'api.taddy', function ( $c ) {
            return new Podcast_Prospector_API_Taddy(
                $c->get( 'settings' ),
                $c->get( 'logger' ),
                $c->get( 'rate_limiter' )
            );
        } );

        // Podcast Location Repository
        $this->singleton( 'podcast_location', function ( $c ) {
            return new Podcast_Prospector_Podcast_Location_Repository(
                $c->get( 'settings' ),
                $c->get( 'logger' )
            );
        } );

        // YouTube API
        $this->singleton( 'api.youtube', function ( $c ) {
            return new Podcast_Prospector_API_YouTube(
                $c->get( 'settings' ),
                $c->get( 'logger' ),
                $c->get( 'rate_limiter' )
            );
        } );

        // YouTube Channel Repository (for deduplication)
        $this->singleton( 'youtube_channel', function ( $c ) {
            return new Podcast_Prospector_YouTube_Channel_Repository(
                $c->get( 'settings' ),
                $c->get( 'logger' )
            );
        } );

        // Renderer
        $this->singleton( 'renderer', function ( $c ) {
            return new Podcast_Prospector_Renderer( $c->get( 'rss_cache' ) );
        } );

        // Form Handler
        $this->singleton( 'form_handler', function ( $c ) {
            return new Podcast_Prospector_Form_Handler(
                $c->get( 'settings' ),
                $c->get( 'logger' )
            );
        } );

        // Search Service
        $this->singleton( 'search', function ( $c ) {
            return new Podcast_Prospector_Search_Service(
                $c->get( 'api.podcastindex' ),
                $c->get( 'api.taddy' ),
                $c->get( 'search_cache' ),
                $c->get( 'membership' ),
                $c->get( 'logger' )
            );
        } );

        // AJAX Handler
        $this->singleton( 'ajax_handler', function ( $c ) {
            return new Podcast_Prospector_Ajax_Handler(
                $c->get( 'search' ),
                $c->get( 'form_handler' ),
                $c->get( 'database' ),
                $c->get( 'membership' ),
                $c->get( 'validator' ),
                $c->get( 'renderer' ),
                $c->get( 'logger' )
            );
        } );

        // REST API
        $this->singleton( 'rest_api', function ( $c ) {
            return new Podcast_Prospector_REST_API(
                $c->get( 'search' ),
                $c->get( 'form_handler' ),
                $c->get( 'validator' ),
                $c->get( 'membership' ),
                $c->get( 'logger' )
            );
        } );

        // Shortcode
        $this->singleton( 'shortcode', function ( $c ) {
            return new Podcast_Prospector_Shortcode(
                $c->get( 'membership' ),
                $c->get( 'database' )
            );
        } );

        // Admin Dashboard
        $this->singleton( 'admin_dashboard', function ( $c ) {
            return new Podcast_Prospector_Admin_Dashboard(
                $c->get( 'database' ),
                $c->get( 'settings' )
            );
        } );

        // Webhooks
        $this->singleton( 'webhooks', function ( $c ) {
            return new Podcast_Prospector_Webhooks(
                $c->get( 'settings' ),
                $c->get( 'logger' )
            );
        } );

        // Set up aliases
        $this->alias( Podcast_Prospector_Settings::class, 'settings' );
        $this->alias( Podcast_Prospector_Logger::class, 'logger' );
        $this->alias( Podcast_Prospector_Database::class, 'database' );
        $this->alias( Podcast_Prospector_Membership::class, 'membership' );
    }

    /**
     * Register a service.
     *
     * @param string   $id       Service identifier.
     * @param callable $resolver Resolver function.
     * @return void
     */
    public function register( string $id, callable $resolver ): void {
        $this->services[ $id ] = $resolver;
    }

    /**
     * Register a singleton service.
     *
     * @param string   $id       Service identifier.
     * @param callable $resolver Resolver function.
     * @return void
     */
    public function singleton( string $id, callable $resolver ): void {
        $this->services[ $id ] = function ( $c ) use ( $id, $resolver ) {
            if ( ! isset( $this->instances[ $id ] ) ) {
                $this->instances[ $id ] = $resolver( $c );
            }
            return $this->instances[ $id ];
        };
    }

    /**
     * Register an alias for a service.
     *
     * @param string $alias Alias name (typically class name).
     * @param string $id    Service ID to resolve to.
     * @return void
     */
    public function alias( string $alias, string $id ): void {
        $this->aliases[ $alias ] = $id;
    }

    /**
     * Get a service instance.
     *
     * @param string $id Service identifier.
     * @return mixed
     * @throws InvalidArgumentException If service not found.
     */
    public function get( string $id ) {
        // Check aliases first
        if ( isset( $this->aliases[ $id ] ) ) {
            $id = $this->aliases[ $id ];
        }

        if ( ! isset( $this->services[ $id ] ) ) {
            throw new InvalidArgumentException( "Service '{$id}' not found in container." );
        }

        return $this->services[ $id ]( $this );
    }

    /**
     * Check if a service exists.
     *
     * @param string $id Service identifier.
     * @return bool
     */
    public function has( string $id ): bool {
        if ( isset( $this->aliases[ $id ] ) ) {
            $id = $this->aliases[ $id ];
        }
        return isset( $this->services[ $id ] );
    }

    /**
     * Set an instance directly.
     *
     * @param string $id       Service identifier.
     * @param object $instance Instance to set.
     * @return void
     */
    public function set( string $id, object $instance ): void {
        $this->instances[ $id ] = $instance;
        $this->services[ $id ] = function () use ( $instance ) {
            return $instance;
        };
    }

    /**
     * Reset a service (for testing).
     *
     * @param string $id Service identifier.
     * @return void
     */
    public function reset( string $id ): void {
        unset( $this->instances[ $id ] );
    }

    /**
     * Reset all services (for testing).
     *
     * @return void
     */
    public function reset_all(): void {
        $this->instances = [];
    }
}

/**
 * Helper function to get container instance.
 *
 * @return Podcast_Prospector_Container
 */
function podcast_prospector_container(): Podcast_Prospector_Container {
    return Podcast_Prospector_Container::get_instance();
}

/**
 * Helper function to get a service from container.
 *
 * @param string $id Service identifier.
 * @return mixed
 */
function podcast_prospector_get( string $id ) {
    return Podcast_Prospector_Container::get_instance()->get( $id );
}
