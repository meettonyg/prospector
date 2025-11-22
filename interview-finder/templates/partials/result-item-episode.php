<?php
/**
 * Partial: Episode Result Item (Taddy API)
 *
 * @package Interview_Finder
 * @var array $item Result item
 * @var int $index Item index
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$name = $item['name'] ?? '';
$description = $item['description'] ?? '';
$date_published = $item['datePublished'] ?? '';
$audio_url = $item['audioUrl'] ?? '';
$podcast_series = $item['podcastSeries'] ?? [];
$podcast_name = $podcast_series['name'] ?? '';
$podcast_image = $podcast_series['imageUrl'] ?? '';
$item_id = $item['uuid'] ?? $index;
?>
<li class="if-result-item if-result-item--episode" data-index="<?php echo esc_attr( $index ); ?>">
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
            <?php echo esc_html( $name ); ?>
        </label>
    </div>

    <div class="if-result-item__image">
        <?php if ( $podcast_image ) : ?>
            <img
                src="<?php echo esc_url( $podcast_image ); ?>"
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
            <?php if ( $audio_url ) : ?>
                <a href="<?php echo esc_url( $audio_url ); ?>" target="_blank" rel="noopener noreferrer">
                    <?php echo esc_html( $name ); ?>
                    <span class="screen-reader-text"><?php esc_html_e( '(opens in new tab)', 'interview-finder' ); ?></span>
                </a>
            <?php else : ?>
                <?php echo esc_html( $name ); ?>
            <?php endif; ?>
        </h3>

        <?php if ( $podcast_name ) : ?>
            <p class="if-result-item__podcast"><?php echo esc_html( $podcast_name ); ?></p>
        <?php endif; ?>

        <?php if ( $date_published ) : ?>
            <time class="if-result-item__date" datetime="<?php echo esc_attr( $date_published ); ?>">
                <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $date_published ) ) ); ?>
            </time>
        <?php endif; ?>

        <?php if ( $description ) : ?>
            <p class="if-result-item__description">
                <?php echo esc_html( wp_trim_words( wp_strip_all_tags( $description ), 30 ) ); ?>
            </p>
        <?php endif; ?>
    </div>
</li>
