<template>
  <div class="prospector-result-grid">
    <div
      v-for="(result, index) in results"
      :key="result.id || index"
      class="prospector-result-grid__item"
      @click="$emit('result-click', { result, index })"
    >
      <!-- Checkbox for selection -->
      <div class="prospector-result-grid__checkbox-wrapper">
        <input
          type="checkbox"
          :checked="result._selected"
          @click.stop="$emit('toggle-select', index)"
          @change.stop
          class="prospector-result-grid__checkbox"
          :disabled="isTracked(index)"
        />
      </div>

      <!-- Cover Image -->
      <img
        :src="result.image || result.artwork || '/wp-content/plugins/podcast-prospector/assets/placeholder.png'"
        :alt="result.title"
        class="prospector-result-grid__image"
        @error="handleImageError"
      />

      <!-- Content -->
      <div class="prospector-result-grid__content">
        <!-- Type Badge -->
        <div class="prospector-result-grid__type">
          <component :is="getTypeIcon(result)" class="prospector-result-grid__type-icon" :class="getTypeColor(result)" />
          <span class="prospector-result-grid__type-label">
            {{ getTypeLabel(result) }}
          </span>
          <!-- Tracked Badge -->
          <span
            v-if="isTracked(index) && hasEngagement(index)"
            class="prospector-result-grid__tracked-badge prospector-result-grid__tracked-badge--aired"
          >
            Aired
          </span>
          <span
            v-else-if="isTracked(index)"
            class="prospector-result-grid__tracked-badge"
          >
            In Pipeline
          </span>
        </div>

        <!-- Title -->
        <h4 class="prospector-result-grid__title">
          {{ result.title }}
        </h4>

        <!-- Author/Host -->
        <p class="prospector-result-grid__author">
          {{ result.author || result.host || result.ownerName || 'Unknown' }}
        </p>

        <!-- Category/Genre Badge -->
        <span
          v-if="result.category || result.genre"
          class="prospector-result-grid__category"
        >
          {{ result.category || result.genre }}
        </span>
      </div>

      <!-- Action Buttons -->
      <div class="prospector-result-grid__action">
        <!-- Not tracked: Import button(s) -->
        <template v-if="!isTracked(index)">
          <!-- Episode-level search: split button with dropdown -->
          <div v-if="isEpisodeLevelSearch" class="prospector-import-split" @click.stop>
            <button
              @click="$emit('import', { result, index, importMode: 'potential' })"
              :disabled="isImporting(index)"
              class="prospector-import-split__main"
              title="Add to pipeline as potential opportunity"
            >
              <ArrowDownTrayIcon v-if="!isImporting(index)" class="prospector-result-grid__import-icon" />
              <LoadingSpinner v-else size="sm" />
            </button>
            <button
              @click="toggleDropdown(index)"
              :disabled="isImporting(index)"
              class="prospector-import-split__toggle"
              title="Import options"
            >
              <ChevronDownIcon class="prospector-import-split__chevron" />
            </button>
            <div v-if="openDropdown === index" class="prospector-import-split__menu">
              <button
                @click="$emit('import', { result, index, importMode: 'potential' }); openDropdown = null"
                class="prospector-import-split__option"
              >
                <PlusIcon class="prospector-import-split__option-icon" />
                Import as Potential
              </button>
              <button
                @click="$emit('import', { result, index, importMode: 'aired' }); openDropdown = null"
                class="prospector-import-split__option"
              >
                <LinkIcon class="prospector-import-split__option-icon" />
                Import as Aired
              </button>
            </div>
          </div>

          <!-- Podcast-level search: single import button -->
          <button
            v-else
            @click.stop="$emit('import', { result, index, importMode: 'potential' })"
            :disabled="isImporting(index)"
            class="prospector-result-grid__import-btn"
            title="Add to Pipeline"
          >
            <ArrowDownTrayIcon v-if="!isImporting(index)" class="prospector-result-grid__import-icon" />
            <LoadingSpinner v-else size="sm" />
          </button>
        </template>

        <!-- Tracked but no episode linked: Link Episode button -->
        <button
          v-else-if="!hasEngagement(index)"
          @click.stop="$emit('link-episode', { result, index })"
          :disabled="isLinking(index)"
          class="prospector-result-grid__link-btn"
          title="Link this episode to your pipeline"
        >
          <LinkIcon v-if="!isLinking(index)" class="prospector-result-grid__link-icon" />
          <LoadingSpinner v-else size="sm" />
        </button>

        <!-- Tracked and episode linked: View button -->
        <a
          v-else
          :href="getHydration(index)?.crm_url"
          @click.stop
          class="prospector-result-grid__view-btn"
          title="View in Pipeline"
        >
          <EyeIcon class="prospector-result-grid__view-icon" />
        </a>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import {
  ArrowDownTrayIcon,
  EyeIcon,
  LinkIcon,
  MicrophoneIcon,
  PresentationChartBarIcon,
  ChevronDownIcon,
  PlusIcon
} from '@heroicons/vue/24/outline'
import LoadingSpinner from './LoadingSpinner.vue'

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

defineEmits(['result-click', 'toggle-select', 'import', 'link-episode'])

const isEpisodeLevelSearch = computed(() =>
  ['byperson', 'byadvancedepisode', 'byyoutube'].includes(props.searchMode)
)

const openDropdown = ref(null)
const toggleDropdown = (index) => {
  openDropdown.value = openDropdown.value === index ? null : index
}

const closeDropdown = (e) => {
  if (openDropdown.value !== null) {
    openDropdown.value = null
  }
}

onMounted(() => {
  document.addEventListener('click', closeDropdown)
})

onUnmounted(() => {
  document.removeEventListener('click', closeDropdown)
})

const getHydration = (index) => props.hydrationMap[index]
const isTracked = (index) => props.hydrationMap[index]?.tracked
const hasEngagement = (index) => {
  const h = props.hydrationMap[index]
  return h?.has_engagement || h?.opportunity_status === 'aired'
}
const isImporting = (index) => props.importingIndices.includes(index)
const isLinking = (index) => props.linkingIndices.includes(index)

const getTypeIcon = (result) => {
  if (result.type === 'youtube' || result.channel === 'youtube') return YouTubeIcon
  if (result.type === 'summit' || result.channel === 'summits') return PresentationChartBarIcon
  return MicrophoneIcon
}

const getTypeColor = (result) => {
  if (result.type === 'youtube' || result.channel === 'youtube') return 'prospector-result-grid__type-icon--youtube'
  if (result.type === 'summit' || result.channel === 'summits') return 'prospector-result-grid__type-icon--summit'
  return 'prospector-result-grid__type-icon--podcast'
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
.prospector-result-grid {
  display: grid;
  grid-template-columns: repeat(1, 1fr);
  gap: var(--prospector-space-md);
  animation: prospectorFadeIn 0.3s ease-out forwards;
}

@media (min-width: 768px) {
  .prospector-result-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (min-width: 1024px) {
  .prospector-result-grid {
    grid-template-columns: repeat(3, 1fr);
  }
}

.prospector-result-grid__item {
  display: flex;
  align-items: flex-start;
  gap: var(--prospector-space-md);
  background: white;
  border: 1px solid var(--prospector-slate-200);
  border-radius: var(--prospector-radius-lg);
  padding: var(--prospector-space-md);
  cursor: pointer;
  transition: all var(--prospector-transition-fast);
  min-width: 0;
  overflow: hidden;
}

.prospector-result-grid__item:hover {
  border-color: var(--prospector-slate-300);
  box-shadow: var(--prospector-shadow-md);
}

.prospector-result-grid__checkbox-wrapper {
  flex-shrink: 0;
  padding-top: var(--prospector-space-xs);
}

.prospector-result-grid__checkbox {
  width: 1rem;
  height: 1rem;
  border-radius: var(--prospector-radius-sm);
  border: 1px solid var(--prospector-slate-300);
  accent-color: var(--prospector-primary-500);
  cursor: pointer;
}

.prospector-result-grid__checkbox:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.prospector-result-grid__image {
  width: 4rem;
  height: 4rem;
  border-radius: var(--prospector-radius-lg);
  object-fit: cover;
  background: var(--prospector-slate-100);
  border: 1px solid var(--prospector-slate-200);
  flex-shrink: 0;
}

.prospector-result-grid__content {
  flex: 1;
  min-width: 0;
}

.prospector-result-grid__type {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  margin-bottom: 0.375rem;
}

.prospector-result-grid__type-icon {
  width: 0.875rem;
  height: 0.875rem;
}

.prospector-result-grid__type-icon--podcast {
  color: #a855f7;
}

.prospector-result-grid__type-icon--youtube {
  color: #ef4444;
}

.prospector-result-grid__type-icon--summit {
  color: #f97316;
}

.prospector-result-grid__type-label {
  font-size: 0.625rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--prospector-slate-500);
}

.prospector-result-grid__tracked-badge {
  margin-left: auto;
  font-size: 0.625rem;
  font-weight: 700;
  text-transform: uppercase;
  background: var(--prospector-success-100);
  color: var(--prospector-success-700);
  padding: 0.125rem 0.375rem;
  border-radius: var(--prospector-radius-sm);
}

.prospector-result-grid__tracked-badge--aired {
  background: var(--prospector-primary-100, #dbeafe);
  color: var(--prospector-primary-700, #1d4ed8);
}

.prospector-result-grid__title {
  font-weight: 600;
  font-size: var(--prospector-font-size-sm);
  color: var(--prospector-slate-800);
  margin: 0;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  line-height: 1.4;
  transition: color var(--prospector-transition-fast);
}

.prospector-result-grid__item:hover .prospector-result-grid__title {
  color: var(--prospector-primary-500);
}

.prospector-result-grid__author {
  font-size: var(--prospector-font-size-xs);
  color: var(--prospector-slate-500);
  margin: var(--prospector-space-xs) 0 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.prospector-result-grid__category {
  display: inline-block;
  margin-top: var(--prospector-space-md);
  background: var(--prospector-primary-50);
  color: var(--prospector-primary-600);
  font-size: 0.6875rem;
  font-weight: 600;
  padding: 0.125rem 0.5rem;
  border-radius: var(--prospector-radius-sm);
}

.prospector-result-grid__action {
  flex-shrink: 0;
}

.prospector-result-grid__import-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--prospector-space-sm);
  background: white;
  border: 1px solid var(--prospector-slate-200);
  border-radius: var(--prospector-radius-lg);
  color: var(--prospector-slate-400);
  cursor: pointer;
  transition: all var(--prospector-transition-fast);
}

.prospector-result-grid__import-btn:hover:not(:disabled) {
  background: var(--prospector-primary-50);
  border-color: var(--prospector-primary-500);
  color: var(--prospector-primary-500);
}

.prospector-result-grid__import-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.prospector-result-grid__import-icon {
  width: 1rem;
  height: 1rem;
}

/* Split button for import mode choice */
.prospector-import-split {
  position: relative;
  display: flex;
  align-items: center;
}

.prospector-import-split__main {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--prospector-space-sm);
  background: white;
  border: 1px solid var(--prospector-slate-200);
  border-right: none;
  border-radius: var(--prospector-radius-lg) 0 0 var(--prospector-radius-lg);
  color: var(--prospector-slate-400);
  cursor: pointer;
  transition: all var(--prospector-transition-fast);
}

.prospector-import-split__main:hover:not(:disabled) {
  background: var(--prospector-primary-50);
  border-color: var(--prospector-primary-500);
  color: var(--prospector-primary-500);
}

.prospector-import-split__main:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.prospector-import-split__toggle {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--prospector-space-sm) 0.25rem;
  background: white;
  border: 1px solid var(--prospector-slate-200);
  border-radius: 0 var(--prospector-radius-lg) var(--prospector-radius-lg) 0;
  color: var(--prospector-slate-400);
  cursor: pointer;
  transition: all var(--prospector-transition-fast);
}

.prospector-import-split__toggle:hover:not(:disabled) {
  background: var(--prospector-primary-50);
  border-color: var(--prospector-primary-500);
  color: var(--prospector-primary-500);
}

.prospector-import-split__toggle:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.prospector-import-split__chevron {
  width: 0.75rem;
  height: 0.75rem;
}

.prospector-import-split__menu {
  position: absolute;
  top: 100%;
  right: 0;
  margin-top: 0.25rem;
  background: white;
  border: 1px solid var(--prospector-slate-200);
  border-radius: var(--prospector-radius-lg);
  box-shadow: var(--prospector-shadow-lg, 0 4px 12px rgba(0, 0, 0, 0.15));
  z-index: 50;
  min-width: 10rem;
  overflow: hidden;
}

.prospector-import-split__option {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  width: 100%;
  padding: 0.5rem 0.75rem;
  background: none;
  border: none;
  font-size: var(--prospector-font-size-sm, 0.875rem);
  color: var(--prospector-slate-700);
  cursor: pointer;
  transition: background var(--prospector-transition-fast);
  white-space: nowrap;
}

.prospector-import-split__option:hover {
  background: var(--prospector-primary-50);
  color: var(--prospector-primary-600);
}

.prospector-import-split__option-icon {
  width: 1rem;
  height: 1rem;
  flex-shrink: 0;
}

.prospector-result-grid__link-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--prospector-space-sm);
  background: var(--prospector-primary-50, #eff6ff);
  border: 1px solid var(--prospector-primary-200, #bfdbfe);
  border-radius: var(--prospector-radius-lg);
  color: var(--prospector-primary-500);
  cursor: pointer;
  transition: all var(--prospector-transition-fast);
}

.prospector-result-grid__link-btn:hover:not(:disabled) {
  background: var(--prospector-primary-100, #dbeafe);
  border-color: var(--prospector-primary-500);
}

.prospector-result-grid__link-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.prospector-result-grid__link-icon {
  width: 1rem;
  height: 1rem;
}

.prospector-result-grid__view-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: var(--prospector-space-sm);
  background: var(--prospector-success-50);
  border: 1px solid var(--prospector-success-200);
  border-radius: var(--prospector-radius-lg);
  color: var(--prospector-success-600);
  transition: all var(--prospector-transition-fast);
}

.prospector-result-grid__view-btn:hover {
  background: var(--prospector-success-100);
}

.prospector-result-grid__view-icon {
  width: 1rem;
  height: 1rem;
}

@keyframes prospectorFadeIn {
  from {
    opacity: 0;
    transform: translateY(8px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>
