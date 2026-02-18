<template>
  <div class="prospector-chat-result-card">
    <div class="prospector-chat-result-card__inner">
      <!-- Artwork -->
      <img
        v-if="artwork"
        :src="artwork"
        :alt="title"
        class="prospector-chat-result-card__artwork"
        loading="lazy"
      />
      <div
        v-else
        class="prospector-chat-result-card__artwork-placeholder"
      >
        <MicrophoneIcon class="prospector-chat-result-card__artwork-icon" />
      </div>

      <!-- Content -->
      <div class="prospector-chat-result-card__content">
        <div class="prospector-chat-result-card__header">
          <h4 class="prospector-chat-result-card__title">{{ title }}</h4>
          <span
            v-if="isTracked"
            class="prospector-chat-result-card__badge"
          >
            In Pipeline
          </span>
        </div>
        <p v-if="author" class="prospector-chat-result-card__author">{{ author }}</p>

        <div class="prospector-chat-result-card__actions">
          <a
            v-if="websiteUrl"
            :href="websiteUrl"
            target="_blank"
            rel="noopener noreferrer"
            class="prospector-chat-result-card__link"
          >
            Visit Website
          </a>

          <button
            v-if="!isTracked"
            @click="$emit('import')"
            class="prospector-chat-result-card__import-btn"
          >
            + Add to Pipeline
          </button>
          <a
            v-else-if="hydration?.crm_url"
            :href="hydration.crm_url"
            target="_blank"
            rel="noopener noreferrer"
            class="prospector-chat-result-card__link"
          >
            View in Pipeline
          </a>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { MicrophoneIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  result: {
    type: Object,
    required: true
  },
  hydration: {
    type: Object,
    default: null
  }
})

defineEmits(['import'])

const isTracked = computed(() => props.hydration?.tracked === true)

const title = computed(() => {
  return props.result.title || props.result.name || props.result.feedTitle || 'Untitled'
})

const author = computed(() => {
  return props.result.author || props.result.publisher || props.result.ownerName || ''
})

const artwork = computed(() => {
  return props.result.artwork || props.result.image || props.result.imageUrl || ''
})

const websiteUrl = computed(() => {
  return props.result.link || props.result.websiteUrl || props.result.website || ''
})
</script>

<style scoped>
.prospector-chat-result-card {
  background: white;
  border: 1px solid var(--prospector-slate-200);
  border-radius: var(--prospector-radius-lg);
  padding: var(--prospector-space-md);
  transition: border-color var(--prospector-transition-fast);
}

.prospector-chat-result-card:hover {
  border-color: var(--prospector-primary-300);
}

.prospector-chat-result-card__inner {
  display: flex;
  align-items: flex-start;
  gap: var(--prospector-space-md);
}

.prospector-chat-result-card__artwork {
  width: 3rem;
  height: 3rem;
  border-radius: var(--prospector-radius-lg);
  object-fit: cover;
  background: var(--prospector-slate-100);
  flex-shrink: 0;
}

.prospector-chat-result-card__artwork-placeholder {
  width: 3rem;
  height: 3rem;
  border-radius: var(--prospector-radius-lg);
  background: var(--prospector-slate-100);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.prospector-chat-result-card__artwork-icon {
  width: 1.5rem;
  height: 1.5rem;
  color: var(--prospector-slate-400);
}

.prospector-chat-result-card__content {
  flex: 1;
  min-width: 0;
}

.prospector-chat-result-card__header {
  display: flex;
  align-items: center;
  gap: var(--prospector-space-sm);
}

.prospector-chat-result-card__badge {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  padding: 0.0625rem 0.375rem;
  font-size: 0.625rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.025em;
  color: var(--prospector-success-700, #15803d);
  background: var(--prospector-success-50, #f0fdf4);
  border: 1px solid var(--prospector-success-200, #bbf7d0);
  border-radius: var(--prospector-radius-full);
}

.prospector-chat-result-card__title {
  font-weight: 500;
  font-size: var(--prospector-font-size-sm);
  color: var(--prospector-slate-800);
  margin: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.prospector-chat-result-card__author {
  font-size: var(--prospector-font-size-xs);
  color: var(--prospector-slate-500);
  margin: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.prospector-chat-result-card__actions {
  display: flex;
  align-items: center;
  gap: var(--prospector-space-sm);
  margin-top: var(--prospector-space-sm);
}

.prospector-chat-result-card__link {
  font-size: var(--prospector-font-size-xs);
  color: var(--prospector-primary-600);
  text-decoration: none;
  transition: color var(--prospector-transition-fast);
}

.prospector-chat-result-card__link:hover {
  color: var(--prospector-primary-700);
  text-decoration: underline;
}

.prospector-chat-result-card__import-btn {
  font-size: var(--prospector-font-size-xs);
  font-weight: 500;
  color: var(--prospector-primary-600);
  background: transparent;
  border: none;
  cursor: pointer;
  padding: 0;
  transition: color var(--prospector-transition-fast);
}

.prospector-chat-result-card__import-btn:hover {
  color: var(--prospector-primary-700);
}
</style>
