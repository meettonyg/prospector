<template>
  <div
    :class="[
      'card p-4 cursor-pointer',
      isSelected && 'ring-2 ring-primary-500 border-primary-300',
      hydration?.tracked && 'bg-slate-50'
    ]"
    @click="$emit('click')"
  >
    <!-- Header -->
    <div class="flex gap-4">
      <!-- Artwork -->
      <div class="flex-shrink-0">
        <img
          v-if="artwork"
          :src="artwork"
          :alt="title"
          class="w-16 h-16 rounded-lg object-cover bg-slate-100"
          loading="lazy"
          @error="$event.target.src = '/wp-content/plugins/podcast-prospector/assets/placeholder-podcast.png'"
        />
        <div
          v-else
          class="w-16 h-16 rounded-lg bg-slate-100 flex items-center justify-center"
        >
          <MicrophoneIcon class="w-8 h-8 text-slate-400" />
        </div>
      </div>

      <!-- Content -->
      <div class="flex-1 min-w-0">
        <div class="flex items-start justify-between gap-2">
          <div class="min-w-0">
            <h3 class="font-semibold text-slate-800 truncate">{{ title }}</h3>
            <p v-if="author" class="text-sm text-slate-500 truncate">{{ author }}</p>
          </div>

          <!-- Selection checkbox -->
          <div v-if="selectable" class="flex-shrink-0" @click.stop>
            <input
              type="checkbox"
              :checked="isSelected"
              @change="$emit('select')"
              :disabled="hydration?.tracked"
              class="w-4 h-4 rounded border-slate-300 text-primary-500 focus:ring-primary-500"
            />
          </div>
        </div>

        <!-- Description -->
        <p v-if="description" class="mt-2 text-sm text-slate-600 line-clamp-2">
          {{ truncatedDescription }}
        </p>

        <!-- Meta info -->
        <div class="mt-3 flex flex-wrap items-center gap-2">
          <!-- Hydration badge -->
          <span
            v-if="hydration?.hasOpportunity"
            class="badge-success"
          >
            <CheckCircleIcon class="w-3.5 h-3.5 mr-1" />
            In Pipeline
          </span>
          <span
            v-else-if="hydration?.tracked"
            class="badge-neutral"
          >
            Tracked
          </span>

          <!-- Episode count -->
          <span v-if="episodeCount" class="badge-neutral">
            {{ episodeCount }} episodes
          </span>

          <!-- Language -->
          <span v-if="language && language !== 'en'" class="badge-neutral">
            {{ language.toUpperCase() }}
          </span>

          <!-- Categories -->
          <span
            v-for="category in displayCategories"
            :key="category"
            class="badge-primary"
          >
            {{ category }}
          </span>
        </div>
      </div>
    </div>

    <!-- Actions -->
    <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-3">
      <div class="flex items-center gap-2">
        <!-- Website link -->
        <a
          v-if="websiteUrl"
          :href="websiteUrl"
          target="_blank"
          rel="noopener noreferrer"
          @click.stop
          class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors"
          title="Visit website"
        >
          <GlobeAltIcon class="w-5 h-5" />
        </a>

        <!-- RSS link -->
        <a
          v-if="rssUrl"
          :href="rssUrl"
          target="_blank"
          rel="noopener noreferrer"
          @click.stop
          class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors"
          title="RSS Feed"
        >
          <RssIcon class="w-5 h-5" />
        </a>
      </div>

      <!-- Import button -->
      <ImportButton
        v-if="showImport"
        :is-tracked="hydration?.tracked"
        :has-opportunity="hydration?.hasOpportunity"
        :crm-url="hydration?.crm_url"
        :importing="importing"
        :disabled="!canImport"
        @import="$emit('import')"
        @click.stop
      />
    </div>
  </div>
</template>

<script setup>
import { computed, inject } from 'vue'
import {
  MicrophoneIcon,
  GlobeAltIcon,
  RssIcon,
  CheckCircleIcon
} from '@heroicons/vue/24/outline'
import ImportButton from './ImportButton.vue'
import { truncateText } from '../../utils/dataNormalizer'

const props = defineProps({
  title: {
    type: String,
    required: true
  },
  author: {
    type: String,
    default: ''
  },
  description: {
    type: String,
    default: ''
  },
  artwork: {
    type: String,
    default: ''
  },
  websiteUrl: {
    type: String,
    default: ''
  },
  rssUrl: {
    type: String,
    default: ''
  },
  episodeCount: {
    type: [Number, String],
    default: null
  },
  language: {
    type: String,
    default: 'en'
  },
  categories: {
    type: Array,
    default: () => []
  },
  hydration: {
    type: Object,
    default: () => ({})
  },
  isSelected: {
    type: Boolean,
    default: false
  },
  selectable: {
    type: Boolean,
    default: true
  },
  showImport: {
    type: Boolean,
    default: true
  },
  importing: {
    type: Boolean,
    default: false
  }
})

defineEmits(['click', 'select', 'import'])

const config = inject('config', {})
const canImport = computed(() => config.guestIntelActive !== false)

const truncatedDescription = computed(() => truncateText(props.description, 120))

const displayCategories = computed(() => {
  return (props.categories || []).slice(0, 2)
})
</script>

<style scoped>
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
