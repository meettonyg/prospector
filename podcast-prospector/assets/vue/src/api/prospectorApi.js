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
  async importToPipeline(podcasts, searchMeta, importMode = 'auto') {
    const response = await api.post('/import', {
      podcasts: podcasts.map(p => JSON.stringify(p)),
      search_term: searchMeta.term || '',
      search_type: searchMeta.type || 'byperson',
      import_mode: importMode
    })
    return response.data
  },

  /**
   * Link episode to existing pipeline opportunity
   * @param {number} opportunityId - The opportunity ID to link to
   * @param {Object} result - Raw search result object
   * @param {string} searchType - Search type (byperson, byadvancedepisode, etc.)
   * @returns {Promise<Object>} Link result
   */
  async linkEpisode(opportunityId, result, searchType) {
    const response = await api.post('/link-episode', {
      opportunity_id: opportunityId,
      result: JSON.stringify(result),
      search_type: searchType
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
  },

  // ========================================
  // ChatGPT Integration (Feature Flagged)
  // ========================================

  /**
   * Detect intent using ChatGPT AI
   * Requires chatGpt feature flag to be enabled
   * @param {string} message - User's message
   * @param {Array} context - Previous conversation context (optional)
   * @returns {Promise<Object>} Intent detection result
   */
  async detectIntentWithAI(message, context = []) {
    const response = await api.post('/chat/intent', {
      message,
      context
    })
    return response.data
  },

  /**
   * Get streaming ChatGPT response (returns EventSource URL)
   * Requires chatGpt feature flag to be enabled
   * @param {string} message - User's message
   * @param {Array} context - Previous conversation context (optional)
   * @returns {Promise<Object>} Stream configuration
   */
  async getChatStreamConfig(message, context = []) {
    // Return the endpoint URL and payload for EventSource
    return {
      url: `${config.apiBase || '/wp-json/podcast-prospector/v1'}/chat/stream`,
      payload: {
        message,
        context
      },
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': config.nonce || ''
      }
    }
  },

  /**
   * Stream ChatGPT response using fetch with ReadableStream
   * @param {string} message - User's message
   * @param {Array} context - Previous conversation context
   * @param {Function} onChunk - Callback for each streamed chunk
   * @param {Function} onComplete - Callback when stream completes
   * @param {Function} onError - Callback for errors
   * @returns {Promise<void>}
   */
  async streamChatResponse(message, context = [], { onChunk, onComplete, onError }) {
    try {
      const response = await fetch(
        `${config.apiBase || '/wp-json/podcast-prospector/v1'}/chat/stream`,
        {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': config.nonce || ''
          },
          body: JSON.stringify({ message, context })
        }
      )

      if (!response.ok) {
        throw new Error(`Stream request failed: ${response.status}`)
      }

      const reader = response.body.getReader()
      const decoder = new TextDecoder()

      while (true) {
        const { done, value } = await reader.read()
        if (done) break

        const chunk = decoder.decode(value, { stream: true })
        const lines = chunk.split('\n')

        for (const line of lines) {
          if (line.startsWith('data: ')) {
            const data = line.slice(6).trim()
            if (data === '[DONE]') {
              onComplete?.()
              return
            }
            try {
              const parsed = JSON.parse(data)
              if (parsed.content) {
                onChunk?.(parsed.content)
              }
            } catch (e) {
              // Ignore JSON parse errors for partial chunks
            }
          }
        }
      }

      onComplete?.()
    } catch (error) {
      onError?.(error)
    }
  }
}
