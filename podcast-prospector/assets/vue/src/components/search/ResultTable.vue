<template>
  <div class="overflow-x-auto animate-fade-in">
    <table class="w-full text-sm text-left">
      <thead class="text-xs text-slate-500 uppercase bg-slate-50">
        <tr>
          <th class="px-4 py-3 font-semibold border-b border-slate-200 w-10">
            <input
              type="checkbox"
              :checked="allSelected"
              @change="toggleSelectAll"
              class="w-4 h-4 rounded border-slate-300 text-primary-500 focus:ring-primary-500 cursor-pointer"
            />
          </th>
          <th class="px-4 py-3 font-semibold border-b border-slate-200 w-16">Cover</th>
          <th class="px-4 py-3 font-semibold border-b border-slate-200">Show Name</th>
          <th class="px-4 py-3 font-semibold border-b border-slate-200">Host</th>
          <th class="px-4 py-3 font-semibold border-b border-slate-200 w-24">Type</th>
          <th class="px-4 py-3 font-semibold border-b border-slate-200 w-32">Category</th>
          <th class="px-4 py-3 font-semibold border-b border-slate-200 text-right w-24">Action</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-200">
        <tr
          v-for="(result, index) in results"
          :key="result.id || index"
          class="hover:bg-slate-50 group transition-colors"
        >
          <!-- Checkbox -->
          <td class="px-4 py-3">
            <input
              type="checkbox"
              :checked="result._selected"
              @change="$emit('toggle-select', index)"
              :disabled="isTracked(index)"
              class="w-4 h-4 rounded border-slate-300 text-primary-500 focus:ring-primary-500 cursor-pointer disabled:opacity-50"
            />
          </td>

          <!-- Cover -->
          <td class="px-4 py-3">
            <img
              :src="result.image || result.artwork || '/wp-content/plugins/podcast-prospector/assets/placeholder.png'"
              :alt="result.title"
              class="w-10 h-10 rounded object-cover border border-slate-200"
              @error="handleImageError"
            />
          </td>

          <!-- Title -->
          <td class="px-4 py-3">
            <div class="flex items-center gap-2">
              <span class="font-medium text-slate-800 group-hover:text-primary-500 transition-colors">
                {{ result.title }}
              </span>
              <span 
                v-if="isTracked(index)" 
                class="text-[10px] uppercase font-bold bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded whitespace-nowrap"
              >
                In Pipeline
              </span>
            </div>
          </td>

          <!-- Host -->
          <td class="px-4 py-3 text-slate-500">
            {{ result.author || result.host || result.ownerName || 'Unknown' }}
          </td>

          <!-- Type -->
          <td class="px-4 py-3">
            <div class="flex items-center gap-2 text-slate-500">
              <component :is="getTypeIcon(result)" class="w-4 h-4" :class="getTypeColor(result)" />
              <span class="text-xs capitalize">{{ getTypeLabel(result) }}</span>
            </div>
          </td>

          <!-- Category -->
          <td class="px-4 py-3">
            <span 
              v-if="result.category || result.genre"
              class="bg-primary-50 text-primary-600 text-[11px] font-semibold px-2 py-0.5 rounded"
            >
              {{ result.category || result.genre }}
            </span>
          </td>

          <!-- Action -->
          <td class="px-4 py-3 text-right">
            <button
              v-if="!isTracked(index)"
              @click="$emit('import', { result, index })"
              :disabled="isImporting(index)"
              class="inline-flex items-center gap-2 bg-white border border-slate-200 text-slate-500 hover:bg-slate-50 hover:text-primary-500 hover:border-primary-500 font-medium rounded-lg transition-all duration-200 px-3 py-1.5 text-xs disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <ArrowDownTrayIcon v-if="!isImporting(index)" class="w-3.5 h-3.5" />
              <LoadingSpinner v-else size="xs" />
              <span>Import</span>
            </button>
            <a
              v-else
              :href="getHydration(index)?.crm_url"
              class="inline-flex items-center gap-2 bg-white border border-slate-200 text-slate-500 hover:bg-slate-50 hover:text-primary-500 hover:border-primary-500 font-medium rounded-lg transition-all duration-200 px-3 py-1.5 text-xs"
            >
              <EyeIcon class="w-3.5 h-3.5" />
              <span>View</span>
            </a>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { 
  ArrowDownTrayIcon, 
  EyeIcon,
  MicrophoneIcon,
  PresentationChartBarIcon
} from '@heroicons/vue/24/outline'
import LoadingSpinner from '../common/LoadingSpinner.vue'

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

const emit = defineEmits(['result-click', 'toggle-select', 'import'])

const getHydration = (index) => props.hydrationMap[index]
const isTracked = (index) => props.hydrationMap[index]?.tracked
const isImporting = (index) => props.importingIndices.includes(index)

const allSelected = computed(() => {
  const selectableResults = props.results.filter((_, i) => !isTracked(i))
  if (selectableResults.length === 0) return false
  return selectableResults.every(r => r._selected)
})

const toggleSelectAll = () => {
  if (allSelected.value) {
    // Deselect all
    props.results.forEach((_, i) => {
      if (!isTracked(i)) emit('toggle-select', i)
    })
  } else {
    // Select all non-tracked
    props.results.forEach((result, i) => {
      if (!isTracked(i) && !result._selected) emit('toggle-select', i)
    })
  }
}

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
  from { opacity: 0; }
  to { opacity: 1; }
}
.animate-fade-in {
  animation: fadeIn 0.3s ease-out forwards;
}
</style>
