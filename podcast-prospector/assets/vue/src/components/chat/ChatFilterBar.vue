<template>
  <div class="prospector-chat-filters">
    <div class="prospector-chat-filters__row">
      <!-- Language -->
      <select
        :value="filterStore.language || ''"
        @change="handleChange('language', $event.target.value || null)"
        class="prospector-chat-filters__select"
      >
        <option value="">Language</option>
        <option v-for="lang in LANGUAGES" :key="lang.value" :value="lang.value">
          {{ lang.label }}
        </option>
      </select>

      <!-- Country -->
      <select
        :value="filterStore.country || ''"
        @change="handleChange('country', $event.target.value || null)"
        class="prospector-chat-filters__select"
      >
        <option value="">Country</option>
        <option v-for="country in COUNTRIES" :key="country.value" :value="country.value">
          {{ country.label }}
        </option>
      </select>

      <!-- Genre -->
      <select
        :value="filterStore.genre || ''"
        @change="handleChange('genre', $event.target.value || null)"
        class="prospector-chat-filters__select"
      >
        <option value="">Genre</option>
        <option v-for="genre in GENRES" :key="genre.value" :value="genre.value">
          {{ genre.label }}
        </option>
      </select>

      <!-- Clear button -->
      <button
        v-if="filterStore.hasActiveFilters"
        @click="$emit('clear')"
        class="prospector-chat-filters__clear"
      >
        Clear
      </button>
    </div>
  </div>
</template>

<script setup>
import { useFilterStore } from '../../stores/filterStore'

const filterStore = useFilterStore()

const emit = defineEmits(['clear', 'change'])

const handleChange = (key, value) => {
  filterStore.setFilter(key, value)
  emit('change')
}

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
.prospector-chat-filters {
  padding: var(--prospector-space-sm) var(--prospector-space-md);
  border-top: 1px solid var(--prospector-slate-200);
  background: var(--prospector-slate-50);
  animation: prospectorFilterSlide 0.15s ease-out;
}

.prospector-chat-filters__row {
  display: flex;
  gap: var(--prospector-space-sm);
  align-items: center;
  flex-wrap: wrap;
}

.prospector-chat-filters__select {
  flex: 1;
  min-width: 0;
  appearance: none;
  padding: 0.375rem 1.75rem 0.375rem 0.5rem;
  font-family: var(--prospector-font-family);
  font-size: var(--prospector-font-size-xs);
  color: var(--prospector-slate-700);
  background-color: white;
  background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2'%3e%3cpath d='M6 9l6 6 6-6'/%3e%3c/svg%3e");
  background-repeat: no-repeat;
  background-position: right 0.375rem center;
  background-size: 1rem;
  border: 1px solid var(--prospector-slate-200);
  border-radius: var(--prospector-radius-md);
  cursor: pointer;
  transition: border-color var(--prospector-transition-fast);
}

.prospector-chat-filters__select:focus {
  border-color: var(--prospector-primary-400);
  outline: none;
}

.prospector-chat-filters__clear {
  flex-shrink: 0;
  padding: 0.375rem 0.625rem;
  font-family: var(--prospector-font-family);
  font-size: var(--prospector-font-size-xs);
  font-weight: 500;
  color: var(--prospector-slate-500);
  background: transparent;
  border: 1px solid var(--prospector-slate-200);
  border-radius: var(--prospector-radius-md);
  cursor: pointer;
  transition: all var(--prospector-transition-fast);
}

.prospector-chat-filters__clear:hover {
  color: var(--prospector-slate-700);
  border-color: var(--prospector-slate-300);
}

@keyframes prospectorFilterSlide {
  from {
    opacity: 0;
    max-height: 0;
  }
  to {
    opacity: 1;
    max-height: 4rem;
  }
}
</style>
