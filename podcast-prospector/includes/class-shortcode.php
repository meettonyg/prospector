<?php
/**
 * Interview Finder Shortcode Class
 *
 * Handles the [podcast_prospector] shortcode rendering.
 *
 * @package Podcast_Prospector
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Podcast_Prospector_Shortcode
 *
 * Renders the search interface shortcode.
 */
class Podcast_Prospector_Shortcode {

    /**
     * Singleton instance.
     *
     * @var Podcast_Prospector_Shortcode|null
     */
    private static ?Podcast_Prospector_Shortcode $instance = null;

    /**
     * Genre list for dropdowns.
     *
     * @var array
     */
    private static array $genres = [
        'ALL', 'PODCASTSERIES_ARTS', 'PODCASTSERIES_ARTS_BOOKS', 'PODCASTSERIES_ARTS_DESIGN',
        'PODCASTSERIES_ARTS_FASHION_AND_BEAUTY', 'PODCASTSERIES_ARTS_FOOD', 'PODCASTSERIES_ARTS_PERFORMING_ARTS',
        'PODCASTSERIES_ARTS_VISUAL_ARTS', 'PODCASTSERIES_BUSINESS', 'PODCASTSERIES_BUSINESS_CAREERS',
        'PODCASTSERIES_BUSINESS_ENTREPRENEURSHIP', 'PODCASTSERIES_BUSINESS_INVESTING',
        'PODCASTSERIES_BUSINESS_MANAGEMENT', 'PODCASTSERIES_BUSINESS_MARKETING', 'PODCASTSERIES_BUSINESS_NON_PROFIT',
        'PODCASTSERIES_COMEDY', 'PODCASTSERIES_COMEDY_INTERVIEWS', 'PODCASTSERIES_COMEDY_IMPROV',
        'PODCASTSERIES_COMEDY_STANDUP', 'PODCASTSERIES_EDUCATION', 'PODCASTSERIES_EDUCATION_COURSES',
        'PODCASTSERIES_EDUCATION_HOW_TO', 'PODCASTSERIES_EDUCATION_LANGUAGE_LEARNING',
        'PODCASTSERIES_EDUCATION_SELF_IMPROVEMENT', 'PODCASTSERIES_FICTION', 'PODCASTSERIES_FICTION_COMEDY_FICTION',
        'PODCASTSERIES_FICTION_DRAMA', 'PODCASTSERIES_FICTION_SCIENCE_FICTION', 'PODCASTSERIES_GOVERNMENT',
        'PODCASTSERIES_HISTORY', 'PODCASTSERIES_HEALTH_AND_FITNESS', 'PODCASTSERIES_HEALTH_AND_FITNESS_ALTERNATIVE_HEALTH',
        'PODCASTSERIES_HEALTH_AND_FITNESS_FITNESS', 'PODCASTSERIES_HEALTH_AND_FITNESS_MEDICINE',
        'PODCASTSERIES_HEALTH_AND_FITNESS_MENTAL_HEALTH', 'PODCASTSERIES_HEALTH_AND_FITNESS_NUTRITION',
        'PODCASTSERIES_HEALTH_AND_FITNESS_SEXUALITY', 'PODCASTSERIES_KIDS_AND_FAMILY',
        'PODCASTSERIES_KIDS_AND_FAMILY_EDUCATION_FOR_KIDS', 'PODCASTSERIES_KIDS_AND_FAMILY_PARENTING',
        'PODCASTSERIES_KIDS_AND_FAMILY_PETS_AND_ANIMALS', 'PODCASTSERIES_KIDS_AND_FAMILY_STORIES_FOR_KIDS',
        'PODCASTSERIES_LEISURE', 'PODCASTSERIES_LEISURE_ANIMATION_AND_MANGA', 'PODCASTSERIES_LEISURE_AUTOMOTIVE',
        'PODCASTSERIES_LEISURE_AVIATION', 'PODCASTSERIES_LEISURE_CRAFTS', 'PODCASTSERIES_LEISURE_GAMES',
        'PODCASTSERIES_LEISURE_HOBBIES', 'PODCASTSERIES_LEISURE_HOME_AND_GARDEN', 'PODCASTSERIES_LEISURE_VIDEO_GAMES',
        'PODCASTSERIES_MUSIC', 'PODCASTSERIES_MUSIC_COMMENTARY', 'PODCASTSERIES_MUSIC_HISTORY',
        'PODCASTSERIES_MUSIC_INTERVIEWS', 'PODCASTSERIES_NEWS', 'PODCASTSERIES_NEWS_BUSINESS',
        'PODCASTSERIES_NEWS_DAILY_NEWS', 'PODCASTSERIES_NEWS_ENTERTAINMENT', 'PODCASTSERIES_NEWS_COMMENTARY',
        'PODCASTSERIES_NEWS_POLITICS', 'PODCASTSERIES_NEWS_SPORTS', 'PODCASTSERIES_NEWS_TECH',
        'PODCASTSERIES_RELIGION_AND_SPIRITUALITY', 'PODCASTSERIES_RELIGION_AND_SPIRITUALITY_BUDDHISM',
        'PODCASTSERIES_RELIGION_AND_SPIRITUALITY_CHRISTIANITY', 'PODCASTSERIES_RELIGION_AND_SPIRITUALITY_HINDUISM',
        'PODCASTSERIES_RELIGION_AND_SPIRITUALITY_ISLAM', 'PODCASTSERIES_RELIGION_AND_SPIRITUALITY_JUDAISM',
        'PODCASTSERIES_RELIGION_AND_SPIRITUALITY_RELIGION', 'PODCASTSERIES_RELIGION_AND_SPIRITUALITY_SPIRITUALITY',
        'PODCASTSERIES_SCIENCE', 'PODCASTSERIES_SCIENCE_ASTRONOMY', 'PODCASTSERIES_SCIENCE_CHEMISTRY',
        'PODCASTSERIES_SCIENCE_EARTH_SCIENCES', 'PODCASTSERIES_SCIENCE_LIFE_SCIENCES', 'PODCASTSERIES_SCIENCE_MATHEMATICS',
        'PODCASTSERIES_SCIENCE_NATURAL_SCIENCES', 'PODCASTSERIES_SCIENCE_NATURE', 'PODCASTSERIES_SCIENCE_PHYSICS',
        'PODCASTSERIES_SCIENCE_SOCIAL_SCIENCES', 'PODCASTSERIES_SOCIETY_AND_CULTURE',
        'PODCASTSERIES_SOCIETY_AND_CULTURE_DOCUMENTARY', 'PODCASTSERIES_SOCIETY_AND_CULTURE_PERSONAL_JOURNALS',
        'PODCASTSERIES_SOCIETY_AND_CULTURE_PHILOSOPHY', 'PODCASTSERIES_SOCIETY_AND_CULTURE_PLACES_AND_TRAVEL',
        'PODCASTSERIES_SOCIETY_AND_CULTURE_RELATIONSHIPS', 'PODCASTSERIES_SPORTS', 'PODCASTSERIES_SPORTS_BASEBALL',
        'PODCASTSERIES_SPORTS_BASKETBALL', 'PODCASTSERIES_SPORTS_CRICKET', 'PODCASTSERIES_SPORTS_FANTASY_SPORTS',
        'PODCASTSERIES_SPORTS_FOOTBALL', 'PODCASTSERIES_SPORTS_GOLF', 'PODCASTSERIES_SPORTS_HOCKEY',
        'PODCASTSERIES_SPORTS_RUGBY', 'PODCASTSERIES_SPORTS_RUNNING', 'PODCASTSERIES_SPORTS_SOCCER',
        'PODCASTSERIES_SPORTS_SWIMMING', 'PODCASTSERIES_SPORTS_TENNIS', 'PODCASTSERIES_SPORTS_VOLLEYBALL',
        'PODCASTSERIES_SPORTS_WILDERNESS', 'PODCASTSERIES_SPORTS_WRESTLING', 'PODCASTSERIES_TECHNOLOGY',
        'PODCASTSERIES_TRUE_CRIME', 'PODCASTSERIES_TV_AND_FILM', 'PODCASTSERIES_TV_AND_FILM_AFTER_SHOWS',
        'PODCASTSERIES_TV_AND_FILM_HISTORY', 'PODCASTSERIES_TV_AND_FILM_INTERVIEWS',
        'PODCASTSERIES_TV_AND_FILM_FILM_REVIEWS', 'PODCASTSERIES_TV_AND_FILM_TV_REVIEWS',
    ];

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

        $membership = Podcast_Prospector_Membership::get_instance();
        $database = Podcast_Prospector_Database::get_instance();

        $ghl_id = $membership->get_ghl_id( $user_id );
        $search_cap = $membership->get_search_cap( $user_id );
        $settings = $membership->get_user_settings( $user_id );

        if ( ! $ghl_id && ! $user_id ) {
            return '<p>' . esc_html__( 'Error: Required user data not found.', 'podcast-prospector' ) . '</p>';
        }

        // Get user search data
        $user_data = $database->get_user_data( $ghl_id, $user_id );
        $search_count = $user_data ? (int) $user_data->search_count : 0;
        $remaining = max( 0, $search_cap - $search_count );

        // Get posted values for maintaining form state
        $search_type = isset( $_POST['search_type'] ) ? sanitize_text_field( $_POST['search_type'] ) : 'byperson';
        $search_term = isset( $_POST['search_term'] ) ? sanitize_text_field( $_POST['search_term'] ) : '';
        $number_of_results = isset( $_POST['number_of_results'] ) ? (int) $_POST['number_of_results'] : 10;
        $results_per_page = isset( $_POST['results_per_page'] ) ? (int) $_POST['results_per_page'] : 10;
        $sort_order = isset( $_POST['sort_order'] ) ? sanitize_text_field( $_POST['sort_order'] ) : 'BEST_MATCH';
        $language = isset( $_POST['language'] ) ? sanitize_text_field( $_POST['language'] ) : 'ALL';
        $country = isset( $_POST['country'] ) ? sanitize_text_field( $_POST['country'] ) : 'ALL';
        $genre = isset( $_POST['genre'] ) ? sanitize_text_field( $_POST['genre'] ) : 'ALL';
        $after_date = isset( $_POST['after_date'] ) ? sanitize_text_field( $_POST['after_date'] ) : '';
        $before_date = isset( $_POST['before_date'] ) ? sanitize_text_field( $_POST['before_date'] ) : '';
        $is_safe_mode = isset( $_POST['isSafeMode'] ) ? filter_var( $_POST['isSafeMode'], FILTER_VALIDATE_BOOLEAN ) : false;

        // Start output
        $output = '<div class="search-form-wrapper">';
        $output .= '<form method="post" action="" class="search-form">';

        // Tabs
        $output .= $this->render_tabs( $search_type );

        // Search row
        $output .= $this->render_search_row( $search_term, $settings, $number_of_results, $results_per_page, $sort_order, $search_type );

        // Filter sidebar
        $output .= $this->render_filters( $settings, $language, $country, $genre, $after_date, $before_date, $is_safe_mode );

        $output .= '</form>';

        // Loading spinner
        $output .= '<div id="loading-spinner" style="display:none;">'
            . '<img src="' . esc_url( PODCAST_PROSPECTOR_PLUGIN_URL . 'assets/spinner.gif' ) . '" alt="Loading...">'
            . '</div>';

        // Error message container
        $output .= '<div id="search-error-message" style="color:red; font-weight:bold;"></div>';

        // Search cap warning
        if ( $search_cap > 0 && $search_count >= $search_cap ) {
            $output .= '<div class="tabinfo msg-template pageupcta">'
                . '<i class="iconcta fas fa-exclamation-circle"></i>'
                . '<h3>' . esc_html__( 'You used up all of your searches on your current plan', 'podcast-prospector' ) . '</h3>'
                . '<p>' . sprintf(
                    /* translators: %s: pricing link */
                    esc_html__( 'You have reached the maximum number of searches allowed this month. %s to continue searching.', 'podcast-prospector' ),
                    '<a href="/pricing/">' . esc_html__( 'Upgrade your plan', 'podcast-prospector' ) . '</a>'
                ) . '</p>'
                . '<p><a class="btn-trial-cta" href="/pricing/" role="button">'
                . esc_html__( 'Upgrade your plan', 'podcast-prospector' ) . ' <i class="fas fa-lock"></i></a></p>'
                . '</div>';
        }

        $output .= '</div>'; // .search-form-wrapper

        return $output;
    }

    /**
     * Render search tabs.
     *
     * @param string $selected Selected tab.
     * @return string HTML.
     */
    private function render_tabs( string $selected ): string {
        $tabs = [
            'byperson'          => __( 'Search Episodes by Person', 'podcast-prospector' ),
            'bytitle'           => __( 'Search Podcasts by Title', 'podcast-prospector' ),
            'byadvancedpodcast' => __( 'Advanced Search Podcasts', 'podcast-prospector' ),
            'byadvancedepisode' => __( 'Advanced Search Episodes', 'podcast-prospector' ),
        ];

        $output = '<div class="tabs tabs-top">';
        foreach ( $tabs as $value => $label ) {
            $checked = $selected === $value ? 'checked' : '';
            $id = 'tab-' . $value;
            $output .= sprintf(
                '<input type="radio" name="search_type" id="%s" value="%s" %s>'
                . '<label for="%s">%s</label>',
                esc_attr( $id ),
                esc_attr( $value ),
                $checked,
                esc_attr( $id ),
                esc_html( $label )
            );
        }
        $output .= '</div>';

        return $output;
    }

    /**
     * Render search row.
     *
     * @param string $search_term       Current search term.
     * @param array  $settings          Membership settings.
     * @param int    $number_of_results Number of results.
     * @param int    $results_per_page  Results per page.
     * @param string $sort_order        Sort order.
     * @param string $search_type       Search type for determining visibility.
     * @return string HTML.
     */
    private function render_search_row( string $search_term, array $settings, int $number_of_results, int $results_per_page, string $sort_order, string $search_type = 'byperson' ): string {
        $output = '<div class="search-row" style="display:flex; align-items:center; justify-content:space-between; margin-top:15px;">';

        // Determine if this is a basic search type (byperson or bytitle)
        $is_basic_search = in_array( $search_type, [ 'byperson', 'bytitle' ], true );
        $filter_hidden = $is_basic_search ? ' hidden' : '';

        // Left group: search input, button, filter toggle
        $output .= '<div class="left-group" style="display:flex; gap:10px; align-items:center;">'
            . '<input type="text" name="search_term" class="search-term" placeholder="' . esc_attr__( 'Enter search term', 'podcast-prospector' ) . '" '
            . 'value="' . esc_attr( $search_term ) . '" required style="min-width:220px;">'
            . '<input type="submit" class="search-btn" value="' . esc_attr__( 'Search', 'podcast-prospector' ) . '">'
            . '<button id="toggle-filters" class="filter-btn" type="button"' . $filter_hidden . '>' . esc_html__( 'Filter', 'podcast-prospector' ) . '</button>'
            . '</div>';

        // Right group: results dropdowns
        $output .= '<div class="right-group" style="display:flex; align-items:center; gap:10px;">';

        // Basic block (PodcastIndex) - visible for basic searches (byperson, bytitle)
        $basic_hidden = $is_basic_search ? '' : ' hidden';
        $output .= '<div id="basic-block"' . $basic_hidden . '>'
            . '<label for="number_of_results" class="results-label">' . esc_html__( 'Number of Results:', 'podcast-prospector' ) . '</label>'
            . '<select name="number_of_results" id="number_of_results" class="results-dropdown">';

        $basic_options = [ 5, 10, 25, 50 ];
        $basic_max = $settings['podcastindex_max'];
        foreach ( $basic_options as $val ) {
            $sel = $number_of_results === $val ? 'selected' : '';
            $disabled = $val > $basic_max ? 'disabled' : '';
            $lock = $disabled ? ' &#x1F512;' : '';
            $output .= sprintf( '<option value="%d" %s %s>%d%s</option>', $val, $sel, $disabled, $val, $lock );
        }
        $output .= '</select></div>';

        // Advanced block (Taddy) - visible for advanced searches
        $advanced_hidden = $is_basic_search ? ' hidden' : '';
        $output .= '<div id="advanced-block"' . $advanced_hidden . '>';

        // Results per page
        $output .= '<label for="results_per_page" class="results-label">' . esc_html__( 'Display:', 'podcast-prospector' ) . '</label>'
            . '<select name="results_per_page" id="results_per_page" class="results-dropdown">';

        $advanced_options = [ 5, 10, 15, 20, 25 ];
        $advanced_max = $settings['max_results_per_page'];
        foreach ( $advanced_options as $opt ) {
            $sel = $results_per_page === $opt ? 'selected' : '';
            $disabled = $opt > $advanced_max ? 'disabled' : '';
            $lock = $disabled ? ' &#x1F512;' : '';
            $output .= sprintf( '<option value="%d" %s %s>%d%s</option>', $opt, $sel, $disabled, $opt, $lock );
        }
        $output .= '</select>';

        // Sort order
        $output .= '<label for="sort_order" class="results-label">' . esc_html__( 'Sort By:', 'podcast-prospector' ) . '</label>'
            . '<select name="sort_order" id="sort_order" class="results-dropdown">';

        $sort_options = [
            'BEST_MATCH' => __( 'Best Match', 'podcast-prospector' ),
            'LATEST'     => __( 'Latest', 'podcast-prospector' ),
            'OLDEST'     => __( 'Oldest', 'podcast-prospector' ),
        ];

        $allowed_sorts = $settings['sort_by_date_published_options'] ?? [];
        foreach ( $sort_options as $value => $label ) {
            $sel = $sort_order === $value ? 'selected' : '';
            $disabled = ( 'BEST_MATCH' !== $value && ! in_array( $value, $allowed_sorts, true ) ) ? 'disabled' : '';
            $lock = $disabled ? ' &#x1F512;' : '';
            $output .= sprintf( '<option value="%s" %s %s>%s%s</option>', esc_attr( $value ), $sel, $disabled, esc_html( $label ), $lock );
        }
        $output .= '</select></div>';

        $output .= '</div>'; // .right-group
        $output .= '</div>'; // .search-row

        return $output;
    }

    /**
     * Render filter sidebar.
     *
     * @param array  $settings    Membership settings.
     * @param string $language    Selected language.
     * @param string $country     Selected country.
     * @param string $genre       Selected genre.
     * @param string $after_date  After date.
     * @param string $before_date Before date.
     * @param bool   $is_safe_mode Safe mode.
     * @return string HTML.
     */
    private function render_filters( array $settings, string $language, string $country, string $genre, string $after_date, string $before_date, bool $is_safe_mode ): string {
        $output = '<div id="filter-sidebar" hidden style="margin-top:15px;">';

        // Language
        $output .= $this->render_filter_select(
            'language',
            __( 'Language:', 'podcast-prospector' ),
            [ 'ALL', 'ENGLISH', 'FRENCH', 'SPANISH', 'GERMAN', 'ITALIAN', 'PORTUGUESE' ],
            $language,
            ! $settings['can_filter_language']
        );

        // Country
        $output .= $this->render_filter_select(
            'country',
            __( 'Country:', 'podcast-prospector' ),
            [ 'ALL', 'UNITED_STATES_OF_AMERICA', 'CANADA', 'UNITED_KINGDOM', 'AUSTRALIA', 'GERMANY', 'FRANCE', 'SPAIN', 'ITALY', 'INDIA', 'CHINA' ],
            $country,
            ! $settings['can_filter_country']
        );

        // Genre
        $output .= $this->render_filter_select(
            'genre',
            __( 'Genre:', 'podcast-prospector' ),
            self::$genres,
            $genre,
            ! $settings['can_filter_genre']
        );

        // Date filters
        $date_disabled = ! $settings['can_filter_date'];
        $lock_icon = $date_disabled ? ' <span class="locked" title="Upgrade"><i class="fas fa-lock"></i></span>' : '';

        $output .= '<div class="filter-group">'
            . '<label for="after_date">' . esc_html__( 'Published After:', 'podcast-prospector' ) . $lock_icon . '</label>'
            . '<input type="date" name="after_date" id="after_date" value="' . esc_attr( $after_date ) . '" '
            . ( $date_disabled ? 'disabled' : '' ) . '>'
            . '</div>';

        $output .= '<div class="filter-group">'
            . '<label for="before_date">' . esc_html__( 'Published Before:', 'podcast-prospector' ) . $lock_icon . '</label>'
            . '<input type="date" name="before_date" id="before_date" value="' . esc_attr( $before_date ) . '" '
            . ( $date_disabled ? 'disabled' : '' ) . '>'
            . '</div>';

        // Safe mode
        if ( $settings['safe_mode_forced'] ) {
            $output .= '<div class="filter-group">'
                . '<p>' . esc_html__( 'Explicit content is disabled on your plan', 'podcast-prospector' ) . ' <span class="locked"><i class="fas fa-lock"></i></span></p>'
                . '<input type="hidden" name="isSafeMode" value="true">'
                . '</div>';
        } else {
            $checked = ! $is_safe_mode ? 'checked' : '';
            $output .= '<div class="filter-group">'
                . '<label><input type="checkbox" name="isSafeMode" ' . $checked . '> ' . esc_html__( 'Include explicit content', 'podcast-prospector' ) . '</label>'
                . '</div>';
        }

        $output .= '</div>'; // #filter-sidebar

        return $output;
    }

    /**
     * Render a filter select dropdown.
     *
     * @param string $name     Field name.
     * @param string $label    Label text.
     * @param array  $options  Options array.
     * @param string $selected Selected value.
     * @param bool   $disabled Whether field is disabled.
     * @return string HTML.
     */
    private function render_filter_select( string $name, string $label, array $options, string $selected, bool $disabled ): string {
        $lock_icon = $disabled ? ' <span class="locked" title="Upgrade"><i class="fas fa-lock"></i></span>' : '';
        $disabled_attr = $disabled ? 'disabled' : '';

        $output = '<div class="filter-group">'
            . '<label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . $lock_icon . '</label>'
            . '<select name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '" ' . $disabled_attr . '>';

        foreach ( $options as $option ) {
            $sel = $selected === $option ? 'selected' : '';
            $display = 'ALL' === $option ? __( 'All', 'podcast-prospector' ) : ucwords( strtolower( str_replace( [ 'PODCASTSERIES_', '_' ], [ '', ' ' ], $option ) ) );
            $output .= sprintf( '<option value="%s" %s>%s</option>', esc_attr( $option ), $sel, esc_html( $display ) );
        }

        $output .= '</select></div>';

        return $output;
    }
}
