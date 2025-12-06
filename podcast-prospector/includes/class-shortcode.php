<?php
/**
 * Interview Finder Shortcode Class
 *
 * Handles the [podcast_prospector] shortcode rendering.
 *
 * @package Podcast_Prospector
 * @since 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Podcast_Prospector_Shortcode
 *
 * Renders the Vue 3 search interface shortcode.
 */
class Podcast_Prospector_Shortcode {

    /**
     * Singleton instance.
     *
     * @var Podcast_Prospector_Shortcode|null
     */
    private static ?Podcast_Prospector_Shortcode $instance = null;

    /**
     * Get singleton instance.
     *
     * @return Podcast_Prospector_Shortcode
     */
    public static function get_instance(): Podcast_Prospector_Shortcode {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register shortcode.
     *
     * @return void
     */
    public function init(): void {
        add_shortcode( 'podcast_prospector', [ $this, 'render' ] );
    }

    /**
     * Render shortcode.
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output.
     */
    public function render( $atts ): string {
        $user_id = get_current_user_id();

        if ( ! $user_id ) {
            return '<p>' . esc_html__( 'Please log in to use the Interview Finder.', 'podcast-prospector' ) . '</p>';
        }

        $vue_assets = Podcast_Prospector_Vue_Assets::get_instance();
        return $vue_assets->render_vue_container();
    }
}
