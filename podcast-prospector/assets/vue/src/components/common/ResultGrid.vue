<template>
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 animate-fade-in">
    <div
      v-for="(result, index) in results"
      :key="result.id || index"
      class="border border-slate-200 rounded-lg p-4 hover:shadow-md hover:border-slate-300 transition-all flex items-start gap-4 cursor-pointer group bg-white"
      @click="$emit('result-click', { result, index })"
    >
      <!-- Checkbox for selection -->
      <div class="flex-shrink-0 pt-1">
        <input
          type="checkbox"
          :checked="result._selected"
          @click.stop="$emit('toggle-select', index)"
          @change.stop
          class="w-4 h-4 rounded border-slate-300 text-primary-500 focus:ring-primary-500 cursor-pointer"
          :disabled="isTracked(index)"
        />
      </div>

      <!-- Cover Image -->
      <img
        :src="result.image || result.artwork || '/wp-content/plugins/podcast-prospector/assets/placeholder.png'"
        :alt="result.title"
        class="w-16 h-16 rounded-lg object-cover bg-slate-100 border border-slate-200 flex-shrink-0"
        @error="handleImageError"
      />

      <!-- Content -->
      <div class="flex-1 min-w-0">
        <!-- Type Badge -->
        <div class="flex items-center gap-1.5 mb-1.5">
          <component :is="getTypeIcon(result)" class="w-3.5 h-3.5" :class="getTypeColor(result)" />
          <span class="text-[10px] uppercase font-bold text-slate-500 tracking-wide">
            {{ getTypeLabel(result) }}
          </span>
          <!-- Tracked Badge -->
          <span 
            v-if="isTracked(index)" 
            class="ml-auto text-[10px] uppercase font-bold bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded"
          >
            In Pipeline
          </span>
        </div>

        <!-- Title -->
        <h4 class="font-semibold text-sm text-slate-800 group-hover:text-primary-500 transition-colors line-clamp-2 leading-tight">
          {{ result.title }}
        </h4>

        <!-- Author/Host -->
        <p class="text-xs text-slate-500 mt-1 line-clamp-1">
          {{ result.author || result.host || result.ownerName || 'Unknown' }}
        </p>

        <!-- Category/Genre Badge -->
        <span 
          v-if="result.category || result.genre"
          class="inline-block mt-3 bg-primary-50 text-primary-600 text-[11px] font-semibold px-2 py-0.5 rounded"
        >
          {{ result.category || result.genre }}
        </span>
      </div>

      <!-- Import Button -->
      <div class="flex-shrink-0">
        <button
          v-if="!isTracked(index)"
          @click.stop="$emit('import', { result, index })"
          :disabled="isImporting(index)"
          class="p-2 rounded-lg bg-white border border-slate-200 text-slate-400 hover:bg-primary-50 hover:border-primary-500 hover:text-primary-500 transition-all disabled:opacity-50 disabled:cursor-not-allowed"
          title="Add to Pipeline"
        >
          <ArrowDownTrayIcon v-if="!isImporting(index)" class="w-4 h-4" />
          <LoadingSpinner v-else size="sm" />
        </button>
        <a
          v-else
          :href="getHydration(index)?.crm_url"
          @click.stop
          class="p-2 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-600 hover:bg-emerald-100 transition-all inline-flex"
          title="View in Pipeline"
        >
          <EyeIcon class="w-4 h-4" />
        </a>
      </div>
    </div>
  </div>
</template>

<script setup>
import { 
  ArrowDownTrayIcon, 
  EyeIcon,
  MicrophoneIcon,
  PresentationChartBarIcon
} from '@heroicons/vue/24/outline'
import LoadingSpinner from './LoadingSpinner.vue'

// YouTube icon
const YouTubeIcon = {
  template: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M19.615 3.184c-3.604-.246-11.631-.245-15.23 0-3.897.266-4.356 2.62-4.385 8.816.029 6.185.484 8.549 4.385 8.816 3.6.245 11.626.246 15.23 0 3.897-.266 4.356-2.62 4.385-8.816-.029-6.185-.484-8.549-4.385-8.816zm-10.615 12.816v-8l8 3.993-8 4.007z"/></svg>`
}

const props = defineProps({
  results: {
    type: Array,
    default: () => []
  },
  hydrationMap: {
    type: Object,
    default: () => ({})
  },
  importingIndices: {
    type: Array,
    default: () => []
  },
  searchMode: {
    type: String,
    default: 'byperson'
  }
})

defineEmits(['result-click', 'toggle-select', 'import'])

const getHydration = (index) => props.hydrationMap[index]
const isTracked = (index) => props.hydrationMap[index]?.tracked
const isImporting = (index) => props.importingIndices.includes(index)

const getTypeIcon = (result) => {
  if (result.type === 'youtube' || result.channel === 'youtube') return YouTubeIcon
  if (result.type === 'summit' || result.channel === 'summits') return PresentationChartBarIcon
  return MicrophoneIcon
}

const getTypeColor = (result) => {
  if (result.type === 'youtube' || result.channel === 'youtube') return 'text-red-500'
  if (result.type === 'summit' || result.channel === 'summits') return 'text-orange-500'
  return 'text-purple-500'
}

const getTypeLabel = (result) => {
  if (result.type === 'youtube' || result.channel === 'youtube') return 'YouTube'
  if (result.type === 'summit' || result.channel === 'summits') return 'Summit'
  if (props.searchMode.includes('episode')) return 'Episode'
  return 'Podcast'
}

const handleImageError = (e) => {
  e.target.src = '/wp-content/plugins/podcast-prospector/assets/placeholder.png'
}
</script>

<style scoped>
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(8px); }
  to { opacity: 1; transform: translateY(0); }
}
.animate-fade-in {
  animation: fadeIn 0.3s ease-out forwards;
}
.line-clamp-1 {
  display: -webkit-box;
  -webkit-line-clamp: 1;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
