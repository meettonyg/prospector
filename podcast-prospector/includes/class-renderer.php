<?php
/**
 * Interview Finder Renderer Class
 *
 * Handles all HTML rendering for search results and forms.
 *
 * @package Podcast_Prospector
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Podcast_Prospector_Renderer
 *
 * Consolidates HTML rendering logic for search results.
 */
class Podcast_Prospector_Renderer {

    /**
     * Singleton instance.
     *
     * @var Podcast_Prospector_Renderer|null
     */
    private static ?Podcast_Prospector_Renderer $instance = null;

    /**
     * RSS Cache instance.
     *
     * @var Podcast_Prospector_RSS_Cache|null
     */
    private ?Podcast_Prospector_RSS_Cache $rss_cache = null;

    /**
     * Language map for code to name conversion.
     *
     * @var array
     */
    private static array $language_map = [
        'af' => 'Afrikaans', 'ak' => 'Akan', 'sq' => 'Albanian', 'am' => 'Amharic', 'ar' => 'Arabic',
        'hy' => 'Armenian', 'as' => 'Assamese', 'ay' => 'Aymara', 'az' => 'Azerbaijani', 'bm' => 'Bambara',
        'eu' => 'Basque', 'be' => 'Belarusian', 'bn' => 'Bengali', 'bho' => 'Bhojpuri', 'bs' => 'Bosnian',
        'bg' => 'Bulgarian', 'my' => 'Burmese', 'ca' => 'Catalan', 'ceb' => 'Cebuano', 'ny' => 'Chichewa',
        'zh' => 'Chinese', 'co' => 'Corsican', 'hr' => 'Croatian', 'cs' => 'Czech', 'da' => 'Danish',
        'dv' => 'Divehi', 'nl' => 'Dutch', 'en' => 'English', 'eo' => 'Esperanto', 'et' => 'Estonian',
        'ee' => 'Ewe', 'fi' => 'Finnish', 'fr' => 'French', 'fy' => 'Frisian', 'gl' => 'Galician',
        'ka' => 'Georgian', 'de' => 'German', 'el' => 'Greek', 'gn' => 'Guarani', 'gu' => 'Gujarati',
        'ht' => 'Haitian Creole', 'ha' => 'Hausa', 'haw' => 'Hawaiian', 'he' => 'Hebrew', 'iw' => 'Hebrew',
        'hi' => 'Hindi', 'hmn' => 'Hmong', 'hu' => 'Hungarian', 'is' => 'Icelandic', 'ig' => 'Igbo',
        'ilo' => 'Iloko', 'id' => 'Indonesian', 'in' => 'Indonesian', 'ga' => 'Irish', 'it' => 'Italian',
        'ja' => 'Japanese', 'jv' => 'Javanese', 'jw' => 'Javanese', 'kn' => 'Kannada', 'kk' => 'Kazakh',
        'km' => 'Khmer', 'rw' => 'Kinyarwanda', 'gom' => 'Konkani', 'ko' => 'Korean', 'kri' => 'Krio',
        'ku' => 'Kurdish', 'ckb' => 'Kurdish (Sorani)', 'ky' => 'Kyrgyz', 'lo' => 'Lao', 'la' => 'Latin',
        'lv' => 'Latvian', 'ln' => 'Lingala', 'lt' => 'Lithuanian', 'lg' => 'Luganda', 'lb' => 'Luxembourgish',
        'mk' => 'Macedonian', 'mai' => 'Maithili', 'mg' => 'Malagasy', 'ms' => 'Malay', 'ml' => 'Malayalam',
        'mt' => 'Maltese', 'mi' => 'Maori', 'mr' => 'Marathi', 'mn' => 'Mongolian', 'ne' => 'Nepali',
        'no' => 'Norwegian', 'or' => 'Odia', 'om' => 'Oromo', 'ps' => 'Pashto', 'fa' => 'Persian',
        'pl' => 'Polish', 'pt' => 'Portuguese', 'pa' => 'Punjabi', 'qu' => 'Quechua', 'ro' => 'Romanian',
        'ru' => 'Russian', 'sm' => 'Samoan', 'sa' => 'Sanskrit', 'gd' => 'Scots Gaelic', 'nso' => 'Sepedi',
        'sr' => 'Serbian', 'st' => 'Sesotho', 'sn' => 'Shona', 'sd' => 'Sindhi', 'si' => 'Sinhala',
        'sk' => 'Slovak', 'sl' => 'Slovenian', 'so' => 'Somali', 'es' => 'Spanish', 'su' => 'Sundanese',
        'sw' => 'Swahili', 'sv' => 'Swedish', 'tg' => 'Tajik', 'ta' => 'Tamil', 'tt' => 'Tatar',
        'te' => 'Telugu', 'th' => 'Thai', 'ti' => 'Tigrinya', 'ts' => 'Tsonga', 'tr' => 'Turkish',
        'tk' => 'Turkmen', 'uk' => 'Ukrainian', 'ur' => 'Urdu', 'ug' => 'Uyghur', 'uz' => 'Uzbek',
        'vi' => 'Vietnamese', 'cy' => 'Welsh', 'xh' => 'Xhosa', 'yi' => 'Yiddish', 'ji' => 'Yiddish',
        'yo' => 'Yoruba', 'zu' => 'Zulu',
    ];

    /**
     * Get singleton instance.
     *
     * @return Podcast_Prospector_Renderer
     */
    public static function get_instance(): Podcast_Prospector_Renderer {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor.
     */
    private function __construct() {
        if ( class_exists( 'Podcast_Prospector_RSS_Cache' ) ) {
            $this->rss_cache = Podcast_Prospector_RSS_Cache::get_instance();
        }
    }

    /**
     * Convert language code to readable name.
     *
     * @param string|null $lang_code Language code.
     * @return string Language name.
     */
    public function get_language_name( ?string $lang_code ): string {
        if ( empty( $lang_code ) || ! is_string( $lang_code ) ) {
            return 'N/A';
        }

        // Normalize: lowercase, remove region suffix
        $normalized = strtolower( preg_replace( '/[_-].*/', '', trim( $lang_code ) ) );

        if ( isset( self::$language_map[ $normalized ] ) ) {
            return self::$language_map[ $normalized ];
        }

        return ! empty( $lang_code ) ? ucfirst( $lang_code ) : 'N/A';
    }

    /**
     * Highlight search term in text.
     *
     * @param string $text        Text to search within.
     * @param string $search_term Term to highlight.
     * @param string $css_class   CSS class for highlight.
     * @return string Highlighted text.
     */
    public function highlight_search_term( string $text, string $search_term, string $css_class = 'search-highlight' ): string {
        if ( empty( $search_term ) || empty( $text ) ) {
            return $text;
        }

        $term = trim( $search_term );
        if ( '' === $term ) {
            return $text;
        }

        $highlighted = '<mark class="' . esc_attr( $css_class ) . '">'
            . esc_html( $term ) . '</mark>';

        return str_ireplace( $term, $highlighted, $text );
    }

    /**
     * Format genre name from API format.
     *
     * @param string $genre Raw genre string.
     * @return string Formatted genre.
     */
    public function format_genre( string $genre ): string {
        $genre = str_replace( 'PODCASTSERIES_', '', $genre );
        $genre = str_replace( '_', ' ', $genre );
        return ucwords( strtolower( $genre ) );
    }

    /**
     * Get status icon HTML based on last episode date.
     *
     * @param int|string|null $last_episode_date Last episode timestamp or date string.
     * @return string HTML for status icon.
     */
    public function get_status_icon( $last_episode_date ): string {
        $three_months_ago = strtotime( '-3 months' );

        $timestamp = is_numeric( $last_episode_date )
            ? (int) $last_episode_date
            : ( $last_episode_date ? strtotime( $last_episode_date ) : 0 );

        if ( $timestamp && $timestamp >= $three_months_ago ) {
            return '<i class="fas fa-check-circle" style="color:green;" title="Active (recent episode)"></i>';
        }

        return '<i class="fas fa-exclamation-triangle" style="color:red;" title="Potentially inactive"></i>';
    }

    /**
     * Render results table for PodcastIndex data.
     *
     * @param array  $data        API response data.
     * @param string $search_term Search term for highlighting.
     * @return string HTML output.
     */
    public function render_podcastindex_results( array $data, string $search_term = '' ): string {
        $results = [];
        $is_episode = false;

        if ( ! empty( $data['items'] ) && is_array( $data['items'] ) ) {
            $results = $data['items'];
            $is_episode = true;
        } elseif ( ! empty( $data['feeds'] ) && is_array( $data['feeds'] ) ) {
            $results = $data['feeds'];
            $is_episode = false;
        }

        if ( empty( $results ) ) {
            if ( isset( $data['status'] ) && 'false' === $data['status'] && isset( $data['description'] ) ) {
                return '<p>API Error: ' . esc_html( $data['description'] ) . '</p>';
            }
            if ( isset( $data['error'] ) ) {
                return '<p>Search Error: ' . esc_html( $data['error'] ) . '</p>';
            }
            return '<p>No search results found.</p>';
        }

        return $this->render_results_table( $results, $search_term, $is_episode, 'podcastindex' );
    }

    /**
     * Render results table for Taddy podcast data.
     *
     * @param array  $response    API response data.
     * @param string $search_term Search term.
     * @param array  $settings    User membership settings.
     * @return string HTML output.
     */
    public function render_taddy_podcast_results( array $response, string $search_term = '', array $settings = [] ): string {
        $results = $response['data']['searchForTerm']['podcastSeries'] ?? [];

        if ( empty( $results ) ) {
            return '<p>No podcasts found.</p>';
        }

        // Transform Taddy format to common format
        $normalized = array_map( function( $item ) {
            return [
                'title'            => $item['name'] ?? 'N/A',
                'image'            => $item['imageUrl'] ?? '',
                'description'      => $item['description'] ?? '',
                'language'         => $item['language'] ?? 'N/A',
                'author'           => $item['authorName'] ?? 'N/A',
                'explicit'         => $item['isExplicitContent'] ?? false,
                'genres'           => $item['genres'] ?? [],
                'uuid'             => $item['uuid'] ?? '',
                'rssUrl'           => $item['rssUrl'] ?? '',
                'itunesId'         => $item['itunesId'] ?? '',
                'lastEpisodeDate'  => $item['episodes'][0]['datePublished'] ?? null,
                '_raw'             => $item,
            ];
        }, $results );

        return $this->render_results_table( $normalized, $search_term, false, 'taddy_podcast', $settings );
    }

    /**
     * Render results table for Taddy episode data.
     *
     * @param array  $response    API response data.
     * @param string $search_term Search term.
     * @param array  $settings    User membership settings.
     * @return string HTML output.
     */
    public function render_taddy_episode_results( array $response, string $search_term = '', array $settings = [] ): string {
        $results = $response['data']['searchForTerm']['podcastEpisodes'] ?? [];

        if ( empty( $results ) ) {
            return '<p>No episodes found matching your criteria.</p>';
        }

        // Transform Taddy episode format
        $normalized = array_map( function( $item ) {
            $series = $item['podcastSeries'] ?? [];
            return [
                'episodeTitle'     => $item['name'] ?? 'N/A',
                'episodeDescription' => $item['description'] ?? '',
                'episodeGuid'      => $item['guid'] ?? '',
                'episodeUuid'      => $item['uuid'] ?? '',
                'datePublished'    => $item['datePublished'] ?? null,
                'title'            => $series['name'] ?? 'N/A',
                'image'            => $series['imageUrl'] ?? '',
                'description'      => $series['description'] ?? '',
                'language'         => $series['language'] ?? 'N/A',
                'author'           => $series['authorName'] ?? 'N/A',
                'explicit'         => $series['isExplicitContent'] ?? false,
                'genres'           => $series['genres'] ?? [],
                'uuid'             => $series['uuid'] ?? '',
                'rssUrl'           => $series['rssUrl'] ?? '',
                'itunesId'         => $series['itunesId'] ?? '',
                '_raw'             => $item,
            ];
        }, $results );

        return $this->render_results_table( $normalized, $search_term, true, 'taddy_episode', $settings );
    }

    /**
     * Render unified results table.
     *
     * @param array  $results     Normalized results array.
     * @param string $search_term Search term.
     * @param bool   $is_episode  Whether showing episode results.
     * @param string $source      Data source (podcastindex, taddy_podcast, taddy_episode).
     * @param array  $settings    User membership settings.
     * @return string HTML output.
     */
    private function render_results_table( array $results, string $search_term, bool $is_episode, string $source, array $settings = [] ): string {
        $output = '<div class="podsearch-results-container">';
        $output .= '<form method="post" action="" id="podcast-results-form">';
        $output .= '<table class="podcast-results-table">';
        $output .= $this->render_table_header( $is_episode );
        $output .= '<tbody>';

        $three_months_ago = strtotime( '-3 months' );

        foreach ( $results as $index => $item ) {
            $output .= $this->render_result_row( $item, $index, $search_term, $is_episode, $source, $three_months_ago, $settings );
        }

        $output .= '</tbody></table>';
        $output .= $this->render_bottom_controls( $search_term );
        $output .= '</form>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Render table header.
     *
     * @param bool $is_episode Whether showing episode results.
     * @return string HTML.
     */
    private function render_table_header( bool $is_episode ): string {
        $date_label = $is_episode ? 'Episode Date' : 'Last Episode';
        return '<thead><tr>'
            . '<th><input type="checkbox" id="select_all" title="Select All/None"/></th>'
            . '<th>Details</th>'
            . '<th>Categories</th>'
            . '<th>Language</th>'
            . '<th>Status</th>'
            . '<th>' . esc_html( $date_label ) . '</th>'
            . '<th>Explicit</th>'
            . '<th>Publisher</th>'
            . '</tr></thead>';
    }

    /**
     * Render a single result row.
     *
     * @param array  $item             Item data.
     * @param int    $index            Row index.
     * @param string $search_term      Search term.
     * @param bool   $is_episode       Whether this is an episode.
     * @param string $source           Data source.
     * @param int    $three_months_ago Timestamp for status comparison.
     * @param array  $settings         User membership settings.
     * @return string HTML.
     */
    private function render_result_row( array $item, int $index, string $search_term, bool $is_episode, string $source, int $three_months_ago, array $settings ): string {
        // Get RSS data for additional info if available
        $rss_data = [];
        $feed_url = $item['rssUrl'] ?? ( $item['feedUrl'] ?? ( $item['url'] ?? '' ) );
        if ( ! empty( $feed_url ) && $this->rss_cache ) {
            $rss_data = $this->rss_cache->get_last_episode_data( $feed_url );
        }

        // Determine last episode date for status
        $last_ep_date = null;
        if ( $is_episode && isset( $item['datePublished'] ) ) {
            $last_ep_date = $item['datePublished'];
        } elseif ( isset( $item['lastEpisodeDate'] ) ) {
            $last_ep_date = $item['lastEpisodeDate'];
        } elseif ( ! empty( $rss_data['lastEpisodePubDate'] ) ) {
            $last_ep_date = strtotime( $rss_data['lastEpisodePubDate'] );
        }

        // Format display date
        $date_display = 'N/A';
        if ( $last_ep_date ) {
            $timestamp = is_numeric( $last_ep_date ) ? $last_ep_date : strtotime( $last_ep_date );
            $date_display = gmdate( 'Y-m-d', $timestamp );
        }

        // Language
        $lang_code = $item['language'] ?? ( $item['feedLanguage'] ?? 'N/A' );
        $language_name = ucwords( strtolower( $this->get_language_name( $lang_code ) ) );

        // Publisher
        $publisher = $item['author'] ?? ( $item['feedAuthor'] ?? 'N/A' );
        if ( 'N/A' === $publisher && ! empty( $rss_data['itunes:name'] ) ) {
            $publisher = $rss_data['itunes:name'];
        }

        // Explicit
        $explicit_raw = $item['explicit'] ?? false;
        $force_safe = $settings['safe_mode_forced'] ?? false;
        if ( $force_safe && $explicit_raw ) {
            $explicit_display = 'Locked';
        } else {
            $explicit_display = $explicit_raw ? 'Yes' : 'No';
        }

        // Categories
        $categories = $item['genres'] ?? ( $rss_data['itunes:categories'] ?? [] );

        // JSON for import
        $json_data = isset( $item['_raw'] ) ? wp_json_encode( $item['_raw'] ) : wp_json_encode( $item );

        $output = '<tr>';

        // Checkbox
        $output .= '<td class="checkbox-column">'
            . '<input type="checkbox" name="selected_podcasts[]" value="' . esc_attr( $json_data ) . '">'
            . '</td>';

        // Details cell
        $output .= '<td>';
        $output .= $this->render_details_cell( $item, $index, $search_term, $is_episode, $rss_data, $json_data );
        $output .= '</td>';

        // Categories
        $output .= '<td>' . $this->render_categories( $categories ) . '</td>';

        // Language
        $output .= '<td>' . esc_html( $language_name ) . '</td>';

        // Status
        $output .= '<td style="text-align:center;">' . $this->get_status_icon( $last_ep_date ) . '</td>';

        // Date
        $output .= '<td style="text-align:center;">' . esc_html( $date_display ) . '</td>';

        // Explicit
        $output .= '<td style="text-align:center;">' . esc_html( $explicit_display ) . '</td>';

        // Publisher
        $output .= '<td>' . esc_html( $publisher ) . '</td>';

        $output .= '</tr>';

        return $output;
    }

    /**
     * Render details cell content.
     *
     * @param array  $item        Item data.
     * @param int    $index       Row index.
     * @param string $search_term Search term.
     * @param bool   $is_episode  Whether this is an episode.
     * @param array  $rss_data    RSS data.
     * @param string $json_data   JSON for import button.
     * @return string HTML.
     */
    private function render_details_cell( array $item, int $index, string $search_term, bool $is_episode, array $rss_data, string $json_data ): string {
        $output = '';

        // Image
        $image_url = $item['image'] ?? ( $item['feedImage'] ?? '' );
        if ( $image_url ) {
            $output .= '<img src="' . esc_url( $image_url ) . '" class="podcast-feed-image" alt="">';
        }

        $output .= '<div class="podcast-details">';

        // Podcast name
        $podcast_name = $item['title'] ?? 'N/A';
        $output .= '<strong>' . esc_html( $podcast_name ) . '</strong><br>';

        // Episode name (if applicable)
        if ( $is_episode && ! empty( $item['episodeTitle'] ) ) {
            $output .= 'Episode: ' . esc_html( $item['episodeTitle'] ) . '<br>';
        }

        // Episode description (expandable)
        if ( $is_episode && ! empty( $item['episodeDescription'] ) ) {
            $toggle_id = 'ep-' . ( $item['episodeUuid'] ?? $index );
            $highlighted = $this->highlight_search_term( $item['episodeDescription'], $search_term );
            $output .= $this->render_expandable_section( $toggle_id, 'View Episode Description', $highlighted );
        }

        // Podcast description (expandable)
        $pod_desc = $item['description'] ?? ( $rss_data['description'] ?? '' );
        if ( ! empty( $pod_desc ) ) {
            $toggle_id = 'pod-' . ( $item['uuid'] ?? $index );
            $highlighted = $this->highlight_search_term( $pod_desc, $search_term );
            $output .= $this->render_expandable_section( $toggle_id, 'View Podcast Description', $highlighted );
        }

        // Import button
        $output .= '<div class="details-actions">'
            . '<button type="button" class="individual-import-button" data-podcast="' . esc_attr( $json_data ) . '">'
            . 'Import to Tracker'
            . '</button>'
            . '</div>';

        $output .= '</div>';

        return $output;
    }

    /**
     * Render expandable section.
     *
     * @param string $toggle_id Unique ID for toggle.
     * @param string $label     Toggle label.
     * @param string $content   Content to show.
     * @return string HTML.
     */
    private function render_expandable_section( string $toggle_id, string $label, string $content ): string {
        $safe_id = esc_attr( $toggle_id );
        return '<div class="shared-expand simple-expand">'
            . '<input id="toggle-' . $safe_id . '" type="checkbox" class="toggle-checkbox">'
            . '<label for="toggle-' . $safe_id . '" class="expand-toggle">' . esc_html( $label ) . '</label>'
            . '<div class="expandcontent"><section>' . wp_kses_post( $content ) . '</section></div>'
            . '</div>';
    }

    /**
     * Render categories.
     *
     * @param array $categories Category array.
     * @return string HTML.
     */
    private function render_categories( array $categories ): string {
        if ( empty( $categories ) ) {
            return 'N/A';
        }

        $output = '';
        foreach ( $categories as $cat ) {
            $label = $this->format_genre( $cat );
            $output .= '<span class="category-tag">' . esc_html( $label ) . '</span> ';
        }

        return trim( $output ) ?: 'N/A';
    }

    /**
     * Render bottom controls.
     *
     * @param string $search_term Search term.
     * @return string HTML.
     */
    private function render_bottom_controls( string $search_term ): string {
        return '<div class="bottom-row-container">'
            . '<div class="left-side">'
            . '<button type="button" id="add-to-formidable-button" class="add-to-formidable-button">Import Selected</button>'
            . '</div>'
            . '<div class="right-side">'
            . '<div class="cta-upsearch">'
            . '<a class="btn-trial-tbl-cta" href="/pricing" role="button">Upgrade for More Features & Results</a>'
            . '</div>'
            . '</div>'
            . '</div>'
            . '<input type="hidden" name="search_term" value="' . esc_attr( $search_term ) . '">';
    }

    /**
     * Render pagination controls.
     *
     * @param int $current_page Current page number.
     * @param int $max_pages    Maximum allowed pages.
     * @param int $results_count Results on current page.
     * @param int $per_page     Results per page.
     * @return string HTML.
     */
    public function render_pagination( int $current_page, int $max_pages, int $results_count, int $per_page ): string {
        $output = '<div class="pagination-controls">';

        if ( $current_page > 1 ) {
            $output .= '<button type="button" class="pagination-btn prev-page" data-page="' . ( $current_page - 1 ) . '">'
                . '&larr; Previous'
                . '</button>';
        }

        $has_next = $results_count >= $per_page && $current_page < $max_pages;
        if ( $has_next ) {
            $output .= '<button type="button" class="pagination-btn next-page" data-page="' . ( $current_page + 1 ) . '">'
                . 'Next &rarr;'
                . '</button>';
        }

        $output .= '</div>';

        return $output;
    }
}
