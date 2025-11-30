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
}
