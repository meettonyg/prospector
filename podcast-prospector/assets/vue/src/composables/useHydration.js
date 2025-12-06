import { ref, computed } from 'vue'
import api from '../api/prospectorApi'

/**
 * Composable for handling CRM hydration (checking if podcasts are tracked)
 */
export function useHydration() {
  const hydrationMap = ref({})
  const hydrating = ref(false)
  const hydrationError = ref(null)
  const guestIntelActive = ref(true)

  /**
   * Hydrate a list of results with CRM status
   * @param {Array} results - Array of podcast/episode results
   * @param {string} searchMode - Search mode (byperson, bytitle, etc.)
   * @returns {Promise<Object>} Hydration map by index
   */
  async function hydrateResults(results, searchMode = 'byperson') {
    if (!results || results.length === 0) {
      hydrationMap.value = {}
      return {}
    }

    hydrating.value = true
    hydrationError.value = null

    // Extract identifiers based on result type
    const identifiers = results.map(result => {
      // Handle different API response formats
      if (['byadvancedpodcast', 'byadvancedepisode'].includes(searchMode)) {
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

      guestIntelActive.value = response.guest_intel_active !== false
      hydrationMap.value = response.results || {}

      return hydrationMap.value
    } catch (err) {
      hydrationError.value = err.message
      console.warn('Hydration failed:', err)
      // Don't block the UI if hydration fails
      hydrationMap.value = {}
      return {}
    } finally {
      hydrating.value = false
    }
  }

  /**
   * Get hydration status for a specific index
   * @param {number} index - Result index
   * @returns {Object} Hydration data for that index
   */
  function getHydration(index) {
    return hydrationMap.value[index] || {
      tracked: false,
      podcast_id: null,
      podcast_slug: null,
      has_opportunity: false,
      opportunity_id: null,
      crm_url: null
    }
  }

  /**
   * Check if a result is tracked
   * @param {number} index - Result index
   * @returns {boolean}
   */
  function isTracked(index) {
    return hydrationMap.value[index]?.tracked === true
  }

  /**
   * Check if a result has an active opportunity
   * @param {number} index - Result index
   * @returns {boolean}
   */
  function hasOpportunity(index) {
    return hydrationMap.value[index]?.has_opportunity === true
  }

  /**
   * Get CRM URL for a tracked result
   * @param {number} index - Result index
   * @returns {string|null}
   */
  function getCrmUrl(index) {
    return hydrationMap.value[index]?.crm_url || null
  }

  /**
   * Count of tracked results
   */
  const trackedCount = computed(() => {
    return Object.values(hydrationMap.value).filter(h => h.tracked).length
  })

  /**
   * Count of results with opportunities
   */
  const opportunityCount = computed(() => {
    return Object.values(hydrationMap.value).filter(h => h.has_opportunity).length
  })

  /**
   * Clear hydration data
   */
  function clearHydration() {
    hydrationMap.value = {}
    hydrationError.value = null
  }

  /**
   * Update hydration for a single index (after import)
   * @param {number} index - Result index
   * @param {Object} data - Hydration data
   */
  function updateHydration(index, data) {
    hydrationMap.value[index] = {
      ...hydrationMap.value[index],
      ...data,
      tracked: true
    }
  }

  return {
    hydrationMap,
    hydrating,
    hydrationError,
    guestIntelActive,
    hydrateResults,
    getHydration,
    isTracked,
    hasOpportunity,
    getCrmUrl,
    trackedCount,
    opportunityCount,
    clearHydration,
    updateHydration
  }
}
