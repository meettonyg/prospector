<template>
  <div class="flex flex-col items-center justify-center py-12 text-center">
    <!-- Icon -->
    <div class="mb-6">
      <component 
        :is="channelIcon" 
        class="w-16 h-16 text-primary-500 opacity-60"
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

    <!-- Quick Tips -->
    <div class="mt-8 text-left bg-slate-50 rounded-lg p-4 max-w-md w-full">
      <h4 class="text-sm font-medium text-slate-700 mb-2">Quick Tips:</h4>
      <ul class="space-y-1.5 text-sm text-slate-500">
        <li v-for="tip in tips" :key="tip" class="flex items-start gap-2">
          <span class="text-primary-500 mt-0.5">•</span>
          <span>{{ tip }}</span>
        </li>
      </ul>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import {
  MicrophoneIcon,
  PresentationChartBarIcon
} from '@heroicons/vue/24/outline'
import { CHANNELS } from '../../utils/constants'

// YouTube icon component
const YouTubeIcon = {
  template: `
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
      <path d="M19.615 3.184c-3.604-.246-11.631-.245-15.23 0-3.897.266-4.356 2.62-4.385 8.816.029 6.185.484 8.549 4.385 8.816 3.6.245 11.626.246 15.23 0 3.897-.266 4.356-2.62 4.385-8.816-.029-6.185-.484-8.549-4.385-8.816zm-10.615 12.816v-8l8 3.993-8 4.007z"/>
    </svg>
  `
}

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
    case CHANNELS.YOUTUBE:
      return YouTubeIcon
    case CHANNELS.SUMMITS:
      return PresentationChartBarIcon
    default:
      return MicrophoneIcon
  }
})

const title = computed(() => {
  switch (props.channel) {
    case CHANNELS.YOUTUBE:
      return 'Search for YouTube channels'
    case CHANNELS.SUMMITS:
      return 'Search for virtual summits'
    default:
      return 'Search for podcasts'
  }
})

const description = computed(() => {
  switch (props.searchMode) {
    case 'byperson':
      return 'Enter a search term above to find podcasts by guest name, host, or topic.'
    case 'bytitle':
      return 'Enter a podcast title to find shows matching your criteria.'
    case 'byadvancedpodcast':
      return 'Use advanced filters to find podcasts by genre, language, and more.'
    case 'byadvancedepisode':
      return 'Search for specific episodes using advanced filters.'
    case 'byyoutube':
      return 'Find YouTube channels related to your topic or niche.'
    case 'bysummit':
      return 'Discover virtual summits and speaking opportunities.'
    default:
      return 'Enter a search term above to find podcasts by guest name, host, or topic.'
  }
})

const tips = computed(() => {
  switch (props.searchMode) {
    case 'byperson':
      return [
        'Search by guest or host name to find episode appearances',
        'Try "Tim Ferriss" or "Joe Rogan" to see how it works',
        'Results include episode title, date, and podcast info'
      ]
    case 'bytitle':
      return [
        'Enter the podcast name or partial title',
        'Results show matching podcasts with details',
        'Click on a result to view episodes'
      ]
    default:
      return [
        'Use filters to narrow down your search',
        'Click "Import" to add podcasts to your pipeline',
        'Switch between grid and table view for results'
      ]
  }
})
</script>
