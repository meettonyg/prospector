<?php
/**
 * Vue App Template
 *
 * Template for mounting the Vue 3 application in WordPress admin.
 *
 * @package Podcast_Prospector
 * @since 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <!-- CRITICAL: Vue mounts here with matching ID -->
    <div id="prospector-app">
        <!-- Vue app loads here -->
        <div class="loading-placeholder" style="padding: 40px; text-align: center;">
            <div class="spinner is-active" style="float: none; margin: 0 auto 10px;"></div>
            <p style="color: #646970;">Loading Podcast Prospector...</p>
        </div>
    </div>
</div>
