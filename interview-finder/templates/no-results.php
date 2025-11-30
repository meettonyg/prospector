<?php
/**
 * Template: No Results
 *
 * @package Interview_Finder
 * @var string $search_type Search type
 * @var string $message Message to display
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="if-no-results" role="status">
    <div class="if-no-results__icon" aria-hidden="true">
        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8"></circle>
            <path d="m21 21-4.35-4.35"></path>
        </svg>
    </div>
    <p class="if-no-results__message"><?php echo esc_html( $message ); ?></p>
    <p class="if-no-results__suggestion">
        <?php esc_html_e( 'Try adjusting your search terms or filters.', 'interview-finder' ); ?>
    </p>
</div>
