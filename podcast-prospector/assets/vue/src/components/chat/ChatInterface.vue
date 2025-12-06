<template>
  <div class="flex flex-col h-[600px] bg-white border border-slate-200 rounded-xl overflow-hidden">
    <!-- Chat header -->
    <div class="flex items-center justify-between px-4 py-3 border-b border-slate-200 bg-slate-50">
      <div class="flex items-center gap-2">
        <ChatBubbleLeftRightIcon class="w-5 h-5 text-primary-500" />
        <h3 class="font-medium text-slate-800">Podcast Discovery Assistant</h3>
      </div>
      <button
        v-if="chatStore.hasMessages"
        @click="startNewChat"
        class="text-sm text-slate-500 hover:text-slate-700"
      >
        New Chat
      </button>
    </div>

    <!-- Messages area -->
    <div
      ref="messagesContainer"
      class="flex-1 overflow-y-auto p-4 space-y-4"
    >
      <!-- Empty state -->
      <ChatEmptyState
        v-if="!chatStore.hasMessages"
        @example-click="handleExampleClick"
      />

      <!-- Messages -->
      <ChatMessage
        v-for="message in chatStore.allMessages"
        :key="message.id"
        :message="message"
        @import="handleImport"
      />

      <!-- Typing indicator -->
      <div
        v-if="chatStore.isTyping"
        class="flex items-center gap-2 text-slate-500"
      >
        <div class="flex gap-1">
          <span class="w-2 h-2 bg-slate-400 rounded-full animate-bounce" style="animation-delay: 0ms"></span>
          <span class="w-2 h-2 bg-slate-400 rounded-full animate-bounce" style="animation-delay: 150ms"></span>
          <span class="w-2 h-2 bg-slate-400 rounded-full animate-bounce" style="animation-delay: 300ms"></span>
        </div>
        <span class="text-sm">Searching...</span>
      </div>
    </div>

    <!-- Quick actions -->
    <QuickActionChips
      v-if="suggestedActions.length > 0"
      :actions="suggestedActions"
      @action="handleQuickAction"
      class="px-4 pb-2"
    />

    <!-- Input area -->
    <ChatInput
      v-model="inputText"
      :disabled="chatStore.isTyping || !userStore.canSearch"
      :placeholder="inputPlaceholder"
      @send="handleSend"
    />
  </div>
</template>

<script setup>
import { ref, computed, nextTick, watch } from 'vue'
import { ChatBubbleLeftRightIcon } from '@heroicons/vue/24/outline'
import { useChatStore } from '../../stores/chatStore'
import { useSearchStore } from '../../stores/searchStore'
import { useUserStore } from '../../stores/userStore'
import { useToast } from '../../stores/toastStore'
import {
  detectIntent,
  intentToSearchParams,
  generateResponse,
  getSuggestedActions
} from '../../utils/intentDetector'
import api from '../../api/prospectorApi'

import ChatEmptyState from './ChatEmptyState.vue'
import ChatMessage from './ChatMessage.vue'
import ChatInput from './ChatInput.vue'
import QuickActionChips from './QuickActionChips.vue'

const chatStore = useChatStore()
const searchStore = useSearchStore()
const userStore = useUserStore()
const { success, error: showError } = useToast()

const messagesContainer = ref(null)
const inputText = ref('')
const suggestedActions = ref([])

const inputPlaceholder = computed(() => {
  if (!userStore.canSearch) {
    return 'Search limit reached'
  }
  return 'Ask me to find podcasts...'
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

  // Get search params
  const searchParams = intentToSearchParams(intent)

  if (!searchParams) {
    const response = generateResponse(intent, 0)
    chatStore.addAssistantMessage(response)
    suggestedActions.value = getSuggestedActions(intent, false)
    return
  }

  // Perform search
  chatStore.setTyping(true)

  try {
    const response = await api.search({
      ...searchParams,
      results_per_page: 5
    })

    // Extract results
    let results = []
    if (response.data?.data?.search?.podcastSeries) {
      results = response.data.data.search.podcastSeries
    } else if (response.data?.feeds) {
      results = response.data.feeds
    }

    // Generate response
    const assistantResponse = generateResponse(intent, results.length)

    // Add message with results
    chatStore.addAssistantMessage(assistantResponse, results)

    // Update suggested actions
    suggestedActions.value = getSuggestedActions(intent, results.length > 0)

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
      inputText.value = 'Show more results'
      handleSend()
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
}
</script>
