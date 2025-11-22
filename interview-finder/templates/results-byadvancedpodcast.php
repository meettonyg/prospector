<?php
/**
 * Template: Advanced Podcast Search Results
 *
 * @package Interview_Finder
 * @var array $items Search result items
 * @var string $search_type Search type
 * @var array $options Display options
 * @var Interview_Finder_Template_Loader $loader Template loader
 * @var array $ranking_details Optional ranking details from Taddy API
 * @var array $response_details Optional response details (total results, pages)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$ranking_details = $ranking_details ?? $options['ranking_details'] ?? [];
$response_details = $response_details ?? $options['response_details'] ?? [];
$total_results = $response_details['totalResults'] ?? count( $items );
$total_pages = $response_details['totalPages'] ?? 1;
$current_page = $response_details['currentPage'] ?? 1;
?>
<div class="if-results if-results--advanced if-results--podcasts" role="region" aria-label="<?php esc_attr_e( 'Podcast Results', 'interview-finder' ); ?>">
    <p class="if-results__count">
        <?php
        printf(
            /* translators: %d: number of results */
            esc_html( _n( '%d podcast found', '%d podcasts found', count( $items ), 'interview-finder' ) ),
            count( $items )
        );
        ?>
    </p>

    <form id="if-results-form" class="if-results__form">
        <div class="if-results__actions">
            <button type="button" class="if-btn if-btn--select-all" id="if-select-all">
                <?php esc_html_e( 'Select All', 'interview-finder' ); ?>
            </button>
            <button type="submit" class="if-btn if-btn--primary" id="if-import-selected" disabled>
                <?php esc_html_e( 'Import Selected', 'interview-finder' ); ?>
            </button>
        </div>

        <ul class="if-results__list if-results__list--podcasts" role="list">
            <?php foreach ( $items as $index => $item ) : ?>
                <?php
                $loader->partial( 'result-item-podcast', [
                    'item'            => $item,
                    'index'           => $index,
                    'ranking_details' => $ranking_details,
                ] );
                ?>
            <?php endforeach; ?>
        </ul>

        <div class="if-results__actions if-results__actions--bottom">
            <button type="submit" class="if-btn if-btn--primary" id="if-import-selected-bottom" disabled>
                <?php esc_html_e( 'Import Selected', 'interview-finder' ); ?>
            </button>
        </div>
    </form>
</div>
