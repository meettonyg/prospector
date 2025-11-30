<?php
/**
 * Error Handler Class
 *
 * @package Podcast_Prospector
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Podcast_Prospector_Error_Handler
 *
 * Provides error boundaries and graceful degradation for the plugin.
 */
class Podcast_Prospector_Error_Handler {

    /**
     * Singleton instance.
     *
     * @var Podcast_Prospector_Error_Handler|null
     */
    private static ?Podcast_Prospector_Error_Handler $instance = null;

    /**
     * Logger instance.
     *
     * @var Podcast_Prospector_Logger|null
     */
    private ?Podcast_Prospector_Logger $logger;

    /**
     * Collected errors.
     *
     * @var array
     */
    private array $errors = [];

    /**
     * Whether errors should be displayed.
     *
     * @var bool
     */
    private bool $display_errors;

    /**
     * Error display mode.
     *
     * @var string
     */
    private string $error_mode = 'graceful';

    /**
     * Get singleton instance.
     *
     * @return Podcast_Prospector_Error_Handler
     */
    public static function get_instance(): Podcast_Prospector_Error_Handler {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->display_errors = defined( 'WP_DEBUG' ) && WP_DEBUG;
        $this->logger = class_exists( 'Podcast_Prospector_Logger' )
            ? Podcast_Prospector_Logger::get_instance()
            : null;
    }

    /**
     * Set logger.
     *
     * @param Podcast_Prospector_Logger $logger Logger instance.
     * @return void
     */
    public function set_logger( Podcast_Prospector_Logger $logger ): void {
        $this->logger = $logger;
    }

    /**
     * Execute a callback with error boundary protection.
     *
     * @param callable $callback   Callback to execute.
     * @param mixed    $fallback   Fallback value if callback fails.
     * @param string   $context    Context description for logging.
     * @return mixed Result of callback or fallback value.
     */
    public function try_catch( callable $callback, $fallback = null, string $context = '' ) {
        try {
            return $callback();
        } catch ( Throwable $e ) {
            $this->handle_exception( $e, $context );
            return $fallback;
        }
    }

    /**
     * Execute callback with error boundary and HTML output.
     *
     * @param callable $callback      Callback to execute.
     * @param string   $fallback_html Fallback HTML if callback fails.
     * @param string   $context       Context description.
     * @return string
     */
    public function try_render( callable $callback, string $fallback_html = '', string $context = '' ): string {
        try {
            ob_start();
            $result = $callback();

            // If callback returns a string, use that
            if ( is_string( $result ) ) {
                ob_end_clean();
                return $result;
            }

            // Otherwise capture output buffer
            return ob_get_clean();

        } catch ( Throwable $e ) {
            ob_end_clean();
            $this->handle_exception( $e, $context );

            if ( $this->display_errors ) {
                return $this->render_error_message( $e, $context );
            }

            return $fallback_html;
        }
    }

    /**
     * Execute AJAX callback with error boundary.
     *
     * @param callable $callback Callback to execute.
     * @param string   $context  Context description.
     * @return void
     */
    public function try_ajax( callable $callback, string $context = '' ): void {
        try {
            $callback();
        } catch ( Throwable $e ) {
            $this->handle_exception( $e, $context );
            $this->send_error_response( $e );
        }
    }

    /**
     * Execute API call with error boundary.
     *
     * @param callable $callback Callback to execute.
     * @param string   $api_name API name for logging.
     * @return array|WP_Error
     */
    public function try_api_call( callable $callback, string $api_name = '' ) {
        try {
            $result = $callback();

            if ( is_wp_error( $result ) ) {
                $this->log_error( 'API returned error', [
                    'api'     => $api_name,
                    'code'    => $result->get_error_code(),
                    'message' => $result->get_error_message(),
                ] );
            }

            return $result;

        } catch ( Throwable $e ) {
            $this->handle_exception( $e, "API call: {$api_name}" );

            return new WP_Error(
                'api_exception',
                $this->get_safe_error_message( $e ),
                [ 'status' => 500 ]
            );
        }
    }

    /**
     * Handle an exception.
     *
     * @param Throwable $e       Exception.
     * @param string    $context Context description.
     * @return void
     */
    public function handle_exception( Throwable $e, string $context = '' ): void {
        $error_data = [
            'message' => $e->getMessage(),
            'code'    => $e->getCode(),
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
            'context' => $context,
            'time'    => current_time( 'mysql' ),
        ];

        $this->errors[] = $error_data;
        $this->log_error( 'Exception caught', $error_data );

        /**
         * Fires when an exception is caught.
         *
         * @param Throwable $e          The exception.
         * @param array     $error_data Error details.
         */
        do_action( 'interview_finder_exception', $e, $error_data );
    }

    /**
     * Wrap a callback to catch errors.
     *
     * @param callable $callback Callback to wrap.
     * @param string   $context  Context description.
     * @return callable
     */
    public function wrap( callable $callback, string $context = '' ): callable {
        return function( ...$args ) use ( $callback, $context ) {
            return $this->try_catch(
                fn() => $callback( ...$args ),
                null,
                $context
            );
        };
    }

    /**
     * Get safe error message for user display.
     *
     * @param Throwable $e Exception.
     * @return string
     */
    public function get_safe_error_message( Throwable $e ): string {
        if ( $this->display_errors ) {
            return $e->getMessage();
        }

        // Map common errors to user-friendly messages
        $message = $e->getMessage();

        if ( stripos( $message, 'timeout' ) !== false ) {
            return __( 'The request timed out. Please try again.', 'interview-finder' );
        }

        if ( stripos( $message, 'connection' ) !== false ) {
            return __( 'Unable to connect to the server. Please check your internet connection.', 'interview-finder' );
        }

        if ( stripos( $message, 'rate limit' ) !== false ) {
            return __( 'Too many requests. Please wait a moment and try again.', 'interview-finder' );
        }

        return __( 'An unexpected error occurred. Please try again later.', 'interview-finder' );
    }

    /**
     * Render error message HTML.
     *
     * @param Throwable $e       Exception.
     * @param string    $context Context.
     * @return string
     */
    private function render_error_message( Throwable $e, string $context ): string {
        $html = '<div class="if-error-boundary" role="alert">';
        $html .= '<p class="if-error-boundary__title">' . esc_html__( 'An error occurred', 'interview-finder' ) . '</p>';

        if ( $this->display_errors ) {
            $html .= '<p class="if-error-boundary__message">' . esc_html( $e->getMessage() ) . '</p>';
            $html .= '<p class="if-error-boundary__context">' . esc_html( $context ) . '</p>';
            $html .= '<p class="if-error-boundary__location">';
            $html .= esc_html( basename( $e->getFile() ) . ':' . $e->getLine() );
            $html .= '</p>';
        } else {
            $html .= '<p class="if-error-boundary__message">';
            $html .= esc_html( $this->get_safe_error_message( $e ) );
            $html .= '</p>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Send AJAX error response.
     *
     * @param Throwable $e Exception.
     * @return void
     */
    private function send_error_response( Throwable $e ): void {
        $response = [
            'success' => false,
            'data'    => [
                'message' => $this->get_safe_error_message( $e ),
            ],
        ];

        if ( $this->display_errors ) {
            $response['data']['debug'] = [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ];
        }

        wp_send_json( $response, 500 );
    }

    /**
     * Log error.
     *
     * @param string $message Message.
     * @param array  $context Context data.
     * @return void
     */
    private function log_error( string $message, array $context = [] ): void {
        if ( $this->logger ) {
            $this->logger->error( '[Error Handler] ' . $message, $context );
        }
    }

    /**
     * Get all collected errors.
     *
     * @return array
     */
    public function get_errors(): array {
        return $this->errors;
    }

    /**
     * Check if errors occurred.
     *
     * @return bool
     */
    public function has_errors(): bool {
        return ! empty( $this->errors );
    }

    /**
     * Clear collected errors.
     *
     * @return void
     */
    public function clear_errors(): void {
        $this->errors = [];
    }

    /**
     * Create a WP_Error from an exception.
     *
     * @param Throwable $e    Exception.
     * @param string    $code Error code.
     * @return WP_Error
     */
    public function to_wp_error( Throwable $e, string $code = 'exception' ): WP_Error {
        return new WP_Error(
            $code,
            $this->get_safe_error_message( $e ),
            [
                'status'    => 500,
                'exception' => get_class( $e ),
            ]
        );
    }
}
