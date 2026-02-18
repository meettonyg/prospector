/**
 * Intent Detection for Chat Interface
 *
 * Detects user intent from natural language queries and maps to search parameters.
 */

const INTENT_PATTERNS = {
  searchByPerson: [
    /find.*(podcasts?|episodes?|shows?).*(?:featuring|with|by|guest)\s+(.+)/i,
    /where has\s+(.+)\s+(?:appeared|been|spoken)/i,
    /podcasts?\s+(?:with|featuring)\s+(.+)/i,
    /who\s+(?:has\s+)?(?:interviewed|had)\s+(.+)/i,
    /(.+)\s+(?:podcast\s+)?(?:appearances?|interviews?)/i
  ],
  searchByTopic: [
    /find.*(podcasts?|shows?).*(?:about|on|covering)\s+(.+)/i,
    /(podcasts?|shows?)\s+(?:about|on|covering)\s+(.+)/i,
    /(?:search|look)\s+(?:for\s+)?(.+)\s+podcasts?/i,
    /podcasts?\s+(?:discussing|talking about)\s+(.+)/i
  ],
  searchByTitle: [
    /(?:look up|find|search for)\s+(?:the\s+)?["']?(.+?)["']?\s+(?:podcast|show)/i,
    /(?:is there a podcast called|find the podcast)\s+["']?(.+?)["']?/i
  ],
  showMore: [
    /show\s+(?:me\s+)?more/i,
    /more\s+results/i,
    /next\s+(?:page|results)/i,
    /load\s+more/i
  ],
  filter: [
    /filter\s+(?:by|to)\s+(.+)/i,
    /only\s+(?:show|include)\s+(.+)/i,
    /(?:in|from)\s+(english|spanish|french|german)/i,
    /(?:from|in)\s+(?:the\s+)?(us|uk|canada|australia)/i
  ],
  import: [
    /(?:add|import|save)\s+(?:this|that|it)\s+(?:to\s+)?(?:my\s+)?(?:pipeline|tracker)/i,
    /(?:add|import|save)\s+(.+)\s+(?:to\s+)?(?:my\s+)?(?:pipeline|tracker)/i,
    /(?:I\s+)?want\s+(?:to\s+)?(?:track|follow)\s+(?:this|that|it)/i
  ],
  help: [
    /^(?:help|how\s+(?:do\s+I|can\s+I)|what\s+can\s+(?:you|I)\s+do)/i,
    /^(?:commands?|examples?)/i
  ],
  greeting: [
    /^(?:hi|hello|hey|good\s+(?:morning|afternoon|evening))/i
  ]
}

/**
 * Detect intent from user query
 * @param {string} query - User's natural language query
 * @returns {Object} Detected intent with extracted values
 */
export function detectIntent(query) {
  if (!query || typeof query !== 'string') {
    return {
      intent: 'unknown',
      match: null,
      extractedValue: null,
      confidence: 'none'
    }
  }

  const trimmedQuery = query.trim()

  // Check each intent type
  for (const [intent, patterns] of Object.entries(INTENT_PATTERNS)) {
    for (const pattern of patterns) {
      const match = trimmedQuery.match(pattern)
      if (match) {
        return {
          intent,
          match,
          extractedValue: extractValue(match, intent),
          confidence: 'high',
          originalQuery: trimmedQuery
        }
      }
    }
  }

  // Default: treat as topic search if it looks like a search
  if (trimmedQuery.length > 2 && !trimmedQuery.includes('?')) {
    return {
      intent: 'searchByTopic',
      match: null,
      extractedValue: trimmedQuery,
      confidence: 'low',
      originalQuery: trimmedQuery
    }
  }

  return {
    intent: 'unknown',
    match: null,
    extractedValue: null,
    confidence: 'none',
    originalQuery: trimmedQuery
  }
}

/**
 * Extract the relevant value from a regex match
 */
function extractValue(match, intent) {
  if (!match) return null

  switch (intent) {
    case 'searchByPerson':
      // Return the captured group that contains the person's name
      return match[2] || match[1] || null

    case 'searchByTopic':
    case 'searchByTitle':
      return match[1] || match[2] || null

    case 'filter':
      return match[1] || null

    case 'import':
      return match[1] || 'current'

    default:
      return match[1] || null
  }
}

/**
 * Map intent to search parameters
 * @param {Object} detectedIntent - Result from detectIntent
 * @param {Object|null} lastSearchParams - Previous search params for context-dependent intents
 * @returns {Object|null} Search parameters for API, or null if not a searchable intent
 */
export function intentToSearchParams(detectedIntent, lastSearchParams = null) {
  const { intent, extractedValue } = detectedIntent

  switch (intent) {
    case 'searchByPerson':
      return {
        search_term: extractedValue,
        search_type: 'byperson'
      }

    case 'searchByTopic':
      return {
        search_term: extractedValue,
        search_type: 'bytitle'
      }

    case 'searchByTitle':
      return {
        search_term: extractedValue,
        search_type: 'bytitle'
      }

    case 'showMore':
      // Re-use previous search params — caller handles page increment
      if (lastSearchParams) {
        return { ...lastSearchParams }
      }
      return null

    default:
      return null
  }
}

/**
 * Generate a natural language response based on intent
 * @param {Object} detectedIntent - Detected intent
 * @param {number} resultCount - Number of results found
 * @returns {string} Natural language response
 */
export function generateResponse(detectedIntent, resultCount = 0) {
  const { intent, extractedValue } = detectedIntent

  switch (intent) {
    case 'searchByPerson':
      if (resultCount > 0) {
        return `I found ${resultCount} podcast${resultCount === 1 ? '' : 's'} featuring "${extractedValue}". Here's what I found:`
      }
      return `I couldn't find any podcasts featuring "${extractedValue}". Try a different name or check the spelling.`

    case 'searchByTopic':
      if (resultCount > 0) {
        return `Here are ${resultCount} podcast${resultCount === 1 ? '' : 's'} about "${extractedValue}":`
      }
      return `No podcasts found about "${extractedValue}". Try using different keywords.`

    case 'searchByTitle':
      if (resultCount > 0) {
        return `I found ${resultCount} podcast${resultCount === 1 ? '' : 's'} matching "${extractedValue}":`
      }
      return `I couldn't find a podcast called "${extractedValue}". Check the title and try again.`

    case 'greeting':
      return "Hello! I'm your podcast discovery assistant. You can ask me to find podcasts by guest name, topic, or title. For example:\n\n• \"Find podcasts featuring Tim Ferriss\"\n• \"Podcasts about entrepreneurship\"\n• \"Search for The Joe Rogan Experience\""

    case 'help':
      return "Here's what I can help you with:\n\n**Search by Guest**: \"Find podcasts with [name]\"\n**Search by Topic**: \"Podcasts about [topic]\"\n**Search by Title**: \"Look up [podcast name]\"\n**Import**: \"Add this to my pipeline\"\n\nJust type naturally and I'll understand what you're looking for!"

    case 'showMore':
      return "Loading more results..."

    case 'import':
      return "I'll add that to your pipeline right away."

    default:
      return "I'm not sure what you're looking for. Try asking me to find podcasts by guest name, topic, or title."
  }
}

/**
 * Get suggested follow-up actions based on context
 * @param {Object} detectedIntent - Detected intent
 * @param {boolean} hasResults - Whether there are results
 * @param {Object} options - Additional context
 * @param {boolean} options.hasMore - Whether more pages of results are available
 * @param {boolean} options.hasActiveFilters - Whether any filters are currently set
 * @returns {Array} Array of suggested actions
 */
export function getSuggestedActions(detectedIntent, hasResults = false, { hasMore = true, hasActiveFilters = false } = {}) {
  const { intent } = detectedIntent

  if (!hasResults && intent !== 'greeting' && intent !== 'help') {
    const actions = [
      { label: 'Try a different search', action: 'newSearch' }
    ]
    if (hasActiveFilters) {
      actions.push({ label: 'Clear filters', action: 'clearFilters' })
    }
    return actions
  }

  switch (intent) {
    case 'searchByPerson':
    case 'searchByTopic':
    case 'searchByTitle':
    case 'showMore': {
      const actions = []
      if (hasMore) {
        actions.push({ label: 'Show more results', action: 'loadMore' })
      }
      actions.push({ label: 'New search', action: 'newSearch' })
      return actions
    }

    case 'greeting':
    case 'help':
      return [
        { label: 'Find podcasts by guest', action: 'examplePerson' },
        { label: 'Search by topic', action: 'exampleTopic' }
      ]

    default:
      return [
        { label: 'Start a new search', action: 'newSearch' }
      ]
  }
}
