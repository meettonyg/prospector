<?php
/**
 * Partial: YouTube Video Result Item
 *
 * @package Podcast_Prospector
 * @var array $item Result item
 * @var int $index Item index
 * @var Podcast_Prospector_Template_Loader $loader Template loader
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$video_id = $item['id'] ?? '';
$title = $item['title'] ?? '';
$description = $item['description'] ?? '';
$channel_title = $item['channelTitle'] ?? '';
$channel_id = $item['channelId'] ?? '';
$thumbnail_url = $item['thumbnailUrl'] ?? '';
$video_url = $item['videoUrl'] ?? '';
$channel_url = $item['channelUrl'] ?? '';
$published_at = $item['publishedAt'] ?? '';
$view_count = $item['viewCount'] ?? null;
$like_count = $item['likeCount'] ?? null;
$duration = $item['duration'] ?? null;
$duration_formatted = $item['durationFormatted'] ?? '';
$is_duplicate = $item['is_duplicate'] ?? false;
?>
<li class="if-result-item if-result-item--youtube<?php echo $is_duplicate ? ' if-result-item--duplicate' : ''; ?>" data-index="<?php echo esc_attr( $index ); ?>" data-channel-id="<?php echo esc_attr( $channel_id ); ?>" data-is-duplicate="<?php echo $is_duplicate ? 'true' : 'false'; ?>">
    <div class="if-result-item__checkbox">
        <input
            type="checkbox"
            id="if-item-<?php echo esc_attr( $video_id ); ?>"
            name="podcasts[]"
            value="<?php echo esc_attr( wp_json_encode( $item ) ); ?>"
            class="if-checkbox"
            aria-describedby="if-item-desc-<?php echo esc_attr( $video_id ); ?>"
        >
        <label for="if-item-<?php echo esc_attr( $video_id ); ?>" class="screen-reader-text">
            <?php echo esc_html( $title ); ?>
        </label>
    </div>

    <div class="if-result-item__image if-result-item__image--video">
        <?php if ( $thumbnail_url ) : ?>
            <a href="<?php echo esc_url( $video_url ); ?>" target="_blank" rel="noopener noreferrer" class="if-thumbnail-link">
                <img
                    src="<?php echo esc_url( $thumbnail_url ); ?>"
                    alt=""
                    loading="lazy"
                    width="120"
                    height="68"
                >
                <?php if ( $duration_formatted ) : ?>
                    <span class="if-duration-badge"><?php echo esc_html( $duration_formatted ); ?></span>
                <?php endif; ?>
            </a>
        <?php else : ?>
            <div class="if-result-item__placeholder" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polygon points="5 3 19 12 5 21 5 3"></polygon>
                </svg>
            </div>
        <?php endif; ?>
    </div>

    <div class="if-result-item__content" id="if-item-desc-<?php echo esc_attr( $video_id ); ?>">
        <h3 class="if-result-item__title">
            <a href="<?php echo esc_url( $video_url ); ?>" target="_blank" rel="noopener noreferrer">
                <?php echo esc_html( $title ); ?>
                <span class="screen-reader-text"><?php esc_html_e( '(opens in new tab)', 'interview-finder' ); ?></span>
            </a>
        </h3>

        <?php if ( $channel_title ) : ?>
            <p class="if-result-item__channel">
                <a href="<?php echo esc_url( $channel_url ); ?>" target="_blank" rel="noopener noreferrer">
                    <?php echo esc_html( $channel_title ); ?>
                </a>
            </p>
        <?php endif; ?>

        <?php if ( $published_at ) : ?>
            <time class="if-result-item__date" datetime="<?php echo esc_attr( $published_at ); ?>">
                <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $published_at ) ) ); ?>
            </time>
        <?php endif; ?>

        <?php if ( $description ) : ?>
            <p class="if-result-item__description">
                <?php echo esc_html( wp_trim_words( wp_strip_all_tags( $description ), 30 ) ); ?>
            </p>
        <?php endif; ?>

        <div class="if-result-item__meta if-result-item__meta--youtube">
            <?php if ( null !== $view_count ) : ?>
                <span class="if-result-item__views" title="<?php esc_attr_e( 'Views', 'interview-finder' ); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                    <span><?php echo esc_html( number_format( $view_count ) ); ?></span>
                </span>
            <?php endif; ?>

            <?php if ( null !== $like_count ) : ?>
                <span class="if-result-item__likes" title="<?php esc_attr_e( 'Likes', 'interview-finder' ); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path>
                    </svg>
                    <span><?php echo esc_html( number_format( $like_count ) ); ?></span>
                </span>
            <?php endif; ?>

            <?php if ( $duration_formatted ) : ?>
                <span class="if-result-item__duration" title="<?php esc_attr_e( 'Duration', 'interview-finder' ); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                    <span><?php echo esc_html( $duration_formatted ); ?></span>
                </span>
            <?php endif; ?>

            <span class="if-result-item__source if-result-item__source--youtube" title="<?php esc_attr_e( 'YouTube', 'interview-finder' ); ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                </svg>
            </span>

            <?php if ( $is_duplicate ) : ?>
                <span class="if-result-item__duplicate-badge" title="<?php esc_attr_e( 'Channel already in database', 'interview-finder' ); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    <span><?php esc_html_e( 'Existing', 'interview-finder' ); ?></span>
                </span>
            <?php endif; ?>
        </div>
    </div>
</li>
