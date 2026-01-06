<?php
/**
 * Prospector Home Widget Shortcode
 *
 * Renders a compact widget for the Guestify Home Dashboard showing
 * quick search functionality and recent searches.
 *
 * Usage: [prospector_home_widget]
 *
 * @package Podcast_Prospector
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Prospector_Home_Widget_Shortcode
 */
class Prospector_Home_Widget_Shortcode {

    /**
     * Initialize the shortcode
     */
    public static function init() {
        add_shortcode('prospector_home_widget', [__CLASS__, 'render']);
    }

    /**
     * Render the home widget
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public static function render($atts) {
        // Parse attributes
        $atts = shortcode_atts([
            'show_search' => 'true',
            'show_recent' => 'true',
            'compact' => 'false',
        ], $atts, 'prospector_home_widget');

        // Check permissions
        if (!is_user_logged_in()) {
            return '<div class="psp-home-widget psp-home-widget--empty">Please log in to search for podcasts</div>';
        }

        $user_id = get_current_user_id();
        $widget_data = self::get_widget_data($user_id);

        // Build classes
        $classes = ['psp-home-widget'];
        if ($atts['compact'] === 'true') {
            $classes[] = 'psp-home-widget--compact';
        }

        ob_start();
        ?>
        <div class="<?php echo esc_attr(implode(' ', $classes)); ?>">
            <?php if ($atts['show_search'] === 'true'): ?>
                <div class="psp-home-widget__search">
                    <form action="<?php echo esc_url(home_url('/app/prospector/')); ?>" method="get" class="psp-home-widget__form">
                        <div class="psp-home-widget__input-wrapper">
                            <i class="fa-solid fa-magnifying-glass psp-home-widget__search-icon"></i>
                            <input type="text"
                                   name="q"
                                   placeholder="Search podcasts by topic..."
                                   class="psp-home-widget__input"
                                   autocomplete="off">
                        </div>
                        <button type="submit" class="psp-home-widget__btn psp-home-widget__btn--primary">
                            Search
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <div class="psp-home-widget__stats">
                <div class="psp-home-widget__stat">
                    <span class="psp-home-widget__stat-value"><?php echo esc_html($widget_data['saved_count']); ?></span>
                    <span class="psp-home-widget__stat-label">Saved Shows</span>
                </div>
                <div class="psp-home-widget__stat">
                    <span class="psp-home-widget__stat-value"><?php echo esc_html($widget_data['searches_remaining']); ?></span>
                    <span class="psp-home-widget__stat-label">Searches Left</span>
                </div>
            </div>

            <?php if ($atts['show_recent'] === 'true' && !empty($widget_data['recent_searches'])): ?>
                <div class="psp-home-widget__recent">
                    <span class="psp-home-widget__recent-label">Recent:</span>
                    <div class="psp-home-widget__recent-tags">
                        <?php foreach ($widget_data['recent_searches'] as $search): ?>
                            <a href="<?php echo esc_url(home_url('/app/prospector/?q=' . urlencode($search))); ?>"
                               class="psp-home-widget__tag">
                                <?php echo esc_html($search); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="psp-home-widget__actions">
                <a href="<?php echo esc_url(home_url('/app/prospector/')); ?>" class="psp-home-widget__link">
                    Open Prospector
                    <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>
        </div>

        <style>
            .psp-home-widget {
                background: var(--gfy-surface, #fff);
                border-radius: var(--gfy-radius-lg, 12px);
                padding: var(--gfy-spacing-lg, 20px);
                border: 1px solid var(--gfy-border, #e2e8f0);
            }
            .psp-home-widget__search {
                margin-bottom: var(--gfy-spacing-md, 12px);
            }
            .psp-home-widget__form {
                display: flex;
                gap: var(--gfy-spacing-sm, 8px);
            }
            .psp-home-widget__input-wrapper {
                position: relative;
                flex: 1;
            }
            .psp-home-widget__search-icon {
                position: absolute;
                left: 12px;
                top: 50%;
                transform: translateY(-50%);
                color: var(--gfy-text-tertiary, #94a3b8);
                font-size: 14px;
                pointer-events: none;
            }
            .psp-home-widget__input {
                width: 100%;
                height: 40px;
                padding: 0 12px 0 36px;
                border: 1px solid var(--gfy-border, #e2e8f0);
                border-radius: var(--gfy-radius-md, 8px);
                font-size: 14px;
                background: var(--gfy-bg-subtle, #f8fafc);
                transition: all 0.15s ease;
            }
            .psp-home-widget__input:focus {
                outline: none;
                border-color: var(--gfy-primary, #0ea5e9);
                background: var(--gfy-surface, #fff);
                box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
            }
            .psp-home-widget__input::placeholder {
                color: var(--gfy-text-tertiary, #94a3b8);
            }
            .psp-home-widget__btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 0 16px;
                height: 40px;
                font-size: 14px;
                font-weight: 500;
                border: none;
                border-radius: var(--gfy-radius-md, 8px);
                cursor: pointer;
                transition: all 0.15s ease;
            }
            .psp-home-widget__btn--primary {
                background: var(--gfy-primary, #0ea5e9);
                color: white;
            }
            .psp-home-widget__btn--primary:hover {
                background: var(--gfy-primary-hover, #0284c7);
            }
            .psp-home-widget__stats {
                display: flex;
                gap: var(--gfy-spacing-lg, 20px);
                margin-bottom: var(--gfy-spacing-md, 12px);
                padding: var(--gfy-spacing-md, 12px) 0;
                border-top: 1px solid var(--gfy-border-subtle, #f1f5f9);
                border-bottom: 1px solid var(--gfy-border-subtle, #f1f5f9);
            }
            .psp-home-widget__stat {
                display: flex;
                flex-direction: column;
            }
            .psp-home-widget__stat-value {
                font-size: 20px;
                font-weight: 600;
                color: var(--gfy-text-primary, #1e293b);
                line-height: 1.2;
            }
            .psp-home-widget__stat-label {
                font-size: 12px;
                color: var(--gfy-text-secondary, #64748b);
            }
            .psp-home-widget__recent {
                display: flex;
                align-items: flex-start;
                gap: var(--gfy-spacing-sm, 8px);
                margin-bottom: var(--gfy-spacing-md, 12px);
            }
            .psp-home-widget__recent-label {
                font-size: 12px;
                color: var(--gfy-text-secondary, #64748b);
                flex-shrink: 0;
                padding-top: 4px;
            }
            .psp-home-widget__recent-tags {
                display: flex;
                flex-wrap: wrap;
                gap: 6px;
            }
            .psp-home-widget__tag {
                display: inline-flex;
                padding: 4px 10px;
                background: var(--gfy-bg-subtle, #f1f5f9);
                border-radius: var(--gfy-radius-full, 9999px);
                font-size: 12px;
                color: var(--gfy-text-secondary, #64748b);
                text-decoration: none;
                transition: all 0.15s ease;
            }
            .psp-home-widget__tag:hover {
                background: var(--gfy-primary-light, #e0f2fe);
                color: var(--gfy-primary, #0ea5e9);
            }
            .psp-home-widget__actions {
                text-align: right;
            }
            .psp-home-widget__link {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                font-size: 13px;
                font-weight: 500;
                color: var(--gfy-primary, #0ea5e9);
                text-decoration: none;
                transition: all 0.15s ease;
            }
            .psp-home-widget__link:hover {
                color: var(--gfy-primary-hover, #0284c7);
            }
            .psp-home-widget__link i {
                font-size: 11px;
                transition: transform 0.15s ease;
            }
            .psp-home-widget__link:hover i {
                transform: translateX(2px);
            }
            .psp-home-widget--compact {
                padding: var(--gfy-spacing-md, 12px);
            }
            .psp-home-widget--compact .psp-home-widget__stats {
                gap: var(--gfy-spacing-md, 12px);
            }
            .psp-home-widget--compact .psp-home-widget__stat-value {
                font-size: 16px;
            }
        </style>
        <?php
        return ob_get_clean();
    }

    /**
     * Get widget data for user
     *
     * @param int $user_id
     * @return array
     */
    private static function get_widget_data($user_id) {
        global $wpdb;

        $data = [
            'saved_count' => 0,
            'searches_remaining' => 0,
            'recent_searches' => [],
        ];

        // Get saved podcasts count
        $podcasts_table = $wpdb->prefix . 'pit_podcasts';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $podcasts_table)) === $podcasts_table) {
            $data['saved_count'] = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$podcasts_table} WHERE user_id = %d",
                $user_id
            ));
        }

        // Get searches remaining
        if (class_exists('Podcast_Prospector_Membership')) {
            $membership = new Podcast_Prospector_Membership();
            $data['searches_remaining'] = $membership->get_searches_remaining($user_id);
        } else {
            // Fallback: check user meta
            $searches_used = (int) get_user_meta($user_id, 'prospector_searches_this_month', true);
            $limit = 50; // Default limit
            $data['searches_remaining'] = max(0, $limit - $searches_used);
        }

        // Get recent searches
        $searches_table = $wpdb->prefix . 'podcast_prospector_searches';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $searches_table)) === $searches_table) {
            $recent = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT search_term FROM {$searches_table}
                 WHERE user_id = %d AND search_term != ''
                 ORDER BY created_at DESC LIMIT 3",
                $user_id
            ));
            $data['recent_searches'] = $recent ?: [];
        }

        return $data;
    }
}

// Initialize the shortcode
add_action('init', ['Prospector_Home_Widget_Shortcode', 'init']);
