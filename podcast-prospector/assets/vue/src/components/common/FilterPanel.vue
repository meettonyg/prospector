<template>
  <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
    <!-- Toggle header -->
    <button
      @click="toggleExpanded"
      class="w-full flex items-center justify-between px-4 py-3 hover:bg-slate-50 transition-colors"
    >
      <div class="flex items-center gap-2">
        <AdjustmentsHorizontalIcon class="w-5 h-5 text-slate-500" />
        <span class="font-medium text-slate-700">Filters</span>
        <span
          v-if="activeFilterCount > 0"
          class="px-2 py-0.5 bg-primary-100 text-primary-700 rounded-full text-xs font-medium"
        >
          {{ activeFilterCount }}
        </span>
      </div>
      <ChevronDownIcon
        class="w-5 h-5 text-slate-400 transition-transform"
        :class="{ 'rotate-180': isExpanded }"
      />
    </button>

    <!-- Filter content -->
    <Transition name="slide">
      <div v-if="isExpanded" class="border-t border-slate-200 p-4">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <!-- Language -->
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1.5">
              Language
            </label>
            <select
              :value="filterStore.language"
              @change="filterStore.setFilter('language', $event.target.value || null)"
              class="input-field"
            >
              <option value="">All Languages</option>
              <option v-for="lang in LANGUAGES" :key="lang.value" :value="lang.value">
                {{ lang.label }}
              </option>
            </select>
          </div>

          <!-- Country -->
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1.5">
              Country
            </label>
            <select
              :value="filterStore.country"
              @change="filterStore.setFilter('country', $event.target.value || null)"
              class="input-field"
            >
              <option value="">All Countries</option>
              <option v-for="country in COUNTRIES" :key="country.value" :value="country.value">
                {{ country.label }}
              </option>
            </select>
          </div>

          <!-- Genre -->
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1.5">
              Genre
            </label>
            <select
              :value="filterStore.genre"
              @change="filterStore.setFilter('genre', $event.target.value || null)"
              class="input-field"
              :disabled="!canUseAdvancedFilters"
            >
              <option value="">All Genres</option>
              <option v-for="genre in GENRES" :key="genre.value" :value="genre.value">
                {{ genre.label }}
              </option>
            </select>
            <p v-if="!canUseAdvancedFilters" class="mt-1 text-xs text-slate-400">
              Upgrade to use genre filters
            </p>
          </div>

          <!-- Sort By -->
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1.5">
              Sort By
            </label>
            <select
              :value="filterStore.sortBy"
              @change="filterStore.setFilter('sortBy', $event.target.value)"
              class="input-field"
            >
              <option v-for="sort in SORT_OPTIONS" :key="sort.value" :value="sort.value">
                {{ sort.label }}
              </option>
            </select>
          </div>
        </div>

        <!-- Active filters display -->
        <div v-if="activeFiltersDisplay.length > 0" class="mt-4 flex flex-wrap gap-2">
          <span
            v-for="filter in activeFiltersDisplay"
            :key="filter.key"
            class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-slate-100 rounded-full text-sm"
          >
            <span class="text-slate-600">{{ filter.label }}:</span>
            <span class="font-medium text-slate-800">{{ filter.value }}</span>
            <button
              @click="filterStore.clearFilter(filter.key)"
              class="ml-1 text-slate-400 hover:text-slate-600"
            >
              <XMarkIcon class="w-3.5 h-3.5" />
            </button>
          </span>
        </div>

        <!-- Actions -->
        <div class="mt-4 flex items-center justify-between">
          <button
            v-if="activeFilterCount > 0"
            @click="filterStore.clearFilters"
            class="text-sm text-slate-500 hover:text-slate-700"
          >
            Clear all filters
          </button>
          <div v-else></div>

          <button
            @click="$emit('apply')"
            class="btn-primary"
          >
            Apply Filters
          </button>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import {
  AdjustmentsHorizontalIcon,
  ChevronDownIcon,
  XMarkIcon
} from '@heroicons/vue/24/outline'
import { useFilterStore } from '../../stores/filterStore'
import { useUserStore } from '../../stores/userStore'
import { LANGUAGES, COUNTRIES, GENRES, SORT_OPTIONS } from '../../utils/constants'

defineEmits(['apply'])

const filterStore = useFilterStore()
const userStore = useUserStore()

const isExpanded = computed(() => filterStore.expanded)
const activeFilterCount = computed(() => filterStore.activeFilterCount)
const activeFiltersDisplay = computed(() => filterStore.activeFiltersDisplay)
const canUseAdvancedFilters = computed(() => userStore.canUseAdvancedFilters)

const toggleExpanded = () => filterStore.toggleExpanded()
</script>

<style scoped>
.slide-enter-active,
.slide-leave-active {
  transition: all 0.2s ease;
}

.slide-enter-from,
.slide-leave-to {
  opacity: 0;
  max-height: 0;
}

.slide-enter-to,
.slide-leave-from {
  max-height: 500px;
}
</style>
