<?php
/**
 * Partial: Sponsored Podcast Result Item
 *
 * Template for displaying a sponsored podcast listing in search results.
 *
 * @package Podcast_Prospector
 * @since 2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$sponsored_id = $item['sponsored_id'] ?? 0;
$uuid = $item['uuid'] ?? '';
$name = $item['name'] ?? $item['title'] ?? '';
$description = $item['description'] ?? '';
$image_url = $item['imageUrl'] ?? '';
$itunes_id = $item['itunesId'] ?? '';
$website_url = $item['websiteUrl'] ?? '';
$rss_url = $item['rssUrl'] ?? '';
$categories = $item['categories'] ?? [];
?>
<li class="if-result-item if-result-item--sponsored" data-index="<?php echo esc_attr( $index ); ?>" data-sponsored-id="<?php echo esc_attr( $sponsored_id ); ?>">
    <div class="if-result-item__checkbox">
        <input
            type="checkbox"
            id="if-item-sponsored-<?php echo esc_attr( $sponsored_id ); ?>"
            class="if-result-item__input"
            data-type="podcast"
            data-podcast='<?php echo esc_attr( wp_json_encode( [
                'uuid'        => $uuid,
                'name'        => $name,
                'description' => $description,
                'imageUrl'    => $image_url,
                'itunesId'    => $itunes_id,
                'websiteUrl'  => $website_url,
                'rssUrl'      => $rss_url,
                'is_sponsored' => true,
                'sponsored_id' => $sponsored_id,
            ] ) ); ?>'
        >
        <label for="if-item-sponsored-<?php echo esc_attr( $sponsored_id ); ?>" class="screen-reader-text">
            <?php
            /* translators: %s: podcast name */
            printf( esc_html__( 'Select %s', 'interview-finder' ), esc_html( $name ) );
            ?>
        </label>
    </div>

    <div class="if-result-item__content">
        <?php if ( $image_url ) : ?>
            <div class="if-result-item__image if-result-item__image--sponsored">
                <img
                    src="<?php echo esc_url( $image_url ); ?>"
                    alt="<?php echo esc_attr( $name ); ?>"
                    loading="lazy"
                    width="80"
                    height="80"
                >
            </div>
        <?php endif; ?>

        <div class="if-result-item__details">
            <div class="if-result-item__header">
                <span class="if-result-item__sponsored-badge" title="<?php esc_attr_e( 'Sponsored', 'interview-finder' ); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                    </svg>
                    <span><?php esc_html_e( 'Sponsored', 'interview-finder' ); ?></span>
                </span>
            </div>

            <h3 class="if-result-item__title if-result-item__title--sponsored">
                <?php if ( $website_url ) : ?>
                    <a
                        href="<?php echo esc_url( $website_url ); ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="if-sponsored-link"
                        data-sponsored-id="<?php echo esc_attr( $sponsored_id ); ?>"
                    >
                        <?php echo esc_html( $name ); ?>
                    </a>
                <?php else : ?>
                    <?php echo esc_html( $name ); ?>
                <?php endif; ?>
            </h3>

            <?php if ( $description ) : ?>
                <p class="if-result-item__description">
                    <?php echo esc_html( wp_trim_words( $description, 30, '...' ) ); ?>
                </p>
            <?php endif; ?>

            <?php if ( ! empty( $categories ) ) : ?>
                <div class="if-result-item__categories">
                    <?php foreach ( array_slice( $categories, 0, 3 ) as $category ) : ?>
                        <span class="if-result-item__category"><?php echo esc_html( $category ); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</li>
