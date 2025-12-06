<template>
  <button
    @click="handleImport"
    :disabled="disabled || importing || isTracked"
    :class="[
      'inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium transition-all',
      buttonClass
    ]"
    :title="buttonTitle"
  >
    <LoadingSpinner v-if="importing" size="sm" />
    <component v-else :is="buttonIcon" class="w-4 h-4" />
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

const buttonClass = computed(() => {
  if (props.hasOpportunity) {
    return 'bg-emerald-100 text-emerald-700 cursor-default'
  }
  if (props.isTracked) {
    return 'bg-slate-100 text-slate-600 cursor-default'
  }
  if (props.disabled) {
    return 'bg-slate-100 text-slate-400 cursor-not-allowed'
  }
  return 'bg-primary-500 text-white hover:bg-primary-600 active:bg-primary-700'
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
