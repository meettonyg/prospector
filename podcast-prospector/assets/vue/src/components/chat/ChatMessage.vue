<template>
  <div
    :class="[
      'flex gap-3',
      message.role === 'user' ? 'flex-row-reverse' : ''
    ]"
  >
    <!-- Avatar -->
    <div
      :class="[
        'flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center',
        message.role === 'user'
          ? 'bg-primary-500 text-white'
          : 'bg-slate-200 text-slate-600'
      ]"
    >
      <UserIcon v-if="message.role === 'user'" class="w-4 h-4" />
      <SparklesIcon v-else class="w-4 h-4" />
    </div>

    <!-- Content -->
    <div
      :class="[
        'max-w-[80%] rounded-2xl px-4 py-3',
        message.role === 'user'
          ? 'bg-primary-500 text-white rounded-tr-none'
          : 'bg-slate-100 text-slate-800 rounded-tl-none'
      ]"
    >
      <!-- Message text -->
      <p class="text-sm whitespace-pre-wrap">{{ message.content }}</p>

      <!-- Results (for assistant messages) -->
      <div
        v-if="message.results && message.results.length > 0"
        class="mt-3 space-y-2"
      >
        <ChatResultCard
          v-for="(result, index) in message.results"
          :key="result.id || index"
          :result="result"
          @import="$emit('import', result)"
        />
      </div>

      <!-- Timestamp -->
      <p
        :class="[
          'mt-2 text-xs',
          message.role === 'user' ? 'text-primary-200' : 'text-slate-400'
        ]"
      >
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
