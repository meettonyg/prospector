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
import { useToast } from '../../stores/toastStore'
import {
  detectIntent,
  intentToSearchParams,
  generateResponse,
  getSuggestedActions
} from '../../utils/intentDetector'
import api from '../../api/prospectorApi'

// Get config for feature flags
const config = inject('config', {})

import AppHeader from '../common/AppHeader.vue'
import ChatEmptyState from './ChatEmptyState.vue'
import ChatMessage from './ChatMessage.vue'
import ChatInput from './ChatInput.vue'
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
const { success, error: showError } = useToast()

const messagesContainer = ref(null)
const inputText = ref('')
const suggestedActions = ref([])

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
 * Normalize API response into a flat results array.
 * Mirrors the logic in searchStore.search() to handle all API formats.
 */
const extractResults = (response) => {
  // Taddy — podcast series
  if (response.data?.data?.searchForTerm?.podcastSeries) {
    return response.data.data.searchForTerm.podcastSeries.map(p => ({
      ...p,
      image: p.imageUrl || '',
      artwork: p.imageUrl || '',
      title: p.name || 'Untitled Podcast',
      author: p.authorName || ''
    }))
  }
  // Taddy — podcast episodes
  if (response.data?.data?.searchForTerm?.podcastEpisodes) {
    return response.data.data.searchForTerm.podcastEpisodes.map(ep => ({
      ...ep,
      image: ep.podcastSeries?.imageUrl || '',
      artwork: ep.podcastSeries?.imageUrl || '',
      title: ep.name || 'Untitled Episode',
      author: ep.podcastSeries?.authorName || ''
    }))
  }
  // PodcastIndex — by person (items)
  if (response.data?.items) {
    return response.data.items.map(item => ({
      ...item,
      image: item.feedImage || '',
      artwork: item.feedImage || '',
      author: item.feedTitle || ''
    }))
  }
  // PodcastIndex — by title (feeds)
  if (response.data?.feeds) {
    return response.data.feeds
  }
  // YouTube
  if (response.data?.data?.items) {
    return response.data.data.items.map(item => ({
      ...item,
      image: item.thumbnailUrl || '',
      artwork: item.thumbnailUrl || '',
      author: item.channelTitle || ''
    }))
  }
  // Fallbacks
  if (response.data?.results) return response.data.results
  if (Array.isArray(response.data)) return response.data
  return []
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

  // Get search params — pass last search context for showMore / filter intents
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

  // Perform search
  chatStore.setTyping(true)

  try {
    const response = await api.search({
      ...searchParams,
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
    const useIntent = isShowMore && chatStore.lastIntent ? chatStore.lastIntent : intent
    const assistantResponse = isShowMore && results.length > 0
      ? `Here are ${results.length} more result${results.length === 1 ? '' : 's'}:`
      : generateResponse(useIntent, results.length)

    // Add message with results
    chatStore.addAssistantMessage(assistantResponse, results)

    // Update suggested actions
    suggestedActions.value = getSuggestedActions(
      useIntent,
      results.length > 0,
      { hasMore, hasActiveFilters: false }
    )

    // Decrement search count
    userStore.decrementSearchCount()

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
    case 'clearFilters':
      // placeholder for future filter support
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
  chatStore.setTyping(true)

  try {
    const response = await api.search({
      ...chatStore.lastSearchParams,
      results_per_page: CHAT_RESULTS_PER_PAGE,
      page: chatStore.currentPage
    })

    const results = extractResults(response)
    const hasMore = results.length >= CHAT_RESULTS_PER_PAGE
    if (!hasMore) {
      chatStore.setNoMore()
    }

    const assistantResponse = results.length > 0
      ? `Here are ${results.length} more result${results.length === 1 ? '' : 's'}:`
      : "No more results found."

    chatStore.addAssistantMessage(assistantResponse, results)

    const useIntent = chatStore.lastIntent || { intent: 'searchByTopic' }
    suggestedActions.value = getSuggestedActions(
      useIntent,
      results.length > 0,
      { hasMore, hasActiveFilters: false }
    )

    userStore.decrementSearchCount()
  } catch (err) {
    chatStore.addAssistantMessage(
      "I'm sorry, I encountered an error loading more results. Please try again."
    )
    chatStore.setError(err.message)
  } finally {
    chatStore.setTyping(false)
  }
}

const handleImport = async (podcast) => {
  try {
    const response = await api.importToPipeline([podcast], {
      term: 'chat',
      type: 'chat'
    })

    if (response.success_count > 0) {
      success('Added to Pipeline', `${podcast.title || 'Podcast'} added to your tracker.`)
    }
  } catch (err) {
    showError('Import Failed', err.message)
  }
}

const startNewChat = () => {
  chatStore.clearMessages()
  suggestedActions.value = []
  inputText.value = ''
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
