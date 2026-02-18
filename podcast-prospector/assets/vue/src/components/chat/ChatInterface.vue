<template>
  <div class="prospector-container">
    <!-- Main Card Container -->
    <div class="prospector-card">
      <!-- Header -->
      <AppHeader
        :mode="mode"
        @update:mode="$emit('update:mode', $event)"
      />
    </div>

    <!-- Chat Card -->
    <div class="prospector-card prospector-card--chat">
      <!-- Chat subheader -->
      <div class="prospector-chat__subheader">
        <div class="prospector-chat__subheader-title">
          <ChatBubbleLeftRightIcon class="prospector-chat__subheader-icon" />
          <h3 class="prospector-chat__subheader-text">Podcast Discovery Assistant</h3>
        </div>
        <button
          v-if="chatStore.hasMessages"
          @click="startNewChat"
          class="prospector-chat__subheader-action"
        >
          New Chat
        </button>
      </div>

    <!-- Messages area -->
    <div
      ref="messagesContainer"
      class="prospector-chat__messages"
    >
      <!-- Empty state -->
      <ChatEmptyState
        v-if="!chatStore.hasMessages"
        @example-click="handleExampleClick"
      />

      <!-- Messages -->
      <div class="prospector-chat__messages-list">
        <ChatMessage
          v-for="message in chatStore.allMessages"
          :key="message.id"
          :message="message"
          @import="handleImport"
        />
      </div>

      <!-- Typing indicator -->
      <div
        v-if="chatStore.isTyping"
        class="prospector-chat__typing"
      >
        <div class="prospector-chat__typing-dots">
          <span class="prospector-chat__typing-dot"></span>
          <span class="prospector-chat__typing-dot"></span>
          <span class="prospector-chat__typing-dot"></span>
        </div>
        <span class="prospector-chat__typing-text">Searching...</span>
      </div>
    </div>

    <!-- Quick actions -->
    <QuickActionChips
      v-if="suggestedActions.length > 0"
      :actions="suggestedActions"
      @action="handleQuickAction"
      class="prospector-chat__quick-actions"
    />

      <!-- Filter bar (collapsible) -->
      <ChatFilterBar
        v-if="showFilters"
        @clear="handleClearFilters"
        @change="handleFilterChange"
      />

      <!-- Input area -->
      <ChatInput
        v-model="inputText"
        :disabled="chatStore.isTyping || !userStore.canSearch"
        :placeholder="inputPlaceholder"
        @send="handleSend"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, computed, nextTick, watch, inject } from 'vue'
import { ChatBubbleLeftRightIcon } from '@heroicons/vue/24/outline'
import { useChatStore } from '../../stores/chatStore'
import { useUserStore } from '../../stores/userStore'
import { useFilterStore } from '../../stores/filterStore'
import { useToast } from '../../stores/toastStore'
import {
  detectIntent,
  intentToSearchParams,
  generateResponse,
  getSuggestedActions,
  parseFilterValue
} from '../../utils/intentDetector'
import api from '../../api/prospectorApi'

// Get config for feature flags
const config = inject('config', {})

import AppHeader from '../common/AppHeader.vue'
import ChatEmptyState from './ChatEmptyState.vue'
import ChatMessage from './ChatMessage.vue'
import ChatInput from './ChatInput.vue'
import ChatFilterBar from './ChatFilterBar.vue'
import QuickActionChips from './QuickActionChips.vue'

defineProps({
  mode: {
    type: String,
    default: 'chat'
  }
})

defineEmits(['update:mode'])

const chatStore = useChatStore()
const userStore = useUserStore()
const filterStore = useFilterStore()
const { success, error: showError } = useToast()

const messagesContainer = ref(null)
const inputText = ref('')
const suggestedActions = ref([])
const showFilters = ref(false)

// ChatGPT feature flag
const chatGptEnabled = computed(() => config.features?.chatGpt === true)

const inputPlaceholder = computed(() => {
  if (!userStore.canSearch) {
    return 'Search limit reached'
  }
  return chatGptEnabled.value 
    ? 'Ask me anything about podcasts...' 
    : 'Ask me to find podcasts...'
})

// Scroll to bottom when messages change
watch(
  () => chatStore.messageCount,
  async () => {
    await nextTick()
    scrollToBottom()
  }
)

const scrollToBottom = () => {
  if (messagesContainer.value) {
    messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
  }
}

const CHAT_RESULTS_PER_PAGE = 5

/**
 * Build hydration identifiers from results (same logic as searchStore).
 */
const buildHydrationIdentifiers = (results, searchType) => {
  return results.map(result => {
    if (searchType === 'byadvancedpodcast' || searchType === 'byadvancedepisode') {
      const podcast = result.podcastSeries || result
      return {
        itunes_id: podcast.itunesId || null,
        rss_url: podcast.rssUrl || null,
        podcast_index_id: null
      }
    }
    return {
      itunes_id: result.itunesId || result.feedItunesId || null,
      rss_url: result.feedUrl || result.url || null,
      podcast_index_id: result.feedId || result.id || null
    }
  })
}

/**
 * Hydrate results for a message — check which podcasts are already in CRM.
 */
const hydrateMessage = async (messageId, results, searchType) => {
  if (results.length === 0) return
  try {
    const identifiers = buildHydrationIdentifiers(results, searchType)
    const response = await api.hydrate(identifiers)
    chatStore.setMessageHydration(messageId, response.results || {})
  } catch (err) {
    // Don't block the UI if hydration fails
    console.warn('[Chat] Hydration failed:', err)
  }
}

/**
 * Normalize API response into a flat results array.
 * Each handler has a `match` predicate and a `extract` mapper.
 * Order matters — first match wins.
 */
const resultHandlers = [
  {
    name: 'taddy-series',
    match: (r) => r.data?.data?.searchForTerm?.podcastSeries,
    extract: (r) => r.data.data.searchForTerm.podcastSeries.map(p => ({
      ...p,
      image: p.imageUrl || '',
      artwork: p.imageUrl || '',
      title: p.name || 'Untitled Podcast',
      author: p.authorName || ''
    }))
  },
  {
    name: 'taddy-episodes',
    match: (r) => r.data?.data?.searchForTerm?.podcastEpisodes,
    extract: (r) => r.data.data.searchForTerm.podcastEpisodes.map(ep => ({
      ...ep,
      image: ep.podcastSeries?.imageUrl || '',
      artwork: ep.podcastSeries?.imageUrl || '',
      title: ep.name || 'Untitled Episode',
      author: ep.podcastSeries?.authorName || ''
    }))
  },
  {
    name: 'podcastindex-items',
    match: (r) => r.data?.items,
    extract: (r) => r.data.items.map(item => ({
      ...item,
      image: item.feedImage || '',
      artwork: item.feedImage || '',
      author: item.feedTitle || ''
    }))
  },
  {
    name: 'podcastindex-feeds',
    match: (r) => r.data?.feeds,
    extract: (r) => r.data.feeds
  },
  {
    name: 'youtube',
    match: (r) => r.data?.data?.items,
    extract: (r) => r.data.data.items.map(item => ({
      ...item,
      image: item.thumbnailUrl || '',
      artwork: item.thumbnailUrl || '',
      author: item.channelTitle || ''
    }))
  },
  {
    name: 'generic-results',
    match: (r) => r.data?.results,
    extract: (r) => r.data.results
  },
  {
    name: 'raw-array',
    match: (r) => Array.isArray(r.data),
    extract: (r) => r.data
  }
]

const extractResults = (response) => {
  const handler = resultHandlers.find(h => h.match(response))
  return handler ? handler.extract(response) : []
}

const handleSend = async () => {
  const query = inputText.value.trim()
  if (!query) return

  // Clear input
  inputText.value = ''

  // Add user message
  chatStore.addUserMessage(query)

  // Detect intent
  const intent = detectIntent(query)

  // Handle based on intent
  if (intent.intent === 'greeting' || intent.intent === 'help') {
    const response = generateResponse(intent)
    chatStore.addAssistantMessage(response)
    suggestedActions.value = getSuggestedActions(intent)
    return
  }

  // Handle filter intent — apply filter and re-run last search
  if (intent.intent === 'filter') {
    const parsed = parseFilterValue(intent.extractedValue)
    if (parsed) {
      filterStore.setFilter(parsed.key, parsed.value)
      const response = generateResponse(intent)
      chatStore.addAssistantMessage(response)

      // If there was a previous search, re-run it with the new filter
      if (chatStore.lastSearchParams) {
        chatStore.setSearchContext(chatStore.lastSearchParams, chatStore.lastIntent)
        inputText.value = '' // already cleared, but be safe
        await executeSearch(chatStore.lastSearchParams, 1, chatStore.lastIntent)
      } else {
        suggestedActions.value = [
          { label: 'Start a new search', action: 'newSearch' }
        ]
      }
    } else {
      chatStore.addAssistantMessage(
        "I didn't recognize that filter. Try: \"filter by English\", \"from US\", or \"filter by business\"."
      )
      suggestedActions.value = [
        { label: 'Start a new search', action: 'newSearch' }
      ]
    }
    return
  }

  // Get search params — pass last search context for showMore intents
  const searchParams = intentToSearchParams(intent, chatStore.lastSearchParams)

  if (!searchParams) {
    const response = generateResponse(intent, 0)
    chatStore.addAssistantMessage(response)
    suggestedActions.value = getSuggestedActions(intent, false)
    return
  }

  // Determine page number
  const isShowMore = intent.intent === 'showMore'
  let page = 1
  if (isShowMore) {
    chatStore.nextPage()
    page = chatStore.currentPage
  } else {
    // New search — save context and reset page
    chatStore.setSearchContext(searchParams, intent)
    page = 1
  }

  const useIntent = isShowMore && chatStore.lastIntent ? chatStore.lastIntent : intent
  const responseTextOverride = isShowMore ? 'more' : null
  await executeSearch(searchParams, page, useIntent, responseTextOverride)
}

/**
 * Core search execution — shared by handleSend, handleLoadMore, and filter re-search.
 */
const executeSearch = async (searchParams, page, displayIntent, responseTextOverride = null) => {
  chatStore.setTyping(true)

  try {
    const response = await api.search({
      ...searchParams,
      ...filterStore.filterParams,
      results_per_page: CHAT_RESULTS_PER_PAGE,
      page
    })

    // Extract results using comprehensive normalizer
    const results = extractResults(response)

    // Determine if more results are likely available
    const hasMore = results.length >= CHAT_RESULTS_PER_PAGE
    if (!hasMore) {
      chatStore.setNoMore()
    }

    // Generate response text
    let assistantResponse
    if (responseTextOverride === 'more' && results.length > 0) {
      assistantResponse = `Here are ${results.length} more result${results.length === 1 ? '' : 's'}:`
    } else {
      assistantResponse = generateResponse(displayIntent, results.length)
    }

    // Add message with results (hydration filled in asynchronously)
    const message = chatStore.addAssistantMessage(assistantResponse, results)

    // Update suggested actions
    suggestedActions.value = getSuggestedActions(
      displayIntent,
      results.length > 0,
      { hasMore, hasActiveFilters: filterStore.hasActiveFilters }
    )

    // Decrement search count
    userStore.decrementSearchCount()

    // Hydrate results in background (non-blocking)
    hydrateMessage(message.id, results, searchParams.search_type)

  } catch (err) {
    chatStore.addAssistantMessage(
      "I'm sorry, I encountered an error while searching. Please try again."
    )
    chatStore.setError(err.message)
  } finally {
    chatStore.setTyping(false)
  }
}

const handleExampleClick = (example) => {
  inputText.value = example
  handleSend()
}

const handleQuickAction = (action) => {
  switch (action) {
    case 'newSearch':
      inputText.value = ''
      break
    case 'loadMore':
      handleLoadMore()
      break
    case 'openFilters':
      showFilters.value = !showFilters.value
      break
    case 'clearFilters':
      handleClearFilters()
      break
    case 'examplePerson':
      inputText.value = 'Find podcasts featuring Tim Ferriss'
      handleSend()
      break
    case 'exampleTopic':
      inputText.value = 'Podcasts about entrepreneurship'
      handleSend()
      break
    default:
      break
  }
}

/**
 * Load more results for the previous search (next page).
 */
const handleLoadMore = async () => {
  if (!chatStore.lastSearchParams || !chatStore.hasMore) return
  chatStore.nextPage()
  const useIntent = chatStore.lastIntent || { intent: 'searchByTopic' }
  await executeSearch(chatStore.lastSearchParams, chatStore.currentPage, useIntent, 'more')
}

/**
 * Handle filter clear — reset filters and re-run previous search if any.
 */
const handleClearFilters = () => {
  filterStore.clearFilters()
  showFilters.value = false
  // Re-run previous search without filters
  if (chatStore.lastSearchParams) {
    chatStore.setSearchContext(chatStore.lastSearchParams, chatStore.lastIntent)
    const useIntent = chatStore.lastIntent || { intent: 'searchByTopic' }
    executeSearch(chatStore.lastSearchParams, 1, useIntent)
  }
}

/**
 * Handle filter change from ChatFilterBar — re-run search with new filters.
 */
const handleFilterChange = () => {
  if (chatStore.lastSearchParams) {
    chatStore.setSearchContext(chatStore.lastSearchParams, chatStore.lastIntent)
    const useIntent = chatStore.lastIntent || { intent: 'searchByTopic' }
    executeSearch(chatStore.lastSearchParams, 1, useIntent)
  }
}

const handleImport = async ({ result: podcast, messageId, index }) => {
  try {
    const response = await api.importToPipeline([podcast], {
      term: 'chat',
      type: 'chat'
    })

    if (response.success_count > 0) {
      success('Added to Pipeline', `${podcast.title || 'Podcast'} added to your tracker.`)

      // Update hydration for this result so the badge appears immediately
      const message = chatStore.messages.find(m => m.id === messageId)
      if (message) {
        const hydration = { ...(message.hydration || {}) }
        hydration[index] = { ...(hydration[index] || {}), tracked: true }
        chatStore.setMessageHydration(messageId, hydration)
      }
    }
  } catch (err) {
    showError('Import Failed', err.message)
  }
}

const startNewChat = () => {
  chatStore.clearMessages()
  suggestedActions.value = []
  inputText.value = ''
  showFilters.value = false
  filterStore.clearFilters()
}
</script>

<style scoped>
.prospector-card--chat {
  margin-top: var(--prospector-space-lg);
  display: flex;
  flex-direction: column;
  height: 600px;
  overflow: hidden;
}

.prospector-chat__subheader {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--prospector-space-md) var(--prospector-space-lg);
  border-bottom: 1px solid var(--prospector-slate-200);
  background: var(--prospector-slate-50);
}

.prospector-chat__subheader-title {
  display: flex;
  align-items: center;
  gap: var(--prospector-space-sm);
}

.prospector-chat__subheader-icon {
  width: 1.125rem;
  height: 1.125rem;
  color: var(--prospector-primary-500);
}

.prospector-chat__subheader-text {
  font-weight: 500;
  font-size: var(--prospector-font-size-sm);
  color: var(--prospector-slate-800);
  margin: 0;
}

.prospector-chat__subheader-action {
  font-size: var(--prospector-font-size-xs);
  color: var(--prospector-slate-500);
  background: transparent;
  border: none;
  cursor: pointer;
  transition: color var(--prospector-transition-fast);
}

.prospector-chat__subheader-action:hover {
  color: var(--prospector-slate-700);
}

.prospector-chat__messages {
  flex: 1;
  overflow-y: auto;
  padding: var(--prospector-space-md);
}

.prospector-chat__messages-list {
  display: flex;
  flex-direction: column;
  gap: var(--prospector-space-md);
}

.prospector-chat__typing {
  display: flex;
  align-items: center;
  gap: var(--prospector-space-sm);
  color: var(--prospector-slate-500);
  margin-top: var(--prospector-space-md);
}

.prospector-chat__typing-dots {
  display: flex;
  gap: var(--prospector-space-xs);
}

.prospector-chat__typing-dot {
  width: 0.5rem;
  height: 0.5rem;
  background: var(--prospector-slate-400);
  border-radius: 50%;
  animation: prospectorBounce 1.4s infinite ease-in-out both;
}

.prospector-chat__typing-dot:nth-child(1) {
  animation-delay: 0ms;
}

.prospector-chat__typing-dot:nth-child(2) {
  animation-delay: 150ms;
}

.prospector-chat__typing-dot:nth-child(3) {
  animation-delay: 300ms;
}

.prospector-chat__typing-text {
  font-size: var(--prospector-font-size-sm);
}

.prospector-chat__quick-actions {
  padding: 0 var(--prospector-space-md) var(--prospector-space-sm);
}

@keyframes prospectorBounce {
  0%, 80%, 100% {
    transform: scale(0);
  }
  40% {
    transform: scale(1);
  }
}
</style>
