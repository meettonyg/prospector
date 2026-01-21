import { defineStore } from 'pinia'
import api from '../api/prospectorApi'
import { useCelebrationStore } from './celebrationStore'
import { useUserStore } from './userStore'

export const useSearchStore = defineStore('search', {
  state: () => ({
    // Results
    results: [],
    hydrationMap: {}, // { index: { tracked, podcast_id, crm_url, ... } }

    // Search parameters
    query: '',
    channel: 'podcasts', // podcasts | youtube | summits
    mode: 'byperson',    // byperson | bytitle | byadvancedpodcast | byadvancedepisode

    // Pagination
    page: 1,
    perPage: 20,
    total: 0,
    hasMore: false,

    // UI state
    loading: false,
    hydrating: false,
    error: null,

    // Cache info
    lastSearchParams: null,
    cachedAt: null,

    // View preferences
    viewMode: 'grid' // grid | table
  }),

  getters: {
    /**
     * Results with hydration data merged
     */
    hydratedResults: (state) => {
      return state.results.map((result, index) => ({
        ...result,
        _hydration: state.hydrationMap[index] || { tracked: false },
        _index: index
      }))
    },

    /**
     * Currently selected results
     */
    selectedResults: (state) => {
      return state.results.filter(r => r._selected)
    },

    /**
     * Count of selected results
     */
    selectedCount: (state) => {
      return state.results.filter(r => r._selected).length
    },

    /**
     * Count of results already in CRM
     */
    trackedCount: (state) => {
      return Object.values(state.hydrationMap).filter(h => h.tracked).length
    },

    /**
     * Count of importable (not yet tracked) results
     */
    importableCount: (state) => {
      return state.results.length - Object.values(state.hydrationMap).filter(h => h.tracked).length
    },

    /**
     * Check if results are from cache
     */
    isCached: (state) => {
      return !!state.cachedAt
    },

    /**
     * Check if there are results
     */
    hasResults: (state) => {
      return state.results.length > 0
    },

    /**
     * Get search metadata for imports
     */
    searchMeta: (state) => ({
      term: state.query,
      type: state.mode,
      channel: state.channel
    })
  },

  actions: {
    /**
     * Execute search
     */
    async search(params = {}) {
      this.loading = true
      this.error = null

      const searchParams = {
        search_term: this.query,
        search_type: this.mode,
        channel: this.channel,
        page: this.page,
        results_per_page: this.perPage,
        ...params
      }

      console.log('[SearchStore] Sending search params:', searchParams)

      try {
        const response = await api.search(searchParams)
        
        console.log('[SearchStore] Raw API response:', response)

        // Handle different response formats
        // response = { success, data: {...}, count, from_cache, ... }
        // response.data contains the actual API results in different formats
        let results = []
        
        // =====================================================
        // TADDY API - Uses "searchForTerm" in GraphQL response
        // =====================================================
        
        // Taddy - Podcast Series (byadvancedpodcast)
        if (response.data?.data?.searchForTerm?.podcastSeries) {
          // Normalize podcast series data to match expected field names
          results = response.data.data.searchForTerm.podcastSeries.map(podcast => ({
            ...podcast,
            // Map Taddy field names to expected component field names
            image: podcast.imageUrl || '',
            artwork: podcast.imageUrl || '',
            title: podcast.name || 'Untitled Podcast',
            author: podcast.authorName || ''
          }))
          console.log('[SearchStore] Found Taddy podcastSeries:', results.length)
        }
        // Taddy - Podcast Episodes (byadvancedepisode)
        else if (response.data?.data?.searchForTerm?.podcastEpisodes) {
          // Normalize episode data to include image at top level from podcastSeries
          results = response.data.data.searchForTerm.podcastEpisodes.map(episode => ({
            ...episode,
            // Extract image from podcastSeries for display components
            image: episode.podcastSeries?.imageUrl || '',
            artwork: episode.podcastSeries?.imageUrl || '',
            // Use episode name as title, keep podcast info accessible
            title: episode.name || 'Untitled Episode',
            // Extract author from podcastSeries
            author: episode.podcastSeries?.authorName || ''
          }))
          console.log('[SearchStore] Found Taddy podcastEpisodes:', results.length)
        }
        
        // =====================================================
        // PODCASTINDEX API
        // =====================================================

        // PodcastIndex - search by person (returns items array)
        else if (response.data?.items) {
          // Normalize PodcastIndex episode data - image is in feedImage, not image
          results = response.data.items.map(item => ({
            ...item,
            // Map feedImage to image/artwork for display components
            image: item.feedImage || '',
            artwork: item.feedImage || '',
            // feedTitle is the podcast name, title is the episode title
            author: item.feedTitle || ''
          }))
          console.log('[SearchStore] Found PodcastIndex items (byperson):', results.length)
        }
        // PodcastIndex - search by term/title (returns feeds array)
        else if (response.data?.feeds) {
          // Feeds already have correct field names (image, artwork, title, author)
          results = response.data.feeds
          console.log('[SearchStore] Found PodcastIndex feeds (bytitle):', results.length)
        }
        
        // =====================================================
        // YOUTUBE API
        // =====================================================

        // YouTube - returns data.items
        else if (response.data?.data?.items) {
          // Normalize YouTube data - image is thumbnailUrl, author is channelTitle
          results = response.data.data.items.map(item => ({
            ...item,
            // Map thumbnailUrl to image/artwork for display components
            image: item.thumbnailUrl || '',
            artwork: item.thumbnailUrl || '',
            // Map channelTitle to author
            author: item.channelTitle || ''
          }))
          console.log('[SearchStore] Found YouTube items:', results.length)
        }
        
        // =====================================================
        // GENERIC FALLBACKS
        // =====================================================
        
        // Generic results array
        else if (response.data?.results) {
          results = response.data.results
          console.log('[SearchStore] Found generic results:', results.length)
        } 
        // Direct array
        else if (Array.isArray(response.data)) {
          results = response.data
          console.log('[SearchStore] Found direct array:', results.length)
        }
        else {
          console.warn('[SearchStore] Unknown response format:', response)
        }

        this.results = results.map(r => ({ ...r, _selected: false }))
        this.total = response.count || results.length
        this.hasMore = results.length === this.perPage
        this.lastSearchParams = searchParams
        this.cachedAt = response.from_cache ? new Date().toISOString() : null

        console.log('[SearchStore] Processed results:', this.results.length, 'Total:', this.total)

        // Update user stats and check for milestone celebration
        if (response.user_stats) {
          const userStore = useUserStore()
          userStore.updateFromResponse(response.user_stats)

          // Check for milestone celebration
          const celebrationStore = useCelebrationStore()
          celebrationStore.checkMilestone(response.user_stats)
        }

        // Auto-hydrate results
        if (this.results.length > 0) {
          await this.hydrateResults()
        }

      } catch (err) {
        this.error = err.response?.data?.message || err.message || 'Search failed'
        this.results = []
        console.error('[SearchStore] Search error:', err)
      } finally {
        this.loading = false
      }
    },

    /**
     * Hydrate results with CRM status
     */
    async hydrateResults() {
      if (this.results.length === 0) {
        this.hydrationMap = {}
        return
      }

      this.hydrating = true

      // Extract identifiers based on result type
      const identifiers = this.results.map(result => {
        // Handle different API response formats
        if (this.mode === 'byadvancedpodcast' || this.mode === 'byadvancedepisode') {
          // Taddy format - note: for episodes, the podcast info is nested in podcastSeries
          const podcast = result.podcastSeries || result
          return {
            itunes_id: podcast.itunesId || null,
            rss_url: podcast.rssUrl || null,
            podcast_index_id: null
          }
        } else {
          // PodcastIndex format
          return {
            itunes_id: result.itunesId || result.feedItunesId || null,
            rss_url: result.feedUrl || result.url || null,
            podcast_index_id: result.feedId || result.id || null
          }
        }
      })

      try {
        const response = await api.hydrate(identifiers)
        this.hydrationMap = response.results || {}
      } catch (err) {
        console.warn('[SearchStore] Hydration failed:', err)
        // Don't block the UI if hydration fails
        this.hydrationMap = {}
      } finally {
        this.hydrating = false
      }
    },

    /**
     * Set search query
     */
    setQuery(query) {
      this.query = query
    },

    /**
     * Set channel (clears results)
     */
    setChannel(channel) {
      this.channel = channel
      this.clearResults()

      // Reset mode to default for channel
      const defaultModes = {
        podcasts: 'byperson',
        youtube: 'byyoutube',
        summits: 'bysummit'
      }
      this.mode = defaultModes[channel] || 'byperson'
    },

    /**
     * Set search mode
     */
    setMode(mode) {
      this.mode = mode
      this.clearResults()
    },

    /**
     * Set page number
     */
    setPage(page) {
      this.page = page
    },

    /**
     * Set view mode (grid/table)
     */
    setViewMode(mode) {
      this.viewMode = mode
    },

    /**
     * Toggle result selection
     */
    toggleSelection(index) {
      if (this.results[index]) {
        this.results[index]._selected = !this.results[index]._selected
      }
    },

    /**
     * Select all importable results
     */
    selectAllImportable() {
      this.results.forEach((result, index) => {
        const hydration = this.hydrationMap[index]
        if (!hydration?.tracked) {
          result._selected = true
        }
      })
    },

    /**
     * Deselect all results
     */
    deselectAll() {
      this.results.forEach(r => r._selected = false)
    },

    /**
     * Clear results and reset state
     */
    clearResults() {
      this.results = []
      this.hydrationMap = {}
      this.total = 0
      this.page = 1
      this.hasMore = false
      this.cachedAt = null
      this.error = null
    },

    /**
     * Refresh hydration after import
     */
    async refreshHydration() {
      await this.hydrateResults()
    },

    /**
     * Mark specific result as imported
     */
    markAsImported(index, hydrationData) {
      if (this.hydrationMap[index]) {
        this.hydrationMap[index] = {
          ...this.hydrationMap[index],
          ...hydrationData,
          tracked: true
        }
      } else {
        this.hydrationMap[index] = {
          tracked: true,
          ...hydrationData
        }
      }
      // Deselect the imported item
      if (this.results[index]) {
        this.results[index]._selected = false
      }
    }
  }
})
