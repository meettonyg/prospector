<?php
/**
 * Plugin Name: Podcast Prospector
 * Description: Search and display podcast episodes from PodcastIndex API by guest name.
 * Version: 1.0
 * Author: Your Name
 * Author URI: Your Website
 * Text Domain: podcast-prospector
 */

// Create custom database table on activation
function podcast_prospector_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'podcast_prospector';
    $charset_collate = $wpdb->get_charset_collate();

    // Note the new 'last_reset_date' column
    $sql = "CREATE TABLE $table_name (
        id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        wp_user_id BIGINT(20) UNSIGNED,
        ghl_id VARCHAR(255),
        search_count INT(11) DEFAULT 0,
		total_searches INT(11) DEFAULT 0,
        last_searched DATETIME DEFAULT CURRENT_TIMESTAMP,
        last_reset_date DATETIME DEFAULT '0000-00-00 00:00:00',
        UNIQUE KEY ghl_id (ghl_id),
        UNIQUE KEY wp_user_id (wp_user_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'podcast_prospector_create_table');

/**
 * Resets the user's search count if a new renewal payment has been detected.
 * This function uses the HighLevel renewal date synced via WP Fusion and compares
 * it with the table's stored last_reset_date. If they differ, search_count is reset to 0.
 *
 * @param string|null $ghl_id   The HighLevel contact ID (if available).
 * @param int|null    $user_id  The WordPress user ID (if available).
 */
/**
 * Resets the user's search count if a new renewal payment has been detected.
 * This function uses the HighLevel renewal date (synced via WP Fusion) and compares
 * it with the table's stored last_reset_date. If they differ, search_count is reset to 0.
 *
 * @param string|null $ghl_id   The HighLevel contact ID (if available).
 * @param int|null    $user_id  The WordPress user ID (if available).
 */
/**
 * Resets the user's search count if a new renewal payment has been detected.
 * This version compares only the date portion (YYYY-MM-DD) of last_renewal_date
 * and last_reset_date to avoid resetting on every search if times differ.
 *
 * @param string|null $ghl_id   The HighLevel contact ID (if available).
 * @param int|null    $user_id  The WordPress user ID (if available).
 */
function podcast_prospector_reset_search_cap_if_needed( $ghl_id = null, $user_id = null ) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'podcast_prospector';

    // Debug
    error_log("DEBUG: Starting reset check. Table name: $table_name, wpdb->prefix={$wpdb->prefix}, user_id=$user_id, ghl_id=$ghl_id");

    // Attempt to retrieve the user's row by either GHL ID or WP user ID
    $row = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM $table_name WHERE (ghl_id = %s OR wp_user_id = %d)",
        $ghl_id,
        $user_id
    ) );
    if ( ! $row ) {
        error_log("DEBUG: No row found in $table_name for user_id=$user_id, ghl_id=$ghl_id. Exiting reset function.");
        return;
    }

    // Ensure we have a valid WordPress user ID
    if ( ! $user_id && ! empty( $row->wp_user_id ) ) {
        $user_id = $row->wp_user_id;
        error_log("DEBUG: Updated user_id from row->wp_user_id: $user_id");
    }
    if ( ! $user_id ) {
        error_log("DEBUG: Still no valid user_id. Exiting reset function.");
        return;
    }

    // Fetch renewal info from user meta (synced via WP Fusion)
    $last_renewal_date = get_user_meta( $user_id, 'guestify_last_renewal_date', true );
    $renewal_type      = get_user_meta( $user_id, 'contact.guestify_renewal_type', true );
    $last_reset_date   = $row->last_reset_date;

    // Debug logging
    error_log("DEBUG: For user_id=$user_id, last_reset_date={$last_reset_date}, last_renewal_date={$last_renewal_date}, renewal_type={$renewal_type}");

    // Convert both to YYYY-MM-DD for comparison
    $renewal_date_fmt = date('Y-m-d', strtotime($last_renewal_date));
    $reset_date_fmt   = date('Y-m-d', strtotime($last_reset_date));

    // If last_reset_date is still the default or empty
    if ( empty($last_reset_date) || $last_reset_date === '0000-00-00 00:00:00' ) {
        // Only initialize if we actually have a renewal date
        if ( ! empty($last_renewal_date) ) {
            // Optionally store the new date as a full datetime
            // e.g. "YYYY-MM-DD 00:00:00"
            $new_reset_datetime = $renewal_date_fmt . ' 00:00:00';

            $wpdb->update(
                $table_name,
                array(
                    'last_reset_date' => $new_reset_datetime,
                    'search_count'    => 0
                ),
                array( 'id' => $row->id )
            );
            error_log("DEBUG: Set last_reset_date to $new_reset_datetime and reset search_count to 0 for row ID {$row->id}");
        } else {
            error_log("DEBUG: last_renewal_date is empty. Not updating last_reset_date.");
        }
        return;
    }

    // If we have a new renewal date (comparing only YYYY-MM-DD)
    if ( ! empty($last_renewal_date) && $renewal_date_fmt !== $reset_date_fmt ) {
        $new_reset_datetime = $renewal_date_fmt . ' 00:00:00';

        $wpdb->update(
            $table_name,
            array(
                'search_count'    => 0,
                'last_reset_date' => $new_reset_datetime
            ),
            array( 'id' => $row->id )
        );
        error_log("Search cap reset for user $user_id due to a $renewal_type renewal on $last_renewal_date. Row ID: {$row->id}");
    } else {
        error_log("DEBUG: No new renewal detected (reset_date_fmt=$reset_date_fmt, renewal_date_fmt=$renewal_date_fmt).");
    }
}

/**
 * Return an array of membership-based settings for the given user.
 *
 * @param int $user_id The WP user ID.
 * @return array {
 *   @type int   $max_pages            The max number of pages allowed for Taddy pagination.
 *   @type int   $max_results_per_page The max "results per page" user can choose.
 *   @type array $allowed_filters      A list of which filters (language, country, etc.) are enabled.
 * }
 */
function podcast_prospector_get_membership_settings($user_id) {
    // Fetch membership level from user meta, uppercase for consistency
    $membership_level = strtoupper( get_user_meta($user_id, 'guestify_membership', true) );

    // Define membership tiers with both numeric limits and feature booleans
    $membership_config = [
        'ZENITH' => [
            // Taddy pagination
            'max_pages'             => 20,
            'max_results_per_page'  => 25,

            // PodcastIndex
            'podcastindex_max'      => 50,  // up to 50 total results

            // Feature-based toggles
            // For Taddy advanced filters:
            'can_filter_country'    => true,
            'can_filter_language'   => true,
            'can_filter_genre'      => true,
            'can_filter_date'       => true,  // date range filters

            // Sort by date published for Taddy
            // In Zenith: LATEST or OLDEST
            'sort_by_date_published_options' => ['LATEST','OLDEST'],

            // Safe mode forced?
            'safe_mode_forced' => false,

            // For PodcastIndex date-based sorting
            // In Zenith: can do LATEST or OLDEST
            'podcastindex_sort_options' => ['LATEST','OLDEST'],
        ],

        'VELOCITY' => [
            'max_pages'             => 10,
            'max_results_per_page'  => 15,
            'podcastindex_max'      => 25,  // up to 25 total results

            'can_filter_country'    => true,
            'can_filter_language'   => true,
            'can_filter_genre'      => true,
            'can_filter_date'       => false, // date range locked

            // LATEST only
            'sort_by_date_published_options' => ['LATEST'],

            'safe_mode_forced' => false,

            // For PodcastIndex, user can only do LATEST
            'podcastindex_sort_options' => ['LATEST'],
        ],

        'ACCELERATOR' => [
            'max_pages'             => 5,
            'max_results_per_page'  => 10,
            'podcastindex_max'      => 10, // up to 10 total results

            // Feature locks
            'can_filter_country'    => false,
            'can_filter_language'   => false,
            'can_filter_genre'      => false,
            'can_filter_date'       => false,

            // No date sort allowed
            'sort_by_date_published_options' => [],

            // Always safe
            'safe_mode_forced' => true,

            // For PodcastIndex, no LATEST or OLDEST => default BEST_MATCH
            'podcastindex_sort_options' => [],
        ],
    ];

    // If membership is unknown or missing, treat them like ACCELERATOR by default
    $default_settings = [
        'max_pages'             => 5,
        'max_results_per_page'  => 10,
        'podcastindex_max'      => 5,

        'can_filter_country'    => false,
        'can_filter_language'   => false,
        'can_filter_genre'      => false,
        'can_filter_date'       => false,

        'sort_by_date_published_options' => [],
        'safe_mode_forced' => true,

        'podcastindex_sort_options' => [],
    ];

    // Final check
    return isset($membership_config[$membership_level])
        ? $membership_config[$membership_level]
        : $default_settings;
}

// Function to get user data from the database
function podcast_prospector_get_user_data($ghl_id = null, $wp_user_id = null) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'podcast_prospector';

    // Fetch user data by GHL ID or WP User ID
    if ($ghl_id) {
        $result = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE ghl_id = %s", $ghl_id));
    } elseif ($wp_user_id) {
        $result = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE wp_user_id = %d", $wp_user_id));
    } else {
        return null;
    }

    // Debugging: Log the retrieved data
    if ($result) {
        error_log("Retrieved user data: " . print_r($result, true));
    } else {
        error_log("No user data found for GHL ID: {$ghl_id}, WP User ID: {$wp_user_id}");
    }

    return $result;
}

function podcast_prospector_get_or_create_user_data($ghl_id = null, $wp_user_id = null) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'podcast_prospector';

    // Check for an existing record
    $result = podcast_prospector_get_user_data($ghl_id, $wp_user_id);

    if (!$result) {
        // Insert a new record if none exists
        $insert_result = $wpdb->insert(
            $table_name,
            [
                'ghl_id' => $ghl_id,
                'wp_user_id' => $wp_user_id,
                'search_count' => 0,
                'last_searched' => current_time('mysql'),
            ]
        );

        if (false === $insert_result) {
            error_log("Failed to create new user data. Error: " . $wpdb->last_error);
            return null;
        }

        // Retrieve the newly created record
        $result = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE ghl_id = %s AND wp_user_id = %d", $ghl_id, $wp_user_id));
    }

    return $result;
}

// Function to update or insert user data into the database
function podcast_prospector_update_user_data($ghl_id = null, $wp_user_id = null) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'podcast_prospector';

    // Retrieve or create the user record
    $user_data = podcast_prospector_get_or_create_user_data($ghl_id, $wp_user_id);

    if ($user_data) {
        $new_search_count = $user_data->search_count + 1;
        $current_total    = isset($user_data->total_searches) ? (int)$user_data->total_searches : 0;
        $new_total        = $current_total + 1;

        // Update the custom table
        $update_result = $wpdb->update(
            $table_name,
            [
                'search_count'   => $new_search_count,
                'total_searches' => $new_total,
                'last_searched'  => current_time('mysql'),
            ],
            ['id' => $user_data->id]
        );

        // Debugging logs
        if (false === $update_result) {
            error_log("Failed to update user data. Error: " . $wpdb->last_error);
        } elseif ($update_result === 0) {
            error_log("Update query executed but no rows were affected.");
        } else {
            error_log("User data updated successfully. search_count=$new_search_count, total_searches=$new_total");
        }

        // ✅ Always update `guestify_total_searches` in user meta
        update_user_meta($wp_user_id, 'guestify_total_searches', $new_total);

        // ✅ Define milestones at which to sync to GHL
        $milestones = [5, 100, 250, 500];

        if (in_array($new_total, $milestones, true)) {
            // Sync only when a milestone is hit
            if (function_exists('wp_fusion')) {
                wp_fusion()->user->push_user_meta($wp_user_id, [
                    'guestify_total_searches' => $new_total,
                ]);
            }

            error_log("Milestone reached: guestify_total_searches=$new_total for user $wp_user_id. Synced to GHL.");
        } else {
            error_log("Updated guestify_total_searches to $new_total, but no milestone reached.");
        }
    } else {
        error_log("Failed to retrieve or create user data for GHL ID: $ghl_id, WP User ID: $wp_user_id");
    }
}


/**
 * Converts a language code (e.g., 'en', 'en-us', 'FR') into a full language name.
 *
 * @param string|null $lang_code The language code from the API.
 * @return string The full language name or the original code if not found/empty.
 */
function podcast_prospector_get_language_name($lang_code) {
    if (empty($lang_code) || !is_string($lang_code)) {
        return 'N/A';
    }

    // --- Language Code to Full Name Mapping ---
    // Based on ISO 639-1 codes primarily. Add more as needed.
    // Keys MUST be lowercase for matching.
    $language_map = [
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
        'ilo' => 'Iloko', 'id' => 'Indonesian', 'in' => 'Indonesian',
        'ga' => 'Irish', 'it' => 'Italian', 'ja' => 'Japanese', 'jv' => 'Javanese', 'jw' => 'Javanese',
        'kn' => 'Kannada', 'kk' => 'Kazakh', 'km' => 'Khmer', 'rw' => 'Kinyarwanda', 'gom' => 'Konkani',
        'ko' => 'Korean', 'kri' => 'Krio', 'ku' => 'Kurdish (Kurmanji)', 'ckb' => 'Kurdish (Sorani)',
        'ky' => 'Kyrgyz', 'lo' => 'Lao', 'la' => 'Latin', 'lv' => 'Latvian', 'ln' => 'Lingala',
        'lt' => 'Lithuanian', 'lg' => 'Luganda', 'lb' => 'Luxembourgish', 'mk' => 'Macedonian',
        'mai' => 'Maithili', 'mg' => 'Malagasy', 'ms' => 'Malay', 'ml' => 'Malayalam', 'mt' => 'Maltese',
        'mi' => 'Maori', 'mr' => 'Marathi', 'mn' => 'Mongolian', 'ne' => 'Nepali', 'no' => 'Norwegian',
        'or' => 'Odia (Oriya)', 'om' => 'Oromo', 'ps' => 'Pashto', 'fa' => 'Persian', 'pl' => 'Polish',
        'pt' => 'Portuguese', 'pa' => 'Punjabi', 'qu' => 'Quechua', 'ro' => 'Romanian', 'ru' => 'Russian',
        'sm' => 'Samoan', 'sa' => 'Sanskrit', 'gd' => 'Scots Gaelic', 'nso' => 'Sepedi', 'sr' => 'Serbian',
        'st' => 'Sesotho', 'sn' => 'Shona', 'sd' => 'Sindhi', 'si' => 'Sinhala', 'sk' => 'Slovak',
        'sl' => 'Slovenian', 'so' => 'Somali', 'es' => 'Spanish', 'su' => 'Sundanese', 'sw' => 'Swahili',
        'sv' => 'Swedish', 'tg' => 'Tajik', 'ta' => 'Tamil', 'tt' => 'Tatar', 'te' => 'Telugu',
        'th' => 'Thai', 'ti' => 'Tigrinya', 'ts' => 'Tsonga', 'tr' => 'Turkish', 'tk' => 'Turkmen',
        'uk' => 'Ukrainian', 'ur' => 'Urdu', 'ug' => 'Uyghur', 'uz' => 'Uzbek', 'vi' => 'Vietnamese',
        'cy' => 'Welsh', 'xh' => 'Xhosa', 'yi' => 'Yiddish', 'ji' => 'Yiddish',
        'yo' => 'Yoruba', 'zu' => 'Zulu'
    ];

    // Normalize the input code: lowercase, take base code before '-' or '_'
    $normalized_code = strtolower(preg_replace('/[_-].*/', '', trim($lang_code)));

    if (isset($language_map[$normalized_code])) {
        return $language_map[$normalized_code];
    } else {
        // If not found, return the original code capitalized (or N/A if it was empty)
        return !empty($lang_code) ? ucfirst($lang_code) : 'N/A';
    }
}

/**
 * Highlights occurrences of a search term within a text string.
 * Case-insensitive replacement.
 *
 * @param string $text The text to search within.
 * @param string $term The search term to highlight.
 * @param string $class The CSS class to apply to the highlight tag.
 * @return string The text with the term highlighted, or original text if term is empty.
 */
function highlight_search_term($text, $term, $class = 'search-highlight') {
    // If no term or text, return original text
    if (empty($term) || empty($text) || trim($term) === '') {
        return $text;
    }

    // Trim the search term to avoid issues with leading/trailing whitespace
    $term = trim($term);

    // Define the replacement string with the <mark> tag
    // Using htmlspecialchars on the term *inside* the mark tag prevents XSS if the term itself contains HTML chars
    $highlighted_term = '<mark class="' . esc_attr($class) . '">' . htmlspecialchars($term, ENT_QUOTES, 'UTF-8') . '</mark>';

    // Perform case-insensitive replacement
    // We replace the raw term with the highlighted version
    $output = str_ireplace($term, $highlighted_term, $text);

    // Return the modified text (it will be sanitized later with wp_kses_post)
    return $output;
}

function api_request($url, $data = [], $headers = [], $is_post = true) {
    $ch = curl_init();

    if ($is_post) {
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } else {
        $url = $url . '?' . http_build_query($data);
    }

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);

    if ($response === false) {
        return 'cURL Error: ' . curl_error($ch);
    }

    curl_close($ch);

    return json_decode($response, true);
}

function buildFilterString($filters) {
    $filtered = array_filter($filters, function($value) {
        return !empty($value);
    });
    return implode(", ", $filtered);
}

function search_podcastindex($search_term, $search_type = 'byperson', $max_results = null) {
    // 1. Get the user’s membership settings to determine how many results they can fetch
    $user_id  = get_current_user_id();
    $settings = podcast_prospector_get_membership_settings($user_id);
    // If $max_results is not provided, default to the membership setting
    if ($max_results === null) {
        $max_results = isset($settings['podcastindex_max']) ? (int)$settings['podcastindex_max'] : 33;
    }
    
    // 2. Prepare PodcastIndex API Auth
    $apiKey        = "XP5VAV9ZNX7LQJ5HD8RW";
    $apiSecret     = "EaxD4y8KS85AMKGeBcZrUFs#ssXN9xWFUAhZ7TvD";
    $apiHeaderTime = intval(time());
    $hash          = sha1($apiKey . $apiSecret . $apiHeaderTime);

    $headers = [
        "User-Agent: InterviewFinder",
        "X-Auth-Key: $apiKey",
        "X-Auth-Date: $apiHeaderTime",
        "Authorization: $hash"
    ];

    // 3. Format search term and pick endpoint
    $formatted_term = str_replace(' ', '+', $search_term);
    $url = ($search_type === 'byperson')
        ? "https://api.podcastindex.org/api/1.0/search/byperson"
        : "https://api.podcastindex.org/api/1.0/search/byterm";

    // 4. Build the request data using the passed $max_results
    $data = [
        'q'      => $formatted_term,
        'pretty' => true,
        'max'    => $max_results,
    ];
	
    // Log what is being sent to PodcastIndex (do not log the API response)
    error_log("PodcastIndex Request: URL: $url, Data: " . json_encode($data));

    // 5. Make the API request (assuming api_request is your cURL helper)
    $response = api_request($url, $data, $headers, false);

    // 6. If searching by person, add convenience fields and enforce the result limit
    if ($search_type === 'byperson' && isset($response['items']) && is_array($response['items'])) {
        $response['items'] = array_slice($response['items'], 0, (int)$max_results);
        foreach ($response['items'] as &$item) {
            $item['episodeTitle']       = isset($item['title'])       ? $item['title']       : 'No title available';
            $item['episodeDescription'] = isset($item['description']) ? $item['description'] : 'No description available';
            $item['podcastName']        = isset($item['feedTitle'])   ? $item['feedTitle']   : 'No podcast name available';
        }
    }

    // (Removed logging of the full API response to keep logs clean)
    // error_log("PodcastIndex Response: " . json_encode($response));

    return $response;
}


function extract_rss_data($feedUrl) {
    $rss = @simplexml_load_file($feedUrl, 'SimpleXMLElement', LIBXML_NOCDATA);

    if ($rss === false) {
        return [];
    }

    $data = [
        'description' => (string) $rss->channel->description ?? '',
        'lastBuildDate' => (string) $rss->channel->lastBuildDate ?? '',
        'link' => (string) $rss->channel->link ?? '',
        'itunes:keywords' => (string) $rss->channel->children('itunes', true)->keywords ?? '',
        'itunes:name' => (string) $rss->channel->children('itunes', true)->owner->name ?? '',
        'itunes:email' => (string) $rss->channel->children('itunes', true)->owner->email ?? '',
        'itunes:categories' => [],
        'episodeCount' => count($rss->channel->item),
        'lastEpisodeTitle' => '',
        'lastEpisodePubDate' => ''
    ];

    $categories = $rss->channel->children('itunes', true)->category;
    foreach ($categories as $category) {
        $data['itunes:categories'][] = (string) $category->attributes()->text;
    }

    $data['itunes:categories'] = array_unique($data['itunes:categories']);

    foreach ($rss->channel->item as $episode) {
        $pubDate = (string) $episode->pubDate;

        if ($pubDate && (!$data['lastEpisodePubDate'] || strtotime($pubDate) > strtotime($data['lastEpisodePubDate']))) {
            $data['lastEpisodeTitle'] = (string) $episode->title;
            $data['lastEpisodePubDate'] = $pubDate;
        }
    }

    return $data;
}

function display_podcast_results( $data, $search_term = '' ) {
    $user_id   = get_current_user_id(); // Although not used here, kept for context
    $output    = '';
    $results   = [];
    $isEpisode = false; // Flag to determine if we are processing episode items or feed items

    // Determine the source of results (episodes from 'items' or podcasts/feeds from 'feeds')
    if ( ! empty( $data['items'] ) && is_array( $data['items'] ) ) {
        // Likely episode results (e.g., from 'byperson' search)
        $results   = $data['items'];
        $isEpisode = true;
    } elseif ( ! empty( $data['feeds'] ) && is_array( $data['feeds'] ) ) {
        // Likely feed/podcast results (e.g., from 'byterm' or 'bytitle' search)
        $results   = $data['feeds'];
        $isEpisode = false;
    }

    // Handle no results
    if ( empty( $results ) ) {
        // Check for potential API errors passed back
        if (isset($data['status']) && $data['status'] === 'false' && isset($data['description'])) {
             return '<p>API Error: ' . esc_html($data['description']) . '</p>';
        }
         if (isset($data['error'])) { // Handle potential cURL or other errors
            return '<p>Search Error: ' . esc_html($data['error']) . '</p>';
        }
        return '<p>No search results found.</p>';
    }

    // Prep cutoff date for "Active" status icon
    $three_months_ago = strtotime( '-3 months' );

    // --- Start HTML Output ---
    $output .= '<div class="podsearch-results-container">'; // Ensure this class matches JS/CSS
    $output .= '<form method="post" action="" id="podcast-results-form">'; // ID might be useful
    $output .= '<table class="podcast-results-table">'; // Class for styling
    $output .= '<thead><tr>'
             .   '<th><input type="checkbox" id="select_all" title="Select All/None"/></th>' // Checkbox column
             .   '<th>Details</th>'          // Details (Image, Name, Descriptions, Actions)
             .   '<th>Categories</th>'       // Categories from RSS
             .   '<th>Language</th>'         // Language from API/RSS
             .   '<th>Status</th>'           // Active/Inactive icon
             .   '<th>Last Episode</th>'     // Date of last episode from RSS
             .   '<th>Explicit</th>'         // Explicit content flag from API
             .   '<th>Publisher</th>'        // Publisher/Author from API/RSS
             . '</tr></thead><tbody>';

    // --- Loop Through Results ---
    foreach ( $results as $index => $item ) {
        // Determine Feed URL (different keys for feeds vs items)
        $feedUrl  = $isEpisode
            ? ( $item['feedUrl'] ?? '' ) // From episode item
            : ( $item['url']     ?? '' ); // From feed item

        // Attempt to fetch supplementary data from the RSS feed
        $rss_data = $feedUrl ? extract_rss_data( $feedUrl ) : []; // extract_rss_data should handle errors gracefully

        // --- Prepare Data for Display ---

        // Publisher Name (with fallback to RSS owner name)
        $publisherName = $item['author'] ?? ''; // API might provide 'author' for feeds
        if ( empty( $publisherName ) && ! empty( $rss_data['itunes:name'] ) ) {
            $publisherName = $rss_data['itunes:name']; // Fallback to RSS owner
        }
        if ( empty( $publisherName ) && $isEpisode && ! empty( $item['feedAuthor'] ) ) {
             $publisherName = $item['feedAuthor']; // Fallback for episodes if 'author' wasn't in RSS
        }
         if ( empty( $publisherName ) ) {
              $publisherName = 'N/A';
         }


        // Language (using helper function for readable name)
        $lang_code     = $item['feedLanguage'] ?? ( $item['language'] ?? 'N/A' ); // Check item first, then feedLanguage
        $raw_language  = podcast_prospector_get_language_name( $lang_code );
        $language_name = ucwords( strtolower( $raw_language ) );

        // Last Episode Date (from RSS data)
        $lastEpisodePubDate = 'N/A';
        if ( ! empty( $rss_data['lastEpisodePubDate'] ) ) {
            $d = date_create( $rss_data['lastEpisodePubDate'] );
            if ( $d ) {
                $lastEpisodePubDate = $d->format( 'Y-m-d' );
            }
        }

        // Status Icon (based on last episode date from RSS)
        $status_icon = '<i class="fas fa-exclamation-triangle" style="color:red;" title="Potentially inactive (no recent episode found in RSS)"></i>';
        if ( $lastEpisodePubDate !== 'N/A' && strtotime( $lastEpisodePubDate ) >= $three_months_ago ) {
            $status_icon = '<i class="fas fa-check-circle" style="color:green;" title="Active (recent episode found in RSS)"></i>';
        }

        // Explicit Flag (from API data)
        // PodcastIndex uses boolean true/false for 'explicit' key on feeds/items
        $explicit = ( isset( $item['explicit'] ) && $item['explicit'] === true ) ? 'Yes' : 'No';


        // --- Descriptions & Highlighting ---
        // Podcast Description (from RSS)
        $podcast_description = $rss_data['description'] ?? '';
        $highlighted_podcast_desc = highlight_search_term($podcast_description, $search_term);

        // Episode Description (only if $isEpisode and available in the item)
        $episode_description = '';
        $highlighted_episode_desc = '';
        if ($isEpisode) {
             // Use the convenience key added in search_podcastindex or fallback to item's description
             $episode_description = $item['episodeDescription'] ?? ($item['description'] ?? '');
             $highlighted_episode_desc = highlight_search_term($episode_description, $search_term);
        }

        // JSON payload for import buttons (contains the original $item data)
        $item_json_data = esc_attr( json_encode( $item ) );

        // --- Build Table Row ---
        $output .= '<tr>';

        // 1. Checkbox Column
        $output .= '<td class="checkbox-column">' // Added class for potential styling
                 .   '<input type="checkbox" name="selected_podcasts[]" value="' . $item_json_data . '">'
                 . '</td>';

        // 2. Details Column
        $output .= '<td>'; // Start Details TD

        // Image
        $imageUrl = $isEpisode ? ($item['feedImage'] ?? ($item['image'] ?? '')) : ($item['image'] ?? ($item['feedImage'] ?? '')); // Try both keys
        if ( $imageUrl ) {
            $output .= '<img src="' . esc_url( $imageUrl ) . '" '
                     . 'class="podcast-feed-image" alt="">'; // Alt should ideally be podcast name
        }

        // Text Details Container
        $output .= '<div class="podcast-details">'; // Handles clearing the float

        // Podcast Name
        $podcast_name = $isEpisode ? ($item['feedTitle'] ?? 'N/A') : ($item['title'] ?? 'N/A');
        $output .= '<strong>' . esc_html( $podcast_name ) . '</strong><br>';

        // Episode Name (only if $isEpisode)
        if ($isEpisode) {
             $episode_name = $item['title'] ?? 'N/A'; // Episode item's title
             $output .= 'Episode: ' . esc_html( $episode_name ) . '<br>';
        }

        // Expandable Sections for Descriptions

        // Episode Description (Show only if $isEpisode and description exists)
        if ($isEpisode && !empty($episode_description)) {
             $toggle_id_ep = esc_attr( $item['id'] ?? 'ep' . $index ); // Use episode ID if available
             $output .= '<div class="shared-expand simple-expand">'
                      .   '<input id="toggle-episode-' . $toggle_id_ep . '" type="checkbox" class="toggle-checkbox">'
                      .   '<label for="toggle-episode-' . $toggle_id_ep . '" class="expand-toggle">View Episode Description</label>'
                      .   '<div class="expandcontent"><section>' . wp_kses_post($highlighted_episode_desc) . '</section></div>'
                      . '</div>';
        }

        // Podcast Description (Show if description exists)
        if (!empty($podcast_description)) {
             // Use Feed ID for podcast toggle uniqueness, or fallback to item ID
             $toggle_id_pod = esc_attr( $item['feedId'] ?? ($item['id'] ?? 'pod' . $index) );
             $output .= '<div class="shared-expand simple-expand">'
                      .   '<input id="toggle-podcast-' . $toggle_id_pod . '" type="checkbox" class="toggle-checkbox">'
                      .   '<label for="toggle-podcast-' . $toggle_id_pod . '" class="expand-toggle">View Podcast Description</label>'
                      .   '<div class="expandcontent"><section>' . wp_kses_post($highlighted_podcast_desc) . '</section></div>'
                      . '</div>';
        }

        // Actions Row (Import Button)
        $output .= '<div class="details-actions">'
                 .   '<button type="button" class="individual-import-button" '
                 .           'data-podcast="' . $item_json_data . '">' // Pass original item data
                 .       'Import to Tracker'
                 .   '</button>'
                 . '</div>'; // End details-actions

        $output .= '</div>'; // End podcast-details
        $output .= '</td>'; // End Details TD

        // 3. Categories Column
        $cats      = $rss_data['itunes:categories'] ?? [];
        $cats_html = 'N/A';
        if ( is_array( $cats ) && count( $cats ) ) {
            $cats_html = '';
            foreach ( $cats as $c ) {
                // Filter out potential parent categories if they appear (e.g., "Business", "News") if subcategories exist
                // This is optional and depends on desired display preference
                // if (strpos($c, ':') === false) { // Basic check if it looks like a main category without sub
                     $cats_html .= '<span class="category-tag">' . esc_html( trim($c) ) . '</span> ';
                // }
            }
            $cats_html = trim($cats_html);
             if (empty($cats_html)) $cats_html = 'N/A'; // Handle case where only parent categories were filtered out
        }
        $output .= '<td>' . $cats_html . '</td>';

        // 4. Language Column
        $output .= '<td>' . esc_html( $language_name ) . '</td>';

        // 5. Status Column
        $output .= '<td style="text-align: center;">' . $status_icon . '</td>'; // Centered icon

        // 6. Last Episode Date Column (From RSS)
        $output .= '<td style="text-align: center;">' . esc_html( $lastEpisodePubDate ) . '</td>'; // Centered date

        // 7. Explicit Column
        $output .= '<td style="text-align: center;">' . esc_html( $explicit ) . '</td>'; // Centered Yes/No

        // 8. Publisher Column
        $output .= '<td>' . esc_html( $publisherName ) . '</td>';

        $output .= '</tr>'; // End table row
    } // End foreach loop

    // --- Close Table and Add Bottom Row ---
    $output .= '</tbody></table>';

    // Bottom row container for bulk actions / potentially pagination later
    // NOTE: PodcastIndex API (basic search) doesn't support pagination in the same way Taddy does.
    // The 'max' parameter controls total results. Pagination buttons are omitted here for PI results.
    $output .= '<div class="bottom-row-container">'
             .   '<div class="left-side">'
             .     '<button type="button" id="add-to-formidable-button" ' // Use ID for JS targeting
             .             'class="add-to-formidable-button">' // Use class for styling if needed
             .         'Import Selected'
             .     '</button>'
             .   '</div>'
             .   '<div class="right-side">'
             .     '<div class="cta-upsearch">' // Placeholder for consistency, content can vary
            //  .         '<span>PodcastIndex results limit controlled by "Number of Results" dropdown.</span>' // Example text
             .         '<a class="btn-trial-tbl-cta" href="/pricing" role="button">'
             .           'Upgrade for More Features & Results' // Generic upgrade CTA
             .         '</a>'
             .     '</div>'
             .   '</div>'
             . '</div>'; // End bottom-row-container

    // Hidden field to potentially pass search term (useful if JS needs it later)
    $output .= '<input type="hidden" name="search_term" value="' . esc_attr( $search_term ) . '">';
    $output .= '</form>'; // Close form
    $output .= '</div>'; // Close podsearch-results-container

    return $output;
}

// AJAX handlers
add_action('wp_ajax_perform_search', 'handle_perform_search');
add_action('wp_ajax_nopriv_perform_search', 'handle_perform_search');

function search_taddy_episodes($search_term, $language, $country, $genre, $after_date, $before_date, $isSafeMode = false) {
    error_log("Entering search_taddy_episodes function");
    
    // 1. Current page from $_POST
    $current_page = isset($_POST['page']) && !empty($_POST['page']) ? (int)$_POST['page'] : 1;
    error_log("Taddy API Request Page: " . $current_page);

    // 2. Results per page
    $results_per_page = isset($_POST['results_per_page']) ? (int) $_POST['results_per_page'] : 10;
    if ($results_per_page < 5) {
        $results_per_page = 5;
    }
    if ($results_per_page > 25) {
        $results_per_page = 25;
    }

    error_log("Processed results_per_page: " . $results_per_page);

    // 3. Build filters
    $language_filter = $language !== 'ALL' ? "filterForLanguages: [$language]" : '';
    $country_filter  = $country !== 'ALL' ? "filterForCountries: [$country]" : '';
    $genre_filter    = $genre !== 'ALL' ? "filterForGenres: [$genre]" : '';
    $safe_mode_filter = "isSafeMode: " . ($isSafeMode ? 'true' : 'false');

    $date_filters = [];
    if (!empty($after_date)) {
        $timestamp_after = strtotime($after_date . ' UTC');
        $date_filters[] = "filterForPublishedAfter: $timestamp_after";
    }
    if (!empty($before_date)) {
        $timestamp_before = strtotime($before_date . ' 23:59:59 UTC');
        $date_filters[] = "filterForPublishedBefore: $timestamp_before";
    }

    error_log("Date filters for Episodes: " . json_encode($date_filters));

    $filters = [$language_filter, $country_filter, $genre_filter, implode(", ", $date_filters), $safe_mode_filter];
    $filter_string = buildFilterString($filters);
    $query_filters = !empty($filter_string) ? ", $filter_string" : '';

    // 4. Grab the sort order from $_POST
    $sort_order = isset($_POST['sort_order']) ? sanitize_text_field($_POST['sort_order']) : 'BEST_MATCH';
    error_log("Sort order selected: " . $sort_order);

    // If user picks BEST_MATCH, we omit the sort line
    $sort_line = '';
    if ($sort_order !== 'BEST_MATCH') {
        // e.g., LATEST or OLDEST
        $sort_line = "sortByDatePublished: $sort_order,";
    }

    // 5. Build the GraphQL query for episodes
    $query = <<<GRAPHQL
    {
        searchForTerm(
            term: "$search_term",
            limitPerPage: $results_per_page,
            page: $current_page,
            filterForTypes: PODCASTEPISODE,
            $sort_line
            includeSearchOperator: AND$query_filters
        ) {
            searchId
            podcastEpisodes {
                uuid
                name
				guid
                audioUrl
                datePublished
                description
                podcastSeries {
                    uuid
                    name
					authorName
                    description
                    imageUrl
                    genres
                    itunesId
                    language
                    isExplicitContent
                    rssUrl
                    websiteUrl
                    episodes(sortOrder: LATEST, limitPerPage: 1) {
                        uuid
						guid
                        datePublished
                    }
                }
            }
        }
    }
GRAPHQL;

    error_log("Final GraphQL Query for Episodes: " . $query);

    // 6. Make the Taddy API request
    $response = taddy_api_request($query);
    // error_log("Response for Episodes: " . json_encode($response));

    return $response;
}


function search_taddy_podcasts($search_term, $language, $country, $genre, $after_date, $before_date, $isSafeMode = false) {
    error_log("Entering search_taddy_podcasts function");
    
    // 1. Current page from AJAX
    $current_page = isset($_POST['page']) && !empty($_POST['page']) ? (int)$_POST['page'] : 1;
    error_log("Taddy API Request Page: " . $current_page);

    // 2. Results per page, clamped to safe range
    $results_per_page = isset($_POST['results_per_page']) ? (int) $_POST['results_per_page'] : 10;
    if ($results_per_page < 5) {
        $results_per_page = 5;
    }
    if ($results_per_page > 25) {
        $results_per_page = 25;
    }

    error_log("Processed results_per_page: " . $results_per_page);

    // 3. Build filter strings (language, country, genre, safe mode, dates)
    $language_filter = $language !== 'ALL' ? "filterForLanguages: [$language]" : '';
    $country_filter  = $country !== 'ALL' ? "filterForCountries: [$country]" : '';
    $genre_filter    = $genre !== 'ALL' ? "filterForGenres: [$genre]" : '';
    $safe_mode_filter = "isSafeMode: " . ($isSafeMode ? 'true' : 'false');

    $date_filters = [];
    if (!empty($after_date)) {
        $timestamp_after = strtotime($after_date . ' UTC');
        $date_filters[] = "filterForPublishedAfter: $timestamp_after";
    }
    if (!empty($before_date)) {
        $timestamp_before = strtotime($before_date . ' 23:59:59 UTC');
        $date_filters[] = "filterForPublishedBefore: $timestamp_before";
    }

    error_log("Date filters for Podcasts: " . json_encode($date_filters));

    // Put all filters into one array, then build the final string
    $filters = [$language_filter, $country_filter, $genre_filter, implode(", ", $date_filters), $safe_mode_filter];
    $filter_string = buildFilterString($filters);
    $query_filters = !empty($filter_string) ? ", $filter_string" : '';

    // 4. Retrieve sort order from $_POST, default to 'LATEST'
    $sort_order = isset($_POST['sort_order']) ? sanitize_text_field($_POST['sort_order']) : 'BEST_MATCH';
    error_log("Sort order selected: " . $sort_order);

    // If user picks BEST_MATCH, we omit the sort line
    $sort_line = '';
    if ($sort_order !== 'BEST_MATCH') {
        // e.g., LATEST or OLDEST
        $sort_line = "sortByDatePublished: $sort_order,";
    }
	
    // 5. Construct the GraphQL query
    $query = <<<GRAPHQL
    {
        searchForTerm(
            term: "$search_term",
            limitPerPage: $results_per_page,
            page: $current_page,
            filterForTypes: PODCASTSERIES,
            $sort_line
            includeSearchOperator: AND$query_filters
        ) {
            searchId
            podcastSeries {
                uuid
                name
				authorName
                description
                imageUrl
                genres
                totalEpisodesCount
                itunesId
                language
                isExplicitContent
                rssUrl
                websiteUrl
                episodes(sortOrder: LATEST, limitPerPage: 1) {
                    uuid
                    datePublished
                }
            }
        }
    }
GRAPHQL;

    error_log("Final GraphQL Query for Podcasts: " . str_replace("\n", '', $query));

    // 6. Execute API request
    $response = taddy_api_request($query);
    // error_log("API Response for Podcasts: " . json_encode($response));

    return $response;
}

function taddy_api_request($query) {
    $apiKey = '651633ec826d9c0ce016d3c8576fa56fed9fe10e9c96f0dd77b8f003d2fc796022879e288271f402756cc8a343306da0a8';
    $userId = '665';

    define('TADDY_API_URL', 'https://api.taddy.org');

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, TADDY_API_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, 1);
    $data = ['query' => $query];
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    $headers = [
        'Content-Type: application/json',
        'X-USER-ID: ' . $userId,
        'X-API-KEY: ' . $apiKey
    ];

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);

    if ($response === false) {
        echo 'cURL Error: ' . curl_error($ch);
    } else {
        $responseData = json_decode($response, true);
    }

    curl_close($ch);

    // Log
    error_log("Taddy API Query: $query");
    error_log("Taddy API Response: " . json_encode($responseData));

    return $responseData;
}

/**
 * Here we update the pagination buttons to type="button"
 * and rely on JS to trigger an AJAX call instead of a form submit
 */
function display_taddy_api_podcast_results($response, $search_term = '') {
    global $wpdb;
    $output = '';
    $user_id                = get_current_user_id();
    $settings               = podcast_prospector_get_membership_settings($user_id);
    $max_pages_allowed      = $settings['max_pages'];
    $max_results_per_page   = $settings['max_results_per_page'];
    $force_safe_mode        = $settings['safe_mode_forced'];

    $results = ! empty( $response['data']['searchForTerm']['podcastSeries'] )
        ? $response['data']['searchForTerm']['podcastSeries']
        : [];

    $current_page   = isset($_POST['page']) ? (int) $_POST['page'] : 1;
    $results_count  = count($results);
    $limit_per_page = min(
        max( (int)($_POST['results_per_page'] ?? 10), 1 ),
        $max_results_per_page
    );

    $next_page = ( $results_count >= $limit_per_page && $current_page < $max_pages_allowed )
        ? $current_page + 1
        : false;

    if ( $results_count > 0 ) {
        $output .= '<form method="post" action="/export-csv/">';
        $output .= '<table class="podcast-results-table">';
        $output .= '<thead>
                        <tr>
                          <th><input type="checkbox" id="select_all" /></th>
                          <th>Details</th>
                          <th>Categories</th>
                          <th>Language</th>
                          <th>Status</th>
                          <th>Last Episode</th>
                          <th>Explicit</th>
                          <th>Publisher</th>
                        </tr>
                    </thead>
                    <tbody>';

        $three_months_ago = strtotime('-3 months');

        foreach ( $results as $index => $podcast ) {
            // --- Language (proper-case) ---
            $lang_code     = $podcast['language'] ?? 'N/A';
            $raw_name      = podcast_prospector_get_language_name($lang_code);
            $language_name = ucwords(strtolower($raw_name));

            // --- Status icon only ---
            $status_icon = '<i class="fas fa-exclamation-triangle" style="color:red;"></i>';
            $lastEpTs    = $podcast['episodes'][0]['datePublished'] ?? null;
            if ( $lastEpTs && $lastEpTs >= $three_months_ago ) {
                $status_icon = '<i class="fas fa-check-circle" style="color:green;"></i>';
            } elseif ( empty($lastEpTs) && ! empty($podcast['rssUrl']) ) {
                $rss_data = extract_last_episode_data($podcast['rssUrl']);
                if ( ! empty($rss_data['lastEpisodePubDate']) ) {
                    $rssTs = strtotime($rss_data['lastEpisodePubDate']);
                    if ( $rssTs >= $three_months_ago ) {
                        $status_icon = '<i class="fas fa-check-circle" style="color:green;"></i>';
                    }
                }
            }

            // --- Last Episode date ---
            if ( ! empty($podcast['episodes'][0]['datePublished']) ) {
                $lastEpisodeDate = date('Y-m-d', $podcast['episodes'][0]['datePublished']);
            } elseif ( ! empty($podcast['rssUrl']) ) {
                $rss_data         = extract_last_episode_data($podcast['rssUrl']);
                $lastEpisodeDate  = ! empty($rss_data['lastEpisodePubDate'])
                    ? date('Y-m-d', strtotime($rss_data['lastEpisodePubDate']))
                    : 'N/A';
            } else {
                $lastEpisodeDate = 'N/A';
            }

            // --- Other fields ---
            $name        = $podcast['name']        ?? 'N/A';
            $imageUrl    = $podcast['imageUrl']    ?? '';
            $description = $podcast['description'] ?? '';
            $publisher   = $podcast['authorName']  ?? 'N/A';
            $genres      = $podcast['genres']      ?? [];

            // --- Build the row ---
            $output .= '<tr>';
            $output .= '<td><input type="checkbox" name="selected_podcasts[]" value="' 
                     . esc_attr( json_encode($podcast) ) . '"></td>';

            // DETAILS cell
            $output .= '<td>';
            if ( $imageUrl ) {
                $output .= '<img src="' . esc_url($imageUrl) 
                         . '" class="podcast-feed-image" alt="">';
            }
            $output .= '<div class="podcast-details">';
            $output .=    '<strong>' . esc_html($name) . '</strong><br>';		
 // --- MODIFICATION START ---
			$description = $podcast['description'] ?? '';
			$highlighted_description = highlight_search_term($description, $search_term);
			// --- MODIFICATION END ---

			// --- MODIFICATION HERE (Use the highlighted version) ---
			if ( $description ) { // Still check if original description existed
				$toggle_id = esc_attr( $podcast['uuid'] ?? $index );
				$output .= '<div class="shared-expand simple-expand">
								<input id="toggle-podcast-' . $toggle_id . '"
									   type="checkbox" class="toggle-checkbox">
								<label for="toggle-podcast-' . $toggle_id . '"
									   class="expand-toggle">View Podcast Description</label>
								<div class="expandcontent"><section>'
								// Use the highlighted description variable
							  . wp_kses_post($highlighted_description) .
								'</section></div>
							</div>';
			}
			// --- END MODIFICATION ---
			$json_attr = esc_attr( json_encode($podcast) );
			$output  .= '<div class="details-actions">
                             <button type="button" class="individual-import-button" 
                                     data-podcast="' . $json_attr . '">
                               Import to Tracker
                             </button>
                          </div>';
            $output .= '</div>';  // .podcast-details
            $output .= '</td>';

            // CATEGORIES
            $output .= '<td>';
            if ( is_array($genres) && count($genres) ) {
                foreach ( $genres as $g ) {
                    $label = ucwords(strtolower(str_replace('_',' ',
                              substr($g,13))));
                    $output .= '<span class="category-tag">'
                             . esc_html($label) . '</span> ';
                }
            } else {
                $output .= 'N/A';
            }
            $output .= '</td>';

            // Language, Status, Last Episode, Explicit, Publisher
            $output .= '<td>' . esc_html($language_name) . '</td>';
            $output .= '<td>' . $status_icon . '</td>';
            $output .= '<td>' . esc_html($lastEpisodeDate) . '</td>';

            $exp = $podcast['isExplicitContent'] ?? false;
            if ( $force_safe_mode && $exp ) {
                $exp_val = 'Locked';
            } else {
                $exp_val = $exp ? 'Yes' : 'No';
            }
            $output .= '<td>' . esc_html($exp_val) . '</td>';
            $output .= '<td>' . esc_html($publisher) . '</td>';

            $output .= '</tr>';
        }

        $output .= '</tbody></table>';

        // Bottom row: bulk import + pagination + upsell
        $output .= '<div class="bottom-row-container">
                        <div class="left-side">
                          <button type="button" id="add-to-formidable-button" 
                                  class="add-to-formidable-button">
                            Import Selected
                          </button>
                        </div>
                        <div class="right-side">
                          <div class="pagination-controls">';
        if ( $current_page > 1 ) {
            $output .= '<button type="button" class="pagination-btn prev-page" 
                               data-page="' . ($current_page - 1) . '">
                            ← Previous
                        </button>';
        }
        if ( $next_page ) {
            $output .= '<button type="button" class="pagination-btn next-page" 
                               data-page="' . $next_page . '">
                            Next →
                        </button>';
        }
        $output .=         '</div>
                          <div class="cta-upsearch">
                            <a class="btn-trial-tbl-cta" href="/pricing" role="button">
                              Upgrade to View More Search Results
                            </a>
                          </div>
                        </div>
                     </div>';
        $output .= '<input type="hidden" name="search_term" value="' 
                 . esc_attr($search_term) . '">';
        $output .= '</form>';
    } else {
        $output = '<p>No podcasts found.</p>';
    }

    return $output;
}

/**
 * Displays Taddy API episode search results in an HTML table with highlighting.
 *
 * @param array  $response    The decoded JSON response from the Taddy API.
 * @param string $search_term The term used for the search, for highlighting.
 * @return string HTML output for the results table and pagination.
 */
function display_taddy_episode_results($response, $search_term = '') {
    $output = '';
    $user_id               = get_current_user_id();
    $settings              = podcast_prospector_get_membership_settings($user_id);
    $max_pages_allowed     = $settings['max_pages'];
    $max_results_per_page  = $settings['max_results_per_page'];
    $force_safe_mode       = $settings['safe_mode_forced'];

    $episodes      = $response['data']['searchForTerm']['podcastEpisodes'] ?? [];
    $current_page  = (int) ($_POST['page'] ?? 1);
    $results_count = count($episodes);
    $limit_per_page = min(
        max( (int)($_POST['results_per_page'] ?? 10), 1 ),
        $max_results_per_page
    );

    $next_page = ( $results_count >= $limit_per_page && $current_page < $max_pages_allowed )
        ? $current_page + 1
        : false;

    if ( $results_count > 0 ) {
        $output .= '<form method="post" action="/export-csv/">'; // Assuming you might still want this form wrapper
        $output .= '<table class="podcast-results-table">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="select_all" title="Select All/None"/></th>
                                <th>Details</th>
                                <th>Categories</th>
                                <th>Language</th>
                                <th>Status</th>
                                <th>Episode Date</th>
                                <th>Explicit</th>
                                <th>Publisher</th>
                            </tr>
                        </thead>
                        <tbody>';

        $three_months_ago = strtotime('-3 months');

        foreach ( $episodes as $index => $ep ) {
            $series     = $ep['podcastSeries'] ?? [];
            $pod_name   = $series['name']        ?? 'N/A';
            $imageUrl   = $series['imageUrl']    ?? '';
            $rssUrl     = $series['rssUrl']      ?? '';
            $genres     = $series['genres']      ?? [];
            $publisher  = $series['authorName']  ?? 'N/A';

            // --- Language (proper-case) ---
            $lang_code   = $series['language'] ?? 'N/A';
            $raw_name    = podcast_prospector_get_language_name($lang_code);
            $language    = ucwords(strtolower($raw_name));

            // --- Status icon only via RSS fallback ---
            $status_icon = '<i class="fas fa-exclamation-triangle" style="color:red;"></i>';
            $lastEpisodeDateForStatusCheck = 'N/A'; // Use a separate var for status check date
            if ( $rssUrl ) {
                $rss_data = extract_last_episode_data($rssUrl); // Assuming this function exists and gets latest ep date
                $lastPub  = $rss_data['lastEpisodePubDate'] ?? '';
                if ( $lastPub ) {
                    $lastEpisodeDateForStatusCheck = $lastPub; // Store the date found in RSS
                    if ( strtotime($lastPub) >= $three_months_ago ) {
                        $status_icon = '<i class="fas fa-check-circle" style="color:green;"></i>';
                    }
                 }
            }
             // If RSS didn't provide a date, maybe use the current episode's date? (Optional, depends on desired logic)
            if ($lastEpisodeDateForStatusCheck === 'N/A' && isset($ep['datePublished'])) {
                 if ($ep['datePublished'] >= $three_months_ago) {
                     // Consider episode itself as "active enough" if published recently
                      // $status_icon = '<i class="fas fa-check-circle" style="color:green;"></i>'; // Uncomment if desired
                 }
            }


            // --- This episode’s date (for the 'Episode Date' column) ---
            $episodeTs = $ep['datePublished'] ?? null;
            $episodeDateDisplay = $episodeTs ? date('Y-m-d', $episodeTs) : 'N/A';


            // --- Explicit ---
            $exp = $series['isExplicitContent'] ?? false;
            if ( $force_safe_mode && $exp ) {
                $exp_val = 'Locked';
            } else {
                $exp_val = $exp ? 'Yes' : 'No';
            }

            // JSON for import buttons
            $item_json_data = esc_attr(json_encode($ep));

            // --- Build the row ---
            $output .= '<tr>';
            $output .= '<td><input type="checkbox" name="selected_podcasts[]" value="' . $item_json_data . '"></td>';

            // DETAILS cell
            $output .= '<td>';
            if ( $imageUrl ) {
                $output .= '<img src="' . esc_url($imageUrl)
                         . '" class="podcast-feed-image" alt="">';
            }
            $output .= '<div class="podcast-details">';
            $output .=     '<strong>' . esc_html($pod_name) . '</strong><br>';
            $output .=     'Episode: ' . esc_html($ep['name'] ?? 'N/A') . '<br>';

            // --- HIGHLIGHT EPISODE DESCRIPTION ---
            $ep_desc = $ep['description'] ?? '';
            // Apply highlighting using the helper function
            $highlighted_ep_desc = highlight_search_term($ep_desc, $search_term);
            if ( ! empty($ep_desc) ) { // Check original description for existence
                $tid_ep = esc_attr($ep['uuid'] ?? 'ep'.$index); // Unique ID for episode toggle
                $output .= '<div class="shared-expand simple-expand">
                                <input id="toggle-episode-' . $tid_ep . '"
                                       type="checkbox" class="toggle-checkbox">
                                <label for="toggle-episode-' . $tid_ep . '"
                                       class="expand-toggle">View Episode Description</label>
                                <div class="expandcontent"><section>'
                              // Output the highlighted and sanitized version
                              . wp_kses_post($highlighted_ep_desc) .
                                '</section></div>
                            </div>';
            }
            // --- END EPISODE DESCRIPTION HIGHLIGHT ---

            // --- HIGHLIGHT PODCAST SERIES DESCRIPTION ---
            $pod_desc = $series['description'] ?? '';
             // Apply highlighting using the helper function
            $highlighted_pod_desc = highlight_search_term($pod_desc, $search_term);
            if ( $pod_desc ) { // Check original description for existence
                $tid_series = esc_attr($series['uuid'] ?? 'series'.$index); // Unique ID for series toggle
                $output .= '<div class="shared-expand simple-expand">
                                <input id="toggle-series-' . $tid_series . '"
                                       type="checkbox" class="toggle-checkbox">
                                <label for="toggle-series-' . $tid_series . '"
                                       class="expand-toggle">View Podcast Description</label>
                                <div class="expandcontent"><section>'
                              // Output the highlighted and sanitized version
                              . wp_kses_post($highlighted_pod_desc) .
                                '</section></div>
                            </div>';
            }
            // --- END PODCAST SERIES DESCRIPTION HIGHLIGHT ---

            $output  .= '<div class="details-actions">
                               <button type="button" class="individual-import-button"
                                       data-podcast="' . $item_json_data . '">
                                  Import to Tracker
                               </button>
                           </div>';
            $output .= '</div>';  // .podcast-details
            $output .= '</td>';

            // CATEGORIES
            $output .= '<td>';
            if ( is_array($genres) && count($genres) ) {
                foreach ( $genres as $g ) {
                    $label = ucwords(strtolower(str_replace('_',' ',
                             substr($g,13)))); // Remove 'PODCASTSERIES_' prefix
                    $output .= '<span class="category-tag">'
                             . esc_html($label) . '</span> ';
                }
            } else {
                $output .= 'N/A';
            }
            $output .= '</td>';

            // Language, Status, Episode Date, Explicit, Publisher
            $output .= '<td>' . esc_html($language) . '</td>';
            $output .= '<td>' . $status_icon . '</td>'; // Status icon (based on last RSS ep date or maybe current ep date)
            $output .= '<td>' . esc_html($episodeDateDisplay) . '</td>'; // The date this specific episode was published
            $output .= '<td>' . esc_html($exp_val) . '</td>';
            $output .= '<td>' . esc_html($publisher) . '</td>';

            $output .= '</tr>';
        } // End foreach episode

        $output .= '</tbody></table>';

        // Bottom row: bulk import + pagination + upsell
        $output .= '<div class="bottom-row-container">
                        <div class="left-side">
                            <button type="button" id="add-to-formidable-button"
                                    class="add-to-formidable-button">
                                Import Selected
                            </button>
                        </div>
                        <div class="right-side">
                            <div class="pagination-controls">';
        if ( $current_page > 1 ) {
            $output .= '<button type="button" class="pagination-btn prev-page"
                                data-page="' . ($current_page - 1) . '">
                            &larr; Previous
                        </button>';
        }
        if ( $next_page ) {
            $output .= '<button type="button" class="pagination-btn next-page"
                                data-page="' . $next_page . '">
                            Next &rarr;
                        </button>';
        }
        $output .=         '</div>
                            <div class="cta-upsearch">
                                <a class="btn-trial-tbl-cta" href="/pricing" role="button">
                                    Upgrade to View More Search Results
                                </a>
                            </div>
                        </div>
                    </div>';
        $output .= '<input type="hidden" name="search_term" value="'
                 . esc_attr($search_term) . '">'; // Pass search term for potential JS use later if needed
        $output .= '</form>';
    } else {
        $output = '<p>No episodes found matching your criteria.</p>'; // More specific message
    }

    return $output;
}


function format_genre($genre) {
    $genre = str_replace('PODCASTSERIES_', '', $genre);
    $genre = str_replace('_', ' ', $genre);
    return ucwords(strtolower($genre));
}

// Master AJAX handler
function handle_perform_search() {
    if (!isset($_POST['search_term']) || !isset($_POST['search_type'])) {
        wp_send_json_error(['message' => 'Missing required parameters']);
        return;
    }

    $user_id = get_current_user_id();
    $ghl_id = get_user_meta($user_id, 'highlevel_contact_id', true);
    $subscription_date = get_user_meta($user_id, 'guestify_subscription_date', true);
    $search_cap = get_user_meta($user_id, 'podcast_prospector_search_cap', true);

    if (!$ghl_id && !$user_id) {
        wp_send_json_error(['message' => 'Error: Required user data not found.']);
        return;
    }
    
    // Ensure a row exists for this user.
    $user_data = podcast_prospector_get_or_create_user_data($ghl_id, $user_id);

    // Now perform the reset check.
    podcast_prospector_reset_search_cap_if_needed($ghl_id, $user_id);

    // Retrieve the user's data from the custom table.
    $user_data = podcast_prospector_get_user_data($ghl_id, $user_id);
    $search_count = $user_data ? $user_data->search_count : 0;

    // Block search if the count exceeds the allowed cap.
    if ($search_count >= $search_cap) {
        wp_send_json_error(['message' => 'You have reached the maximum number of searches allowed for your plan.']);
        return;
    }

    // Increment search count (usage-based: starting from 0).
    podcast_prospector_update_user_data($ghl_id, $user_id);

    // Fetch updated user data.
    $updated_user_data = podcast_prospector_get_user_data($ghl_id, $user_id);
    $search_count = $updated_user_data->search_count ?? 0;
    $searches_remaining = $search_cap - $search_count;
    $last_searched = $updated_user_data->last_searched ?? 'N/A';

    // Sanitize input.
    $search_term = sanitize_text_field($_POST['search_term']);
    $search_type = sanitize_text_field($_POST['search_type']);
    $language = isset($_POST['language']) ? sanitize_text_field($_POST['language']) : 'ALL';
    $country = isset($_POST['country']) ? sanitize_text_field($_POST['country']) : 'ALL';
    $genre = isset($_POST['genre']) ? sanitize_text_field($_POST['genre']) : 'ALL';
    $after_date = isset($_POST['after_date']) ? sanitize_text_field($_POST['after_date']) : '';
    $before_date = isset($_POST['before_date']) ? sanitize_text_field($_POST['before_date']) : '';
    $isSafeMode = isset($_POST['isSafeMode']) ? !filter_var($_POST['isSafeMode'], FILTER_VALIDATE_BOOLEAN) : true;

    try {
        if ($search_type === 'byadvancedepisode') {
            $taddy_episode_data = search_taddy_episodes($search_term, $language, $country, $genre, $after_date, $before_date, $isSafeMode);
            $results = display_taddy_episode_results($taddy_episode_data, $search_term);
        } elseif ($search_type === 'byadvancedpodcast') {
            $taddy_podcast_data = search_taddy_podcasts($search_term, $language, $country, $genre, $after_date, $before_date, $isSafeMode);
            $results = display_taddy_api_podcast_results($taddy_podcast_data, $search_term);
        } else {
            $data = search_podcastindex($search_term, $search_type, $_POST['results_per_page']);
            $results = display_podcast_results($data, $search_term);
        }

        wp_send_json_success([
            'html' => $results,
            'user_data' => [
                'search_count' => $search_count,
                'searches_remaining' => $searches_remaining,
                'last_searched' => $last_searched,
                'subscription_date' => $subscription_date,
                'search_cap' => $search_cap,
            ],
        ]);
    } catch (Exception $e) {
        error_log("Error during search: " . $e->getMessage());
        wp_send_json_error(['message' => 'An error occurred during the search.', 'details' => $e->getMessage()]);
    }
}

function podcast_prospector_shortcode($atts) {
    $output = '';

    // Current user info
    $user_id           = get_current_user_id();
    $ghl_id            = get_user_meta($user_id, 'highlevel_contact_id', true);
    $subscription_date = get_user_meta($user_id, 'guestify_subscription_date', true);
    $search_cap        = get_user_meta($user_id, 'podcast_prospector_search_cap', true);
    $membership_level  = get_user_meta($user_id, 'guestify_membership', true);

    if (!$ghl_id && !$user_id) {
        return '<p>Error: Required user data not found.</p>';
    }

    // Get user data from DB
    $user_data    = podcast_prospector_get_user_data($ghl_id, $user_id);
    $search_count = $user_data ? $user_data->search_count : 0;
    $remaining    = $search_cap - $search_count;

    // Membership settings
    $membership = podcast_prospector_get_membership_settings($user_id);

    // Debug info (displayed to the user)
    /*
	$output .= "<p>
        You have made <strong id='search_count'>{$search_count}</strong> searches this month. 
        You have <strong id='searches_remaining'>{$remaining}</strong> searches remaining.<br>
        Subscription Date: <strong>{$subscription_date}</strong><br>
        Search Cap: <strong>{$search_cap}</strong><br>
        Membership Level: <strong>" . (!empty($membership_level) ? $membership_level : "Not Assigned") . "</strong><br>
    </p>";
	*/

    // Capture posted fields
    $search_type         = isset($_POST['search_type']) ? sanitize_text_field($_POST['search_type']) : 'byperson';
    $search_term         = isset($_POST['search_term']) ? sanitize_text_field($_POST['search_term']) : '';
    $number_of_results = isset($_POST['number_of_results']) ? (int)$_POST['number_of_results'] : 10;
    $results_per_page   = isset($_POST['results_per_page']) ? (int)$_POST['results_per_page'] : 10;
    $sort_order         = isset($_POST['sort_order']) ? sanitize_text_field($_POST['sort_order']) : 'BEST_MATCH';
    $language           = isset($_POST['language']) ? sanitize_text_field($_POST['language']) : 'ALL';
    $country            = isset($_POST['country'])  ? sanitize_text_field($_POST['country'])  : 'ALL';
    $genre              = isset($_POST['genre'])    ? sanitize_text_field($_POST['genre'])    : 'ALL';
    $after_date         = isset($_POST['after_date'])  ? sanitize_text_field($_POST['after_date'])  : '';
    $before_date        = isset($_POST['before_date']) ? sanitize_text_field($_POST['before_date']) : '';
    $isSafeMode         = isset($_POST['isSafeMode'])  ? filter_var($_POST['isSafeMode'], FILTER_VALIDATE_BOOLEAN) : false;

    // Radio tab checks
    $checked_byperson          = ($search_type == 'byperson') ? 'checked' : '';
    $checked_bytitle           = ($search_type == 'bytitle') ? 'checked' : '';
    $checked_byadvancedpodcast = ($search_type == 'byadvancedpodcast') ? 'checked' : '';
    $checked_byadvancedepisode = ($search_type == 'byadvancedepisode') ? 'checked' : '';

    // Start output – the wrapper and the form
    $output .= '<div class="search-form-wrapper">';
    $output .= '<form method="post" action="" class="search-form">';

    // ---------------------------------------------------
    // 1) Tabs
    // ---------------------------------------------------
    $output .= '<div class="tabs tabs-top">';
    $output .= '  <input type="radio" name="search_type" id="tab-person" value="byperson" ' . $checked_byperson . '>';
    $output .= '  <label for="tab-person">Search Episodes by Person</label>';
    $output .= '  <input type="radio" name="search_type" id="tab-title" value="bytitle" ' . $checked_bytitle . '>';
    $output .= '  <label for="tab-title">Search Podcasts by Title</label>';
    $output .= '  <input type="radio" name="search_type" id="tab-adv-podcasts" value="byadvancedpodcast" ' . $checked_byadvancedpodcast . '>';
    $output .= '  <label for="tab-adv-podcasts">Advanced Search Podcasts</label>';
    $output .= '  <input type="radio" name="search_type" id="tab-adv-episodes" value="byadvancedepisode" ' . $checked_byadvancedepisode . '>';
    $output .= '  <label for="tab-adv-episodes">Advanced Search Episodes</label>';
    $output .= '</div>';

    // ---------------------------------------------------
    // 2) Single row: search input + search button + filter (left)
    //                and either "Number of Results" or "Display/Sort" (right)
    // ---------------------------------------------------
    $output .= '<div class="search-row" style="display:flex; align-items:center; justify-content:space-between; margin-top:15px;">';
    $output .= '<div class="left-group" style="display:flex; gap:10px; align-items:center;">';
    $output .= '<input type="text" name="search_term" class="search-term" placeholder="Enter search term" value="' . esc_attr($search_term) . '" required style="min-width:220px;">';
    $output .= '<input type="submit" class="search-btn" value="Search">';
    $output .= '<button id="toggle-filters" class="filter-btn" type="button" style="display:none;">Filter</button>';
    $output .= '</div>';
    $output .= '<div class="right-group" style="display:flex; align-items:center; gap:10px;">';

    // Basic block: "Number of Results" dropdown for PodcastIndex searches.
    $output .= '<div id="basic-block" style="display:none;">';
    $output .= '  <label for="number_of_results" class="results-label">Number of Results:</label>';
    $output .= '  <select name="number_of_results" id="number_of_results" class="results-dropdown">';
    $basic_options = [5, 10, 25, 50];
    $basic_max     = $membership['podcastindex_max'];
    foreach ($basic_options as $val) {
        $sel = ($number_of_results == $val) ? 'selected' : '';
        if ($val > $basic_max) {
            $output .= "<option value='{$val}' disabled {$sel}>{$val} &#x1F512;</option>";
        } else {
            $output .= "<option value='{$val}' {$sel}>{$val}</option>";
        }
    }
    $output .= '  </select>';
    $output .= '</div>';

    // Advanced block: "Display" and "Sort By" for Taddy searches.
    $output .= '<div id="advanced-block" style="display:none;">';
    // "Display" dropdown – restrict options based on membership's max_results_per_page.
    $output .= '  <label for="results_per_page" class="results-label">Display:</label>';
    $output .= '  <select name="results_per_page" id="results_per_page" class="results-dropdown">';
    $advanced_options = [5, 10, 15, 20, 25];
    $advanced_max     = $membership['max_results_per_page'];
    foreach ($advanced_options as $opt) {
        $sel = ($results_per_page == $opt) ? 'selected' : '';
        if ($opt > $advanced_max) {
            $output .= "<option value='{$opt}' disabled {$sel}>{$opt} &#x1F512;</option>";
        } else {
            $output .= "<option value='{$opt}' {$sel}>{$opt}</option>";
        }
    }
    $output .= '  </select>';

// "Sort By" dropdown – always show all options, but disable non-default ones if not allowed
$all_sort_options = ["BEST_MATCH", "LATEST", "OLDEST"];
$output .= '  <label for="sort_order" class="results-label">Sort By:</label>';
$output .= '  <select name="sort_order" id="sort_order" class="results-dropdown">';

foreach ($all_sort_options as $option) {
    $sel = selected($sort_order, $option, false);

    // Always allow BEST_MATCH
    if ($option === "BEST_MATCH") {
        $disabled = "";
        $lock_icon = "";
    } else {
        $disabled = in_array($option, $membership['sort_by_date_published_options']) ? "" : "disabled";
        $lock_icon = $disabled ? " &#x1F512;" : "";
    }

    // Transform the display label (e.g. BEST_MATCH -> Best Match)
    $display_label = str_replace('_', ' ', $option);       // e.g. "BEST_MATCH" -> "BEST MATCH"
    $display_label = ucwords(strtolower($display_label));  // "BEST MATCH" -> "Best Match"

    $output .= '<option value="' . esc_attr($option) . '" ' . $sel . ' ' . $disabled . '>'
            . $display_label
            . $lock_icon
            . '</option>';
}

	$output .= '  </select>';
    $output .= '</div>';

    $output .= '</div>'; // .right-group
    $output .= '</div>'; // .search-row

    // ---------------------------------------------------
    // 3) Filter sidebar for advanced filters (grid layout)
    // ---------------------------------------------------
    // We'll wrap each label+field pair in .filter-group
    $output .= '<div id="filter-sidebar" style="display:none; margin-top:15px;">';

    // =============== LANGUAGE
    $output .= '<div class="filter-group">';
    $output .= '<label for="language">Language:';
    if (!$membership['can_filter_language']) {
        $output .= ' <span class="locked" title="Upgrade"><i class="fas fa-lock"></i></span>';
    }
    $output .= '</label>';
    $output .= '<select name="language" id="language" ' . (!$membership['can_filter_language'] ? 'disabled' : '') . '>';
    $top_languages = array(
        "ALL",
        "ENGLISH",
        "FRENCH",
        "SPANISH",
        "GERMAN",
        "ITALIAN",
        "PORTUGUESE"
    );
    foreach ($top_languages as $lang) {
        $display_label = ucwords(strtolower(str_replace('_', ' ', $lang)));
        $sel = selected($language, $lang, false);
        $output .= "<option value='{$lang}' {$sel}>{$display_label}</option>";
    }
    $output .= '</select>';
    $output .= '</div>'; // .filter-group

    // =============== COUNTRY
    $output .= '<div class="filter-group">';
    $output .= '<label for="country">Country:';
    if (!$membership['can_filter_country']) {
        $output .= ' <span class="locked" title="Upgrade"><i class="fas fa-lock"></i></span>';
    }
    $output .= '</label>';
    $output .= '<select name="country" id="country" ' . (!$membership['can_filter_country'] ? 'disabled' : '') . '>';
    $top_countries = array(
        "ALL",
        "UNITED_STATES_OF_AMERICA",
        "CANADA",
        "UNITED_KINGDOM",
        "AUSTRALIA",
        "GERMANY",
        "FRANCE",
        "SPAIN",
        "ITALY",
        "INDIA",
        "CHINA"
    );
    foreach ($top_countries as $c) {
        if ($c === "ALL") {
            $display_label = "All";
        } else {
            $display_label = ucwords(strtolower(str_replace('_', ' ', $c)));
        }
        $sel = selected($country, $c, false);
        $output .= "<option value='{$c}' {$sel}>{$display_label}</option>";
    }
    $output .= '</select>';
    $output .= '</div>'; // .filter-group

    // =============== GENRE
    $output .= '<div class="filter-group">';
    $output .= '<label for="genre">Genre:';
    if (!$membership['can_filter_genre']) {
        $output .= ' <span class="locked" title="Upgrade"><i class="fas fa-lock"></i></span>';
    }
    $output .= '</label>';
    $output .= '<select name="genre" id="genre" ' . (!$membership['can_filter_genre'] ? 'disabled' : '') . '>';
    $all_genres = array(
        "ALL",
        "PODCASTSERIES_ARTS",
        "PODCASTSERIES_ARTS_BOOKS",
        "PODCASTSERIES_ARTS_DESIGN",
        "PODCASTSERIES_ARTS_FASHION_AND_BEAUTY",
        "PODCASTSERIES_ARTS_FOOD",
        "PODCASTSERIES_ARTS_PERFORMING_ARTS",
        "PODCASTSERIES_ARTS_VISUAL_ARTS",
        "PODCASTSERIES_BUSINESS",
        "PODCASTSERIES_BUSINESS_CAREERS",
        "PODCASTSERIES_BUSINESS_ENTREPRENEURSHIP",
        "PODCASTSERIES_BUSINESS_INVESTING",
        "PODCASTSERIES_BUSINESS_MANAGEMENT",
        "PODCASTSERIES_BUSINESS_MARKETING",
        "PODCASTSERIES_BUSINESS_NON_PROFIT",
        "PODCASTSERIES_COMEDY",
        "PODCASTSERIES_COMEDY_INTERVIEWS",
        "PODCASTSERIES_COMEDY_IMPROV",
        "PODCASTSERIES_COMEDY_STANDUP",
        "PODCASTSERIES_EDUCATION",
        "PODCASTSERIES_EDUCATION_COURSES",
        "PODCASTSERIES_EDUCATION_HOW_TO",
        "PODCASTSERIES_EDUCATION_LANGUAGE_LEARNING",
        "PODCASTSERIES_EDUCATION_SELF_IMPROVEMENT",
        "PODCASTSERIES_FICTION",
        "PODCASTSERIES_FICTION_COMEDY_FICTION",
        "PODCASTSERIES_FICTION_DRAMA",
        "PODCASTSERIES_FICTION_SCIENCE_FICTION",
        "PODCASTSERIES_GOVERNMENT",
        "PODCASTSERIES_HISTORY",
        "PODCASTSERIES_HEALTH_AND_FITNESS",
        "PODCASTSERIES_HEALTH_AND_FITNESS_ALTERNATIVE_HEALTH",
        "PODCASTSERIES_HEALTH_AND_FITNESS_FITNESS",
        "PODCASTSERIES_HEALTH_AND_FITNESS_MEDICINE",
        "PODCASTSERIES_HEALTH_AND_FITNESS_MENTAL_HEALTH",
        "PODCASTSERIES_HEALTH_AND_FITNESS_NUTRITION",
        "PODCASTSERIES_HEALTH_AND_FITNESS_SEXUALITY",
        "PODCASTSERIES_KIDS_AND_FAMILY",
        "PODCASTSERIES_KIDS_AND_FAMILY_EDUCATION_FOR_KIDS",
        "PODCASTSERIES_KIDS_AND_FAMILY_PARENTING",
        "PODCASTSERIES_KIDS_AND_FAMILY_PETS_AND_ANIMALS",
        "PODCASTSERIES_KIDS_AND_FAMILY_STORIES_FOR_KIDS",
        "PODCASTSERIES_LEISURE",
        "PODCASTSERIES_LEISURE_ANIMATION_AND_MANGA",
        "PODCASTSERIES_LEISURE_AUTOMOTIVE",
        "PODCASTSERIES_LEISURE_AVIATION",
        "PODCASTSERIES_LEISURE_CRAFTS",
        "PODCASTSERIES_LEISURE_GAMES",
        "PODCASTSERIES_LEISURE_HOBBIES",
        "PODCASTSERIES_LEISURE_HOME_AND_GARDEN",
        "PODCASTSERIES_LEISURE_VIDEO_GAMES",
        "PODCASTSERIES_MUSIC",
        "PODCASTSERIES_MUSIC_COMMENTARY",
        "PODCASTSERIES_MUSIC_HISTORY",
        "PODCASTSERIES_MUSIC_INTERVIEWS",
        "PODCASTSERIES_NEWS",
        "PODCASTSERIES_NEWS_BUSINESS",
        "PODCASTSERIES_NEWS_DAILY_NEWS",
        "PODCASTSERIES_NEWS_ENTERTAINMENT",
        "PODCASTSERIES_NEWS_COMMENTARY",
        "PODCASTSERIES_NEWS_POLITICS",
        "PODCASTSERIES_NEWS_SPORTS",
        "PODCASTSERIES_NEWS_TECH",
        "PODCASTSERIES_RELIGION_AND_SPIRITUALITY",
        "PODCASTSERIES_RELIGION_AND_SPIRITUALITY_BUDDHISM",
        "PODCASTSERIES_RELIGION_AND_SPIRITUALITY_CHRISTIANITY",
        "PODCASTSERIES_RELIGION_AND_SPIRITUALITY_HINDUISM",
        "PODCASTSERIES_RELIGION_AND_SPIRITUALITY_ISLAM",
        "PODCASTSERIES_RELIGION_AND_SPIRITUALITY_JUDAISM",
        "PODCASTSERIES_RELIGION_AND_SPIRITUALITY_RELIGION",
        "PODCASTSERIES_RELIGION_AND_SPIRITUALITY_SPIRITUALITY",
        "PODCASTSERIES_SCIENCE",
        "PODCASTSERIES_SCIENCE_ASTRONOMY",
        "PODCASTSERIES_SCIENCE_CHEMISTRY",
        "PODCASTSERIES_SCIENCE_EARTH_SCIENCES",
        "PODCASTSERIES_SCIENCE_LIFE_SCIENCES",
        "PODCASTSERIES_SCIENCE_MATHEMATICS",
        "PODCASTSERIES_SCIENCE_NATURAL_SCIENCES",
        "PODCASTSERIES_SCIENCE_NATURE",
        "PODCASTSERIES_SCIENCE_PHYSICS",
        "PODCASTSERIES_SCIENCE_SOCIAL_SCIENCES",
        "PODCASTSERIES_SOCIETY_AND_CULTURE",
        "PODCASTSERIES_SOCIETY_AND_CULTURE_DOCUMENTARY",
        "PODCASTSERIES_SOCIETY_AND_CULTURE_PERSONAL_JOURNALS",
        "PODCASTSERIES_SOCIETY_AND_CULTURE_PHILOSOPHY",
        "PODCASTSERIES_SOCIETY_AND_CULTURE_PLACES_AND_TRAVEL",
        "PODCASTSERIES_SOCIETY_AND_CULTURE_RELATIONSHIPS",
        "PODCASTSERIES_SPORTS",
        "PODCASTSERIES_SPORTS_BASEBALL",
        "PODCASTSERIES_SPORTS_BASKETBALL",
        "PODCASTSERIES_SPORTS_CRICKET",
        "PODCASTSERIES_SPORTS_FANTASY_SPORTS",
        "PODCASTSERIES_SPORTS_FOOTBALL",
        "PODCASTSERIES_SPORTS_GOLF",
        "PODCASTSERIES_SPORTS_HOCKEY",
        "PODCASTSERIES_SPORTS_RUGBY",
        "PODCASTSERIES_SPORTS_RUNNING",
        "PODCASTSERIES_SPORTS_SOCCER",
        "PODCASTSERIES_SPORTS_SWIMMING",
        "PODCASTSERIES_SPORTS_TENNIS",
        "PODCASTSERIES_SPORTS_VOLLEYBALL",
        "PODCASTSERIES_SPORTS_WILDERNESS",
        "PODCASTSERIES_SPORTS_WRESTLING",
        "PODCASTSERIES_TECHNOLOGY",
        "PODCASTSERIES_TRUE_CRIME",
        "PODCASTSERIES_TV_AND_FILM",
        "PODCASTSERIES_TV_AND_FILM_AFTER_SHOWS",
        "PODCASTSERIES_TV_AND_FILM_HISTORY",
        "PODCASTSERIES_TV_AND_FILM_INTERVIEWS",
        "PODCASTSERIES_TV_AND_FILM_FILM_REVIEWS",
        "PODCASTSERIES_TV_AND_FILM_TV_REVIEWS"
    );
    foreach ($all_genres as $g) {
        $sel = selected($genre, $g, false);
        if ($g === "ALL") {
            $display_label = "All";
        } else {
            $temp_label = str_replace('PODCASTSERIES_', '', $g);
            $display_label = ucwords(strtolower(str_replace('_', ' ', $temp_label)));
        }
        $output .= "<option value='{$g}' {$sel}>{$display_label}</option>";
    }
    $output .= '</select>';
    $output .= '</div>'; // .filter-group

    // =============== PUBLISHED AFTER
    $output .= '<div class="filter-group">';
    $output .= '<label for="after_date">Published After:';
    if (!$membership['can_filter_date']) {
        $output .= ' <span class="locked" title="Upgrade"><i class="fas fa-lock"></i></span>';
    }
    $output .= '</label>';
    $output .= '<input type="date" name="after_date" id="after_date" value="' . esc_attr($after_date) . '" ' 
               . (!$membership['can_filter_date'] ? 'disabled' : '') . '>';
    $output .= '</div>'; // .filter-group

    // =============== PUBLISHED BEFORE
    $output .= '<div class="filter-group">';
    $output .= '<label for="before_date">Published Before:';
    if (!$membership['can_filter_date']) {
        $output .= ' <span class="locked" title="Upgrade"><i class="fas fa-lock"></i></span>';
    }
    $output .= '</label>';
    $output .= '<input type="date" name="before_date" id="before_date" value="' . esc_attr($before_date) . '" '
               . (!$membership['can_filter_date'] ? 'disabled' : '') . '>';
    $output .= '</div>'; // .filter-group

    // =============== SAFE MODE
    if ($membership['safe_mode_forced']) {
        // If forced safe mode, show locked message
        $output .= '<div class="filter-group">';
        $output .= '<p>Explicit content is disabled on your plan <span class="locked"><i class="fas fa-lock"></i></span></p>';
        $output .= '<input type="hidden" name="isSafeMode" value="true">';
        $output .= '</div>';
    } else {
        $output .= '<div class="filter-group">';
        $chk = $isSafeMode ? '' : 'checked';
        $output .= '<label><input type="checkbox" name="isSafeMode" ' . $chk . '> Include explicit content</label>';
        $output .= '</div>';
    }

    $output .= '</div>'; // #filter-sidebar

    $output .= '</form>';
    $output .= '<div id="loading-spinner" style="display:none;"><img src="' . plugins_url('assets/spinner.gif', __FILE__) . '" alt="Loading..."></div>';

	$output .= '<div id="search-error-message" style="color:red; font-weight:bold;"></div>';

    // If user at search cap
    if ($search_count >= $search_cap) {
        $output .= '<div class="tabinfo msg-template pageupcta">
            <i class="iconcta fas fa-exclamation-circle"></i>
            <h3>You used up all of your searches on your current plan</h3>
            <p>You have reached the maximum number of searches allowed this month. <a href="/pricing/">Upgrade your plan</a> to continue searching</p>
            <p><a class="btn-trial-cta" href="/pricing/" role="button">Upgrade your plan <i class="fas fa-lock"></i></a></p>
        </div>';
    }

    $output .= '</div>'; // .search-form-wrapper
    return $output;
}

add_shortcode('podcast_prospector', 'podcast_prospector_shortcode');

function podcast_prospector_styles() {
    // Only load assets on the specific interview detail page (ID: 43072)
    if ( ! is_page( 43072 ) ) {
        return;
    }
    
    wp_enqueue_style('podcast-prospector-styles', plugins_url('assets/styles.css', __FILE__), array(), null);
    wp_enqueue_script('jquery');
}
add_action('wp_enqueue_scripts', 'podcast_prospector_styles');

function podcast_prospector_enqueue_scripts() {
    // Only load assets on the specific interview detail page (ID: 43072)
    if ( ! is_page( 43072 ) ) {
        return;
    }
    
    wp_enqueue_script('podcast-selection', plugins_url('assets/podcast-selection.js', __FILE__), array('jquery'), null, true);
    wp_localize_script('podcast-selection', 'frontendajax', array('ajaxurl' => admin_url('admin-ajax.php')));
}
add_action('wp_enqueue_scripts', 'podcast_prospector_enqueue_scripts');

function extract_last_episode_data($feedUrl) {
    $rss = @simplexml_load_file($feedUrl, 'SimpleXMLElement', LIBXML_NOCDATA);

    if ($rss === false) {
        return [];
    }

    $data = [];
    $data['description'] = isset($rss->channel->description) ? (string) $rss->channel->description : '';

    $itunes_owner = $rss->channel->children('itunes', true)->owner;
    $data['itunes:name'] = ($itunes_owner && $itunes_owner->name) ? (string) $itunes_owner->name : '';
    $data['itunes:email'] = ($itunes_owner && $itunes_owner->email) ? (string) $itunes_owner->email : '';

    $categories = $rss->channel->children('itunes', true)->category;
    $category_list = [];
    foreach ($categories as $category) {
        $category_list[] = (string) $category->attributes()->text;
    }
    $category_list = array_unique($category_list);
    $data['itunes:categories'] = $category_list;
    $data['episodeCount'] = count($rss->channel->item);

    $lastEpisode = null;
    $lastPubDate = null;

    foreach ($rss->channel->item as $episode) {
        $pubDate = isset($episode->pubDate) ? (string) $episode->pubDate : '';
        if ($pubDate && (!$lastPubDate || strtotime($pubDate) > strtotime($lastPubDate))) {
            $lastEpisode = $episode;
            $lastPubDate = $pubDate;
        }
    }

    if ($lastEpisode) {
        $data['lastEpisodeTitle'] = isset($lastEpisode->title) ? (string) $lastEpisode->title : '';
        $data['lastEpisodePubDate'] = $lastPubDate;
    } else {
        $data['lastEpisodePubDate'] = 'N/A';
    }

    return $data;
}

// Create entries in Formidable and generate HTML feedback
function create_form_entries_for_podcasts_and_episodes() {
    // Sanitize input
    $searchTerm = isset($_POST['search_term']) ? sanitize_text_field($_POST['search_term']) : 'N/A';
    $searchType = isset($_POST['search_type']) ? sanitize_text_field($_POST['search_type']) : 'byperson';

    // Basic validation
    if (!isset($_POST['podcasts']) || !is_array($_POST['podcasts'])) {
        $error_html = '<div class="import-message error" id="import-message-container-error"><div class="message-content"><i class="fas fa-exclamation-triangle message-icon"></i><span class="message-text">Error! No podcasts were selected for import.</span></div></div>';
        wp_send_json_error(array('html' => $error_html));
    }

    $selectedPodcasts = $_POST['podcasts'];
    $currentUser      = get_current_user_id();
    $total_to_import  = count($selectedPodcasts);
    $success_count    = 0;
    $fail_count       = 0;
    $failed_items_details = [];

    foreach ($selectedPodcasts as $item) {
        $decoded_item = json_decode(stripslashes($item), true);

        if (!$decoded_item || !is_array($decoded_item)) {
            $fail_count++;
            $failed_items_details[] = "Invalid data format received for an item.";
            error_log("Failed to import podcast: Invalid data format - " . print_r($item, true));
            continue;
        }

        // Initialize variables
        $podcastTitle       = '';
        $episodeTitle       = '';
        $episodeGuid        = '';
        $podcastGuidField   = ''; // Value for PodID (Field 9931)
        $feedUrl            = '';
        $feedItunesId       = '';
        $podcastIndexId     = ''; // Value for PodcastIndex ID (Field 9930)

        // Determine data source based on search type
        if ($searchType === 'byadvancedepisode' && isset($decoded_item['podcastSeries'])) {
            // Taddy Episode Search Result
            $podcastTitle       = $decoded_item['podcastSeries']['name'] ?? 'N/A';
            $episodeTitle       = $decoded_item['name'] ?? '';
            $episodeGuid        = $decoded_item['guid'] ?? '';
            $feedUrl            = $decoded_item['podcastSeries']['rssUrl'] ?? '';
            $feedItunesId       = $decoded_item['podcastSeries']['itunesId'] ?? '';
            // *** FIX: Assign Taddy podcastSeries uuid to the variable mapped to PodID field ***
            $podcastGuidField   = $decoded_item['podcastSeries']['uuid'] ?? '';
            $podcastIndexId     = ''; // No PI specific ID

        } elseif ($searchType === 'byadvancedpodcast') {
             // Taddy Podcast Search Result
            $podcastTitle       = $decoded_item['name'] ?? 'N/A';
            $episodeTitle       = '';
            $episodeGuid        = '';
            $feedUrl            = $decoded_item['rssUrl'] ?? '';
            $feedItunesId       = $decoded_item['itunesId'] ?? '';
             // *** FIX: Assign Taddy podcastSeries uuid to the variable mapped to PodID field ***
            $podcastGuidField   = $decoded_item['uuid'] ?? ''; // Taddy Series UUID is directly on the item here
            $podcastIndexId     = ''; // No PI specific ID

        } elseif ($searchType === 'byperson' && isset($decoded_item['feedTitle'])) {
             // PodcastIndex Episode Search Result
            $podcastTitle       = $decoded_item['feedTitle'] ?? 'N/A';
            $episodeTitle       = $decoded_item['title'] ?? '';
            $episodeGuid        = $decoded_item['guid'] ?? '';
            $feedUrl            = $decoded_item['feedUrl'] ?? '';
            $feedItunesId       = $decoded_item['feedItunesId'] ?? '';
            $podcastGuidField   = $decoded_item['feedId'] ?? ''; // Use PI Feed ID for PodID field
            $podcastIndexId     = $decoded_item['id'] ?? '';     // Use PI Episode ID for PI ID field

        } elseif ($searchType === 'bytitle' && isset($decoded_item['title']) && !isset($decoded_item['feedTitle'])) {
            // PodcastIndex Feed Search Result
            $podcastTitle       = $decoded_item['title'] ?? 'N/A';
            $episodeTitle       = '';
            $episodeGuid        = '';
            $feedUrl            = $decoded_item['url'] ?? '';
            $feedItunesId       = $decoded_item['itunesId'] ?? '';
            $podcastGuidField   = $decoded_item['id'] ?? ''; // Use PI Feed ID for PodID field
            $podcastIndexId     = $decoded_item['id'] ?? ''; // Use PI Feed ID for PI ID field
        } else {
             // Fallback
             $podcastTitle = $decoded_item['title'] ?? ($decoded_item['feedTitle'] ?? 'Unknown Title');
             $episodeTitle = ($podcastTitle !== ($decoded_item['title'] ?? '')) ? ($decoded_item['title'] ?? '') : '';
             $episodeGuid = $decoded_item['guid'] ?? '';
             $feedUrl = $decoded_item['feedUrl'] ?? ($decoded_item['url'] ?? '');
             $feedItunesId = $decoded_item['itunesId'] ?? ($decoded_item['feedItunesId'] ?? '');
             $podcastIndexId = $decoded_item['id'] ?? '';
             // Best guess for PodID in fallback
             $podcastGuidField = $decoded_item['feedId'] ?? ($decoded_item['podcastSeries']['uuid'] ?? ($decoded_item['uuid'] ?? ($decoded_item['id'] ?? '')));
             error_log("Import: Could not determine exact result type for item: " . json_encode($decoded_item));
        }

        $podcast_name_for_error = !empty($podcastTitle) ? $podcastTitle : 'Unknown Podcast';

        if (empty($feedUrl)) {
             $fail_count++;
             $failed_items_details[] = "Podcast '{$podcast_name_for_error}' couldn't be imported due to missing RSS feed.";
             error_log("Failed to import podcast: Missing RSS Feed URL - " . json_encode($decoded_item));
             continue;
        }

        // ** FIELD MAPPING - CONFIRM THESE IDS MATCH YOUR FORM **
        $form_id = 518;
        $field_map = [
            'podcast_title'    => 8111,
            'feed_url'         => 9928,
            'itunes_id'        => 9929,
            'podcastindex_id'  => 9930, // Stores PI Feed or Episode ID
            'podcast_guid'     => 9931, // Stores Taddy Series UUID or PI Feed ID (Your "PodID")
            'original_search'  => 9932,
            'search_type_used' => 9948,
            'status'           => 8113,
            'assigned_user'    => 8240,
            'episode_guid'     => 10392, // Stores EPISODE GUID
            'episode_title'    => 10393, // Stores EPISODE Title
			'archive'          => 10402,  // ← ADDED: map our “archive” field ID
        ];

        // Base meta data
        $item_meta_data = array(
            $field_map['podcast_title']    => $podcastTitle,
            $field_map['feed_url']         => $feedUrl,
            $field_map['itunes_id']        => $feedItunesId,
            $field_map['podcastindex_id']  => $podcastIndexId, // Will be empty for Taddy results
            $field_map['podcast_guid']     => $podcastGuidField, // Now correctly populated for Taddy results
            $field_map['original_search']  => $searchTerm,
            $field_map['search_type_used'] => $searchType,
            $field_map['status']           => 'Potential',
            $field_map['assigned_user']    => $currentUser,
			$field_map['archive']          => 0,       // ← ADDED: default “archive” value of zero
        );
        // Conditionally add episode data
        if (!empty($episodeGuid)) {
            $item_meta_data[$field_map['episode_guid']] = $episodeGuid;
        }
        if (!empty($episodeTitle)) {
             $item_meta_data[$field_map['episode_title']] = $episodeTitle;
        }

        $newEntry = array(
            'form_id'     => $form_id,
            'item_key'    => 'entry',
            'frm_user_id' => $currentUser,
            'item_meta'   => $item_meta_data,
        );

        if ( class_exists('FrmEntry') ) {
            $entry_id = FrmEntry::create($newEntry);
            if (!$entry_id || is_wp_error($entry_id)) {
                // ... (error handling remains the same) ...
                 $fail_count++;
                 $error_message = is_wp_error($entry_id) ? $entry_id->get_error_message() : 'Unknown Formidable error';
                 $failed_items_details[] = "Podcast '{$podcast_name_for_error}' failed: {$error_message}";
                 error_log("Failed to create Formidable entry for podcast: {$podcast_name_for_error}. Error: " . $error_message . " Data: " . json_encode($decoded_item));
            } else {
                $success_count++;
            }
        } else {
            // ... (error handling remains the same) ...
              $fail_count++;
              $failed_items_details[] = "Formidable Forms class not found. Could not import '{$podcast_name_for_error}'.";
              error_log("Failed to import podcast: FrmEntry class not found.");
        }
    } // End foreach loop

    // Prepare HTML response
    $html_response = '';
    $tracker_link = home_url('/app/interview/board/');

    // [ ... HTML response generation code remains the same ... ]
     if ($fail_count == 0 && $success_count > 0) {
        // All successful
        $html_response = sprintf( /* ... same success HTML ... */
            '<div class="import-message success" id="import-message-container-success"><div class="message-content"><i class="fas fa-check-circle message-icon"></i><span class="message-text">Success! <strong id="import-success-count-success">%1$d</strong> %2$s been added to your collection.</span></div><div class="message-actions"><a href="%3$s" class="view-tracker-link">View in Interview Tracker <i class="fas fa-arrow-right"></i></a></div></div>',
            $success_count,
            ($success_count === 1 ? 'podcast has' : 'podcasts have'),
            esc_url($tracker_link)
        );
        wp_send_json_success(array('html' => $html_response));

    } elseif ($fail_count > 0) {
        // Partial or total failure
        $error_summary = '';
        if ($success_count > 0) { $error_summary = sprintf('Partial success! <strong id="import-success-count-error">%1$d</strong> of <strong id="import-total-count-error">%2$d</strong> podcasts added to your collection.', $success_count, $total_to_import); }
        else { $error_summary = sprintf('Error! <strong id="import-fail-count-error">%1$d</strong> %2$s could not be imported.', $fail_count, ($fail_count === 1 ? 'podcast' : 'podcasts')); }

        $error_details_html = '';
        if (!empty($failed_items_details)) { $error_details_html = sprintf('<div class="error-details" id="import-error-details">%1$s %2$s</div>', esc_html($failed_items_details[0]), ($fail_count > 1 ? sprintf('(%d total failures - check logs for details)', $fail_count) : '')); }

        $html_response = sprintf( /* ... same error HTML ... */
             '<div class="import-message error" id="import-message-container-error"><div class="message-content"><i class="fas fa-exclamation-triangle message-icon"></i><span class="message-text">%1$s</span></div>%2$s<div class="message-actions"><a href="%3$s" class="view-tracker-link">View in Interview Tracker <i class="fas fa-arrow-right"></i></a></div></div>',
             $error_summary,
             $error_details_html,
             esc_url($tracker_link)
        );
        wp_send_json_error(array('html' => $html_response));

    } else {
        // Case: 0 successes, 0 failures
        $html_response = '<div class="import-message error" id="import-message-container-error"><div class="message-content"><i class="fas fa-exclamation-triangle message-icon"></i><span class="message-text">No podcasts were processed. Please check selection.</span></div></div>';
        wp_send_json_error(array('html' => $html_response));
    }
}

add_action('wp_ajax_add_podcasts_to_form', 'create_form_entries_for_podcasts_and_episodes');
add_action('wp_ajax_nopriv_add_podcasts_to_form', 'create_form_entries_for_podcasts_and_episodes');
