<template>
  <div class="flex flex-col items-center justify-center py-16 text-center">
    <!-- Icon -->
    <div class="mb-6">
      <component 
        :is="channelIcon" 
        class="w-16 h-16 text-primary-400 opacity-50"
      />
    </div>

    <!-- Title -->
    <h3 class="text-xl font-semibold text-slate-800 mb-2">
      {{ title }}
    </h3>

    <!-- Description -->
    <p class="text-slate-500 max-w-md">
      {{ description }}
    </p>
  </div>
</template>

<script setup>
import { computed, markRaw } from 'vue'
import {
  MicrophoneIcon,
  PresentationChartBarIcon
} from '@heroicons/vue/24/outline'
import { CHANNELS } from '../../utils/constants'

const props = defineProps({
  channel: {
    type: String,
    default: CHANNELS.PODCASTS
  },
  searchMode: {
    type: String,
    default: 'byperson'
  }
})

const channelIcon = computed(() => {
  switch (props.channel) {
    case CHANNELS.SUMMITS:
      return markRaw(PresentationChartBarIcon)
    default:
      return markRaw(MicrophoneIcon)
  }
})

const title = computed(() => {
  return 'Search for podcasts'
})

const description = computed(() => {
  return 'Enter a search term above to find podcasts by guest name, host, or topic.'
})
</script>
