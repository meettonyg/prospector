/**
 * Application constants
 */

// Available search channels
export const CHANNELS = {
  PODCASTS: 'podcasts',
  YOUTUBE: 'youtube',
  SUMMITS: 'summits'
}

export const CHANNEL_OPTIONS = [
  { value: CHANNELS.PODCASTS, label: 'Podcasts', icon: 'microphone' },
  { value: CHANNELS.YOUTUBE, label: 'YouTube', icon: 'play' },
  { value: CHANNELS.SUMMITS, label: 'Summits', icon: 'users' }
]

// Search modes by channel
export const SEARCH_MODES = {
  [CHANNELS.PODCASTS]: [
    { value: 'byperson', label: 'By Person', description: 'Find podcasts by guest or host name' },
    { value: 'bytitle', label: 'By Title', description: 'Search podcast titles' },
    { value: 'byadvancedpodcast', label: 'Advanced Podcast', description: 'Search with filters', premium: true },
    { value: 'byadvancedepisode', label: 'Advanced Episode', description: 'Search episodes with filters', premium: true }
  ],
  [CHANNELS.YOUTUBE]: [
    { value: 'byyoutube', label: 'YouTube Search', description: 'Search YouTube channels and videos' }
  ],
  [CHANNELS.SUMMITS]: [
    { value: 'bysummit', label: 'Summit Search', description: 'Find virtual summits and conferences' }
  ]
}

// Default search mode per channel
export const DEFAULT_MODES = {
  [CHANNELS.PODCASTS]: 'byperson',
  [CHANNELS.YOUTUBE]: 'byyoutube',
  [CHANNELS.SUMMITS]: 'bysummit'
}

// Podcast genres (Taddy API enum format, also mapped to PodcastIndex categories)
export const GENRES = [
  { value: 'PODCASTSERIES_ARTS', label: 'Arts' },
  { value: 'PODCASTSERIES_BUSINESS', label: 'Business' },
  { value: 'PODCASTSERIES_COMEDY', label: 'Comedy' },
  { value: 'PODCASTSERIES_EDUCATION', label: 'Education' },
  { value: 'PODCASTSERIES_FICTION', label: 'Fiction' },
  { value: 'PODCASTSERIES_GOVERNMENT', label: 'Government' },
  { value: 'PODCASTSERIES_HEALTH_AND_FITNESS', label: 'Health & Fitness' },
  { value: 'PODCASTSERIES_HISTORY', label: 'History' },
  { value: 'PODCASTSERIES_KIDS_AND_FAMILY', label: 'Kids & Family' },
  { value: 'PODCASTSERIES_LEISURE', label: 'Leisure' },
  { value: 'PODCASTSERIES_MUSIC', label: 'Music' },
  { value: 'PODCASTSERIES_NEWS', label: 'News' },
  { value: 'PODCASTSERIES_RELIGION_AND_SPIRITUALITY', label: 'Religion & Spirituality' },
  { value: 'PODCASTSERIES_SCIENCE', label: 'Science' },
  { value: 'PODCASTSERIES_SOCIETY_AND_CULTURE', label: 'Society & Culture' },
  { value: 'PODCASTSERIES_SPORTS', label: 'Sports' },
  { value: 'PODCASTSERIES_TECHNOLOGY', label: 'Technology' },
  { value: 'PODCASTSERIES_TRUE_CRIME', label: 'True Crime' },
  { value: 'PODCASTSERIES_TV_AND_FILM', label: 'TV & Film' }
]

// Languages
export const LANGUAGES = [
  { value: 'en', label: 'English' },
  { value: 'es', label: 'Spanish' },
  { value: 'fr', label: 'French' },
  { value: 'de', label: 'German' },
  { value: 'pt', label: 'Portuguese' },
  { value: 'it', label: 'Italian' },
  { value: 'nl', label: 'Dutch' },
  { value: 'ja', label: 'Japanese' },
  { value: 'ko', label: 'Korean' },
  { value: 'zh', label: 'Chinese' }
]

// Countries
export const COUNTRIES = [
  { value: 'us', label: 'United States' },
  { value: 'gb', label: 'United Kingdom' },
  { value: 'ca', label: 'Canada' },
  { value: 'au', label: 'Australia' },
  { value: 'de', label: 'Germany' },
  { value: 'fr', label: 'France' },
  { value: 'es', label: 'Spain' },
  { value: 'it', label: 'Italy' },
  { value: 'br', label: 'Brazil' },
  { value: 'mx', label: 'Mexico' },
  { value: 'in', label: 'India' },
  { value: 'jp', label: 'Japan' }
]

// Sort options
export const SORT_OPTIONS = [
  { value: 'BEST_MATCH', label: 'Best Match' },
  { value: 'LATEST', label: 'Latest First' },
  { value: 'OLDEST', label: 'Oldest First' },
  { value: 'POPULARITY', label: 'Most Popular' }
]

// Membership levels
export const MEMBERSHIP_LEVELS = {
  FREE: 'free',
  BASIC: 'basic',
  PRO: 'pro',
  ENTERPRISE: 'enterprise'
}

// Features by membership level
export const FEATURES_BY_LEVEL = {
  [MEMBERSHIP_LEVELS.FREE]: {
    searchCap: 10,
    advancedFilters: false,
    bulkImport: false,
    chat: false
  },
  [MEMBERSHIP_LEVELS.BASIC]: {
    searchCap: 50,
    advancedFilters: true,
    bulkImport: true,
    chat: false
  },
  [MEMBERSHIP_LEVELS.PRO]: {
    searchCap: 200,
    advancedFilters: true,
    bulkImport: true,
    chat: true
  },
  [MEMBERSHIP_LEVELS.ENTERPRISE]: {
    searchCap: -1, // Unlimited
    advancedFilters: true,
    bulkImport: true,
    chat: true
  }
}

// Hydration status
export const HYDRATION_STATUS = {
  NOT_TRACKED: 'not_tracked',
  TRACKED: 'tracked',
  HAS_OPPORTUNITY: 'has_opportunity'
}

// Opportunity statuses (matching Guest Intel)
export const OPPORTUNITY_STATUSES = {
  POTENTIAL: 'potential',
  ACTIVE: 'active',
  AIRED: 'aired',
  CONVERT: 'convert',
  ON_HOLD: 'on_hold',
  CANCELLED: 'cancelled',
  UNQUALIFIED: 'unqualified'
}

// View modes
export const VIEW_MODES = {
  GRID: 'grid',
  TABLE: 'table'
}

// Results per page options
export const PER_PAGE_OPTIONS = [10, 20, 50, 100]

// Default pagination
export const DEFAULT_PER_PAGE = 20

// Debounce delays (ms)
export const DEBOUNCE = {
  SEARCH: 300,
  AUTOCOMPLETE: 200,
  RESIZE: 100
}

// Toast types
export const TOAST_TYPES = {
  SUCCESS: 'success',
  ERROR: 'error',
  WARNING: 'warning',
  INFO: 'info'
}

// Toast duration (ms)
export const TOAST_DURATION = 5000
