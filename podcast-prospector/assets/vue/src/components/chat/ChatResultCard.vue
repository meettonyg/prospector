<template>
  <div class="bg-white rounded-lg border border-slate-200 p-3 hover:border-primary-300 transition-colors">
    <div class="flex items-start gap-3">
      <!-- Artwork -->
      <img
        v-if="artwork"
        :src="artwork"
        :alt="title"
        class="w-12 h-12 rounded-lg object-cover bg-slate-100 flex-shrink-0"
        loading="lazy"
      />
      <div
        v-else
        class="w-12 h-12 rounded-lg bg-slate-100 flex items-center justify-center flex-shrink-0"
      >
        <MicrophoneIcon class="w-6 h-6 text-slate-400" />
      </div>

      <!-- Content -->
      <div class="flex-1 min-w-0">
        <h4 class="font-medium text-slate-800 text-sm truncate">{{ title }}</h4>
        <p v-if="author" class="text-xs text-slate-500 truncate">{{ author }}</p>

        <div class="mt-2 flex items-center gap-2">
          <a
            v-if="websiteUrl"
            :href="websiteUrl"
            target="_blank"
            rel="noopener noreferrer"
            class="text-xs text-primary-600 hover:text-primary-700 hover:underline"
          >
            Visit Website
          </a>

          <button
            @click="$emit('import')"
            class="text-xs font-medium text-primary-600 hover:text-primary-700"
          >
            + Add to Pipeline
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { MicrophoneIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  result: {
    type: Object,
    required: true
  }
})

defineEmits(['import'])

const title = computed(() => {
  return props.result.title || props.result.name || props.result.feedTitle || 'Untitled'
})

const author = computed(() => {
  return props.result.author || props.result.publisher || props.result.ownerName || ''
})

const artwork = computed(() => {
  return props.result.artwork || props.result.image || props.result.imageUrl || ''
})

const websiteUrl = computed(() => {
  return props.result.link || props.result.websiteUrl || props.result.website || ''
})
</script>
