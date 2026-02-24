<template>
  <div class="prospector-filter-panel">
    <!-- Filters Grid -->
    <div class="prospector-filter-panel__grid">
      <!-- 1. Language -->
      <div class="prospector-filter-panel__field">
        <div class="prospector-filter-panel__label">
          <label class="prospector-filter-panel__label-text">Language</label>
          <LockClosedIcon v-if="isFilterLocked('language')" class="prospector-filter-panel__lock-icon" />
        </div>
        <select
          :value="filterStore.language"
          @change="filterStore.setFilter('language', $event.target.value || null)"
          :disabled="isFilterLocked('language')"
          :class="[
            'prospector-select--native',
            isFilterLocked('language') && 'prospector-select--native-disabled'
          ]"
        >
          <option value="">All Languages</option>
          <option v-for="lang in LANGUAGES" :key="lang.value" :value="lang.value">
            {{ lang.label }}
          </option>
        </select>
      </div>

      <!-- 2. Country -->
      <div class="prospector-filter-panel__field">
        <div class="prospector-filter-panel__label">
          <label class="prospector-filter-panel__label-text">Country</label>
          <LockClosedIcon v-if="isFilterLocked('country')" class="prospector-filter-panel__lock-icon" />
        </div>
        <select
          :value="filterStore.country"
          @change="filterStore.setFilter('country', $event.target.value || null)"
          :disabled="isFilterLocked('country')"
          :class="[
            'prospector-select--native',
            isFilterLocked('country') && 'prospector-select--native-disabled'
          ]"
        >
          <option value="">All Countries</option>
          <option v-for="country in COUNTRIES" :key="country.value" :value="country.value">
            {{ country.label }}
          </option>
        </select>
      </div>

      <!-- 3. Genre -->
      <div class="prospector-filter-panel__field">
        <div class="prospector-filter-panel__label">
          <label class="prospector-filter-panel__label-text">Genre</label>
          <LockClosedIcon v-if="isFilterLocked('genre')" class="prospector-filter-panel__lock-icon" />
        </div>
        <select
          :value="filterStore.genre"
          @change="filterStore.setFilter('genre', $event.target.value || null)"
          :disabled="isFilterLocked('genre')"
          :class="[
            'prospector-select--native',
            isFilterLocked('genre') && 'prospector-select--native-disabled'
          ]"
        >
          <option value="">All Genres</option>
          <option v-for="genre in GENRES" :key="genre.value" :value="genre.value">
            {{ genre.label }}
          </option>
        </select>
      </div>

      <!-- 4. Results Per Page -->
      <div class="prospector-filter-panel__field">
        <div class="prospector-filter-panel__label">
          <label class="prospector-filter-panel__label-text">Results Per Page</label>
        </div>
        <select
          :value="filterStore.perPage"
          @change="filterStore.setFilter('perPage', parseInt($event.target.value))"
          class="prospector-select--native"
        >
          <option :value="10">10</option>
          <option :value="25">25</option>
          <option :value="50">50</option>
        </select>
      </div>

      <!-- 5. Published After -->
      <div class="prospector-filter-panel__field">
        <div class="prospector-filter-panel__label">
          <label class="prospector-filter-panel__label-text">Published After</label>
          <LockClosedIcon v-if="isFilterLocked('dateFrom')" class="prospector-filter-panel__lock-icon" />
        </div>
        <input
          type="date"
          :value="filterStore.dateFrom"
          @change="filterStore.setFilter('dateFrom', $event.target.value || null)"
          :disabled="isFilterLocked('dateFrom')"
          :class="[
            'prospector-input',
            isFilterLocked('dateFrom') && 'prospector-input--disabled'
          ]"
        />
      </div>

      <!-- 6. Published Before -->
      <div class="prospector-filter-panel__field">
        <div class="prospector-filter-panel__label">
          <label class="prospector-filter-panel__label-text">Published Before</label>
          <LockClosedIcon v-if="isFilterLocked('dateTo')" class="prospector-filter-panel__lock-icon" />
        </div>
        <input
          type="date"
          :value="filterStore.dateTo"
          @change="filterStore.setFilter('dateTo', $event.target.value || null)"
          :disabled="isFilterLocked('dateTo')"
          :class="[
            'prospector-input',
            isFilterLocked('dateTo') && 'prospector-input--disabled'
          ]"
        />
      </div>

      <!-- 7. Sort By -->
      <div class="prospector-filter-panel__field">
        <div class="prospector-filter-panel__label">
          <label class="prospector-filter-panel__label-text">Sort By</label>
        </div>
        <select
          :value="filterStore.sortBy"
          @change="filterStore.setFilter('sortBy', $event.target.value)"
          class="prospector-select--native"
        >
          <option value="BEST_MATCH">Best Match</option>
          <option value="LATEST">Latest</option>
          <option value="OLDEST">Oldest</option>
        </select>
      </div>

      <!-- 8. Explicit Toggle -->
      <div class="prospector-filter-panel__field prospector-filter-panel__field--toggle">
        <div 
          class="prospector-toggle"
          @click="toggleSafeMode"
        >
          <div 
            :class="[
              'prospector-toggle__track',
              !filterStore.safeMode && 'prospector-toggle__track--active'
            ]"
          >
            <div class="prospector-toggle__thumb"></div>
          </div>
          <span class="prospector-toggle__label">
            Explicit Content
          </span>
        </div>
      </div>
    </div>

    <!-- Reset Filters -->
    <div class="prospector-filter-panel__footer">
      <button 
        @click="$emit('reset')"
        class="prospector-btn prospector-btn--outline-primary prospector-btn--sm"
      >
        Reset Filters
        <XMarkIcon class="prospector-filter-panel__reset-icon" />
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

// Determine if a filter is locked based on mode and user permissions
const isFilterLocked = (filterName) => {
  // Genre is available in all modes for users with genre access (needed for guest disambiguation)
  if (filterName === 'genre') {
    return !canUseAdvanced.value
  }
  // For free modes (byperson, bytitle), lock other advanced filters
  if (!isPremiumMode.value) {
    const advancedFilters = ['language', 'country', 'dateFrom', 'dateTo']
    return advancedFilters.includes(filterName)
  }
  // For premium modes, lock if user doesn't have access
  return !canUseAdvanced.value
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
  { value: 'PODCASTSERIES_ARTS', label: 'Arts' },
  { value: 'PODCASTSERIES_BUSINESS', label: 'Business' },
  { value: 'PODCASTSERIES_COMEDY', label: 'Comedy' },
  { value: 'PODCASTSERIES_EDUCATION', label: 'Education' },
  { value: 'PODCASTSERIES_FICTION', label: 'Fiction' },
  { value: 'PODCASTSERIES_GOVERNMENT', label: 'Government' },
  { value: 'PODCASTSERIES_HEALTH_AND_FITNESS', label: 'Health & Fitness' },
  { value: 'PODCASTSERIES_HISTORY', label: 'History' },
  { value: 'PODCASTSERIES_KIDS_AND_FAMILY', label: 'Kids & Family' },
  { value: 'PODCASTSERIES_LEISURE', label: 'Leisure' },
  { value: 'PODCASTSERIES_MUSIC', label: 'Music' },
  { value: 'PODCASTSERIES_NEWS', label: 'News' },
  { value: 'PODCASTSERIES_RELIGION_AND_SPIRITUALITY', label: 'Religion & Spirituality' },
  { value: 'PODCASTSERIES_SCIENCE', label: 'Science' },
  { value: 'PODCASTSERIES_SOCIETY_AND_CULTURE', label: 'Society & Culture' },
  { value: 'PODCASTSERIES_SPORTS', label: 'Sports' },
  { value: 'PODCASTSERIES_TECHNOLOGY', label: 'Technology' },
  { value: 'PODCASTSERIES_TRUE_CRIME', label: 'True Crime' },
  { value: 'PODCASTSERIES_TV_AND_FILM', label: 'TV & Film' }
]
</script>

<style scoped>
.prospector-filter-panel {
  background: #f7fafc;
  border: 1px solid var(--prospector-slate-200);
  border-radius: var(--prospector-radius-xl);
  padding: var(--prospector-space-lg);
  margin: var(--prospector-space-md) var(--prospector-space-lg);
  animation: prospectorFadeIn 0.2s ease-out forwards;
}

.prospector-filter-panel__grid {
  display: grid;
  grid-template-columns: repeat(1, 1fr);
  gap: var(--prospector-space-md) var(--prospector-space-lg);
}

@media (min-width: 768px) {
  .prospector-filter-panel__grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (min-width: 1024px) {
  .prospector-filter-panel__grid {
    grid-template-columns: repeat(4, 1fr);
  }
}

.prospector-filter-panel__field {
  position: relative;
}

.prospector-filter-panel__field--toggle {
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  padding-bottom: var(--prospector-space-xs);
}

.prospector-filter-panel__label {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.375rem;
}

.prospector-filter-panel__label-text {
  font-size: var(--prospector-font-size-xs);
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--prospector-slate-500);
}

.prospector-filter-panel__lock-icon {
  width: 0.75rem;
  height: 0.75rem;
  color: var(--prospector-warning-500);
}

.prospector-filter-panel__footer {
  margin-top: var(--prospector-space-md);
  padding-top: var(--prospector-space-md);
  border-top: 1px solid var(--prospector-slate-100);
  display: flex;
  justify-content: flex-end;
}

.prospector-filter-panel__reset-icon {
  width: 0.75rem;
  height: 0.75rem;
}

/* Native select overrides for this component */
.prospector-select--native {
  width: 100%;
  appearance: none;
  padding: 0.625rem 2.5rem 0.625rem var(--prospector-space-md);
  font-family: var(--prospector-font-family);
  font-size: var(--prospector-font-size-sm);
  color: var(--prospector-slate-700);
  background-color: white;
  background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2'%3e%3cpath d='M6 9l6 6 6-6'/%3e%3c/svg%3e");
  background-repeat: no-repeat;
  background-position: right 0.75rem center;
  background-size: 1.25rem;
  border: 1px solid var(--prospector-slate-200);
  border-radius: var(--prospector-radius-lg);
  cursor: pointer;
  transition: all var(--prospector-transition-fast);
}

.prospector-select--native:focus {
  border-color: var(--prospector-primary-400);
  box-shadow: 0 0 0 1px var(--prospector-primary-100);
  outline: none;
}

.prospector-select--native-disabled,
.prospector-select--native:disabled {
  background-color: var(--prospector-slate-50);
  color: var(--prospector-slate-400);
  cursor: not-allowed;
}

/* Input overrides */
.prospector-input {
  width: 100%;
  padding: 0.625rem var(--prospector-space-md);
  font-family: var(--prospector-font-family);
  font-size: var(--prospector-font-size-sm);
  color: var(--prospector-slate-700);
  background: white;
  border: 1px solid var(--prospector-slate-200);
  border-radius: var(--prospector-radius-lg);
  outline: none;
  transition: all var(--prospector-transition-fast);
}

.prospector-input:focus {
  border-color: var(--prospector-primary-400);
  box-shadow: 0 0 0 1px var(--prospector-primary-100);
}

.prospector-input--disabled,
.prospector-input:disabled {
  background: var(--prospector-slate-50);
  color: var(--prospector-slate-400);
  cursor: not-allowed;
}

/* Toggle styles */
.prospector-toggle {
  display: flex;
  align-items: center;
  gap: var(--prospector-space-md);
  cursor: pointer;
}

.prospector-toggle__track {
  position: relative;
  width: 2.5rem;
  height: 1.5rem;
  background: var(--prospector-slate-200);
  border-radius: var(--prospector-radius-full);
  padding: 0.125rem;
  transition: background var(--prospector-transition-base);
}

.prospector-toggle__track--active {
  background: var(--prospector-primary-500);
}

.prospector-toggle__thumb {
  width: 1.25rem;
  height: 1.25rem;
  background: white;
  border-radius: var(--prospector-radius-full);
  box-shadow: var(--prospector-shadow-sm);
  transition: transform var(--prospector-transition-base);
}

.prospector-toggle__track--active .prospector-toggle__thumb {
  transform: translateX(1rem);
}

.prospector-toggle__label {
  font-size: var(--prospector-font-size-sm);
  color: var(--prospector-slate-600);
  transition: color var(--prospector-transition-fast);
  user-select: none;
}

.prospector-toggle:hover .prospector-toggle__label {
  color: var(--prospector-slate-800);
}

@keyframes prospectorFadeIn {
  from {
    opacity: 0;
    transform: translateY(-8px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>
