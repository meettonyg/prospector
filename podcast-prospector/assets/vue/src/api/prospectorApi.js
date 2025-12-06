import axios from 'axios'

const config = window.PROSPECTOR_CONFIG || {}

// Create Axios instance with WordPress config
const api = axios.create({
  baseURL: config.apiBase || '/wp-json/podcast-prospector/v1',
  headers: {
    'Content-Type': 'application/json',
    'X-WP-Nonce': config.nonce || ''
  }
})

// Response interceptor for error handling
api.interceptors.response.use(
  response => response,
  error => {
    // Handle common errors
    if (error.response?.status === 401) {
      console.error('Authentication required')
    } else if (error.response?.status === 429) {
      console.error('Rate limit exceeded')
    }
    return Promise.reject(error)
  }
)

export default {
  /**
   * Search podcasts/episodes
   * @param {Object} params - Search parameters
   * @param {string} params.query - Search query
   * @param {string} params.search_type - Search type (byperson, bytitle, byadvancedpodcast, byadvancedepisode)
   * @param {string} params.channel - Channel (podcasts, youtube, summits)
   * @param {number} params.page - Page number
   * @param {number} params.per_page - Results per page
   * @returns {Promise<Object>} Search results
   */
  async search(params) {
    const response = await api.post('/search', params)
    return response.data
  },

  /**
   * Hydrate results - check if podcasts are in CRM
   * @param {Array} identifiers - Array of identifier objects
   * @returns {Promise<Object>} Hydration results
   */
  async hydrate(identifiers) {
    const response = await api.post('/hydrate', { identifiers })
    return response.data
  },

  /**
   * Import podcasts to pipeline
   * @param {Array} podcasts - Array of podcast objects
   * @param {Object} searchMeta - Search metadata
   * @returns {Promise<Object>} Import results
   */
  async importToPipeline(podcasts, searchMeta) {
    const response = await api.post('/import', {
      podcasts: podcasts.map(p => JSON.stringify(p)),
      search_term: searchMeta.term || '',
      search_type: searchMeta.type || 'byperson'
    })
    return response.data
  },

  /**
   * Get user stats (search cap, membership)
   * @returns {Promise<Object>} User stats
   */
  async getUserStats() {
    const response = await api.get('/user/stats')
    return response.data
  },

  /**
   * Location autocomplete
   * @param {string} query - Search query
   * @param {string} type - Location type (cities, states, countries)
   * @returns {Promise<Array>} Location suggestions
   */
  async searchLocations(query, type = 'cities') {
    const response = await api.get(`/locations/${type}`, {
      params: { q: query }
    })
    return response.data
  },

  /**
   * Get sponsored listings
   * @param {Object} params - Query parameters
   * @returns {Promise<Array>} Sponsored listings
   */
  async getSponsoredListings(params = {}) {
    const response = await api.get('/sponsored', { params })
    return response.data
  },

  /**
   * Clear search cache (admin only)
   * @returns {Promise<Object>} Clear result
   */
  async clearCache() {
    const response = await api.delete('/cache/clear')
    return response.data
  }
}
