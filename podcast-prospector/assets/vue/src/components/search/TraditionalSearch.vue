<template>
  <div class="prospector-container">
    <!-- Main Card Container -->
    <div class="prospector-card">
      <!-- Header -->
      <AppHeader @open-saved-searches="handleOpenSavedSearches" />

      <!-- Search Mode Tabs -->
      <SearchModeTabs
        v-model="searchStore.mode"
        :channel="searchStore.channel"
        @update:model-value="handleModeChange"
      />

      <!-- Search Controls -->
      <div class="prospector-card__body">
        <!-- Search Input Row -->
        <div class="prospector-search-row">
          <!-- Search Input with integrated channel dropdown -->
          <div class="prospector-search-row__input">
            <SearchInput
              v-model="searchStore.query"
              :placeholder="searchPlaceholder"
              :disabled="!userStore.canSearch"
              :channel="selectedChannel"
              @update:channel="selectedChannel = $event"
              @search="handleSearch"
            />
          </div>

          <!-- Filters & Search Buttons -->
          <div class="prospector-search-row__actions">
            <button
              type="button"
              @click="toggleFilters"
              :class="[
                'prospector-btn prospector-btn--filter',
                filtersVisible && 'is-active'
              ]"
            >
              <AdjustmentsHorizontalIcon class="prospector-btn__icon" />
              <span>Filters</span>
            </button>
            <button
              type="button"
              @click="handleSearch"
              :disabled="!searchStore.query.trim() || !userStore.canSearch || searchStore.loading"
              class="prospector-btn prospector-btn--search"
            >
              <span v-if="searchStore.loading">Searching...</span>
              <span v-else>Search</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Divider before filters -->
      <div v-if="filtersVisible" class="prospector-card__divider"></div>

      <!-- Filters Panel -->
      <FilterPanel
        v-if="filtersVisible"
        :search-mode="searchStore.mode"
        @apply="handleSearch"
        @reset="handleClearFilters"
      />
    </div>

    <!-- Results Section -->
    <div v-if="hasSearched || searchStore.hasResults" class="prospector-card prospector-card--results">
      <!-- Results Toolbar -->
      <div class="prospector-results-toolbar">
        <p class="prospector-results-toolbar__info">
          <template v-if="searchStore.hasResults">
            Showing <span class="prospector-results-toolbar__info-count">{{ searchStore.results.length }}</span> of 
            <span class="prospector-results-toolbar__info-count">{{ searchStore.total }}</span> results
            <span v-if="searchStore.query"> for <span class="prospector-results-toolbar__info-query">"{{ searchStore.query }}"</span></span>
          </template>
          <template v-else-if="searchStore.loading">
            Searching...
          </template>
          <template v-else>
            No results found
          </template>
        </p>

        <!-- View Toggle & Actions -->
        <div class="prospector-results-toolbar__actions">
          <!-- Bulk Import Button -->
          <button
            v-if="searchStore.selectedCount > 0"
            type="button"
            @click="handleBulkImport"
            :disabled="bulkImporting"
            class="prospector-btn prospector-btn--primary"
          >
            <ArrowDownTrayIcon class="prospector-btn__icon" />
            Import {{ searchStore.selectedCount }} Selected
          </button>

          <!-- View Toggle -->
          <div class="prospector-view-toggle">
            <button
              type="button"
              @click="searchStore.setViewMode('grid')"
              :class="[
                'prospector-view-toggle__btn',
                searchStore.viewMode === 'grid' && 'prospector-view-toggle__btn--active'
              ]"
              title="Grid View"
            >
              <Squares2X2Icon class="prospector-view-toggle__icon" />
            </button>
            <button
              type="button"
              @click="searchStore.setViewMode('table')"
              :class="[
                'prospector-view-toggle__btn',
                searchStore.viewMode === 'table' && 'prospector-view-toggle__btn--active'
              ]"
              title="Table View"
            >
              <ListBulletIcon class="prospector-view-toggle__icon" />
            </button>
          </div>
        </div>
      </div>

      <!-- Results Content -->
      <div class="prospector-card__body prospector-card__body--results">
        <ResultsContainer
          :results="searchStore.results"
          :hydration-map="searchStore.hydrationMap"
          :loading="searchStore.loading"
          :error="searchStore.error"
          :has-searched="hasSearched"
          :query="searchStore.query"
          :view-mode="searchStore.viewMode"
          :importing-indices="importingIndices"
          :search-mode="searchStore.mode"
          @toggle-select="searchStore.toggleSelection"
          @import="handleSingleImport"
          @retry="handleSearch"
          @clear-filters="handleClearFilters"
        />
      </div>

      <!-- Pagination -->
      <div v-if="searchStore.hasResults && searchStore.total > searchStore.perPage" class="prospector-card__footer">
        <Pagination
          :current-page="searchStore.page"
          :per-page="searchStore.perPage"
          :total="searchStore.total"
          @update:current-page="handlePageChange"
        />
      </div>
    </div>

    <!-- Empty State (before any search) -->
    <div v-else class="prospector-card prospector-card--empty">
      <EmptyState
        :channel="searchStore.channel"
        :search-mode="searchStore.mode"
        @search="handleExampleSearch"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import {
  AdjustmentsHorizontalIcon,
  Squares2X2Icon,
  ListBulletIcon,
  ArrowDownTrayIcon
} from '@heroicons/vue/24/outline'
import { useSearchStore } from '../../stores/searchStore'
import { useUserStore } from '../../stores/userStore'
import { useFilterStore } from '../../stores/filterStore'
import { useToast } from '../../stores/toastStore'
import api from '../../api/prospectorApi'

// Components
import AppHeader from '../common/AppHeader.vue'
import SearchInput from '../common/SearchInput.vue'
import SearchModeTabs from './SearchModeTabs.vue'
import FilterPanel from '../common/FilterPanel.vue'
import ResultsContainer from './ResultsContainer.vue'
import Pagination from '../common/Pagination.vue'
import EmptyState from '../common/EmptyState.vue'

// Stores
const searchStore = useSearchStore()
const userStore = useUserStore()
const filterStore = useFilterStore()
const { success, error: showError } = useToast()

// Local state
const hasSearched = ref(false)
const filtersVisible = ref(false)
const importingIndices = ref([])
const bulkImporting = ref(false)
const selectedChannel = ref('podcasts')

// Computed
const searchPlaceholder = computed(() => {
  const placeholders = {
    byperson: 'Enter guest or host name (e.g., Tim Ferriss)...',
    bytitle: 'Enter podcast title...',
    byadvancedpodcast: 'Search podcasts with filters...',
    byadvancedepisode: 'Search episodes with filters...',
    byyoutube: 'Search YouTube channels...',
    bysummit: 'Search virtual summits...'
  }
  return placeholders[searchStore.mode] || 'Search for podcasts...'
})

// Handlers
const toggleFilters = () => {
  filtersVisible.value = !filtersVisible.value
}

const handleOpenSavedSearches = () => {
  // TODO: Open saved searches modal
  console.log('Open saved searches')
}

const handleSearch = async () => {
  if (!searchStore.query.trim()) return
  if (!userStore.canSearch) {
    showError('Search limit reached', 'You have no searches remaining. Please upgrade your plan.')
    return
  }

  hasSearched.value = true

  const params = {
    ...filterStore.filterParams
  }

  await searchStore.search(params)
  userStore.decrementSearchCount()
}

const handleExampleSearch = ({ query, mode }) => {
  searchStore.setQuery(query)
  if (mode) {
    searchStore.setMode(mode)
  }
  handleSearch()
}

const handleModeChange = (mode) => {
  searchStore.setMode(mode)
  hasSearched.value = false
}

const handlePageChange = async (page) => {
  searchStore.setPage(page)
  await handleSearch()
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

const handleClearFilters = () => {
  filterStore.clearFilters()
}

const handleSingleImport = async ({ result, index }) => {
  importingIndices.value.push(index)

  try {
    const response = await api.importToPipeline([result], searchStore.searchMeta)

    if (response.success_count > 0) {
      success(
        'Added to Pipeline',
        `${result.title || 'Podcast'} is now in your Interview Tracker.`
      )
      await searchStore.refreshHydration()
    } else {
      throw new Error(response.message || 'Import failed')
    }
  } catch (err) {
    showError('Import Failed', err.message || 'Could not add podcast to pipeline.')
  } finally {
    importingIndices.value = importingIndices.value.filter(i => i !== index)
  }
}

const handleBulkImport = async () => {
  const selected = searchStore.selectedResults
  if (selected.length === 0) return

  bulkImporting.value = true

  try {
    const response = await api.importToPipeline(selected, searchStore.searchMeta)
    const isPartial = response.fail_count > 0

    if (isPartial) {
      showError(
        `Imported ${response.success_count} of ${selected.length}`,
        `${response.fail_count} failed to import.`
      )
    } else {
      success(
        `Imported ${response.success_count} podcasts`,
        'All podcasts added to your pipeline.'
      )
    }

    await searchStore.refreshHydration()
    searchStore.deselectAll()
  } catch (err) {
    showError('Bulk Import Failed', err.message || 'Could not import selected podcasts.')
  } finally {
    bulkImporting.value = false
  }
}

watch(() => searchStore.channel, () => {
  hasSearched.value = false
})
</script>

<style scoped>
/* Search Row Layout */
.prospector-search-row {
  display: flex;
  flex-direction: column;
  gap: var(--prospector-space-md);
}

@media (min-width: 768px) {
  .prospector-search-row {
    flex-direction: row;
    align-items: center;
  }
}

.prospector-search-row__input {
  flex: 1;
}

.prospector-search-row__actions {
  display: flex;
  gap: var(--prospector-space-sm);
}

/* Button icon sizing */
.prospector-btn__icon {
  width: 1.25rem;
  height: 1.25rem;
}

/* Card divider */
.prospector-card__divider {
  height: 1px;
  background: var(--prospector-slate-200);
  margin: 0 var(--prospector-space-lg);
}

/* Card spacing modifiers */
.prospector-card--results {
  margin-top: var(--prospector-space-lg);
}

.prospector-card--empty {
  margin-top: var(--prospector-space-lg);
}

.prospector-card__body--results {
  min-height: 300px;
}
</style>
