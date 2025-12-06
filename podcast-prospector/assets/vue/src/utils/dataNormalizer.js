/**
 * Normalize API responses from different sources
 * (PodcastIndex, Taddy, YouTube) into a unified format
 */

/**
 * Normalize a single podcast result
 * @param {Object} result - Raw API result
 * @param {string} source - Source API (podcastindex, taddy, youtube)
 * @returns {Object} Normalized podcast object
 */
export function normalizePodcast(result, source = 'podcastindex') {
  switch (source) {
    case 'taddy':
      return normalizeTaddyPodcast(result)
    case 'youtube':
      return normalizeYouTubeChannel(result)
    case 'podcastindex':
    default:
      return normalizePodcastIndex(result)
  }
}

/**
 * Normalize PodcastIndex result
 */
function normalizePodcastIndex(result) {
  return {
    id: result.id || result.feedId,
    title: result.title || result.feed?.title || 'Untitled',
    author: result.author || result.feed?.author || '',
    description: result.description || result.feed?.description || '',
    artwork: result.artwork || result.image || result.feed?.artwork || '',
    rssUrl: result.url || result.feedUrl || result.feed?.url || '',
    websiteUrl: result.link || result.feed?.link || '',
    itunesId: result.itunesId || result.feedItunesId || null,
    podcastIndexId: result.feedId || result.id || null,
    language: result.language || 'en',
    categories: parseCategories(result.categories),
    episodeCount: result.episodeCount || null,
    lastEpisodeDate: result.newestItemPubdate || result.lastUpdateTime || null,
    explicit: result.explicit || false,
    source: 'podcastindex',
    _raw: result
  }
}

/**
 * Normalize Taddy result
 */
function normalizeTaddyPodcast(result) {
  return {
    id: result.uuid || result.id,
    title: result.name || result.title || 'Untitled',
    author: result.publisher || result.author || '',
    description: result.description || '',
    artwork: result.imageUrl || result.artwork || '',
    rssUrl: result.rssUrl || '',
    websiteUrl: result.websiteUrl || result.link || '',
    itunesId: result.itunesId || null,
    podcastIndexId: null,
    language: result.language || 'en',
    categories: result.genres || [],
    episodeCount: result.episodeCount || result.totalEpisodeCount || null,
    lastEpisodeDate: result.latestEpisodeDate || null,
    explicit: result.explicit || false,
    source: 'taddy',
    _raw: result
  }
}

/**
 * Normalize YouTube channel/video
 */
function normalizeYouTubeChannel(result) {
  return {
    id: result.channelId || result.id?.channelId || result.id,
    title: result.title || result.snippet?.title || 'Untitled',
    author: result.channelTitle || result.snippet?.channelTitle || '',
    description: result.description || result.snippet?.description || '',
    artwork: result.thumbnail || result.snippet?.thumbnails?.high?.url || '',
    rssUrl: null, // YouTube doesn't have RSS
    websiteUrl: `https://youtube.com/channel/${result.channelId || result.id?.channelId}`,
    itunesId: null,
    podcastIndexId: null,
    language: result.defaultLanguage || 'en',
    categories: [],
    episodeCount: result.videoCount || null,
    lastEpisodeDate: null,
    explicit: false,
    source: 'youtube',
    subscriberCount: result.subscriberCount || null,
    viewCount: result.viewCount || null,
    _raw: result
  }
}

/**
 * Normalize episode result
 * @param {Object} result - Raw episode result
 * @param {string} source - Source API
 * @returns {Object} Normalized episode object
 */
export function normalizeEpisode(result, source = 'podcastindex') {
  switch (source) {
    case 'taddy':
      return normalizeTaddyEpisode(result)
    default:
      return normalizePodcastIndexEpisode(result)
  }
}

/**
 * Normalize PodcastIndex episode
 */
function normalizePodcastIndexEpisode(result) {
  return {
    id: result.id,
    title: result.title || 'Untitled Episode',
    description: result.description || '',
    publishDate: result.datePublished || result.datePublishedPretty || null,
    duration: result.duration || null,
    audioUrl: result.enclosureUrl || '',
    episodeUrl: result.link || '',
    artwork: result.image || result.feedImage || '',
    podcast: {
      id: result.feedId,
      title: result.feedTitle || '',
      artwork: result.feedImage || ''
    },
    source: 'podcastindex',
    _raw: result
  }
}

/**
 * Normalize Taddy episode
 */
function normalizeTaddyEpisode(result) {
  return {
    id: result.uuid || result.id,
    title: result.name || result.title || 'Untitled Episode',
    description: result.description || '',
    publishDate: result.datePublished || result.airDate || null,
    duration: result.duration || null,
    audioUrl: result.audioUrl || '',
    episodeUrl: result.websiteUrl || '',
    artwork: result.imageUrl || '',
    podcast: {
      id: result.podcastUuid || result.podcastId,
      title: result.podcastName || '',
      artwork: result.podcastImageUrl || ''
    },
    source: 'taddy',
    _raw: result
  }
}

/**
 * Parse categories from various formats
 */
function parseCategories(categories) {
  if (!categories) return []

  // If it's already an array of strings
  if (Array.isArray(categories)) {
    return categories.map(c => typeof c === 'string' ? c : c.name || c.title || '')
  }

  // If it's an object with numeric keys (PodcastIndex format)
  if (typeof categories === 'object') {
    return Object.values(categories).filter(Boolean)
  }

  return []
}

/**
 * Extract hydration identifiers from a normalized result
 * @param {Object} result - Normalized podcast/episode
 * @returns {Object} Identifiers for hydration API
 */
export function extractIdentifiers(result) {
  return {
    itunes_id: result.itunesId || null,
    rss_url: result.rssUrl || null,
    podcast_index_id: result.podcastIndexId || null
  }
}

/**
 * Format duration in seconds to human-readable string
 * @param {number} seconds - Duration in seconds
 * @returns {string} Formatted duration
 */
export function formatDuration(seconds) {
  if (!seconds || isNaN(seconds)) return ''

  const hours = Math.floor(seconds / 3600)
  const minutes = Math.floor((seconds % 3600) / 60)
  const secs = seconds % 60

  if (hours > 0) {
    return `${hours}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`
  }
  return `${minutes}:${secs.toString().padStart(2, '0')}`
}

/**
 * Format date to relative time
 * @param {string|number} date - Date string or timestamp
 * @returns {string} Relative time string
 */
export function formatRelativeDate(date) {
  if (!date) return ''

  const d = new Date(typeof date === 'number' ? date * 1000 : date)
  const now = new Date()
  const diffMs = now - d
  const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24))

  if (diffDays === 0) return 'Today'
  if (diffDays === 1) return 'Yesterday'
  if (diffDays < 7) return `${diffDays} days ago`
  if (diffDays < 30) return `${Math.floor(diffDays / 7)} weeks ago`
  if (diffDays < 365) return `${Math.floor(diffDays / 30)} months ago`
  return `${Math.floor(diffDays / 365)} years ago`
}

/**
 * Truncate text to max length with ellipsis
 * @param {string} text - Text to truncate
 * @param {number} maxLength - Maximum length
 * @returns {string} Truncated text
 */
export function truncateText(text, maxLength = 150) {
  if (!text || text.length <= maxLength) return text
  return text.slice(0, maxLength).trim() + '...'
}
