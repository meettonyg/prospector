<?php
/**
 * Template: Advanced Episode Search Results
 *
 * @package Podcast_Prospector
 * @var array $items Search result items
 * @var string $search_type Search type
 * @var array $options Display options
 * @var Podcast_Prospector_Template_Loader $loader Template loader
 * @var array $ranking_details Optional ranking details from Taddy API
 * @var array $response_details Optional response details (total results, pages)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$ranking_details = $ranking_details ?? $options['ranking_details'] ?? [];
$response_details = $response_details ?? $options['response_details'] ?? [];
$location_data = $location_data ?? $options['location_data'] ?? [];
$total_results = $response_details['totalResults'] ?? count( $items );
$total_pages = $response_details['totalPages'] ?? 1;
$current_page = $response_details['currentPage'] ?? 1;
?>
<div class="if-results if-results--advanced if-results--episodes" role="region" aria-label="<?php esc_attr_e( 'Episode Results', 'podcast-prospector' ); ?>">
    <p class="if-results__count">
        <?php
        printf(
            /* translators: %d: number of results */
            esc_html( _n( '%d episode found', '%d episodes found', count( $items ), 'podcast-prospector' ) ),
            count( $items )
        );
        ?>
    </p>

    <form id="if-results-form" class="if-results__form">
        <div class="if-results__actions">
            <button type="button" class="if-btn if-btn--select-all" id="if-select-all">
                <?php esc_html_e( 'Select All', 'podcast-prospector' ); ?>
            </button>
            <button type="submit" class="if-btn if-btn--primary" id="if-import-selected" disabled>
                <?php esc_html_e( 'Import Selected', 'podcast-prospector' ); ?>
            </button>
        </div>

        <ul class="if-results__list if-results__list--episodes" role="list">
            <?php foreach ( $items as $index => $item ) : ?>
                <?php
                $loader->partial( 'result-item-episode', [
                    'item'            => $item,
                    'index'           => $index,
                    'ranking_details' => $ranking_details,
                    'location_data'   => $location_data,
                    'loader'          => $loader,
                ] );
                ?>
            <?php endforeach; ?>
        </ul>

        <div class="if-results__actions if-results__actions--bottom">
            <button type="submit" class="if-btn if-btn--primary" id="if-import-selected-bottom" disabled>
                <?php esc_html_e( 'Import Selected', 'podcast-prospector' ); ?>
            </button>
        </div>
    </form>
</div>
