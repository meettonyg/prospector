<?php
/**
 * Validator Class Tests
 *
 * @package Interview_Finder
 */

class Test_Interview_Finder_Validator extends WP_UnitTestCase {

    /**
     * Validator instance.
     *
     * @var Interview_Finder_Validator
     */
    private $validator;

    /**
     * Set up test fixtures.
     */
    public function setUp(): void {
        parent::setUp();
        $this->validator = Interview_Finder_Validator::get_instance();
    }

    /**
     * Test search term validation.
     */
    public function test_validate_search_term() {
        // Valid terms
        $this->assertEquals( 'john smith', $this->validator->sanitize_search_term( 'john smith' ) );
        $this->assertEquals( 'test', $this->validator->sanitize_search_term( '  test  ' ) );

        // XSS attempts stripped
        $this->assertEquals( 'alert(1)', $this->validator->sanitize_search_term( '<script>alert(1)</script>' ) );

        // Empty string
        $this->assertEquals( '', $this->validator->sanitize_search_term( '' ) );
    }

    /**
     * Test search type validation.
     */
    public function test_validate_search_type() {
        $this->assertEquals( 'byperson', $this->validator->validate_search_type( 'byperson' ) );
        $this->assertEquals( 'bytitle', $this->validator->validate_search_type( 'bytitle' ) );
        $this->assertEquals( 'byadvancedpodcast', $this->validator->validate_search_type( 'byadvancedpodcast' ) );
        $this->assertEquals( 'byadvancedepisode', $this->validator->validate_search_type( 'byadvancedepisode' ) );

        // Invalid types default to byperson
        $this->assertEquals( 'byperson', $this->validator->validate_search_type( 'invalid' ) );
        $this->assertEquals( 'byperson', $this->validator->validate_search_type( '' ) );
    }

    /**
     * Test page number validation.
     */
    public function test_validate_page() {
        $this->assertEquals( 1, $this->validator->validate_page( 1 ) );
        $this->assertEquals( 5, $this->validator->validate_page( 5 ) );
        $this->assertEquals( 1, $this->validator->validate_page( 0 ) );
        $this->assertEquals( 1, $this->validator->validate_page( -5 ) );
        $this->assertEquals( 1, $this->validator->validate_page( 'abc' ) );
    }

    /**
     * Test results per page validation.
     */
    public function test_validate_results_per_page() {
        $this->assertEquals( 10, $this->validator->validate_results_per_page( 10 ) );
        $this->assertEquals( 5, $this->validator->validate_results_per_page( 3 ) );
        $this->assertEquals( 25, $this->validator->validate_results_per_page( 30 ) );
        $this->assertEquals( 10, $this->validator->validate_results_per_page( 'invalid' ) );
    }

    /**
     * Test language validation.
     */
    public function test_validate_language() {
        $this->assertEquals( 'ENGLISH', $this->validator->validate_language( 'ENGLISH' ) );
        $this->assertEquals( 'ALL', $this->validator->validate_language( 'ALL' ) );
        $this->assertEquals( 'ALL', $this->validator->validate_language( 'INVALID_LANG' ) );
        $this->assertEquals( 'ENGLISH', $this->validator->validate_language( 'english' ) );
    }

    /**
     * Test country validation.
     */
    public function test_validate_country() {
        $this->assertEquals( 'UNITED_STATES_OF_AMERICA', $this->validator->validate_country( 'UNITED_STATES_OF_AMERICA' ) );
        $this->assertEquals( 'ALL', $this->validator->validate_country( 'ALL' ) );
        $this->assertEquals( 'ALL', $this->validator->validate_country( 'INVALID_COUNTRY' ) );
    }

    /**
     * Test genre validation.
     */
    public function test_validate_genre() {
        $this->assertEquals( 'PODCASTSERIES_BUSINESS', $this->validator->validate_genre( 'PODCASTSERIES_BUSINESS' ) );
        $this->assertEquals( 'ALL', $this->validator->validate_genre( 'ALL' ) );
        $this->assertEquals( 'ALL', $this->validator->validate_genre( 'INVALID_GENRE' ) );
    }

    /**
     * Test date validation.
     */
    public function test_validate_date() {
        $this->assertEquals( '2024-01-15', $this->validator->validate_date( '2024-01-15' ) );
        $this->assertEquals( '', $this->validator->validate_date( 'invalid-date' ) );
        $this->assertEquals( '', $this->validator->validate_date( '2024-13-45' ) );
        $this->assertEquals( '', $this->validator->validate_date( '' ) );
    }

    /**
     * Test sort order validation.
     */
    public function test_validate_sort_order() {
        $this->assertEquals( 'BEST_MATCH', $this->validator->validate_sort_order( 'BEST_MATCH' ) );
        $this->assertEquals( 'LATEST', $this->validator->validate_sort_order( 'LATEST' ) );
        $this->assertEquals( 'OLDEST', $this->validator->validate_sort_order( 'OLDEST' ) );
        $this->assertEquals( 'BEST_MATCH', $this->validator->validate_sort_order( 'INVALID' ) );
    }

    /**
     * Test boolean validation.
     */
    public function test_validate_boolean() {
        $this->assertTrue( $this->validator->validate_boolean( true ) );
        $this->assertTrue( $this->validator->validate_boolean( 'true' ) );
        $this->assertTrue( $this->validator->validate_boolean( '1' ) );
        $this->assertTrue( $this->validator->validate_boolean( 1 ) );

        $this->assertFalse( $this->validator->validate_boolean( false ) );
        $this->assertFalse( $this->validator->validate_boolean( 'false' ) );
        $this->assertFalse( $this->validator->validate_boolean( '0' ) );
        $this->assertFalse( $this->validator->validate_boolean( 0 ) );
    }

    /**
     * Test full search request validation.
     */
    public function test_validate_search_request() {
        $input = [
            'search_term'      => '  John Smith  ',
            'search_type'      => 'byperson',
            'page'             => '2',
            'results_per_page' => '15',
            'language'         => 'english',
            'country'          => 'CANADA',
            'genre'            => 'ALL',
            'after_date'       => '2024-01-01',
            'before_date'      => '2024-12-31',
            'sort_order'       => 'LATEST',
            'isSafeMode'       => 'true',
        ];

        $result = $this->validator->validate_search_request( $input );

        $this->assertTrue( $result->is_valid() );
        $this->assertEquals( 'John Smith', $result->get( 'search_term' ) );
        $this->assertEquals( 'byperson', $result->get( 'search_type' ) );
        $this->assertEquals( 2, $result->get( 'page' ) );
        $this->assertEquals( 15, $result->get( 'results_per_page' ) );
        $this->assertEquals( 'ENGLISH', $result->get( 'language' ) );
        $this->assertEquals( 'CANADA', $result->get( 'country' ) );
        $this->assertEquals( '2024-01-01', $result->get( 'after_date' ) );
        $this->assertEquals( 'LATEST', $result->get( 'sort_order' ) );
        $this->assertTrue( $result->get( 'is_safe_mode' ) );
    }

    /**
     * Test validation errors.
     */
    public function test_validation_errors() {
        $input = [
            'search_term' => '', // Required field empty
            'search_type' => 'byperson',
        ];

        $result = $this->validator->validate_search_request( $input );

        $this->assertFalse( $result->is_valid() );
        $this->assertNotEmpty( $result->get_errors() );
        $this->assertArrayHasKey( 'search_term', $result->get_errors() );
    }
}
