import { ref, computed } from 'vue'
import api from '../api/prospectorApi'
import { useUserStore } from '../stores/userStore'
import { useFilterStore } from '../stores/filterStore'
import { useHydration } from './useHydration'

/**
 * Composable for handling search functionality
 */
export function useSearch() {
  const userStore = useUserStore()
  const filterStore = useFilterStore()
  const { hydrateResults, hydrationMap, hydrating } = useHydration()

  // State
  const results = ref([])
  const loading = ref(false)
  const error = ref(null)
  const query = ref('')
  const channel = ref('podcasts')
  const mode = ref('byperson')
  const page = ref(1)
  const perPage = ref(20)
  const total = ref(0)
  const hasMore = ref(false)
  const cachedAt = ref(null)
  const hasSearched = ref(false)

  // Computed
  const hasResults = computed(() => results.value.length > 0)

  const canSearch = computed(() => {
    return userStore.canSearch && query.value.trim().length > 0
  })

  const searchMeta = computed(() => ({
    term: query.value,
    type: mode.value,
    channel: channel.value
  }))

  /**
   * Execute search
   * @param {Object} additionalParams - Additional search parameters
   */
  async function search(additionalParams = {}) {
    if (!query.value.trim()) return

    loading.value = true
    error.value = null
    hasSearched.value = true

    const searchParams = {
      search_term: query.value,
      search_type: mode.value,
      channel: channel.value,
      page: page.value,
      results_per_page: perPage.value,
      ...filterStore.filterParams,
      ...additionalParams
    }

    try {
      const response = await api.search(searchParams)

      // Handle different response formats
      let searchResults = []
      if (response.data?.data?.search?.podcastSeries) {
        searchResults = response.data.data.search.podcastSeries
      } else if (response.data?.data?.search?.podcastEpisodes) {
        searchResults = response.data.data.search.podcastEpisodes
      } else if (response.data?.data?.searchForTerm?.podcastSeries) {
        searchResults = response.data.data.searchForTerm.podcastSeries
      } else if (response.data?.feeds) {
        searchResults = response.data.feeds
      } else if (response.data?.results) {
        searchResults = response.data.results
      } else if (Array.isArray(response.data)) {
        searchResults = response.data
      }

      results.value = searchResults.map(r => ({ ...r, _selected: false }))
      total.value = response.count || searchResults.length
      hasMore.value = searchResults.length === perPage.value
      cachedAt.value = response.from_cache ? new Date().toISOString() : null

      // Update user stats if provided
      if (response.user_stats) {
        userStore.updateFromResponse(response.user_stats)
      }

      // Auto-hydrate results
      if (results.value.length > 0) {
        await hydrateResults(results.value, mode.value)
      }

    } catch (err) {
      error.value = err.response?.data?.message || err.message || 'Search failed'
      results.value = []
      console.error('Search error:', err)
    } finally {
      loading.value = false
    }
  }

  /**
   * Set search query
   */
  function setQuery(newQuery) {
    query.value = newQuery
  }

  /**
   * Set channel
   */
  function setChannel(newChannel) {
    channel.value = newChannel
    clearResults()

    // Reset mode to default for channel
    const defaultModes = {
      podcasts: 'byperson',
      youtube: 'byyoutube',
      summits: 'bysummit'
    }
    mode.value = defaultModes[newChannel] || 'byperson'
  }

  /**
   * Set search mode
   */
  function setMode(newMode) {
    mode.value = newMode
    clearResults()
  }

  /**
   * Set page
   */
  function setPage(newPage) {
    page.value = newPage
  }

  /**
   * Toggle selection
   */
  function toggleSelection(index) {
    if (results.value[index]) {
      results.value[index]._selected = !results.value[index]._selected
    }
  }

  /**
   * Select all importable results
   */
  function selectAllImportable() {
    results.value.forEach((result, index) => {
      const hydration = hydrationMap.value[index]
      if (!hydration?.tracked) {
        result._selected = true
      }
    })
  }

  /**
   * Deselect all
   */
  function deselectAll() {
    results.value.forEach(r => r._selected = false)
  }

  /**
   * Get selected results
   */
  const selectedResults = computed(() => {
    return results.value.filter(r => r._selected)
  })

  /**
   * Clear results
   */
  function clearResults() {
    results.value = []
    total.value = 0
    page.value = 1
    hasMore.value = false
    cachedAt.value = null
    error.value = null
    hasSearched.value = false
  }

  /**
   * Refresh hydration
   */
  async function refreshHydration() {
    if (results.value.length > 0) {
      await hydrateResults(results.value, mode.value)
    }
  }

  return {
    // State
    results,
    loading,
    error,
    query,
    channel,
    mode,
    page,
    perPage,
    total,
    hasMore,
    cachedAt,
    hasSearched,
    hydrationMap,
    hydrating,

    // Computed
    hasResults,
    canSearch,
    searchMeta,
    selectedResults,

    // Methods
    search,
    setQuery,
    setChannel,
    setMode,
    setPage,
    toggleSelection,
    selectAllImportable,
    deselectAll,
    clearResults,
    refreshHydration
  }
}
