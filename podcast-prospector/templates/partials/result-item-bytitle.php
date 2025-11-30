<?php
/**
 * Partial: By Title Result Item
 *
 * @package Podcast_Prospector
 * @var array $item Result item
 * @var int $index Item index
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$title = $item['title'] ?? '';
$description = $item['description'] ?? '';
$author = $item['author'] ?? '';
$image = $item['image'] ?? $item['artwork'] ?? '';
$url = $item['url'] ?? $item['link'] ?? '';
$item_id = $item['id'] ?? $index;
?>
<li class="if-result-item if-result-item--podcast" data-index="<?php echo esc_attr( $index ); ?>">
    <div class="if-result-item__checkbox">
        <input
            type="checkbox"
            id="if-item-<?php echo esc_attr( $item_id ); ?>"
            name="podcasts[]"
            value="<?php echo esc_attr( wp_json_encode( $item ) ); ?>"
            class="if-checkbox"
            aria-describedby="if-item-desc-<?php echo esc_attr( $item_id ); ?>"
        >
        <label for="if-item-<?php echo esc_attr( $item_id ); ?>" class="screen-reader-text">
            <?php echo esc_html( $title ); ?>
        </label>
    </div>

    <div class="if-result-item__image">
        <?php if ( $image ) : ?>
            <img
                src="<?php echo esc_url( $image ); ?>"
                alt=""
                loading="lazy"
                width="80"
                height="80"
            >
        <?php else : ?>
            <div class="if-result-item__placeholder" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 18V5l12-2v13"></path>
                    <circle cx="6" cy="18" r="3"></circle>
                    <circle cx="18" cy="16" r="3"></circle>
                </svg>
            </div>
        <?php endif; ?>
    </div>

    <div class="if-result-item__content" id="if-item-desc-<?php echo esc_attr( $item_id ); ?>">
        <h3 class="if-result-item__title">
            <?php if ( $url ) : ?>
                <a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer">
                    <?php echo esc_html( $title ); ?>
                    <span class="screen-reader-text"><?php esc_html_e( '(opens in new tab)', 'interview-finder' ); ?></span>
                </a>
            <?php else : ?>
                <?php echo esc_html( $title ); ?>
            <?php endif; ?>
        </h3>

        <?php if ( $author ) : ?>
            <p class="if-result-item__author"><?php echo esc_html( $author ); ?></p>
        <?php endif; ?>

        <?php if ( $description ) : ?>
            <p class="if-result-item__description">
                <?php echo esc_html( wp_trim_words( wp_strip_all_tags( $description ), 30 ) ); ?>
            </p>
        <?php endif; ?>
    </div>
</li>
