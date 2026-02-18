<template>
  <div
    :class="[
      'prospector-chat-message',
      message.role === 'user' ? 'prospector-chat-message--user' : 'prospector-chat-message--assistant'
    ]"
  >
    <!-- Avatar -->
    <div class="prospector-chat-message__avatar">
      <UserIcon v-if="message.role === 'user'" class="prospector-chat-message__avatar-icon" />
      <SparklesIcon v-else class="prospector-chat-message__avatar-icon" />
    </div>

    <!-- Content -->
    <div class="prospector-chat-message__content">
      <div class="prospector-chat-message__bubble">
        <!-- Message text -->
        <p class="prospector-chat-message__text">{{ message.content }}</p>

        <!-- Results (for assistant messages) -->
        <div
          v-if="message.results && message.results.length > 0"
          class="prospector-chat-message__results"
        >
          <ChatResultCard
            v-for="(result, index) in message.results"
            :key="result.id || index"
            :result="result"
            :hydration="message.hydration?.[index] || null"
            @import="$emit('import', { result, messageId: message.id, index })"
          />
        </div>
      </div>

      <!-- Timestamp -->
      <p class="prospector-chat-message__timestamp">
        {{ formatTime(message.timestamp) }}
      </p>
    </div>
  </div>
</template>

<script setup>
import { UserIcon, SparklesIcon } from '@heroicons/vue/24/outline'
import ChatResultCard from './ChatResultCard.vue'

defineProps({
  message: {
    type: Object,
    required: true
  }
})

defineEmits(['import'])

const formatTime = (timestamp) => {
  if (!timestamp) return ''
  const date = new Date(timestamp)
  return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
}
</script>

<style scoped>
.prospector-chat-message {
  display: flex;
  gap: var(--prospector-space-md);
}

.prospector-chat-message--user {
  flex-direction: row-reverse;
}

.prospector-chat-message__avatar {
  flex-shrink: 0;
  width: 2rem;
  height: 2rem;
  border-radius: var(--prospector-radius-full);
  display: flex;
  align-items: center;
  justify-content: center;
}

.prospector-chat-message--assistant .prospector-chat-message__avatar {
  background: var(--prospector-slate-200);
  color: var(--prospector-slate-600);
}

.prospector-chat-message--user .prospector-chat-message__avatar {
  background: var(--prospector-primary-500);
  color: white;
}

.prospector-chat-message__avatar-icon {
  width: 1rem;
  height: 1rem;
}

.prospector-chat-message__content {
  max-width: 80%;
}

.prospector-chat-message__bubble {
  padding: var(--prospector-space-md);
  border-radius: var(--prospector-radius-xl);
}

.prospector-chat-message--assistant .prospector-chat-message__bubble {
  background: var(--prospector-slate-100);
  color: var(--prospector-slate-800);
  border-top-left-radius: 0;
}

.prospector-chat-message--user .prospector-chat-message__bubble {
  background: var(--prospector-primary-500);
  color: white;
  border-top-right-radius: 0;
}

.prospector-chat-message__text {
  font-size: var(--prospector-font-size-sm);
  line-height: 1.5;
  white-space: pre-wrap;
  margin: 0;
}

.prospector-chat-message__results {
  margin-top: var(--prospector-space-md);
  display: flex;
  flex-direction: column;
  gap: var(--prospector-space-sm);
}

.prospector-chat-message__timestamp {
  margin: var(--prospector-space-sm) 0 0;
  font-size: var(--prospector-font-size-xs);
}

.prospector-chat-message--assistant .prospector-chat-message__timestamp {
  color: var(--prospector-slate-400);
}

.prospector-chat-message--user .prospector-chat-message__timestamp {
  color: var(--prospector-primary-200);
  text-align: right;
}
</style>
