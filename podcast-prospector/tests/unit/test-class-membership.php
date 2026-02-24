<?php
/**
 * Membership Class Tests
 *
 * @package Podcast_Prospector
 */

class Test_Podcast_Prospector_Membership extends WP_UnitTestCase {

    /**
     * Membership instance.
     *
     * @var Podcast_Prospector_Membership
     */
    private $membership;

    /**
     * Test user ID.
     *
     * @var int
     */
    private $user_id;

    /**
     * Set up test fixtures.
     */
    public function setUp(): void {
        parent::setUp();
        $this->membership = Podcast_Prospector_Membership::get_instance();
        $this->user_id = $this->factory->user->create();
    }

    /**
     * Tear down test fixtures.
     */
    public function tearDown(): void {
        wp_delete_user( $this->user_id );
        parent::tearDown();
    }

    /**
     * Test ZENITH tier settings.
     */
    public function test_zenith_tier_has_full_access() {
        update_user_meta( $this->user_id, 'guestify_membership', 'ZENITH' );

        $settings = $this->membership->get_user_settings( $this->user_id );

        $this->assertEquals( 20, $settings['max_pages'] );
        $this->assertEquals( 25, $settings['max_results_per_page'] );
        $this->assertEquals( 50, $settings['podcastindex_max'] );
        $this->assertTrue( $settings['can_filter_country'] );
        $this->assertTrue( $settings['can_filter_language'] );
        $this->assertTrue( $settings['can_filter_genre'] );
        $this->assertTrue( $settings['can_filter_date'] );
        $this->assertFalse( $settings['safe_mode_forced'] );
        $this->assertContains( 'LATEST', $settings['sort_by_date_published_options'] );
        $this->assertContains( 'OLDEST', $settings['sort_by_date_published_options'] );
    }

    /**
     * Test VELOCITY tier settings.
     */
    public function test_velocity_tier_has_limited_access() {
        update_user_meta( $this->user_id, 'guestify_membership', 'VELOCITY' );

        $settings = $this->membership->get_user_settings( $this->user_id );

        $this->assertEquals( 10, $settings['max_pages'] );
        $this->assertEquals( 15, $settings['max_results_per_page'] );
        $this->assertEquals( 25, $settings['podcastindex_max'] );
        $this->assertTrue( $settings['can_filter_country'] );
        $this->assertTrue( $settings['can_filter_language'] );
        $this->assertTrue( $settings['can_filter_genre'] );
        $this->assertFalse( $settings['can_filter_date'] );
        $this->assertFalse( $settings['safe_mode_forced'] );
        $this->assertContains( 'LATEST', $settings['sort_by_date_published_options'] );
        $this->assertNotContains( 'OLDEST', $settings['sort_by_date_published_options'] );
    }

    /**
     * Test ACCELERATOR tier settings.
     */
    public function test_accelerator_tier_has_minimal_access() {
        update_user_meta( $this->user_id, 'guestify_membership', 'ACCELERATOR' );

        $settings = $this->membership->get_user_settings( $this->user_id );

        $this->assertEquals( 5, $settings['max_pages'] );
        $this->assertEquals( 10, $settings['max_results_per_page'] );
        $this->assertEquals( 10, $settings['podcastindex_max'] );
        $this->assertFalse( $settings['can_filter_country'] );
        $this->assertFalse( $settings['can_filter_language'] );
        $this->assertFalse( $settings['can_filter_genre'] );
        $this->assertFalse( $settings['can_filter_date'] );
        $this->assertTrue( $settings['safe_mode_forced'] );
        $this->assertEmpty( $settings['sort_by_date_published_options'] );
    }

    /**
     * Test unknown membership defaults to restricted.
     */
    public function test_unknown_membership_gets_defaults() {
        update_user_meta( $this->user_id, 'guestify_membership', 'UNKNOWN_TIER' );

        $settings = $this->membership->get_user_settings( $this->user_id );

        $this->assertEquals( 5, $settings['max_pages'] );
        $this->assertTrue( $settings['safe_mode_forced'] );
    }

    /**
     * Test can_use_filter method.
     */
    public function test_can_use_filter() {
        update_user_meta( $this->user_id, 'guestify_membership', 'ZENITH' );
        $this->assertTrue( $this->membership->can_use_filter( $this->user_id, 'date' ) );

        update_user_meta( $this->user_id, 'guestify_membership', 'VELOCITY' );
        $this->assertFalse( $this->membership->can_use_filter( $this->user_id, 'date' ) );
    }

    /**
     * Test can_use_sort_option method.
     */
    public function test_can_use_sort_option() {
        update_user_meta( $this->user_id, 'guestify_membership', 'ZENITH' );
        $this->assertTrue( $this->membership->can_use_sort_option( $this->user_id, 'OLDEST' ) );

        update_user_meta( $this->user_id, 'guestify_membership', 'VELOCITY' );
        $this->assertFalse( $this->membership->can_use_sort_option( $this->user_id, 'OLDEST' ) );
    }

    /**
     * Test constrain_results_per_page method.
     */
    public function test_constrain_results_per_page() {
        update_user_meta( $this->user_id, 'guestify_membership', 'VELOCITY' );

        // Max for VELOCITY is 15
        $this->assertEquals( 15, $this->membership->constrain_results_per_page( 25, $this->user_id ) );
        $this->assertEquals( 10, $this->membership->constrain_results_per_page( 10, $this->user_id ) );
        $this->assertEquals( 5, $this->membership->constrain_results_per_page( 3, $this->user_id ) );
    }

    /**
     * Test constrain_page_number method.
     */
    public function test_constrain_page_number() {
        update_user_meta( $this->user_id, 'guestify_membership', 'ACCELERATOR' );

        // Max pages for ACCELERATOR is 5
        $this->assertEquals( 5, $this->membership->constrain_page_number( 10, $this->user_id ) );
        $this->assertEquals( 3, $this->membership->constrain_page_number( 3, $this->user_id ) );
        $this->assertEquals( 1, $this->membership->constrain_page_number( 0, $this->user_id ) );
    }

    /**
     * Test case insensitivity for membership level.
     */
    public function test_membership_level_case_insensitive() {
        update_user_meta( $this->user_id, 'guestify_membership', 'zenith' );
        $settings = $this->membership->get_user_settings( $this->user_id );
        $this->assertEquals( 20, $settings['max_pages'] );

        update_user_meta( $this->user_id, 'guestify_membership', 'Zenith' );
        $settings = $this->membership->get_user_settings( $this->user_id );
        $this->assertEquals( 20, $settings['max_pages'] );
    }

    /**
     * Test config loads from database when saved.
     */
    public function test_config_loads_from_database() {
        $custom_config = [
            'tiers' => [
                'ZENITH' => [
                    'max_pages' => 50,
                    'max_results_per_page' => 30,
                ],
                'VELOCITY' => [
                    'max_pages' => 15,
                ],
                'ACCELERATOR' => [],
            ],
            'defaults' => [
                'max_pages' => 3,
            ],
        ];

        update_option( Podcast_Prospector_Membership::OPTION_NAME, $custom_config );
        $this->membership->reload_config();

        // ZENITH should pick up the saved max_pages
        $config = $this->membership->get_config();
        $this->assertEquals( 50, $config['ZENITH']['max_pages'] );
        $this->assertEquals( 30, $config['ZENITH']['max_results_per_page'] );

        // VELOCITY should merge: saved max_pages with hardcoded rest
        $this->assertEquals( 15, $config['VELOCITY']['max_pages'] );
        $this->assertEquals( 15, $config['VELOCITY']['max_results_per_page'] ); // hardcoded fallback

        // Defaults should merge: saved max_pages with hardcoded rest
        $defaults = $this->membership->get_defaults_config();
        $this->assertEquals( 3, $defaults['max_pages'] );
        $this->assertTrue( $defaults['safe_mode_forced'] ); // hardcoded fallback

        // Clean up
        delete_option( Podcast_Prospector_Membership::OPTION_NAME );
        $this->membership->reload_config();
    }

    /**
     * Test config falls back to hardcoded when no option saved.
     */
    public function test_config_falls_back_to_hardcoded() {
        delete_option( Podcast_Prospector_Membership::OPTION_NAME );
        $this->membership->reload_config();

        $config = $this->membership->get_config();
        $hardcoded = $this->membership->get_hardcoded_config();

        $this->assertEquals( $hardcoded['ZENITH']['max_pages'], $config['ZENITH']['max_pages'] );
        $this->assertEquals( $hardcoded['VELOCITY']['max_results_per_page'], $config['VELOCITY']['max_results_per_page'] );
        $this->assertEquals( $hardcoded['ACCELERATOR']['podcastindex_max'], $config['ACCELERATOR']['podcastindex_max'] );

        $defaults = $this->membership->get_defaults_config();
        $hardcoded_defaults = $this->membership->get_hardcoded_defaults();
        $this->assertEquals( $hardcoded_defaults, $defaults );
    }

    /**
     * Test reload_config picks up new database values.
     */
    public function test_reload_config() {
        // Start with no saved config
        delete_option( Podcast_Prospector_Membership::OPTION_NAME );
        $this->membership->reload_config();

        $config_before = $this->membership->get_config();
        $this->assertEquals( 20, $config_before['ZENITH']['max_pages'] ); // hardcoded

        // Save a new value
        update_option( Podcast_Prospector_Membership::OPTION_NAME, [
            'tiers' => [
                'ZENITH' => [ 'max_pages' => 99 ],
                'VELOCITY' => [],
                'ACCELERATOR' => [],
            ],
            'defaults' => [],
        ] );

        // Before reload, still old value
        $this->assertEquals( 20, $this->membership->get_config()['ZENITH']['max_pages'] );

        // After reload, new value
        $this->membership->reload_config();
        $this->assertEquals( 99, $this->membership->get_config()['ZENITH']['max_pages'] );

        // Clean up
        delete_option( Podcast_Prospector_Membership::OPTION_NAME );
        $this->membership->reload_config();
    }

    /**
     * Test search cap falls back to tier default when no per-user meta.
     */
    public function test_search_cap_falls_back_to_tier_default() {
        update_user_meta( $this->user_id, 'guestify_membership', 'VELOCITY' );
        // Ensure no per-user search cap meta
        delete_user_meta( $this->user_id, 'podcast_prospector_search_cap' );

        // Set tier default search cap via DB config
        update_option( Podcast_Prospector_Membership::OPTION_NAME, [
            'tiers' => [
                'ZENITH' => [],
                'VELOCITY' => [ 'default_search_cap' => 50 ],
                'ACCELERATOR' => [],
            ],
            'defaults' => [],
        ] );
        $this->membership->reload_config();

        $cap = $this->membership->get_search_cap( $this->user_id );
        $this->assertEquals( 50, $cap );

        // Clean up
        delete_option( Podcast_Prospector_Membership::OPTION_NAME );
        $this->membership->reload_config();
    }

    /**
     * Test search cap prefers per-user meta over tier default.
     */
    public function test_search_cap_prefers_user_meta() {
        update_user_meta( $this->user_id, 'guestify_membership', 'VELOCITY' );

        // Set tier default to 50
        update_option( Podcast_Prospector_Membership::OPTION_NAME, [
            'tiers' => [
                'ZENITH' => [],
                'VELOCITY' => [ 'default_search_cap' => 50 ],
                'ACCELERATOR' => [],
            ],
            'defaults' => [],
        ] );
        $this->membership->reload_config();

        // Set per-user cap to 100
        update_user_meta( $this->user_id, 'podcast_prospector_search_cap', 100 );

        $cap = $this->membership->get_search_cap( $this->user_id );
        $this->assertEquals( 100, $cap );

        // Clean up
        delete_user_meta( $this->user_id, 'podcast_prospector_search_cap' );
        delete_option( Podcast_Prospector_Membership::OPTION_NAME );
        $this->membership->reload_config();
    }

    /**
     * Test search cap returns 0 (unlimited) when no per-user meta and tier default is 0.
     */
    public function test_search_cap_unlimited_by_default() {
        update_user_meta( $this->user_id, 'guestify_membership', 'ZENITH' );
        delete_user_meta( $this->user_id, 'podcast_prospector_search_cap' );

        // Hardcoded ZENITH default_search_cap is 0
        delete_option( Podcast_Prospector_Membership::OPTION_NAME );
        $this->membership->reload_config();

        $cap = $this->membership->get_search_cap( $this->user_id );
        $this->assertEquals( 0, $cap );
    }

    /**
     * Test that default_search_cap field exists in all tier configs.
     */
    public function test_default_search_cap_exists_in_all_tiers() {
        $config = $this->membership->get_hardcoded_config();

        foreach ( $config as $tier => $settings ) {
            $this->assertArrayHasKey( 'default_search_cap', $settings, "Tier {$tier} missing default_search_cap" );
        }

        $defaults = $this->membership->get_hardcoded_defaults();
        $this->assertArrayHasKey( 'default_search_cap', $defaults, 'Defaults missing default_search_cap' );
    }
}
