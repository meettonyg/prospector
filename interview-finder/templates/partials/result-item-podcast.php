<?php
/**
 * Partial: Podcast Result Item (Taddy API)
 *
 * @package Interview_Finder
 * @var array $item Result item
 * @var int $index Item index
 * @var array $ranking_details Optional ranking details array
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$name = $item['name'] ?? '';
$description = $item['description'] ?? '';
$author = $item['authorName'] ?? '';
$image_url = $item['imageUrl'] ?? '';
$rss_url = $item['rssUrl'] ?? '';
$website_url = $item['websiteUrl'] ?? '';
$genres = $item['genres'] ?? [];
$total_episodes = $item['totalEpisodesCount'] ?? 0;
$item_id = $item['uuid'] ?? $index;

// Get ranking score if available
$ranking_score = null;
if ( ! empty( $ranking_details ) && is_array( $ranking_details ) ) {
    foreach ( $ranking_details as $ranking ) {
        if ( ( $ranking['uuid'] ?? '' ) === $item_id ) {
            $ranking_score = $ranking['rankingScore'] ?? null;
            break;
        }
    }
}
?>
<li class="if-result-item if-result-item--podcast-series" data-index="<?php echo esc_attr( $index ); ?>">
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
        <?php if ( $image_url ) : ?>
            <img
                src="<?php echo esc_url( $image_url ); ?>"
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
            <?php if ( $website_url ) : ?>
                <a href="<?php echo esc_url( $website_url ); ?>" target="_blank" rel="noopener noreferrer">
                    <?php echo esc_html( $name ); ?>
                    <span class="screen-reader-text"><?php esc_html_e( '(opens in new tab)', 'interview-finder' ); ?></span>
                </a>
            <?php else : ?>
                <?php echo esc_html( $name ); ?>
            <?php endif; ?>
        </h3>

        <?php if ( $author ) : ?>
            <p class="if-result-item__author"><?php echo esc_html( $author ); ?></p>
        <?php endif; ?>

        <?php if ( ! empty( $genres ) ) : ?>
            <p class="if-result-item__genres">
                <?php
                $genre_names = array_map( function( $genre ) {
                    return $genre['name'] ?? '';
                }, array_slice( $genres, 0, 3 ) );
                echo esc_html( implode( ', ', array_filter( $genre_names ) ) );
                ?>
            </p>
        <?php endif; ?>

        <?php if ( $description ) : ?>
            <p class="if-result-item__description">
                <?php echo esc_html( wp_trim_words( wp_strip_all_tags( $description ), 30 ) ); ?>
            </p>
        <?php endif; ?>

        <div class="if-result-item__meta">
            <?php if ( $total_episodes ) : ?>
                <span class="if-result-item__episodes" title="<?php esc_attr_e( 'Total Episodes', 'interview-finder' ); ?>">
                    <?php
                    printf(
                        /* translators: %d: number of episodes */
                        esc_html( _n( '%d episode', '%d episodes', $total_episodes, 'interview-finder' ) ),
                        $total_episodes
                    );
                    ?>
                </span>
            <?php endif; ?>

            <?php if ( null !== $ranking_score ) : ?>
                <span class="if-result-item__ranking" title="<?php esc_attr_e( 'Relevance Score', 'interview-finder' ); ?>">
                    <span class="if-ranking-label"><?php esc_html_e( 'Score:', 'interview-finder' ); ?></span>
                    <span class="if-ranking-value"><?php echo esc_html( number_format( $ranking_score, 1 ) ); ?></span>
                </span>
            <?php endif; ?>
        </div>
    </div>
</li>
