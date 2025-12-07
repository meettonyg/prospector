<template>
  <button
    @click="handleImport"
    :disabled="disabled || importing || isTracked"
    :class="[
      'prospector-import-btn',
      hasOpportunity && 'prospector-import-btn--has-opportunity',
      isTracked && !hasOpportunity && 'prospector-import-btn--tracked',
      importing && 'prospector-import-btn--loading',
      disabled && 'prospector-import-btn--disabled',
      size === 'sm' && 'prospector-import-btn--sm'
    ]"
    :title="buttonTitle"
  >
    <LoadingSpinner v-if="importing" size="sm" />
    <component v-else :is="buttonIcon" class="prospector-import-btn__icon" />
    <span>{{ buttonText }}</span>
  </button>
</template>

<script setup>
import { computed } from 'vue'
import {
  PlusIcon,
  CheckIcon,
  ArrowTopRightOnSquareIcon
} from '@heroicons/vue/24/outline'
import LoadingSpinner from './LoadingSpinner.vue'

const props = defineProps({
  isTracked: {
    type: Boolean,
    default: false
  },
  hasOpportunity: {
    type: Boolean,
    default: false
  },
  crmUrl: {
    type: String,
    default: null
  },
  importing: {
    type: Boolean,
    default: false
  },
  disabled: {
    type: Boolean,
    default: false
  },
  size: {
    type: String,
    default: 'sm',
    validator: (v) => ['sm', 'md'].includes(v)
  }
})

const emit = defineEmits(['import'])

const buttonText = computed(() => {
  if (props.importing) return 'Adding...'
  if (props.hasOpportunity) return 'In Pipeline'
  if (props.isTracked) return 'Tracked'
  return 'Add to Pipeline'
})

const buttonIcon = computed(() => {
  if (props.hasOpportunity || props.isTracked) return CheckIcon
  return PlusIcon
})

const buttonTitle = computed(() => {
  if (props.hasOpportunity) return 'Already in your pipeline'
  if (props.isTracked) return 'Already tracked in Guest Intel'
  if (props.disabled) return 'Import not available'
  return 'Add to your pipeline'
})

const handleImport = () => {
  if (!props.isTracked && !props.hasOpportunity && !props.disabled && !props.importing) {
    emit('import')
  } else if ((props.isTracked || props.hasOpportunity) && props.crmUrl) {
    window.open(props.crmUrl, '_blank')
  }
}
</script>

<style scoped>
.prospector-import-btn {
  display: inline-flex;
  align-items: center;
  gap: var(--prospector-space-sm);
  padding: var(--prospector-space-sm) var(--prospector-space-md);
  font-family: var(--prospector-font-family);
  font-size: var(--prospector-font-size-sm);
  font-weight: 500;
  color: white;
  background: var(--prospector-primary-500);
  border: none;
  border-radius: var(--prospector-radius-lg);
  cursor: pointer;
  transition: all var(--prospector-transition-fast);
}

.prospector-import-btn:hover:not(:disabled) {
  background: var(--prospector-primary-600);
}

.prospector-import-btn:active:not(:disabled) {
  background: var(--prospector-primary-700);
}

.prospector-import-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.prospector-import-btn--has-opportunity {
  color: var(--prospector-success-700);
  background: var(--prospector-success-100);
  cursor: default;
}

.prospector-import-btn--has-opportunity:hover:not(:disabled) {
  background: var(--prospector-success-100);
}

.prospector-import-btn--tracked {
  color: var(--prospector-slate-600);
  background: var(--prospector-slate-100);
  cursor: default;
}

.prospector-import-btn--tracked:hover:not(:disabled) {
  background: var(--prospector-slate-100);
}

.prospector-import-btn--loading {
  color: var(--prospector-slate-500);
  background: var(--prospector-slate-50);
  cursor: wait;
}

.prospector-import-btn--disabled {
  color: var(--prospector-slate-400);
  background: var(--prospector-slate-100);
}

.prospector-import-btn--sm {
  padding: 0.375rem 0.75rem;
  font-size: var(--prospector-font-size-xs);
}

.prospector-import-btn__icon {
  width: 1rem;
  height: 1rem;
}

.prospector-import-btn--sm .prospector-import-btn__icon {
  width: 0.875rem;
  height: 0.875rem;
}
</style>
