import { ref, computed } from 'vue'
import { LANGUAGES, COUNTRIES, GENRES, SORT_OPTIONS } from '../utils/constants'

/**
 * Composable for managing search filters
 */
export function useFilters() {
  // State
  const language = ref(null)
  const country = ref(null)
  const genre = ref(null)
  const safeMode = ref(true)
  const dateRange = ref(null)
  const sortBy = ref('BEST_MATCH')
  const matchBy = ref('MOST_TERMS')
  const locationCity = ref(null)
  const locationState = ref(null)
  const locationCountry = ref(null)
  const expanded = ref(false)

  // Location autocomplete cache
  const locationCache = ref({
    cities: [],
    states: [],
    countries: []
  })

  // Computed
  const hasActiveFilters = computed(() => {
    return !!(
      language.value ||
      country.value ||
      genre.value ||
      dateRange.value ||
      locationCity.value ||
      locationState.value ||
      locationCountry.value
    )
  })

  const activeFilterCount = computed(() => {
    let count = 0
    if (language.value) count++
    if (country.value) count++
    if (genre.value) count++
    if (dateRange.value) count++
    if (locationCity.value) count++
    if (locationState.value) count++
    if (locationCountry.value) count++
    return count
  })

  const filterParams = computed(() => {
    const params = {}

    if (language.value) params.language = language.value
    if (country.value) params.country = country.value
    if (genre.value) params.genre = genre.value
    if (safeMode.value) params.safe_mode = '1'
    if (sortBy.value) params.sort_order = sortBy.value
    if (matchBy.value) params.match_by = matchBy.value

    // Location filters
    if (locationCity.value) params.location_city = locationCity.value
    if (locationState.value) params.location_state = locationState.value
    if (locationCountry.value) params.location_country = locationCountry.value

    // Date range
    if (dateRange.value) {
      if (dateRange.value.start) params.date_from = dateRange.value.start
      if (dateRange.value.end) params.date_to = dateRange.value.end
    }

    return params
  })

  const activeFiltersDisplay = computed(() => {
    const filters = []

    if (language.value) {
      const lang = LANGUAGES.find(l => l.value === language.value)
      filters.push({ key: 'language', label: 'Language', value: lang?.label || language.value })
    }
    if (country.value) {
      const c = COUNTRIES.find(c => c.value === country.value)
      filters.push({ key: 'country', label: 'Country', value: c?.label || country.value })
    }
    if (genre.value) {
      const g = GENRES.find(g => g.value === genre.value)
      filters.push({ key: 'genre', label: 'Genre', value: g?.label || genre.value })
    }
    if (locationCity.value) {
      filters.push({ key: 'locationCity', label: 'City', value: locationCity.value })
    }
    if (locationState.value) {
      filters.push({ key: 'locationState', label: 'State', value: locationState.value })
    }
    if (locationCountry.value) {
      filters.push({ key: 'locationCountry', label: 'Location', value: locationCountry.value })
    }

    return filters
  })

  // Methods
  function setFilter(key, value) {
    const refs = {
      language,
      country,
      genre,
      safeMode,
      dateRange,
      sortBy,
      matchBy,
      locationCity,
      locationState,
      locationCountry
    }
    if (refs[key]) {
      refs[key].value = value
    }
  }

  function clearFilter(key) {
    setFilter(key, null)
  }

  function clearFilters() {
    language.value = null
    country.value = null
    genre.value = null
    dateRange.value = null
    locationCity.value = null
    locationState.value = null
    locationCountry.value = null
    sortBy.value = 'BEST_MATCH'
    matchBy.value = 'MOST_TERMS'
  }

  function toggleExpanded() {
    expanded.value = !expanded.value
  }

  function setExpanded(value) {
    expanded.value = value
  }

  function cacheLocations(type, results) {
    if (type in locationCache.value) {
      locationCache.value[type] = results
    }
  }

  function applyPreset(preset) {
    switch (preset) {
      case 'english':
        language.value = 'en'
        break
      case 'us':
        country.value = 'us'
        language.value = 'en'
        break
      case 'business':
        genre.value = 'PODCASTSERIES_BUSINESS'
        break
      case 'technology':
        genre.value = 'PODCASTSERIES_TECHNOLOGY'
        break
      default:
        break
    }
  }

  return {
    // State
    language,
    country,
    genre,
    safeMode,
    dateRange,
    sortBy,
    matchBy,
    locationCity,
    locationState,
    locationCountry,
    expanded,
    locationCache,

    // Computed
    hasActiveFilters,
    activeFilterCount,
    filterParams,
    activeFiltersDisplay,

    // Constants
    LANGUAGES,
    COUNTRIES,
    GENRES,
    SORT_OPTIONS,

    // Methods
    setFilter,
    clearFilter,
    clearFilters,
    toggleExpanded,
    setExpanded,
    cacheLocations,
    applyPreset
  }
}
