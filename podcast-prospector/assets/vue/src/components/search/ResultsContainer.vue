<template>
  <div>
    <!-- Loading state -->
    <div v-if="loading" class="py-12">
      <LoadingSpinner size="lg" label="Searching..." />
    </div>

    <!-- Error state -->
    <div
      v-else-if="error"
      class="p-6 bg-red-50 border border-red-200 rounded-xl text-center"
    >
      <ExclamationCircleIcon class="w-12 h-12 text-red-400 mx-auto mb-3" />
      <h3 class="font-medium text-red-800 mb-1">Search Error</h3>
      <p class="text-sm text-red-600">{{ error }}</p>
      <button
        @click="$emit('retry')"
        class="mt-4 btn-secondary"
      >
        Try Again
      </button>
    </div>

    <!-- Empty state -->
    <EmptyState
      v-else-if="!hasResults && hasSearched"
      :icon="MagnifyingGlassIcon"
      title="No results found"
      :description="`No podcasts found for '${query}'. Try adjusting your search terms or filters.`"
    >
      <button
        @click="$emit('clear-filters')"
        class="btn-secondary"
      >
        Clear Filters
      </button>
    </EmptyState>

    <!-- Welcome state -->
    <EmptyState
      v-else-if="!hasResults && !hasSearched"
      :icon="MicrophoneIcon"
      title="Search for podcasts"
      description="Enter a search term above to find podcasts by guest name, host, or topic."
    />

    <!-- Results -->
    <div v-else>
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
  MicrophoneIcon,
  ExclamationCircleIcon
} from '@heroicons/vue/24/outline'
import LoadingSpinner from '../common/LoadingSpinner.vue'
import EmptyState from '../common/EmptyState.vue'
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
