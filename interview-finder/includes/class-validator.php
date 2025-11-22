<?php
/**
 * Input Validation Class
 *
 * @package Interview_Finder
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Interview_Finder_Validator
 *
 * Centralized input validation for all user input.
 */
class Interview_Finder_Validator {

    /**
     * Singleton instance.
     *
     * @var Interview_Finder_Validator|null
     */
    private static ?Interview_Finder_Validator $instance = null;

    /**
     * Valid search types.
     *
     * @var array
     */
    private const VALID_SEARCH_TYPES = [ 'byperson', 'bytitle', 'byadvancedpodcast', 'byadvancedepisode' ];

    /**
     * Valid sort orders (legacy).
     *
     * @var array
     */
    private const VALID_SORT_ORDERS = [ 'BEST_MATCH', 'LATEST', 'OLDEST' ];

    /**
     * Valid sort by options (Taddy API).
     *
     * @var array
     */
    private const VALID_SORT_BY = [ 'EXACTNESS', 'POPULARITY' ];

    /**
     * Valid match by options (Taddy API).
     *
     * @var array
     */
    private const VALID_MATCH_BY = [ 'MOST_TERMS', 'ALL_TERMS', 'EXACT_PHRASE' ];

    /**
     * Valid languages.
     *
     * @var array
     */
    private const VALID_LANGUAGES = [
        'ALL', 'ENGLISH', 'FRENCH', 'SPANISH', 'GERMAN', 'ITALIAN', 'PORTUGUESE',
        'DUTCH', 'RUSSIAN', 'JAPANESE', 'CHINESE', 'KOREAN', 'ARABIC', 'HINDI',
    ];

    /**
     * Valid countries.
     *
     * @var array
     */
    private const VALID_COUNTRIES = [
        'ALL', 'UNITED_STATES_OF_AMERICA', 'CANADA', 'UNITED_KINGDOM', 'AUSTRALIA',
        'GERMANY', 'FRANCE', 'SPAIN', 'ITALY', 'INDIA', 'CHINA', 'JAPAN', 'BRAZIL',
    ];

    /**
     * Get singleton instance.
     *
     * @return Interview_Finder_Validator
     */
    public static function get_instance(): Interview_Finder_Validator {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Sanitize search term.
     *
     * @param string $term Raw search term.
     * @return string Sanitized term.
     */
    public function sanitize_search_term( string $term ): string {
        $term = wp_strip_all_tags( $term );
        $term = sanitize_text_field( $term );
        return trim( $term );
    }

    /**
     * Validate search type.
     *
     * @param string $type Search type.
     * @return string Valid search type.
     */
    public function validate_search_type( string $type ): string {
        $type = strtolower( trim( $type ) );
        return in_array( $type, self::VALID_SEARCH_TYPES, true ) ? $type : 'byperson';
    }

    /**
     * Validate page number.
     *
     * @param mixed $page Page number.
     * @return int Valid page number.
     */
    public function validate_page( $page ): int {
        $page = absint( $page );
        return max( 1, $page );
    }

    /**
     * Validate results per page.
     *
     * @param mixed $count Results count.
     * @param int   $min   Minimum allowed.
     * @param int   $max   Maximum allowed.
     * @return int Valid count.
     */
    public function validate_results_per_page( $count, int $min = 5, int $max = 25 ): int {
        $count = absint( $count );
        if ( $count < $min ) {
            return $min;
        }
        if ( $count > $max ) {
            return $max;
        }
        return $count ?: 10;
    }

    /**
     * Validate language.
     *
     * @param string $language Language code.
     * @return string Valid language.
     */
    public function validate_language( string $language ): string {
        $language = strtoupper( trim( $language ) );
        return in_array( $language, self::VALID_LANGUAGES, true ) ? $language : 'ALL';
    }

    /**
     * Validate country.
     *
     * @param string $country Country code.
     * @return string Valid country.
     */
    public function validate_country( string $country ): string {
        $country = strtoupper( trim( $country ) );
        return in_array( $country, self::VALID_COUNTRIES, true ) ? $country : 'ALL';
    }

    /**
     * Validate genre.
     *
     * @param string $genre Genre code.
     * @return string Valid genre.
     */
    public function validate_genre( string $genre ): string {
        $genre = strtoupper( trim( $genre ) );

        if ( 'ALL' === $genre ) {
            return 'ALL';
        }

        // Must start with PODCASTSERIES_
        if ( strpos( $genre, 'PODCASTSERIES_' ) === 0 ) {
            // Basic validation - only alphanumeric and underscores
            if ( preg_match( '/^PODCASTSERIES_[A-Z_]+$/', $genre ) ) {
                return $genre;
            }
        }

        return 'ALL';
    }

    /**
     * Validate date.
     *
     * @param string $date Date string.
     * @return string Valid date (Y-m-d) or empty string.
     */
    public function validate_date( string $date ): string {
        $date = trim( $date );

        if ( empty( $date ) ) {
            return '';
        }

        // Try to parse the date
        $timestamp = strtotime( $date );
        if ( false === $timestamp ) {
            return '';
        }

        // Validate it's a real date
        $parsed = date_parse( $date );
        if ( $parsed['error_count'] > 0 || $parsed['warning_count'] > 0 ) {
            return '';
        }

        // Return in standard format
        return gmdate( 'Y-m-d', $timestamp );
    }

    /**
     * Validate sort order (legacy).
     *
     * @param string $order Sort order.
     * @return string Valid sort order.
     */
    public function validate_sort_order( string $order ): string {
        $order = strtoupper( trim( $order ) );
        return in_array( $order, self::VALID_SORT_ORDERS, true ) ? $order : 'BEST_MATCH';
    }

    /**
     * Validate sort by option (Taddy API).
     *
     * @param string $sort_by Sort by value.
     * @return string Valid sort by option.
     */
    public function validate_sort_by( string $sort_by ): string {
        $sort_by = strtoupper( trim( $sort_by ) );
        return in_array( $sort_by, self::VALID_SORT_BY, true ) ? $sort_by : 'EXACTNESS';
    }

    /**
     * Validate match by option (Taddy API).
     *
     * @param string $match_by Match by value.
     * @return string Valid match by option.
     */
    public function validate_match_by( string $match_by ): string {
        $match_by = strtoupper( trim( $match_by ) );
        return in_array( $match_by, self::VALID_MATCH_BY, true ) ? $match_by : 'MOST_TERMS';
    }

    /**
     * Validate boolean.
     *
     * @param mixed $value Value to validate.
     * @return bool
     */
    public function validate_boolean( $value ): bool {
        return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
    }

    /**
     * Validate a full search request.
     *
     * @param array $input Raw input data.
     * @return Interview_Finder_Validation_Result
     */
    public function validate_search_request( array $input ): Interview_Finder_Validation_Result {
        $result = new Interview_Finder_Validation_Result();

        // Search term (required)
        $search_term = $this->sanitize_search_term( $input['search_term'] ?? '' );
        if ( empty( $search_term ) ) {
            $result->add_error( 'search_term', __( 'Search term is required.', 'interview-finder' ) );
        } else {
            $result->set( 'search_term', $search_term );
        }

        // Search type
        $result->set( 'search_type', $this->validate_search_type( $input['search_type'] ?? '' ) );

        // Pagination
        $result->set( 'page', $this->validate_page( $input['page'] ?? 1 ) );
        $result->set( 'results_per_page', $this->validate_results_per_page( $input['results_per_page'] ?? 10 ) );

        // Filters
        $result->set( 'language', $this->validate_language( $input['language'] ?? 'ALL' ) );
        $result->set( 'country', $this->validate_country( $input['country'] ?? 'ALL' ) );
        $result->set( 'genre', $this->validate_genre( $input['genre'] ?? 'ALL' ) );

        // Dates
        $result->set( 'after_date', $this->validate_date( $input['after_date'] ?? '' ) );
        $result->set( 'before_date', $this->validate_date( $input['before_date'] ?? '' ) );

        // Sort and safe mode
        $result->set( 'sort_order', $this->validate_sort_order( $input['sort_order'] ?? 'BEST_MATCH' ) );
        $result->set( 'is_safe_mode', $this->validate_boolean( $input['isSafeMode'] ?? false ) );

        // Taddy API specific options
        $result->set( 'sort_by', $this->validate_sort_by( $input['sort_by'] ?? 'EXACTNESS' ) );
        $result->set( 'match_by', $this->validate_match_by( $input['match_by'] ?? 'MOST_TERMS' ) );

        return $result;
    }

    /**
     * Validate import request.
     *
     * @param array $input Raw input data.
     * @return Interview_Finder_Validation_Result
     */
    public function validate_import_request( array $input ): Interview_Finder_Validation_Result {
        $result = new Interview_Finder_Validation_Result();

        // Podcasts array (required)
        if ( empty( $input['podcasts'] ) || ! is_array( $input['podcasts'] ) ) {
            $result->add_error( 'podcasts', __( 'No podcasts selected for import.', 'interview-finder' ) );
        } else {
            $valid_podcasts = [];
            foreach ( $input['podcasts'] as $index => $podcast ) {
                $decoded = json_decode( stripslashes( $podcast ), true );
                if ( is_array( $decoded ) ) {
                    $valid_podcasts[] = $decoded;
                } else {
                    $result->add_error( "podcasts.{$index}", __( 'Invalid podcast data format.', 'interview-finder' ) );
                }
            }
            $result->set( 'podcasts', $valid_podcasts );
        }

        // Search context
        $result->set( 'search_term', $this->sanitize_search_term( $input['search_term'] ?? '' ) );
        $result->set( 'search_type', $this->validate_search_type( $input['search_type'] ?? '' ) );

        return $result;
    }
}

/**
 * Class Interview_Finder_Validation_Result
 *
 * Holds validation results with data and errors.
 */
class Interview_Finder_Validation_Result {

    /**
     * Validated data.
     *
     * @var array
     */
    private array $data = [];

    /**
     * Validation errors.
     *
     * @var array
     */
    private array $errors = [];

    /**
     * Set a validated value.
     *
     * @param string $key   Key.
     * @param mixed  $value Value.
     * @return void
     */
    public function set( string $key, $value ): void {
        $this->data[ $key ] = $value;
    }

    /**
     * Get a validated value.
     *
     * @param string $key     Key.
     * @param mixed  $default Default value.
     * @return mixed
     */
    public function get( string $key, $default = null ) {
        return $this->data[ $key ] ?? $default;
    }

    /**
     * Get all validated data.
     *
     * @return array
     */
    public function get_all(): array {
        return $this->data;
    }

    /**
     * Add a validation error.
     *
     * @param string $field   Field name.
     * @param string $message Error message.
     * @return void
     */
    public function add_error( string $field, string $message ): void {
        $this->errors[ $field ] = $message;
    }

    /**
     * Get all errors.
     *
     * @return array
     */
    public function get_errors(): array {
        return $this->errors;
    }

    /**
     * Check if validation passed.
     *
     * @return bool
     */
    public function is_valid(): bool {
        return empty( $this->errors );
    }

    /**
     * Get first error message.
     *
     * @return string
     */
    public function get_first_error(): string {
        return reset( $this->errors ) ?: '';
    }
}
