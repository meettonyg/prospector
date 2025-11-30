<?php
/**
 * Template: YouTube Video Search Results
 *
 * @package Interview_Finder
 * @var array $items Search result items
 * @var string $search_type Search type
 * @var array $options Display options
 * @var Interview_Finder_Template_Loader $loader Template loader
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$total_results = $options['totalResults'] ?? count( $items );
$next_page_token = $options['nextPageToken'] ?? null;
$prev_page_token = $options['prevPageToken'] ?? null;
?>
<div class="if-results if-results--youtube" role="region" aria-label="<?php esc_attr_e( 'YouTube Video Results', 'interview-finder' ); ?>">
    <p class="if-results__count">
        <?php
        printf(
            /* translators: %d: number of results */
            esc_html( _n( '%d video found', '%d videos found', count( $items ), 'interview-finder' ) ),
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

        <ul class="if-results__list if-results__list--youtube" role="list">
            <?php foreach ( $items as $index => $item ) : ?>
                <?php
                $loader->partial( 'result-item-youtube', [
                    'item'   => $item,
                    'index'  => $index,
                    'loader' => $loader,
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
