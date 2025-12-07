<template>
  <div class="prospector-chat">
    <!-- Chat header -->
    <div class="prospector-chat__header">
      <div class="prospector-chat__header-title">
        <ChatBubbleLeftRightIcon class="prospector-chat__header-icon" />
        <h3 class="prospector-chat__header-text">Podcast Discovery Assistant</h3>
      </div>
      <button
        v-if="chatStore.hasMessages"
        @click="startNewChat"
        class="prospector-chat__header-action"
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

<style scoped>
.prospector-chat {
  display: flex;
  flex-direction: column;
  height: 600px;
  background: white;
  border: 1px solid var(--prospector-slate-200);
  border-radius: var(--prospector-radius-xl);
  overflow: hidden;
}

.prospector-chat__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--prospector-space-md);
  border-bottom: 1px solid var(--prospector-slate-200);
  background: var(--prospector-slate-50);
}

.prospector-chat__header-title {
  display: flex;
  align-items: center;
  gap: var(--prospector-space-sm);
}

.prospector-chat__header-icon {
  width: 1.25rem;
  height: 1.25rem;
  color: var(--prospector-primary-500);
}

.prospector-chat__header-text {
  font-weight: 500;
  font-size: var(--prospector-font-size-base);
  color: var(--prospector-slate-800);
  margin: 0;
}

.prospector-chat__header-action {
  font-size: var(--prospector-font-size-sm);
  color: var(--prospector-slate-500);
  background: transparent;
  border: none;
  cursor: pointer;
  transition: color var(--prospector-transition-fast);
}

.prospector-chat__header-action:hover {
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
