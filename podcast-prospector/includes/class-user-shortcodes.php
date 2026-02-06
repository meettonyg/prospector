<?php
/**
 * Podcast Prospector User Shortcodes
 *
 * Provides shortcodes for displaying user subscription and usage data.
 *
 * @package Podcast_Prospector
 * @since 2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Podcast_Prospector_User_Shortcodes
 *
 * Handles user-related shortcodes for displaying subscription status,
 * search counts, and renewal information.
 */
class Podcast_Prospector_User_Shortcodes {

	/**
	 * Singleton instance.
	 *
	 * @var Podcast_Prospector_User_Shortcodes|null
	 */
	private static ?Podcast_Prospector_User_Shortcodes $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return Podcast_Prospector_User_Shortcodes
	 */
	public static function get_instance(): Podcast_Prospector_User_Shortcodes {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register all shortcodes.
	 *
	 * @return void
	 */
	public function init(): void {
		add_shortcode( 'prospector_searches', [ $this, 'render_searches' ] );
		add_shortcode( 'prospector_searches_remaining', [ $this, 'render_searches_remaining' ] );
		add_shortcode( 'prospector_last_renewal_date', [ $this, 'render_last_renewal_date' ] );
		add_shortcode( 'prospector_renewal_type', [ $this, 'render_renewal_type' ] );
		add_shortcode( 'prospector_next_renewal_date', [ $this, 'render_next_renewal_date' ] );
		add_shortcode( 'prospector_total_searches', [ $this, 'render_total_searches' ] );
	}

	/**
	 * Get current user context including user data from database.
	 *
	 * Helper method to reduce code duplication across shortcode methods.
	 *
	 * @return array{user_id: int, user_data: object|null}|null User context or null if not logged in.
	 */
	private function get_current_user_context(): ?array {
		if ( ! is_user_logged_in() ) {
			return null;
		}

		$user_id  = get_current_user_id();
		$ghl_id   = get_user_meta( $user_id, 'highlevel_contact_id', true );
		$database = Podcast_Prospector_Database::get_instance();

		$user_data = $database->get_user_data( $ghl_id, $user_id );

		return [
			'user_id'   => $user_id,
			'user_data' => $user_data,
		];
	}

	/**
	 * Shortcode: [prospector_searches]
	 *
	 * Displays the current period search count.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Search count or empty string if not logged in.
	 */
	public function render_searches( $atts ): string {
		$context = $this->get_current_user_context();

		if ( ! $context ) {
			return '';
		}

		$search_count = $context['user_data'] ? (int) $context['user_data']->search_count : 0;

		return (string) $search_count;
	}

	/**
	 * Shortcode: [prospector_searches_remaining]
	 *
	 * Displays remaining searches for the current period.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Remaining searches or empty string if not logged in.
	 */
	public function render_searches_remaining( $atts ): string {
		$context = $this->get_current_user_context();

		if ( ! $context ) {
			return '';
		}

		$search_cap   = (int) get_user_meta( $context['user_id'], 'podcast_prospector_search_cap', true );
		$search_count = $context['user_data'] ? (int) $context['user_data']->search_count : 0;
		$remaining    = max( 0, $search_cap - $search_count );

		return (string) $remaining;
	}

	/**
	 * Shortcode: [prospector_last_renewal_date]
	 *
	 * Displays the last renewal date from user meta.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Last renewal date or empty string if not logged in.
	 */
	public function render_last_renewal_date( $atts ): string {
		if ( ! is_user_logged_in() ) {
			return '';
		}

		$user_id           = get_current_user_id();
		$last_renewal_date = get_user_meta( $user_id, 'guestify_last_renewal_date', true );

		return (string) $last_renewal_date;
	}

	/**
	 * Shortcode: [prospector_renewal_type]
	 *
	 * Displays the renewal type (e.g., Monthly or Annual).
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Renewal type or empty string if not logged in.
	 */
	public function render_renewal_type( $atts ): string {
		if ( ! is_user_logged_in() ) {
			return '';
		}

		$user_id      = get_current_user_id();
		$renewal_type = get_user_meta( $user_id, 'contact.guestify_renewal_type', true );

		return (string) $renewal_type;
	}

	/**
	 * Shortcode: [prospector_next_renewal_date]
	 *
	 * Calculates and displays the next renewal date based on the last renewal
	 * date and renewal type (Monthly or Annual).
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Next renewal date (Y-m-d format) or empty string.
	 */
	public function render_next_renewal_date( $atts ): string {
		if ( ! is_user_logged_in() ) {
			return '';
		}

		$user_id           = get_current_user_id();
		$last_renewal_date = get_user_meta( $user_id, 'guestify_last_renewal_date', true );
		$renewal_type      = get_user_meta( $user_id, 'contact.guestify_renewal_type', true );

		if ( empty( $last_renewal_date ) ) {
			return '';
		}

		try {
			$date_obj = new DateTime( $last_renewal_date, wp_timezone() );
		} catch ( Exception $e ) {
			if ( class_exists( 'Podcast_Prospector_Logger' ) ) {
				Podcast_Prospector_Logger::get_instance()->error( 'Failed to parse last renewal date: ' . $e->getMessage() );
			}
			return '';
		}

		// Determine interval based on renewal type.
		$renewal_type = strtolower( (string) $renewal_type );
		if ( 'monthly' === $renewal_type ) {
			$interval = new DateInterval( 'P1M' );
		} else {
			// Default to annual if not monthly.
			$interval = new DateInterval( 'P1Y' );
		}

		$date_obj->add( $interval );

		return $date_obj->format( 'Y-m-d' );
	}

	/**
	 * Shortcode: [prospector_total_searches]
	 *
	 * Displays the user's total lifetime searches.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Total searches or empty string if not logged in.
	 */
	public function render_total_searches( $atts ): string {
		$context = $this->get_current_user_context();

		if ( ! $context ) {
			return '';
		}

		$total_searches = $context['user_data'] && isset( $context['user_data']->total_searches )
			? (int) $context['user_data']->total_searches
			: 0;

		return (string) $total_searches;
	}
}
