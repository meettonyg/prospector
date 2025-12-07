<template>
  <div
    :class="[
      'prospector-result-card',
      isSelected && 'prospector-result-card--selected',
      hydration?.tracked && 'prospector-result-card--tracked'
    ]"
    @click="$emit('click')"
  >
    <!-- Header -->
    <div class="prospector-result-card__header">
      <!-- Artwork -->
      <div class="prospector-result-card__artwork-container">
        <img
          v-if="artwork"
          :src="artwork"
          :alt="title"
          class="prospector-result-card__artwork"
          loading="lazy"
          @error="$event.target.src = '/wp-content/plugins/podcast-prospector/assets/placeholder-podcast.png'"
        />
        <div
          v-else
          class="prospector-result-card__artwork-placeholder"
        >
          <MicrophoneIcon class="prospector-result-card__artwork-icon" />
        </div>
      </div>

      <!-- Content -->
      <div class="prospector-result-card__content">
        <div class="prospector-result-card__title-row">
          <div class="prospector-result-card__title-content">
            <h3 class="prospector-result-card__title">{{ title }}</h3>
            <p v-if="author" class="prospector-result-card__author">{{ author }}</p>
          </div>

          <!-- Selection checkbox -->
          <div v-if="selectable" class="prospector-result-card__checkbox-wrapper" @click.stop>
            <input
              type="checkbox"
              :checked="isSelected"
              @change="$emit('select')"
              :disabled="hydration?.tracked"
              class="prospector-result-card__checkbox"
            />
          </div>
        </div>

        <!-- Description -->
        <p v-if="description" class="prospector-result-card__description">
          {{ truncatedDescription }}
        </p>

        <!-- Meta info -->
        <div class="prospector-result-card__meta">
          <!-- Hydration badge -->
          <span
            v-if="hydration?.hasOpportunity"
            class="prospector-badge prospector-badge--success"
          >
            <CheckCircleIcon class="prospector-badge__icon" />
            In Pipeline
          </span>
          <span
            v-else-if="hydration?.tracked"
            class="prospector-badge prospector-badge--neutral"
          >
            Tracked
          </span>

          <!-- Episode count -->
          <span v-if="episodeCount" class="prospector-badge prospector-badge--neutral">
            {{ episodeCount }} episodes
          </span>

          <!-- Language -->
          <span v-if="language && language !== 'en'" class="prospector-badge prospector-badge--neutral">
            {{ language.toUpperCase() }}
          </span>

          <!-- Categories -->
          <span
            v-for="category in displayCategories"
            :key="category"
            class="prospector-badge prospector-badge--primary"
          >
            {{ category }}
          </span>
        </div>
      </div>
    </div>

    <!-- Actions -->
    <div class="prospector-result-card__footer">
      <div class="prospector-result-card__links">
        <!-- Website link -->
        <a
          v-if="websiteUrl"
          :href="websiteUrl"
          target="_blank"
          rel="noopener noreferrer"
          @click.stop
          class="prospector-result-card__link"
          title="Visit website"
        >
          <GlobeAltIcon class="prospector-result-card__link-icon" />
        </a>

        <!-- RSS link -->
        <a
          v-if="rssUrl"
          :href="rssUrl"
          target="_blank"
          rel="noopener noreferrer"
          @click.stop
          class="prospector-result-card__link"
          title="RSS Feed"
        >
          <RssIcon class="prospector-result-card__link-icon" />
        </a>
      </div>

      <!-- Import button -->
      <ImportButton
        v-if="showImport"
        :is-tracked="hydration?.tracked"
        :has-opportunity="hydration?.hasOpportunity"
        :crm-url="hydration?.crm_url"
        :importing="importing"
        :disabled="!canImport"
        @import="$emit('import')"
        @click.stop
      />
    </div>
  </div>
</template>

<script setup>
import { computed, inject } from 'vue'
import {
  MicrophoneIcon,
  GlobeAltIcon,
  RssIcon,
  CheckCircleIcon
} from '@heroicons/vue/24/outline'
import ImportButton from './ImportButton.vue'
import { truncateText } from '../../utils/dataNormalizer'

const props = defineProps({
  title: {
    type: String,
    required: true
  },
  author: {
    type: String,
    default: ''
  },
  description: {
    type: String,
    default: ''
  },
  artwork: {
    type: String,
    default: ''
  },
  websiteUrl: {
    type: String,
    default: ''
  },
  rssUrl: {
    type: String,
    default: ''
  },
  episodeCount: {
    type: [Number, String],
    default: null
  },
  language: {
    type: String,
    default: 'en'
  },
  categories: {
    type: Array,
    default: () => []
  },
  hydration: {
    type: Object,
    default: () => ({})
  },
  isSelected: {
    type: Boolean,
    default: false
  },
  selectable: {
    type: Boolean,
    default: true
  },
  showImport: {
    type: Boolean,
    default: true
  },
  importing: {
    type: Boolean,
    default: false
  }
})

defineEmits(['click', 'select', 'import'])

const config = inject('config', {})
const canImport = computed(() => config.guestIntelActive !== false)

const truncatedDescription = computed(() => truncateText(props.description, 120))

const displayCategories = computed(() => {
  return (props.categories || []).slice(0, 2)
})
</script>

<style scoped>
.prospector-result-card {
  background: white;
  border: 1px solid var(--prospector-slate-200);
  border-radius: var(--prospector-radius-xl);
  padding: var(--prospector-space-md);
  cursor: pointer;
  transition: all var(--prospector-transition-base);
}

.prospector-result-card:hover {
  border-color: var(--prospector-primary-300);
  box-shadow: var(--prospector-shadow-md);
}

.prospector-result-card--selected {
  border-color: var(--prospector-primary-500);
  box-shadow: 0 0 0 2px var(--prospector-primary-100);
}

.prospector-result-card--tracked {
  background: var(--prospector-slate-50);
}

.prospector-result-card__header {
  display: flex;
  gap: var(--prospector-space-md);
}

.prospector-result-card__artwork-container {
  flex-shrink: 0;
}

.prospector-result-card__artwork {
  width: 4rem;
  height: 4rem;
  border-radius: var(--prospector-radius-lg);
  object-fit: cover;
  background: var(--prospector-slate-100);
}

.prospector-result-card__artwork-placeholder {
  width: 4rem;
  height: 4rem;
  border-radius: var(--prospector-radius-lg);
  background: var(--prospector-slate-100);
  display: flex;
  align-items: center;
  justify-content: center;
}

.prospector-result-card__artwork-icon {
  width: 2rem;
  height: 2rem;
  color: var(--prospector-slate-400);
}

.prospector-result-card__content {
  flex: 1;
  min-width: 0;
}

.prospector-result-card__title-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--prospector-space-sm);
}

.prospector-result-card__title-content {
  min-width: 0;
}

.prospector-result-card__title {
  font-weight: 600;
  font-size: var(--prospector-font-size-base);
  color: var(--prospector-slate-800);
  margin: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.prospector-result-card__author {
  font-size: var(--prospector-font-size-sm);
  color: var(--prospector-slate-500);
  margin: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.prospector-result-card__checkbox-wrapper {
  flex-shrink: 0;
}

.prospector-result-card__checkbox {
  width: 1rem;
  height: 1rem;
  border-radius: var(--prospector-radius-sm);
  border: 1px solid var(--prospector-slate-300);
  accent-color: var(--prospector-primary-500);
  cursor: pointer;
}

.prospector-result-card__description {
  margin: var(--prospector-space-sm) 0 0;
  font-size: var(--prospector-font-size-sm);
  color: var(--prospector-slate-600);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.prospector-result-card__meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--prospector-space-sm);
  margin-top: var(--prospector-space-md);
}

.prospector-result-card__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: var(--prospector-space-md);
  padding-top: var(--prospector-space-md);
  border-top: 1px solid var(--prospector-slate-100);
}

.prospector-result-card__links {
  display: flex;
  align-items: center;
  gap: var(--prospector-space-sm);
}

.prospector-result-card__link {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0.375rem;
  color: var(--prospector-slate-400);
  background: transparent;
  border: none;
  border-radius: var(--prospector-radius-md);
  cursor: pointer;
  transition: all var(--prospector-transition-fast);
}

.prospector-result-card__link:hover {
  color: var(--prospector-slate-600);
  background: var(--prospector-slate-100);
}

.prospector-result-card__link-icon {
  width: 1.25rem;
  height: 1.25rem;
}

/* Badge styles used within this component */
.prospector-badge {
  display: inline-flex;
  align-items: center;
  padding: 0.125rem 0.625rem;
  font-size: var(--prospector-font-size-xs);
  font-weight: 500;
  line-height: 1.5;
  border-radius: var(--prospector-radius-full);
}

.prospector-badge--primary {
  color: var(--prospector-primary-700);
  background: var(--prospector-primary-100);
}

.prospector-badge--success {
  color: var(--prospector-success-700);
  background: var(--prospector-success-100);
}

.prospector-badge--neutral {
  color: var(--prospector-slate-700);
  background: var(--prospector-slate-100);
}

.prospector-badge__icon {
  width: 0.875rem;
  height: 0.875rem;
  margin-right: var(--prospector-space-xs);
}
</style>
