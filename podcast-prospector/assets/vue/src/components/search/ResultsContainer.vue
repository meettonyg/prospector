<template>
  <div class="prospector-results-container">
    <!-- Loading state -->
    <div v-if="loading" class="prospector-results-container__loading">
      <LoadingSpinner size="lg" />
      <p class="prospector-results-container__loading-text">Searching for podcasts...</p>
    </div>

    <!-- Error state -->
    <div
      v-else-if="error"
      class="prospector-results-container__error"
    >
      <ExclamationCircleIcon class="prospector-results-container__error-icon" />
      <h3 class="prospector-results-container__error-title">Search Error</h3>
      <p class="prospector-results-container__error-message">{{ error }}</p>
      <button
        @click="$emit('retry')"
        class="prospector-btn prospector-btn--secondary"
      >
        Try Again
      </button>
    </div>

    <!-- No results state -->
    <div
      v-else-if="!hasResults && hasSearched"
      class="prospector-results-container__empty"
    >
      <MagnifyingGlassIcon class="prospector-results-container__empty-icon" />
      <h3 class="prospector-results-container__empty-title">No results found</h3>
      <p class="prospector-results-container__empty-message">
        No podcasts found for "{{ query }}". Try adjusting your search terms or filters.
      </p>
      <button
        @click="$emit('clear-filters')"
        class="prospector-btn prospector-btn--secondary"
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
        :linking-indices="linkingIndices"
        :search-mode="searchMode"
        @result-click="$emit('result-click', $event)"
        @toggle-select="$emit('toggle-select', $event)"
        @import="$emit('import', $event)"
        @link-episode="$emit('link-episode', $event)"
      />

      <!-- Table view -->
      <ResultTable
        v-else
        :results="results"
        :hydration-map="hydrationMap"
        :importing-indices="importingIndices"
        :linking-indices="linkingIndices"
        :search-mode="searchMode"
        @result-click="$emit('result-click', $event)"
        @toggle-select="$emit('toggle-select', $event)"
        @import="$emit('import', $event)"
        @link-episode="$emit('link-episode', $event)"
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
  linkingIndices: {
    type: Array,
    default: () => []
  },
  searchMode: {
    type: String,
    default: 'byperson'
  }
})

defineEmits(['result-click', 'toggle-select', 'import', 'link-episode', 'retry', 'clear-filters'])

const hasResults = computed(() => props.results.length > 0)
</script>

<style scoped>
.prospector-results-container {
  /* Container for results */
}

.prospector-results-container__loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 4rem var(--prospector-space-md);
}

.prospector-results-container__loading-text {
  margin-top: var(--prospector-space-md);
  color: var(--prospector-slate-500);
  font-size: var(--prospector-font-size-sm);
}

.prospector-results-container__error {
  padding: var(--prospector-space-lg);
  background: var(--prospector-error-50);
  border: 1px solid var(--prospector-error-200);
  border-radius: var(--prospector-radius-xl);
  text-align: center;
}

.prospector-results-container__error-icon {
  width: 3rem;
  height: 3rem;
  color: var(--prospector-error-400);
  margin: 0 auto var(--prospector-space-md);
}

.prospector-results-container__error-title {
  font-weight: 500;
  color: var(--prospector-error-800);
  margin: 0 0 var(--prospector-space-xs);
}

.prospector-results-container__error-message {
  font-size: var(--prospector-font-size-sm);
  color: var(--prospector-error-600);
  margin: 0 0 var(--prospector-space-md);
}

.prospector-results-container__empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 4rem var(--prospector-space-md);
  text-align: center;
}

.prospector-results-container__empty-icon {
  width: 4rem;
  height: 4rem;
  color: var(--prospector-slate-300);
  margin-bottom: var(--prospector-space-md);
}

.prospector-results-container__empty-title {
  font-size: var(--prospector-font-size-lg);
  font-weight: 600;
  color: var(--prospector-slate-800);
  margin: 0 0 var(--prospector-space-sm);
}

.prospector-results-container__empty-message {
  color: var(--prospector-slate-500);
  max-width: 28rem;
  margin: 0 0 var(--prospector-space-lg);
}
</style>
