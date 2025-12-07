<?php
/**
 * Vue Assets Loader
 *
 * Handles enqueuing Vue app assets for FRONT-END pages with HMR support for development.
 *
 * @package Podcast_Prospector
 * @since 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Podcast_Prospector_Vue_Assets {

    /**
     * Singleton instance
     */
    private static ?Podcast_Prospector_Vue_Assets $instance = null;

    /**
     * Settings instance
     */
    private ?Podcast_Prospector_Settings $settings = null;

    /**
     * Whether Vue assets have been enqueued
     */
    private bool $enqueued = false;

    /**
     * Get singleton instance
     */
    public static function get_instance(): Podcast_Prospector_Vue_Assets {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        if (class_exists('Podcast_Prospector_Settings')) {
            $this->settings = Podcast_Prospector_Settings::get_instance();
        }

        // Hook into wp_enqueue_scripts to load assets early (before head closes)
        add_action('wp_enqueue_scripts', [$this, 'maybe_enqueue_assets']);
    }

    /**
     * Conditionally enqueue assets on pages with our shortcode
     */
    public function maybe_enqueue_assets(): void {
        // Check if this is a prospector page
        if ($this->settings && method_exists($this->settings, 'is_search_page')) {
            if ($this->settings->is_search_page()) {
                $this->enqueue_vue_app();
            }
            return;
        }

        // Fallback: check for shortcode in current post
        global $post;
        if ($post instanceof WP_Post && has_shortcode($post->post_content, 'podcast_prospector')) {
            $this->enqueue_vue_app();
        }
    }

    /**
     * Enqueue Vue app assets
     */
    public function enqueue_vue_app(): void {
        if ($this->enqueued) {
            return;
        }

        $vue_dir = PODCAST_PROSPECTOR_PLUGIN_DIR . 'assets/vue/';
        $dist_dir = $vue_dir . 'dist/';
        $manifest_path = $dist_dir . 'manifest.json';

        // Development mode: Load from Vite dev server
        if (defined('PROSPECTOR_DEV_MODE') && PROSPECTOR_DEV_MODE) {
            $this->enqueue_dev_assets();
            $this->enqueued = true;
            return;
        }

        // Production: Load from built manifest
        if (!file_exists($manifest_path)) {
            if (current_user_can('manage_options')) {
                add_action('wp_footer', [$this, 'show_build_notice_frontend']);
            }
            return;
        }

        $this->enqueue_production_assets($manifest_path);
        $this->enqueued = true;
    }

    /**
     * Enqueue development assets (Vite HMR)
     */
    private function enqueue_dev_assets(): void {
        $dev_server = apply_filters('prospector_vite_dev_server', 'http://localhost:5173');

        wp_enqueue_script(
            'vite-client',
            $dev_server . '/@vite/client',
            [],
            null,
            false
        );

        wp_enqueue_script(
            'prospector-vue-app',
            $dev_server . '/src/main.js',
            [],
            null,
            true
        );

        add_filter('script_loader_tag', [$this, 'add_module_type'], 10, 3);
    }

    /**
     * Enqueue production assets from Vite manifest
     */
    private function enqueue_production_assets(string $manifest_path): void {
        $manifest = json_decode(file_get_contents($manifest_path), true);
        $entry = $manifest['src/main.js'] ?? null;

        if (!$entry) {
            return;
        }

        $dist_url = PODCAST_PROSPECTOR_PLUGIN_URL . 'assets/vue/dist/';

        // Enqueue CSS files
        if (!empty($entry['css'])) {
            foreach ($entry['css'] as $index => $css_file) {
                wp_enqueue_style(
                    'prospector-vue-css-' . $index,
                    $dist_url . $css_file,
                    [],
                    null
                );
            }
        }

        // Enqueue main JS
        wp_enqueue_script(
            'prospector-vue-app',
            $dist_url . $entry['file'],
            [],
            null,
            true
        );

        add_filter('script_loader_tag', [$this, 'add_module_type'], 10, 3);
    }

    /**
     * Add type="module" to script tags
     */
    public function add_module_type(string $tag, string $handle, string $src): string {
        if ($handle === 'prospector-vue-app' || $handle === 'vite-client') {
            // Return proper module script tag with crossorigin for ES modules
            return '<script type="module" crossorigin src="' . esc_url($src) . '"></script>' . "\n";
        }
        return $tag;
    }

    /**
     * Get current user's membership data
     */
    private function get_membership_data(int $user_id): array {
        if (!class_exists('Podcast_Prospector_Membership')) {
            return [
                'level' => 'free',
                'searchesRemaining' => 10,
                'searchCap' => 10,
            ];
        }

        $membership = Podcast_Prospector_Membership::get_instance();
        $database = Podcast_Prospector_Database::get_instance();

        $ghl_id = $membership->get_ghl_id($user_id);
        $search_cap = $membership->get_search_cap($user_id);
        $user_data = $database->get_user_data($ghl_id, $user_id);
        $search_count = $user_data ? (int) $user_data->search_count : 0;

        return [
            'level' => $membership->get_user_membership_level($user_id),
            'searchesRemaining' => max(0, $search_cap - $search_count),
            'searchCap' => $search_cap,
            'settings' => $membership->get_user_settings($user_id),
        ];
    }

    /**
     * Show build notice in footer (for admins only)
     */
    public function show_build_notice_frontend(): void {
        echo '<!-- Podcast Prospector: Vue app build not found. Run `npm run build` in assets/vue/ -->';
    }

    /**
     * Render Vue app container
     *
     * @return string HTML for Vue app container
     */
    public function render_vue_container(): string {
        $this->enqueue_vue_app();

        // Build config for inline script (ensures it's available before module loads)
        $user_id = get_current_user_id();
        $membership_data = $this->get_membership_data($user_id);

        $config = [
            'apiBase' => rest_url('podcast-prospector/v1'),
            'nonce' => wp_create_nonce('wp_rest'),
            'userId' => $user_id,
            'guestIntelActive' => class_exists('PIT_Podcast_Repository'),
            'membership' => $membership_data,
            'features' => $this->get_feature_flags(),
            'i18n' => [
                'searchPlaceholder' => __('Search for podcasts...', 'podcast-prospector'),
                'importSuccess' => __('Added to pipeline!', 'podcast-prospector'),
                'importError' => __('Import failed', 'podcast-prospector'),
                'alreadyTracked' => __('In Pipeline', 'podcast-prospector'),
                'noResults' => __('No results found', 'podcast-prospector'),
                'loading' => __('Loading...', 'podcast-prospector'),
                'searchesRemaining' => __('searches remaining', 'podcast-prospector'),
                'loginRequired' => __('Please log in to use the Interview Finder.', 'podcast-prospector'),
            ]
        ];

        $config_script = '<script>window.PROSPECTOR_CONFIG = ' . wp_json_encode($config) . ';</script>';

        return $config_script . '
        <div id="prospector-app" class="prospector-vue-app">
            <div class="prospector-loading" style="padding: 40px; text-align: center;">
                <p>' . esc_html__('Loading Podcast Prospector...', 'podcast-prospector') . '</p>
            </div>
        </div>';
    }

    /**
     * Check if Vue assets are enqueued
     */
    public function is_enqueued(): bool {
        return $this->enqueued;
    }

    /**
     * Get feature flags from settings
     *
     * @return array Feature flags for Vue app
     */
    private function get_feature_flags(): array {
        // Use settings class if available
        if ($this->settings) {
            $chatgpt_enabled = (bool) $this->settings->get('chatgpt_enabled', false);
            $chat_enabled = (bool) $this->settings->get('chat_enabled', false);

            return [
                // Chat is enabled if either chat_enabled OR chatgpt_enabled is on
                'chat'    => $chat_enabled || $chatgpt_enabled,
                'youtube' => (bool) $this->settings->get('youtube_features_enabled', true),
                'summits' => (bool) get_option('prospector_enable_summits', false),
                'chatGpt' => $chatgpt_enabled,
            ];
        }

        // Fallback to direct option reading
        $chatgpt_enabled = (bool) get_option('prospector_enable_chatgpt', false);
        $chat_enabled = (bool) get_option('prospector_enable_chat', false);

        return [
            'chat'    => $chat_enabled || $chatgpt_enabled,
            'youtube' => (bool) get_option('prospector_enable_youtube', true),
            'summits' => (bool) get_option('prospector_enable_summits', false),
            'chatGpt' => $chatgpt_enabled,
        ];
    }
}
