<?php
/**
 * Interview Finder Logger Class
 *
 * Provides configurable logging with log levels.
 *
 * @package Interview_Finder
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Interview_Finder_Logger
 *
 * Handles all plugin logging with configurable log levels.
 */
class Interview_Finder_Logger {

    /**
     * Log level constants.
     */
    const LEVEL_DEBUG   = 4;
    const LEVEL_INFO    = 3;
    const LEVEL_WARNING = 2;
    const LEVEL_ERROR   = 1;
    const LEVEL_NONE    = 0;

    /**
     * Singleton instance.
     *
     * @var Interview_Finder_Logger|null
     */
    private static ?Interview_Finder_Logger $instance = null;

    /**
     * Current log level.
     *
     * @var int
     */
    private int $log_level;

    /**
     * Whether logging is enabled.
     *
     * @var bool
     */
    private bool $enabled;

    /**
     * Log file path.
     *
     * @var string
     */
    private string $log_file;

    /**
     * Get singleton instance.
     *
     * @return Interview_Finder_Logger
     */
    public static function get_instance(): Interview_Finder_Logger {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor.
     */
    private function __construct() {
        $this->configure();
    }

    /**
     * Configure logger from settings.
     *
     * @return void
     */
    public function configure(): void {
        // Get settings if available
        if ( class_exists( 'Interview_Finder_Settings' ) ) {
            $settings = Interview_Finder_Settings::get_instance();
            $this->enabled = (bool) $settings->get( 'debug_logging_enabled', false );
            $this->log_level = $this->string_to_level( $settings->get( 'log_level', 'error' ) );
        } else {
            $this->enabled = false;
            $this->log_level = self::LEVEL_ERROR;
        }

        // Set log file location
        $upload_dir = wp_upload_dir();
        $this->log_file = $upload_dir['basedir'] . '/interview-finder-logs/debug.log';

        // Ensure log directory exists
        $log_dir = dirname( $this->log_file );
        if ( $this->enabled && ! file_exists( $log_dir ) ) {
            wp_mkdir_p( $log_dir );
            // Add .htaccess to protect logs
            file_put_contents( $log_dir . '/.htaccess', 'deny from all' );
        }
    }

    /**
     * Convert string log level to integer.
     *
     * @param string $level Log level string.
     * @return int
     */
    private function string_to_level( string $level ): int {
        $levels = [
            'debug'   => self::LEVEL_DEBUG,
            'info'    => self::LEVEL_INFO,
            'warning' => self::LEVEL_WARNING,
            'error'   => self::LEVEL_ERROR,
            'none'    => self::LEVEL_NONE,
        ];
        return $levels[ strtolower( $level ) ] ?? self::LEVEL_ERROR;
    }

    /**
     * Log a message.
     *
     * @param string $message Log message.
     * @param int    $level   Log level.
     * @param array  $context Additional context data.
     * @return void
     */
    private function log( string $message, int $level, array $context = [] ): void {
        if ( ! $this->enabled || $level > $this->log_level ) {
            return;
        }

        $level_labels = [
            self::LEVEL_DEBUG   => 'DEBUG',
            self::LEVEL_INFO    => 'INFO',
            self::LEVEL_WARNING => 'WARNING',
            self::LEVEL_ERROR   => 'ERROR',
        ];

        $label = $level_labels[ $level ] ?? 'LOG';
        $timestamp = current_time( 'Y-m-d H:i:s' );
        $context_str = ! empty( $context ) ? ' | Context: ' . wp_json_encode( $context ) : '';

        $log_entry = sprintf(
            "[%s] [%s] %s%s\n",
            $timestamp,
            $label,
            $message,
            $context_str
        );

        // Write to custom log file
        if ( $this->log_file ) {
            error_log( $log_entry, 3, $this->log_file );
        }

        // Also write to standard error log for critical issues
        if ( $level <= self::LEVEL_ERROR ) {
            error_log( 'Interview Finder: ' . $message );
        }
    }

    /**
     * Log a debug message.
     *
     * @param string $message Log message.
     * @param array  $context Additional context.
     * @return void
     */
    public function debug( string $message, array $context = [] ): void {
        $this->log( $message, self::LEVEL_DEBUG, $context );
    }

    /**
     * Log an info message.
     *
     * @param string $message Log message.
     * @param array  $context Additional context.
     * @return void
     */
    public function info( string $message, array $context = [] ): void {
        $this->log( $message, self::LEVEL_INFO, $context );
    }

    /**
     * Log a warning message.
     *
     * @param string $message Log message.
     * @param array  $context Additional context.
     * @return void
     */
    public function warning( string $message, array $context = [] ): void {
        $this->log( $message, self::LEVEL_WARNING, $context );
    }

    /**
     * Log an error message.
     *
     * @param string $message Log message.
     * @param array  $context Additional context.
     * @return void
     */
    public function error( string $message, array $context = [] ): void {
        $this->log( $message, self::LEVEL_ERROR, $context );
    }

    /**
     * Log an exception.
     *
     * @param Throwable $exception The exception to log.
     * @param string    $message   Additional message.
     * @return void
     */
    public function exception( Throwable $exception, string $message = '' ): void {
        $this->error(
            $message ?: 'Exception occurred',
            [
                'exception' => get_class( $exception ),
                'message'   => $exception->getMessage(),
                'file'      => $exception->getFile(),
                'line'      => $exception->getLine(),
                'trace'     => $exception->getTraceAsString(),
            ]
        );
    }

    /**
     * Log an API request.
     *
     * @param string $api      API name.
     * @param string $endpoint Endpoint called.
     * @param array  $params   Request parameters.
     * @return void
     */
    public function api_request( string $api, string $endpoint, array $params = [] ): void {
        $this->debug(
            sprintf( 'API Request: %s - %s', $api, $endpoint ),
            [ 'params' => $params ]
        );
    }

    /**
     * Log an API response.
     *
     * @param string $api          API name.
     * @param bool   $success      Whether request succeeded.
     * @param int    $status_code  HTTP status code.
     * @param string $message      Response message or error.
     * @return void
     */
    public function api_response( string $api, bool $success, int $status_code = 200, string $message = '' ): void {
        $level = $success ? self::LEVEL_DEBUG : self::LEVEL_WARNING;
        $this->log(
            sprintf( 'API Response: %s - %s', $api, $success ? 'Success' : 'Failed' ),
            $level,
            [
                'status_code' => $status_code,
                'message'     => $message,
            ]
        );
    }

    /**
     * Log user action.
     *
     * @param int    $user_id User ID.
     * @param string $action  Action performed.
     * @param array  $data    Additional data.
     * @return void
     */
    public function user_action( int $user_id, string $action, array $data = [] ): void {
        $this->info(
            sprintf( 'User Action: %s (User ID: %d)', $action, $user_id ),
            $data
        );
    }

    /**
     * Clear old log entries.
     *
     * @param int $days_to_keep Number of days to keep logs.
     * @return bool
     */
    public function clear_old_logs( int $days_to_keep = 30 ): bool {
        if ( ! file_exists( $this->log_file ) ) {
            return true;
        }

        $cutoff_time = strtotime( "-{$days_to_keep} days" );
        $file_time = filemtime( $this->log_file );

        if ( $file_time < $cutoff_time ) {
            return unlink( $this->log_file );
        }

        return true;
    }
}
