import { defineStore } from 'pinia'
import api from '../api/prospectorApi'

const config = window.PROSPECTOR_CONFIG || {}

export const useUserStore = defineStore('user', {
  state: () => ({
    userId: config.userId || 0,
    membershipLevel: config.membership?.level || 'free',
    searchesRemaining: config.membership?.searchesRemaining ?? 10,
    searchCap: config.membership?.searchCap || 10,
    guestIntelActive: config.guestIntelActive !== false,
    features: config.features || {},
    loading: false,
    lastRefresh: null
  }),

  getters: {
    /**
     * Can user perform a search?
     */
    canSearch: (state) => state.searchesRemaining > 0 || state.searchCap === -1,

    /**
     * Is user on a premium plan?
     */
    isPremium: (state) => ['pro', 'enterprise'].includes(state.membershipLevel),

    /**
     * Can user use advanced filters? (Taddy API)
     */
    canUseAdvancedFilters: (state) => state.membershipLevel !== 'free',

    /**
     * Can user import to pipeline?
     */
    canImport: (state) => state.guestIntelActive,

    /**
     * Search usage percentage
     */
    searchUsagePercent: (state) => {
      if (state.searchCap <= 0) return 0
      return Math.round(((state.searchCap - state.searchesRemaining) / state.searchCap) * 100)
    },

    /**
     * Is chat feature enabled?
     */
    chatEnabled: (state) => state.features.chat === true,

    /**
     * Is YouTube search enabled?
     */
    youtubeEnabled: (state) => state.features.youtube === true,

    /**
     * Is Summits search enabled?
     */
    summitsEnabled: (state) => state.features.summits === true,

    /**
     * Is ChatGPT feature enabled?
     */
    chatGptEnabled: (state) => state.features.chatGpt === true,

    /**
     * Is search unlimited?
     */
    isUnlimited: (state) => state.searchCap === -1,

    /**
     * Get membership display name
     */
    membershipDisplayName: (state) => {
      const names = {
        free: 'Free',
        basic: 'Basic',
        pro: 'Pro',
        enterprise: 'Enterprise'
      }
      return names[state.membershipLevel] || 'Free'
    }
  },

  actions: {
    /**
     * Refresh user stats from server
     */
    async refreshStats() {
      this.loading = true
      try {
        const stats = await api.getUserStats()
        this.searchesRemaining = stats.searches_remaining ?? this.searchesRemaining
        this.searchCap = stats.search_cap ?? this.searchCap
        this.membershipLevel = stats.membership_level ?? this.membershipLevel
        this.lastRefresh = new Date().toISOString()
      } catch (err) {
        console.error('Failed to refresh user stats:', err)
      } finally {
        this.loading = false
      }
    },

    /**
     * Decrement search count after successful search
     */
    decrementSearchCount() {
      if (this.searchesRemaining > 0 && this.searchCap !== -1) {
        this.searchesRemaining--
      }
    },

    /**
     * Update Guest Intel status
     */
    setGuestIntelActive(active) {
      this.guestIntelActive = active
    },

    /**
     * Update from API response
     */
    updateFromResponse(userStats) {
      if (userStats) {
        if (typeof userStats.searches_remaining !== 'undefined') {
          this.searchesRemaining = userStats.searches_remaining
        }
        if (typeof userStats.search_cap !== 'undefined') {
          this.searchCap = userStats.search_cap
        }
        if (typeof userStats.search_count !== 'undefined') {
          this.searchesRemaining = Math.max(0, this.searchCap - userStats.search_count)
        }
      }
    }
  }
})
