<?php
/**
 * Template: By Person Search Results
 *
 * @package Podcast_Prospector
 * @var array $items Search result items
 * @var string $search_type Search type
 * @var array $options Display options
 * @var Podcast_Prospector_Template_Loader $loader Template loader
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="if-results if-results--byperson" role="region" aria-label="<?php esc_attr_e( 'Search Results', 'podcast-prospector' ); ?>">
    <p class="if-results__count">
        <?php
        printf(
            /* translators: %d: number of results */
            esc_html( _n( '%d result found', '%d results found', count( $items ), 'podcast-prospector' ) ),
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

        <ul class="if-results__list" role="list">
            <?php foreach ( $items as $index => $item ) : ?>
                <?php
                $loader->partial( 'result-item-byperson', [
                    'item'  => $item,
                    'index' => $index,
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
