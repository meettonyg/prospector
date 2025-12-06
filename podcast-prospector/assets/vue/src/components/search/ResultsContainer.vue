<template>
  <div>
    <!-- Loading state -->
    <div v-if="loading" class="flex flex-col items-center justify-center py-16">
      <LoadingSpinner size="lg" />
      <p class="mt-4 text-slate-500">Searching for podcasts...</p>
    </div>

    <!-- Error state -->
    <div
      v-else-if="error"
      class="p-6 bg-red-50 border border-red-200 rounded-xl text-center"
    >
      <ExclamationCircleIcon class="w-12 h-12 text-red-400 mx-auto mb-3" />
      <h3 class="font-medium text-red-800 mb-1">Search Error</h3>
      <p class="text-sm text-red-600 mb-4">{{ error }}</p>
      <button
        @click="$emit('retry')"
        class="px-4 py-2 bg-white border border-red-200 text-red-600 hover:bg-red-50 font-medium rounded-lg transition-colors"
      >
        Try Again
      </button>
    </div>

    <!-- No results state -->
    <div
      v-else-if="!hasResults && hasSearched"
      class="flex flex-col items-center justify-center py-16 text-center"
    >
      <MagnifyingGlassIcon class="w-16 h-16 text-slate-300 mb-4" />
      <h3 class="text-lg font-semibold text-slate-800 mb-2">No results found</h3>
      <p class="text-slate-500 max-w-md mb-6">
        No podcasts found for "{{ query }}". Try adjusting your search terms or filters.
      </p>
      <button
        @click="$emit('clear-filters')"
        class="px-4 py-2 bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 font-medium rounded-lg transition-colors"
      >
        Clear Filters
      </button>
    </div>

    <!-- Results -->
    <div v-else-if="hasResults">
      <!-- Grid view -->
      <ResultGrid
        v-if="viewMode === 'grid'"
        :results="results"
        :hydration-map="hydrationMap"
        :importing-indices="importingIndices"
        :search-mode="searchMode"
        @result-click="$emit('result-click', $event)"
        @toggle-select="$emit('toggle-select', $event)"
        @import="$emit('import', $event)"
      />

      <!-- Table view -->
      <ResultTable
        v-else
        :results="results"
        :hydration-map="hydrationMap"
        :importing-indices="importingIndices"
        :search-mode="searchMode"
        @result-click="$emit('result-click', $event)"
        @toggle-select="$emit('toggle-select', $event)"
        @import="$emit('import', $event)"
      />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import {
  MagnifyingGlassIcon,
  ExclamationCircleIcon
} from '@heroicons/vue/24/outline'
import LoadingSpinner from '../common/LoadingSpinner.vue'
import ResultGrid from '../common/ResultGrid.vue'
import ResultTable from './ResultTable.vue'

const props = defineProps({
  results: {
    type: Array,
    default: () => []
  },
  hydrationMap: {
    type: Object,
    default: () => ({})
  },
  loading: {
    type: Boolean,
    default: false
  },
  error: {
    type: String,
    default: null
  },
  hasSearched: {
    type: Boolean,
    default: false
  },
  query: {
    type: String,
    default: ''
  },
  viewMode: {
    type: String,
    default: 'grid'
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

defineEmits(['result-click', 'toggle-select', 'import', 'retry', 'clear-filters'])

const hasResults = computed(() => props.results.length > 0)
</script>
