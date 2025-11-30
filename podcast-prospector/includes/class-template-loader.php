<?php
/**
 * Template Loader Class
 *
 * @package Podcast_Prospector
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Podcast_Prospector_Template_Loader
 *
 * Handles loading and rendering of template files with theme override support.
 */
class Podcast_Prospector_Template_Loader {

    /**
     * Plugin template path.
     *
     * @var string
     */
    private string $plugin_path;

    /**
     * Theme template folder name.
     *
     * @var string
     */
    private const THEME_FOLDER = 'podcast-prospector';

    /**
     * Constructor.
     *
     * @param string|null $plugin_path Plugin template path.
     */
    public function __construct( ?string $plugin_path = null ) {
        $this->plugin_path = $plugin_path ?? plugin_dir_path( __DIR__ ) . 'templates/';
    }

    /**
     * Render a template with data.
     *
     * @param string $template Template name (without .php).
     * @param array  $data     Data to pass to template.
     * @param bool   $return   Whether to return output instead of echoing.
     * @return string|void
     */
    public function render( string $template, array $data = [], bool $return = false ) {
        $template_file = $this->locate_template( $template );

        if ( ! $template_file ) {
            if ( WP_DEBUG ) {
                trigger_error( "Template not found: {$template}", E_USER_WARNING );
            }
            return $return ? '' : null;
        }

        if ( $return ) {
            ob_start();
        }

        // Extract data to variables
        extract( $data, EXTR_SKIP );

        // Include template
        include $template_file;

        if ( $return ) {
            return ob_get_clean();
        }
    }

    /**
     * Locate a template file.
     *
     * Checks theme first, then plugin templates.
     *
     * @param string $template Template name.
     * @return string|false Template path or false if not found.
     */
    public function locate_template( string $template ): string|false {
        $template = ltrim( $template, '/' );

        // Ensure .php extension
        if ( ! str_ends_with( $template, '.php' ) ) {
            $template .= '.php';
        }

        // Check theme directory first
        $theme_path = get_stylesheet_directory() . '/' . self::THEME_FOLDER . '/' . $template;
        if ( file_exists( $theme_path ) ) {
            return $theme_path;
        }

        // Check parent theme
        $parent_path = get_template_directory() . '/' . self::THEME_FOLDER . '/' . $template;
        if ( $parent_path !== $theme_path && file_exists( $parent_path ) ) {
            return $parent_path;
        }

        // Fall back to plugin templates
        $plugin_template = $this->plugin_path . $template;
        if ( file_exists( $plugin_template ) ) {
            return $plugin_template;
        }

        return false;
    }

    /**
     * Render a partial template.
     *
     * @param string $partial Partial template name (in partials/ subdirectory).
     * @param array  $data    Data to pass to template.
     * @param bool   $return  Whether to return output.
     * @return string|void
     */
    public function partial( string $partial, array $data = [], bool $return = false ) {
        return $this->render( 'partials/' . $partial, $data, $return );
    }

    /**
     * Render search results.
     *
     * @param array  $results     Search results.
     * @param string $search_type Search type.
     * @param array  $options     Additional options.
     * @return string
     */
    public function render_results( array $results, string $search_type, array $options = [] ): string {
        $items = $this->extract_items( $results, $search_type );

        if ( empty( $items ) ) {
            return $this->render( 'no-results', [
                'search_type' => $search_type,
                'message'     => $options['no_results_message'] ?? __( 'No results found.', 'podcast-prospector' ),
            ], true );
        }

        // Extract ranking and response details for Taddy API results
        $ranking_details = $this->extract_ranking_details( $results );
        $response_details = $this->extract_response_details( $results );

        // Extract location data (from REST API enrichment or passed in options)
        $location_data = $this->extract_location_data( $results, $options );

        return $this->render( 'results-' . $search_type, [
            'items'            => $items,
            'search_type'      => $search_type,
            'options'          => $options,
            'loader'           => $this,
            'ranking_details'  => $ranking_details,
            'response_details' => $response_details,
            'location_data'    => $location_data,
        ], true );
    }

    /**
     * Extract items from API response.
     *
     * @param array  $results     Raw results.
     * @param string $search_type Search type.
     * @return array
     */
    private function extract_items( array $results, string $search_type ): array {
        // PodcastIndex responses
        if ( isset( $results['items'] ) ) {
            return $results['items'];
        }
        if ( isset( $results['feeds'] ) ) {
            return $results['feeds'];
        }

        // YouTube responses
        if ( isset( $results['data']['items'] ) ) {
            return $results['data']['items'];
        }

        // Taddy responses - new API format (search)
        if ( isset( $results['data']['search']['podcastEpisodes'] ) ) {
            return $results['data']['search']['podcastEpisodes'];
        }
        if ( isset( $results['data']['search']['podcastSeries'] ) ) {
            return $results['data']['search']['podcastSeries'];
        }

        // Taddy responses - legacy API format (searchForTerm)
        if ( isset( $results['data']['searchForTerm']['podcastEpisodes'] ) ) {
            return $results['data']['searchForTerm']['podcastEpisodes'];
        }
        if ( isset( $results['data']['searchForTerm']['podcastSeries'] ) ) {
            return $results['data']['searchForTerm']['podcastSeries'];
        }

        return [];
    }

    /**
     * Extract ranking details from Taddy API response.
     *
     * @param array $results Raw results.
     * @return array
     */
    private function extract_ranking_details( array $results ): array {
        // New API format
        if ( isset( $results['data']['search']['rankingDetails'] ) ) {
            return $results['data']['search']['rankingDetails'];
        }

        return [];
    }

    /**
     * Extract response details from Taddy API response.
     *
     * @param array $results Raw results.
     * @return array
     */
    private function extract_response_details( array $results ): array {
        // New API format
        if ( isset( $results['data']['search']['responseDetails'] ) ) {
            return $results['data']['search']['responseDetails'];
        }

        return [];
    }

    /**
     * Extract location data from results or options.
     *
     * Location data can come from:
     * - REST API enrichment (results['locations'])
     * - Passed directly in options (options['location_data'])
     *
     * @param array $results Raw results.
     * @param array $options Additional options.
     * @return array Map of iTunes ID to location data.
     */
    private function extract_location_data( array $results, array $options ): array {
        // Check if location data was passed in options
        if ( ! empty( $options['location_data'] ) && is_array( $options['location_data'] ) ) {
            return $options['location_data'];
        }

        // Check if location data is in the results (from REST API enrichment)
        if ( isset( $results['locations'] ) && is_array( $results['locations'] ) ) {
            return $results['locations'];
        }

        return [];
    }

    /**
     * Get location for a specific iTunes ID.
     *
     * @param array  $location_data Map of iTunes ID to location.
     * @param string $itunes_id     iTunes ID to look up.
     * @return array|null Location data or null if not found.
     */
    public function get_location_for_itunes_id( array $location_data, string $itunes_id ): ?array {
        if ( empty( $itunes_id ) || empty( $location_data ) ) {
            return null;
        }

        return $location_data[ $itunes_id ] ?? null;
    }

    /**
     * Format location for display.
     *
     * @param array|null $location Location data.
     * @return string Formatted location string.
     */
    public function format_location( ?array $location ): string {
        if ( empty( $location ) || empty( $location['has_location'] ) ) {
            return '';
        }

        return $location['formatted'] ?? '';
    }

    /**
     * Escape and format output.
     *
     * @param string $text Text to escape.
     * @return string
     */
    public function e( string $text ): string {
        return esc_html( $text );
    }

    /**
     * Escape URL.
     *
     * @param string $url URL to escape.
     * @return string
     */
    public function url( string $url ): string {
        return esc_url( $url );
    }

    /**
     * Escape attribute.
     *
     * @param string $attr Attribute to escape.
     * @return string
     */
    public function attr( string $attr ): string {
        return esc_attr( $attr );
    }

    /**
     * Format timestamp.
     *
     * @param int|string $timestamp Timestamp.
     * @param string     $format    Date format.
     * @return string
     */
    public function date( $timestamp, string $format = '' ): string {
        if ( empty( $format ) ) {
            $format = get_option( 'date_format' );
        }

        if ( is_string( $timestamp ) ) {
            $timestamp = strtotime( $timestamp );
        }

        return date_i18n( $format, $timestamp );
    }

    /**
     * Get asset URL.
     *
     * @param string $path Asset path relative to plugin.
     * @return string
     */
    public function asset_url( string $path ): string {
        return plugins_url( $path, dirname( __DIR__ ) . '/podcast-prospector.php' );
    }
}
