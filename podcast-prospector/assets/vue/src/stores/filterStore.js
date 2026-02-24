import { defineStore } from 'pinia'

export const useFilterStore = defineStore('filters', {
  state: () => ({
    // Active filters
    language: null,
    country: null,
    genre: null,
    safeMode: true,
    sortBy: 'BEST_MATCH', // BEST_MATCH | LATEST | OLDEST | POPULARITY
    matchBy: 'MOST_TERMS', // MOST_TERMS | ALL_TERMS | EXACT_PHRASE
    perPage: 25,

    // Date filters
    dateFrom: null,
    dateTo: null,

    // Location filters
    locationCity: null,
    locationState: null,
    locationCountry: null,

    // Filter panel state
    expanded: false,

    // Location autocomplete cache
    locationCache: {
      cities: [],
      states: [],
      countries: []
    }
  }),

  getters: {
    /**
     * Check if any filters are active
     */
    hasActiveFilters: (state) => {
      return !!(
        state.language ||
        state.country ||
        state.genre ||
        state.dateFrom ||
        state.dateTo ||
        state.locationCity ||
        state.locationState ||
        state.locationCountry
      )
    },

    /**
     * Get active filter count
     */
    activeFilterCount: (state) => {
      let count = 0
      if (state.language) count++
      if (state.country) count++
      if (state.genre) count++
      if (state.dateFrom) count++
      if (state.dateTo) count++
      if (state.locationCity) count++
      if (state.locationState) count++
      if (state.locationCountry) count++
      return count
    },

    /**
     * Build filter params for API
     */
    filterParams: (state) => {
      const params = {}

      if (state.language) params.language = state.language
      if (state.country) params.country = state.country
      if (state.genre) params.genre = state.genre
      if (state.safeMode) params.safe_mode = '1'
      if (state.sortBy) params.sort_order = state.sortBy
      if (state.matchBy) params.match_by = state.matchBy
      if (state.perPage) params.per_page = state.perPage

      // Location filters
      if (state.locationCity) params.location_city = state.locationCity
      if (state.locationState) params.location_state = state.locationState
      if (state.locationCountry) params.location_country = state.locationCountry

      // Date filters
      if (state.dateFrom) params.date_from = state.dateFrom
      if (state.dateTo) params.date_to = state.dateTo

      return params
    },

    /**
     * Get active filters as display array
     */
    activeFiltersDisplay: (state) => {
      const filters = []

      if (state.language) {
        filters.push({ key: 'language', label: 'Language', value: state.language })
      }
      if (state.country) {
        filters.push({ key: 'country', label: 'Country', value: state.country })
      }
      if (state.genre) {
        filters.push({ key: 'genre', label: 'Genre', value: state.genre })
      }
      if (state.dateFrom) {
        filters.push({ key: 'dateFrom', label: 'After', value: state.dateFrom })
      }
      if (state.dateTo) {
        filters.push({ key: 'dateTo', label: 'Before', value: state.dateTo })
      }
      if (state.locationCity) {
        filters.push({ key: 'locationCity', label: 'City', value: state.locationCity })
      }
      if (state.locationState) {
        filters.push({ key: 'locationState', label: 'State', value: state.locationState })
      }
      if (state.locationCountry) {
        filters.push({ key: 'locationCountry', label: 'Country', value: state.locationCountry })
      }

      return filters
    }
  },

  actions: {
    /**
     * Set a filter value
     */
    setFilter(key, value) {
      if (key in this.$state) {
        this[key] = value
      }
    },

    /**
     * Clear a specific filter
     */
    clearFilter(key) {
      if (key in this.$state) {
        this[key] = null
      }
    },

    /**
     * Clear all filters
     */
    clearFilters() {
      this.language = null
      this.country = null
      this.genre = null
      this.dateFrom = null
      this.dateTo = null
      this.locationCity = null
      this.locationState = null
      this.locationCountry = null
      this.sortBy = 'BEST_MATCH'
      this.matchBy = 'MOST_TERMS'
      this.perPage = 25
    },

    /**
     * Toggle filter panel
     */
    toggleExpanded() {
      this.expanded = !this.expanded
    },

    /**
     * Set filter panel expanded state
     */
    setExpanded(expanded) {
      this.expanded = expanded
    },

    /**
     * Cache location results
     */
    cacheLocations(type, results) {
      if (type in this.locationCache) {
        this.locationCache[type] = results
      }
    },

    /**
     * Apply preset filters
     */
    applyPreset(preset) {
      switch (preset) {
        case 'english':
          this.language = 'en'
          break
        case 'us':
          this.country = 'us'
          this.language = 'en'
          break
        case 'business':
          this.genre = 'PODCASTSERIES_BUSINESS'
          break
        case 'technology':
          this.genre = 'PODCASTSERIES_TECHNOLOGY'
          break
        default:
          break
      }
    }
  }
})
