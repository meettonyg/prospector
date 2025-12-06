import { ref, computed, onMounted } from 'vue'
import api from '../api/prospectorApi'

const config = window.PROSPECTOR_CONFIG || {}

/**
 * Composable for user stats and membership functionality
 */
export function useUserStats() {
  // State
  const userId = ref(config.userId || 0)
  const membershipLevel = ref(config.membership?.level || 'free')
  const searchesRemaining = ref(config.membership?.searchesRemaining ?? 10)
  const searchCap = ref(config.membership?.searchCap || 10)
  const guestIntelActive = ref(config.guestIntelActive !== false)
  const features = ref(config.features || {})
  const loading = ref(false)
  const lastRefresh = ref(null)

  // Computed
  const canSearch = computed(() => {
    return searchesRemaining.value > 0 || searchCap.value === -1
  })

  const isPremium = computed(() => {
    return ['pro', 'enterprise'].includes(membershipLevel.value)
  })

  const canUseAdvancedFilters = computed(() => {
    return membershipLevel.value !== 'free'
  })

  const canImport = computed(() => {
    return guestIntelActive.value
  })

  const searchUsagePercent = computed(() => {
    if (searchCap.value <= 0) return 0
    return Math.round(((searchCap.value - searchesRemaining.value) / searchCap.value) * 100)
  })

  const isUnlimited = computed(() => {
    return searchCap.value === -1
  })

  const chatEnabled = computed(() => features.value.chat === true)
  const youtubeEnabled = computed(() => features.value.youtube === true)
  const summitsEnabled = computed(() => features.value.summits === true)

  const membershipDisplayName = computed(() => {
    const names = {
      free: 'Free',
      basic: 'Basic',
      pro: 'Pro',
      enterprise: 'Enterprise'
    }
    return names[membershipLevel.value] || 'Free'
  })

  /**
   * Refresh user stats from server
   */
  async function refreshStats() {
    loading.value = true
    try {
      const stats = await api.getUserStats()
      searchesRemaining.value = stats.searches_remaining ?? searchesRemaining.value
      searchCap.value = stats.search_cap ?? searchCap.value
      membershipLevel.value = stats.membership_level ?? membershipLevel.value
      lastRefresh.value = new Date().toISOString()
    } catch (err) {
      console.error('Failed to refresh user stats:', err)
    } finally {
      loading.value = false
    }
  }

  /**
   * Decrement search count after successful search
   */
  function decrementSearchCount() {
    if (searchesRemaining.value > 0 && searchCap.value !== -1) {
      searchesRemaining.value--
    }
  }

  /**
   * Update from API response
   */
  function updateFromResponse(userStats) {
    if (userStats) {
      if (typeof userStats.searches_remaining !== 'undefined') {
        searchesRemaining.value = userStats.searches_remaining
      }
      if (typeof userStats.search_cap !== 'undefined') {
        searchCap.value = userStats.search_cap
      }
      if (typeof userStats.search_count !== 'undefined') {
        searchesRemaining.value = Math.max(0, searchCap.value - userStats.search_count)
      }
    }
  }

  /**
   * Set Guest Intel active status
   */
  function setGuestIntelActive(active) {
    guestIntelActive.value = active
  }

  return {
    // State
    userId,
    membershipLevel,
    searchesRemaining,
    searchCap,
    guestIntelActive,
    features,
    loading,
    lastRefresh,

    // Computed
    canSearch,
    isPremium,
    canUseAdvancedFilters,
    canImport,
    searchUsagePercent,
    isUnlimited,
    chatEnabled,
    youtubeEnabled,
    summitsEnabled,
    membershipDisplayName,

    // Methods
    refreshStats,
    decrementSearchCount,
    updateFromResponse,
    setGuestIntelActive
  }
}
