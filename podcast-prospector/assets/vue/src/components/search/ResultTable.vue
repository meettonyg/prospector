<template>
  <div class="prospector-result-table-wrapper">
    <table class="prospector-result-table">
      <thead class="prospector-result-table__head">
        <tr>
          <th class="prospector-result-table__th prospector-result-table__th--checkbox">
            <input
              type="checkbox"
              :checked="allSelected"
              @change="toggleSelectAll"
              class="prospector-result-table__checkbox"
            />
          </th>
          <th class="prospector-result-table__th prospector-result-table__th--cover">Cover</th>
          <th class="prospector-result-table__th">Show Name</th>
          <th class="prospector-result-table__th">Host</th>
          <th class="prospector-result-table__th prospector-result-table__th--type">Type</th>
          <th class="prospector-result-table__th prospector-result-table__th--category">Category</th>
          <th class="prospector-result-table__th prospector-result-table__th--action">Action</th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="(result, index) in results"
          :key="result.id || index"
          class="prospector-result-table__row"
        >
          <!-- Checkbox -->
          <td class="prospector-result-table__td">
            <input
              type="checkbox"
              :checked="result._selected"
              @change="$emit('toggle-select', index)"
              :disabled="isTracked(index)"
              class="prospector-result-table__checkbox"
            />
          </td>

          <!-- Cover -->
          <td class="prospector-result-table__td">
            <img
              :src="result.image || result.artwork || '/wp-content/plugins/podcast-prospector/assets/placeholder.png'"
              :alt="result.title"
              class="prospector-result-table__cover"
              @error="handleImageError"
            />
          </td>

          <!-- Title -->
          <td class="prospector-result-table__td">
            <div class="prospector-result-table__title-cell">
              <span class="prospector-result-table__title">
                {{ result.title }}
              </span>
              <span
                v-if="isTracked(index) && hasEngagement(index)"
                class="prospector-result-table__tracked-badge prospector-result-table__tracked-badge--aired"
              >
                Aired
              </span>
              <span
                v-else-if="isTracked(index)"
                class="prospector-result-table__tracked-badge"
              >
                In Pipeline
              </span>
            </div>
          </td>

          <!-- Host -->
          <td class="prospector-result-table__td prospector-result-table__td--muted">
            {{ result.author || result.host || result.ownerName || 'Unknown' }}
          </td>

          <!-- Type -->
          <td class="prospector-result-table__td">
            <div class="prospector-result-table__type">
              <component :is="getTypeIcon(result)" class="prospector-result-table__type-icon" :class="getTypeColor(result)" />
              <span class="prospector-result-table__type-label">{{ getTypeLabel(result) }}</span>
            </div>
          </td>

          <!-- Category -->
          <td class="prospector-result-table__td">
            <span 
              v-if="result.category || result.genre"
              class="prospector-result-table__category"
            >
              {{ result.category || result.genre }}
            </span>
          </td>

          <!-- Action -->
          <td class="prospector-result-table__td prospector-result-table__td--action">
            <!-- Not tracked: Import -->
            <button
              v-if="!isTracked(index)"
              @click="$emit('import', { result, index })"
              :disabled="isImporting(index)"
              class="prospector-result-table__action-btn"
            >
              <ArrowDownTrayIcon v-if="!isImporting(index)" class="prospector-result-table__action-icon" />
              <LoadingSpinner v-else size="xs" />
              <span>Import</span>
            </button>
            <!-- Tracked but no episode: Link Episode -->
            <button
              v-else-if="!hasEngagement(index)"
              @click="$emit('link-episode', { result, index })"
              :disabled="isLinking(index)"
              class="prospector-result-table__action-btn prospector-result-table__action-btn--link"
            >
              <LinkIcon v-if="!isLinking(index)" class="prospector-result-table__action-icon" />
              <LoadingSpinner v-else size="xs" />
              <span>Link</span>
            </button>
            <!-- Tracked and linked: View -->
            <a
              v-else
              :href="getHydration(index)?.crm_url"
              class="prospector-result-table__action-btn prospector-result-table__action-btn--view"
            >
              <EyeIcon class="prospector-result-table__action-icon" />
              <span>View</span>
            </a>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import {
  ArrowDownTrayIcon,
  EyeIcon,
  LinkIcon,
  MicrophoneIcon,
  PresentationChartBarIcon
} from '@heroicons/vue/24/outline'
import LoadingSpinner from '../common/LoadingSpinner.vue'

// YouTube icon
const YouTubeIcon = {
  template: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M19.615 3.184c-3.604-.246-11.631-.245-15.23 0-3.897.266-4.356 2.62-4.385 8.816.029 6.185.484 8.549 4.385 8.816 3.6.245 11.626.246 15.23 0 3.897-.266 4.356-2.62 4.385-8.816-.029-6.185-.484-8.549-4.385-8.816zm-10.615 12.816v-8l8 3.993-8 4.007z"/></svg>`
}

const props = defineProps({
  results: {
    type: Array,
    default: () => []
  },
  hydrationMap: {
    type: Object,
    default: () => ({})
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

const emit = defineEmits(['result-click', 'toggle-select', 'import', 'link-episode'])

const getHydration = (index) => props.hydrationMap[index]
const isTracked = (index) => props.hydrationMap[index]?.tracked
const hasEngagement = (index) => {
  const h = props.hydrationMap[index]
  return h?.has_engagement || h?.opportunity_status === 'aired'
}
const isImporting = (index) => props.importingIndices.includes(index)
const isLinking = (index) => props.linkingIndices.includes(index)

const allSelected = computed(() => {
  const selectableResults = props.results.filter((_, i) => !isTracked(i))
  if (selectableResults.length === 0) return false
  return selectableResults.every(r => r._selected)
})

const toggleSelectAll = () => {
  if (allSelected.value) {
    // Deselect all
    props.results.forEach((_, i) => {
      if (!isTracked(i)) emit('toggle-select', i)
    })
  } else {
    // Select all non-tracked
    props.results.forEach((result, i) => {
      if (!isTracked(i) && !result._selected) emit('toggle-select', i)
    })
  }
}

const getTypeIcon = (result) => {
  if (result.type === 'youtube' || result.channel === 'youtube') return YouTubeIcon
  if (result.type === 'summit' || result.channel === 'summits') return PresentationChartBarIcon
  return MicrophoneIcon
}

const getTypeColor = (result) => {
  if (result.type === 'youtube' || result.channel === 'youtube') return 'prospector-result-table__type-icon--youtube'
  if (result.type === 'summit' || result.channel === 'summits') return 'prospector-result-table__type-icon--summit'
  return 'prospector-result-table__type-icon--podcast'
}

const getTypeLabel = (result) => {
  if (result.type === 'youtube' || result.channel === 'youtube') return 'YouTube'
  if (result.type === 'summit' || result.channel === 'summits') return 'Summit'
  if (props.searchMode.includes('episode')) return 'Episode'
  return 'Podcast'
}

const handleImageError = (e) => {
  e.target.src = '/wp-content/plugins/podcast-prospector/assets/placeholder.png'
}
</script>

<style scoped>
.prospector-result-table-wrapper {
  overflow-x: auto;
  animation: prospectorFadeIn 0.3s ease-out forwards;
}

.prospector-result-table {
  width: 100%;
  border-collapse: collapse;
  font-size: var(--prospector-font-size-sm);
  text-align: left;
}

.prospector-result-table__head {
  background: var(--prospector-slate-50);
}

.prospector-result-table__th {
  padding: var(--prospector-space-md);
  font-size: var(--prospector-font-size-xs);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--prospector-slate-500);
  border-bottom: 1px solid var(--prospector-slate-200);
}

.prospector-result-table__th--checkbox {
  width: 2.5rem;
}

.prospector-result-table__th--cover {
  width: 4rem;
}

.prospector-result-table__th--type {
  width: 6rem;
}

.prospector-result-table__th--category {
  width: 8rem;
}

.prospector-result-table__th--action {
  width: 6rem;
  text-align: right;
}

.prospector-result-table__row {
  transition: background var(--prospector-transition-fast);
}

.prospector-result-table__row:hover {
  background: var(--prospector-slate-50);
}

.prospector-result-table__td {
  padding: var(--prospector-space-md);
  color: var(--prospector-slate-700);
  border-bottom: 1px solid var(--prospector-slate-100);
  vertical-align: middle;
}

.prospector-result-table__td--muted {
  color: var(--prospector-slate-500);
}

.prospector-result-table__td--action {
  text-align: right;
}

.prospector-result-table__checkbox {
  width: 1rem;
  height: 1rem;
  border-radius: var(--prospector-radius-sm);
  border: 1px solid var(--prospector-slate-300);
  accent-color: var(--prospector-primary-500);
  cursor: pointer;
}

.prospector-result-table__checkbox:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.prospector-result-table__cover {
  width: 2.5rem;
  height: 2.5rem;
  border-radius: var(--prospector-radius-sm);
  object-fit: cover;
  border: 1px solid var(--prospector-slate-200);
}

.prospector-result-table__title-cell {
  display: flex;
  align-items: center;
  gap: var(--prospector-space-sm);
}

.prospector-result-table__title {
  font-weight: 500;
  color: var(--prospector-slate-800);
  transition: color var(--prospector-transition-fast);
}

.prospector-result-table__row:hover .prospector-result-table__title {
  color: var(--prospector-primary-500);
}

.prospector-result-table__tracked-badge {
  font-size: 0.625rem;
  font-weight: 700;
  text-transform: uppercase;
  white-space: nowrap;
  background: var(--prospector-success-100);
  color: var(--prospector-success-700);
  padding: 0.125rem 0.375rem;
  border-radius: var(--prospector-radius-sm);
}

.prospector-result-table__tracked-badge--aired {
  background: var(--prospector-primary-100, #dbeafe);
  color: var(--prospector-primary-700, #1d4ed8);
}

.prospector-result-table__type {
  display: flex;
  align-items: center;
  gap: var(--prospector-space-sm);
  color: var(--prospector-slate-500);
}

.prospector-result-table__type-icon {
  width: 1rem;
  height: 1rem;
}

.prospector-result-table__type-icon--podcast {
  color: #a855f7;
}

.prospector-result-table__type-icon--youtube {
  color: #ef4444;
}

.prospector-result-table__type-icon--summit {
  color: #f97316;
}

.prospector-result-table__type-label {
  font-size: var(--prospector-font-size-xs);
  text-transform: capitalize;
}

.prospector-result-table__category {
  display: inline-block;
  background: var(--prospector-primary-50);
  color: var(--prospector-primary-600);
  font-size: 0.6875rem;
  font-weight: 600;
  padding: 0.125rem 0.5rem;
  border-radius: var(--prospector-radius-sm);
}

.prospector-result-table__action-btn {
  display: inline-flex;
  align-items: center;
  gap: var(--prospector-space-sm);
  padding: 0.375rem 0.75rem;
  font-size: var(--prospector-font-size-xs);
  font-weight: 500;
  color: var(--prospector-slate-500);
  background: white;
  border: 1px solid var(--prospector-slate-200);
  border-radius: var(--prospector-radius-lg);
  cursor: pointer;
  transition: all var(--prospector-transition-fast);
}

.prospector-result-table__action-btn:hover:not(:disabled) {
  color: var(--prospector-primary-500);
  border-color: var(--prospector-primary-500);
  background: var(--prospector-slate-50);
}

.prospector-result-table__action-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.prospector-result-table__action-btn--link {
  color: var(--prospector-primary-500);
  background: var(--prospector-primary-50, #eff6ff);
  border-color: var(--prospector-primary-200, #bfdbfe);
}

.prospector-result-table__action-btn--link:hover:not(:disabled) {
  background: var(--prospector-primary-100, #dbeafe);
  border-color: var(--prospector-primary-500);
}

.prospector-result-table__action-btn--view {
  text-decoration: none;
}

.prospector-result-table__action-icon {
  width: 0.875rem;
  height: 0.875rem;
}

@keyframes prospectorFadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}
</style>
