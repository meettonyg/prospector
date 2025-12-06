<template>
  <div class="space-y-6">
    <!-- Header with search cap -->
    <div class="flex flex-wrap items-center justify-between gap-4">
      <h2 class="text-xl font-semibold text-slate-800">Discover Podcasts</h2>
      <SearchCapBadge />
    </div>

    <!-- Search controls -->
    <div class="space-y-4">
      <!-- Channel and search input row -->
      <div class="flex flex-wrap gap-3">
        <ChannelDropdown
          v-model="searchStore.channel"
          @update:model-value="handleChannelChange"
        />

        <div class="flex-1 min-w-[300px]">
          <SearchInput
            v-model="searchStore.query"
            :placeholder="searchPlaceholder"
            :disabled="!userStore.canSearch"
            @search="handleSearch"
          />
        </div>
      </div>

      <!-- Search mode tabs -->
      <SearchModeTabs
        v-model="searchStore.mode"
        :channel="searchStore.channel"
        @update:model-value="handleModeChange"
      />

      <!-- Filters -->
      <FilterPanel
        v-if="showFilters"
        @apply="handleSearch"
      />
    </div>

    <!-- Results toolbar (only when there are results) -->
    <ResultsToolbar
      v-if="searchStore.hasResults"
      :total="searchStore.total"
      :tracked-count="searchStore.trackedCount"
      :selected-count="searchStore.selectedCount"
      :view-mode="searchStore.viewMode"
      :has-results="searchStore.hasResults"
      :importing="bulkImporting"
      @update:view-mode="searchStore.setViewMode"
      @select-all="searchStore.selectAllImportable"
      @deselect-all="searchStore.deselectAll"
      @bulk-import="handleBulkImport"
    />

    <!-- Results container -->
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

    <!-- Pagination -->
    <Pagination
      v-if="searchStore.hasResults && searchStore.total > searchStore.perPage"
      :current-page="searchStore.page"
      :per-page="searchStore.perPage"
      :total="searchStore.total"
      @update:current-page="handlePageChange"
    />
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
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
import ResultsToolbar from './ResultsToolbar.vue'
import ResultsContainer from './ResultsContainer.vue'
import Pagination from '../common/Pagination.vue'

// Stores
const searchStore = useSearchStore()
const userStore = useUserStore()
const filterStore = useFilterStore()
const { success, error: showError } = useToast()

// Local state
const hasSearched = ref(false)
const importingIndices = ref([])
const bulkImporting = ref(false)

// Computed
const searchPlaceholder = computed(() => {
  const placeholders = {
    byperson: 'Search by guest or host name...',
    bytitle: 'Search by podcast title...',
    byadvancedpodcast: 'Search podcasts with filters...',
    byadvancedepisode: 'Search episodes with filters...',
    byyoutube: 'Search YouTube channels...',
    bysummit: 'Search virtual summits...'
  }
  return placeholders[searchStore.mode] || 'Search for podcasts...'
})

const showFilters = computed(() => {
  // Show filters for advanced search modes
  return ['byadvancedpodcast', 'byadvancedepisode'].includes(searchStore.mode)
})

// Handlers
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
  handleSearch()
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
