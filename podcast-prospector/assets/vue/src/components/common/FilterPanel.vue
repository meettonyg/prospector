<template>
  <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-6 animate-fade-in">
    <!-- Source Badge -->
    <div class="mb-4 flex justify-end">
      <span class="text-[10px] uppercase font-bold tracking-wider text-slate-400 bg-slate-50 border border-slate-200 px-2 py-1 rounded">
        Source: {{ apiSourceLabel }}
      </span>
    </div>

    <!-- Filters Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-x-6 gap-y-4">
      <!-- 1. Language -->
      <div class="relative">
        <div class="flex justify-between">
          <label class="block text-xs font-medium text-slate-500 uppercase tracking-wide mb-1.5">Language</label>
          <LockClosedIcon v-if="isFilterLocked('language')" class="w-3 h-3 text-amber-500" />
        </div>
        <select
          :value="filterStore.language"
          @change="filterStore.setFilter('language', $event.target.value || null)"
          :disabled="isFilterLocked('language')"
          :class="selectClasses(isFilterLocked('language'))"
        >
          <option value="">All Languages</option>
          <option v-for="lang in LANGUAGES" :key="lang.value" :value="lang.value">
            {{ lang.label }}
          </option>
        </select>
      </div>

      <!-- 2. Country -->
      <div class="relative">
        <div class="flex justify-between">
          <label class="block text-xs font-medium text-slate-500 uppercase tracking-wide mb-1.5">Country</label>
          <LockClosedIcon v-if="isFilterLocked('country')" class="w-3 h-3 text-amber-500" />
        </div>
        <select
          :value="filterStore.country"
          @change="filterStore.setFilter('country', $event.target.value || null)"
          :disabled="isFilterLocked('country')"
          :class="selectClasses(isFilterLocked('country'))"
        >
          <option value="">All Countries</option>
          <option v-for="country in COUNTRIES" :key="country.value" :value="country.value">
            {{ country.label }}
          </option>
        </select>
      </div>

      <!-- 3. Genre -->
      <div class="relative">
        <div class="flex justify-between">
          <label class="block text-xs font-medium text-slate-500 uppercase tracking-wide mb-1.5">Genre</label>
          <LockClosedIcon v-if="isFilterLocked('genre')" class="w-3 h-3 text-amber-500" />
        </div>
        <select
          :value="filterStore.genre"
          @change="filterStore.setFilter('genre', $event.target.value || null)"
          :disabled="isFilterLocked('genre')"
          :class="selectClasses(isFilterLocked('genre'))"
        >
          <option value="">All Genres</option>
          <option v-for="genre in GENRES" :key="genre.value" :value="genre.value">
            {{ genre.label }}
          </option>
        </select>
      </div>

      <!-- 4. Results Per Page -->
      <div class="relative">
        <label class="block text-xs font-medium text-slate-500 uppercase tracking-wide mb-1.5">Results Per Page</label>
        <select
          :value="filterStore.perPage"
          @change="filterStore.setFilter('perPage', parseInt($event.target.value))"
          :class="selectClasses(false)"
        >
          <option :value="10">10</option>
          <option :value="25">25</option>
          <option :value="50">50</option>
        </select>
      </div>

      <!-- 5. Published After -->
      <div class="relative">
        <div class="flex justify-between">
          <label class="block text-xs font-medium text-slate-500 uppercase tracking-wide mb-1.5">Published After</label>
          <LockClosedIcon v-if="isFilterLocked('dateFrom')" class="w-3 h-3 text-amber-500" />
        </div>
        <input
          type="date"
          :value="filterStore.dateFrom"
          @change="filterStore.setFilter('dateFrom', $event.target.value || null)"
          :disabled="isFilterLocked('dateFrom')"
          :placeholder="'mm/dd/yyyy'"
          :class="inputClasses(isFilterLocked('dateFrom'))"
        />
      </div>

      <!-- 6. Published Before -->
      <div class="relative">
        <div class="flex justify-between">
          <label class="block text-xs font-medium text-slate-500 uppercase tracking-wide mb-1.5">Published Before</label>
          <LockClosedIcon v-if="isFilterLocked('dateTo')" class="w-3 h-3 text-amber-500" />
        </div>
        <input
          type="date"
          :value="filterStore.dateTo"
          @change="filterStore.setFilter('dateTo', $event.target.value || null)"
          :disabled="isFilterLocked('dateTo')"
          :placeholder="'mm/dd/yyyy'"
          :class="inputClasses(isFilterLocked('dateTo'))"
        />
      </div>

      <!-- 7. Sort By -->
      <div class="relative">
        <label class="block text-xs font-medium text-slate-500 uppercase tracking-wide mb-1.5">Sort By</label>
        <select
          :value="filterStore.sortBy"
          @change="filterStore.setFilter('sortBy', $event.target.value)"
          :class="selectClasses(false)"
        >
          <option value="BEST_MATCH">Best Match</option>
          <option value="LATEST">Latest</option>
          <option value="OLDEST">Oldest</option>
        </select>
      </div>

      <!-- 8. Explicit Toggle -->
      <div class="flex flex-col justify-end pb-1">
        <div 
          class="flex items-center gap-3 cursor-pointer group"
          @click="toggleSafeMode"
        >
          <div 
            :class="[
              'w-10 h-6 flex items-center rounded-full p-0.5 transition-colors duration-300',
              !filterStore.safeMode ? 'bg-primary-500' : 'bg-slate-200'
            ]"
          >
            <div 
              :class="[
                'bg-white w-5 h-5 rounded-full shadow-md transform transition-transform duration-300',
                !filterStore.safeMode ? 'translate-x-4' : 'translate-x-0'
              ]"
            ></div>
          </div>
          <span class="text-sm text-slate-600 group-hover:text-slate-800 transition-colors select-none">
            Explicit Content
          </span>
        </div>
      </div>
    </div>

    <!-- Reset Filters -->
    <div class="mt-4 pt-4 border-t border-slate-200 flex justify-end">
      <button 
        @click="$emit('reset')"
        class="text-xs text-primary-500 font-medium hover:text-primary-600 flex items-center gap-1 transition-colors border border-primary-200 rounded px-2 py-1 hover:bg-primary-50"
      >
        Reset Filters
        <XMarkIcon class="w-3 h-3" />
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { LockClosedIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import { useFilterStore } from '../../stores/filterStore'
import { useUserStore } from '../../stores/userStore'

const props = defineProps({
  searchMode: {
    type: String,
    default: 'byperson'
  }
})

defineEmits(['apply', 'reset'])

const filterStore = useFilterStore()
const userStore = useUserStore()

const canUseAdvanced = computed(() => userStore.canUseAdvancedFilters)
const isPremiumMode = computed(() => props.searchMode.includes('advanced'))

const apiSourceLabel = computed(() => {
  if (props.searchMode.includes('advanced')) {
    return 'Premium (Taddy)'
  }
  return 'Free (Podcast Index)'
})

// Determine if a filter is locked based on mode and user permissions
const isFilterLocked = (filterName) => {
  // For free modes (byperson, bytitle), lock advanced filters
  if (!isPremiumMode.value) {
    const advancedFilters = ['language', 'country', 'genre', 'dateFrom', 'dateTo']
    return advancedFilters.includes(filterName)
  }
  // For premium modes, lock if user doesn't have access
  return !canUseAdvanced.value
}

const selectClasses = (locked) => {
  const base = 'w-full border rounded-lg px-3 py-2.5 text-sm focus:outline-none appearance-none bg-no-repeat bg-right pr-8'
  const arrow = "bg-[url('data:image/svg+xml;charset=UTF-8,%3csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 24 24%27 fill=%27none%27 stroke=%27%2394a3b8%27 stroke-width=%272%27%3e%3cpath d=%27M6 9l6 6 6-6%27/%3e%3c/svg%3e')] bg-[length:1.25rem]"
  
  if (locked) {
    return `${base} ${arrow} bg-slate-50 border-slate-200 text-slate-400 cursor-not-allowed`
  }
  return `${base} ${arrow} bg-white border-slate-200 text-slate-800 focus:border-primary-500 focus:ring-1 focus:ring-primary-500`
}

const inputClasses = (locked) => {
  const base = 'w-full border rounded-lg px-3 py-2.5 text-sm focus:outline-none'
  
  if (locked) {
    return `${base} bg-slate-50 border-slate-200 text-slate-400 cursor-not-allowed`
  }
  return `${base} bg-white border-slate-200 text-slate-800 focus:border-primary-500 focus:ring-1 focus:ring-primary-500`
}

const toggleSafeMode = () => {
  filterStore.setFilter('safeMode', !filterStore.safeMode)
}

// Filter options
const LANGUAGES = [
  { value: 'en', label: 'English' },
  { value: 'es', label: 'Spanish' },
  { value: 'fr', label: 'French' },
  { value: 'de', label: 'German' },
  { value: 'pt', label: 'Portuguese' },
  { value: 'it', label: 'Italian' },
  { value: 'ja', label: 'Japanese' },
  { value: 'zh', label: 'Chinese' }
]

const COUNTRIES = [
  { value: 'us', label: 'United States' },
  { value: 'gb', label: 'United Kingdom' },
  { value: 'ca', label: 'Canada' },
  { value: 'au', label: 'Australia' },
  { value: 'de', label: 'Germany' },
  { value: 'fr', label: 'France' },
  { value: 'es', label: 'Spain' },
  { value: 'mx', label: 'Mexico' }
]

const GENRES = [
  { value: 'business', label: 'Business' },
  { value: 'technology', label: 'Technology' },
  { value: 'health', label: 'Health & Fitness' },
  { value: 'education', label: 'Education' },
  { value: 'society', label: 'Society & Culture' },
  { value: 'comedy', label: 'Comedy' },
  { value: 'news', label: 'News' },
  { value: 'sports', label: 'Sports' }
]
</script>

<style scoped>
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-8px); }
  to { opacity: 1; transform: translateY(0); }
}
.animate-fade-in {
  animation: fadeIn 0.2s ease-out forwards;
}
</style>
