<template>
  <div class="flex flex-col items-center justify-center h-full text-center px-4">
    <div class="w-16 h-16 rounded-full bg-primary-100 flex items-center justify-center mb-4">
      <MicrophoneIcon class="w-8 h-8 text-primary-500" />
    </div>

    <h3 class="text-lg font-semibold text-slate-800 mb-2">
      Podcast Discovery Assistant
    </h3>

    <p class="text-slate-500 max-w-md mb-6">
      Ask me to find podcasts by guest name, topic, or title. I'll search and
      help you discover new opportunities.
    </p>

    <div class="space-y-2 w-full max-w-sm">
      <p class="text-xs font-medium text-slate-400 uppercase tracking-wider mb-3">
        Try asking:
      </p>

      <button
        v-for="example in examples"
        :key="example.query"
        @click="$emit('example-click', example.query)"
        class="w-full flex items-center gap-3 p-3 bg-slate-50 hover:bg-slate-100
               border border-slate-200 rounded-xl text-left transition-colors group"
      >
        <component
          :is="example.icon"
          class="w-5 h-5 text-slate-400 group-hover:text-primary-500 transition-colors"
        />
        <span class="text-sm text-slate-700">{{ example.label }}</span>
      </button>
    </div>
  </div>
</template>

<script setup>
import {
  MicrophoneIcon,
  UserIcon,
  TagIcon,
  MagnifyingGlassIcon
} from '@heroicons/vue/24/outline'

defineEmits(['example-click'])

const examples = [
  {
    query: 'Find podcasts featuring Brené Brown',
    label: '"Find podcasts featuring Brené Brown"',
    icon: UserIcon
  },
  {
    query: 'Podcasts about artificial intelligence',
    label: '"Podcasts about artificial intelligence"',
    icon: TagIcon
  },
  {
    query: 'Look up How I Built This podcast',
    label: '"Look up How I Built This podcast"',
    icon: MagnifyingGlassIcon
  }
]
</script>
