import { defineStore } from 'pinia'
import api from '../api/prospectorApi'
import { extractIdentifiers } from '../utils/dataNormalizer'

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

      try {
        const response = await api.search(searchParams)

        // Handle different response formats
        let results = []
        if (response.data?.data?.search?.podcastSeries) {
          results = response.data.data.search.podcastSeries
        } else if (response.data?.data?.search?.podcastEpisodes) {
          results = response.data.data.search.podcastEpisodes
        } else if (response.data?.data?.searchForTerm?.podcastSeries) {
          results = response.data.data.searchForTerm.podcastSeries
        } else if (response.data?.feeds) {
          results = response.data.feeds
        } else if (response.data?.results) {
          results = response.data.results
        } else if (Array.isArray(response.data)) {
          results = response.data
        }

        this.results = results.map(r => ({ ...r, _selected: false }))
        this.total = response.count || results.length
        this.hasMore = results.length === this.perPage
        this.lastSearchParams = searchParams
        this.cachedAt = response.from_cache ? new Date().toISOString() : null

        // Auto-hydrate results
        if (this.results.length > 0) {
          await this.hydrateResults()
        }

      } catch (err) {
        this.error = err.response?.data?.message || err.message || 'Search failed'
        this.results = []
        console.error('Search error:', err)
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
          // Taddy format
          return {
            itunes_id: result.itunesId || null,
            rss_url: result.rssUrl || null,
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
        console.warn('Hydration failed:', err)
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
