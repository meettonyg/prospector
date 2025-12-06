<template>
  <div class="space-y-6">
    <!-- Page Header Card -->
    <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
      <div>
        <h2 class="text-2xl font-semibold text-slate-800 tracking-tight">Prospector</h2>
        <p class="text-sm text-slate-500 mt-1">Find podcasts, channels, and events for your guests</p>
      </div>
      <div class="flex items-center gap-3">
        <SearchCapBadge />
        <button class="bg-white border border-slate-200 text-slate-500 hover:bg-slate-50 hover:text-primary-500 hover:border-primary-500 font-medium rounded-lg transition-all duration-200 px-4 py-2 text-sm whitespace-nowrap">
          Saved Searches
        </button>
      </div>
    </div>

    <!-- Search Card -->
    <div class="bg-white border border-slate-200 shadow-sm rounded-xl overflow-hidden">
      <!-- Search Mode Tabs -->
      <SearchModeTabs
        v-model="searchStore.mode"
        :channel="searchStore.channel"
        @update:model-value="handleModeChange"
      />

      <!-- Search Controls -->
      <div class="p-6">
        <!-- Search Input Row -->
        <div class="flex flex-col md:flex-row gap-3 mb-4">
          <!-- Channel Dropdown -->
          <ChannelDropdown
            v-model="searchStore.channel"
            @update:model-value="handleChannelChange"
          />

          <!-- Search Input -->
          <div class="flex-1">
            <SearchInput
              v-model="searchStore.query"
              :placeholder="searchPlaceholder"
              :disabled="!userStore.canSearch"
              @search="handleSearch"
            />
          </div>

          <!-- Filters & Search Buttons -->
          <div class="flex gap-2">
            <button
              @click="toggleFilters"
              :class="[
                'h-12 px-4 border rounded-lg flex items-center gap-2 transition-colors font-medium',
                filtersVisible
                  ? 'bg-primary-50 border-primary-500 text-primary-600'
                  : 'bg-white border-slate-200 text-slate-500 hover:border-slate-300'
              ]"
            >
              <AdjustmentsHorizontalIcon class="w-5 h-5" />
              <span class="hidden sm:inline">Filters</span>
            </button>
            <button
              @click="handleSearch"
              :disabled="!searchStore.query.trim() || !userStore.canSearch || searchStore.loading"
              class="h-12 px-8 bg-primary-500 hover:bg-primary-600 disabled:bg-slate-300 disabled:cursor-not-allowed text-white font-semibold rounded-lg transition-colors shadow-sm shadow-primary-500/20 active:scale-[0.98] transform"
            >
              <span v-if="searchStore.loading">Searching...</span>
              <span v-else>Search</span>
            </button>
          </div>
        </div>

        <!-- Filters Panel -->
        <FilterPanel
          v-if="filtersVisible"
          :search-mode="searchStore.mode"
          @apply="handleSearch"
          @reset="handleClearFilters"
        />
      </div>
    </div>

    <!-- Results Card -->
    <div v-if="hasSearched || searchStore.hasResults" class="bg-white border border-slate-200 shadow-sm rounded-xl overflow-hidden">
      <!-- Results Header -->
      <div class="px-6 py-4 border-b border-slate-200 flex flex-wrap justify-between items-center gap-4">
        <p class="text-sm text-slate-500">
          <template v-if="searchStore.hasResults">
            Showing <strong class="text-slate-700">{{ searchStore.results.length }}</strong> of 
            <strong class="text-slate-700">{{ searchStore.total }}</strong> results
            <span v-if="searchStore.query"> for <strong class="text-slate-700">"{{ searchStore.query }}"</strong></span>
          </template>
          <template v-else-if="searchStore.loading">
            Searching...
          </template>
          <template v-else>
            No results found
          </template>
        </p>

        <!-- View Toggle & Actions -->
        <div class="flex items-center gap-3">
          <!-- Bulk Import Button -->
          <button
            v-if="searchStore.selectedCount > 0"
            @click="handleBulkImport"
            :disabled="bulkImporting"
            class="flex items-center gap-2 px-4 py-2 bg-primary-500 hover:bg-primary-600 text-white text-sm font-medium rounded-lg transition-colors"
          >
            <ArrowDownTrayIcon class="w-4 h-4" />
            Import {{ searchStore.selectedCount }} Selected
          </button>

          <!-- View Toggle -->
          <div class="flex border border-slate-200 rounded-lg overflow-hidden shadow-sm">
            <button
              @click="searchStore.setViewMode('grid')"
              :class="[
                'p-2 transition-colors',
                searchStore.viewMode === 'grid'
                  ? 'bg-primary-50 text-primary-600'
                  : 'bg-white text-slate-400 hover:bg-slate-50'
              ]"
              title="Grid View"
            >
              <Squares2X2Icon class="w-4 h-4" />
            </button>
            <div class="w-px bg-slate-200"></div>
            <button
              @click="searchStore.setViewMode('table')"
              :class="[
                'p-2 transition-colors',
                searchStore.viewMode === 'table'
                  ? 'bg-primary-50 text-primary-600'
                  : 'bg-white text-slate-400 hover:bg-slate-50'
              ]"
              title="Table View"
            >
              <ListBulletIcon class="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>

      <!-- Results Content -->
      <div class="p-6 min-h-[300px]">
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
      <div v-if="searchStore.hasResults && searchStore.total > searchStore.perPage" class="px-6 py-4 border-t border-slate-200">
        <Pagination
          :current-page="searchStore.page"
          :per-page="searchStore.perPage"
          :total="searchStore.total"
          @update:current-page="handlePageChange"
        />
      </div>
    </div>

    <!-- Empty State (before any search) -->
    <div v-else class="bg-white border border-slate-200 shadow-sm rounded-xl p-12">
      <EmptyState
        :channel="searchStore.channel"
        :search-mode="searchStore.mode"
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
import SearchCapBadge from '../common/SearchCapBadge.vue'
import ChannelDropdown from '../common/ChannelDropdown.vue'
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
const filtersVisible = ref(true)
const importingIndices = ref([])
const bulkImporting = ref(false)

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

const handleSearch = async () => {
  if (!searchStore.query.trim()) return
  if (!userStore.canSearch) {
    showError('Search limit reached', 'You have no searches remaining. Please upgrade your plan.')
    return
  }

  hasSearched.value = true

  // Build search params with filters
  const params = {
    ...filterStore.filterParams
  }

  await searchStore.search(params)

  // Update user stats after search
  userStore.decrementSearchCount()
}

const handleChannelChange = (channel) => {
  searchStore.setChannel(channel)
  filterStore.clearFilters()
  hasSearched.value = false
}

const handleModeChange = (mode) => {
  searchStore.setMode(mode)
  hasSearched.value = false
}

const handlePageChange = async (page) => {
  searchStore.setPage(page)
  await handleSearch()

  // Scroll to top of results
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

      // Update hydration for this result
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

    // Refresh hydration and clear selection
    await searchStore.refreshHydration()
    searchStore.deselectAll()

  } catch (err) {
    showError('Bulk Import Failed', err.message || 'Could not import selected podcasts.')
  } finally {
    bulkImporting.value = false
  }
}

// Watch for channel changes to reset state
watch(() => searchStore.channel, () => {
  hasSearched.value = false
})
</script>
