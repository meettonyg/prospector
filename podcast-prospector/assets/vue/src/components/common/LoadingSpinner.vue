<template>
  <div :class="['prospector-spinner', containerClass]">
    <svg
      class="prospector-spinner__icon"
      :class="sizeClass"
      xmlns="http://www.w3.org/2000/svg"
      fill="none"
      viewBox="0 0 24 24"
    >
      <circle
        class="prospector-spinner__track"
        cx="12"
        cy="12"
        r="10"
        stroke="currentColor"
        stroke-width="4"
      />
      <path
        class="prospector-spinner__fill"
        fill="currentColor"
        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
      />
    </svg>
    <span v-if="label" class="prospector-spinner__label" :class="labelSizeClass">{{ label }}</span>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  size: {
    type: String,
    default: 'md',
    validator: (v) => ['xs', 'sm', 'md', 'lg', 'xl'].includes(v)
  },
  label: {
    type: String,
    default: ''
  },
  fullScreen: {
    type: Boolean,
    default: false
  }
})

const sizeClass = computed(() => {
  return `prospector-spinner__icon--${props.size}`
})

const labelSizeClass = computed(() => {
  return `prospector-spinner__label--${props.size}`
})

const containerClass = computed(() => {
  if (props.fullScreen) {
    return 'prospector-spinner--fullscreen'
  }
  return ''
})
</script>

<style scoped>
.prospector-spinner {
  display: flex;
  align-items: center;
  justify-content: center;
}

.prospector-spinner--fullscreen {
  position: fixed;
  inset: 0;
  background: rgba(255, 255, 255, 0.8);
  z-index: 50;
}

.prospector-spinner__icon {
  animation: prospectorSpin 0.8s linear infinite;
  color: var(--prospector-primary-500);
}

.prospector-spinner__icon--xs {
  width: 0.75rem;
  height: 0.75rem;
}

.prospector-spinner__icon--sm {
  width: 1rem;
  height: 1rem;
}

.prospector-spinner__icon--md {
  width: 1.5rem;
  height: 1.5rem;
}

.prospector-spinner__icon--lg {
  width: 2rem;
  height: 2rem;
}

.prospector-spinner__icon--xl {
  width: 3rem;
  height: 3rem;
}

.prospector-spinner__track {
  opacity: 0.25;
}

.prospector-spinner__fill {
  opacity: 0.75;
}

.prospector-spinner__label {
  margin-left: var(--prospector-space-sm);
  color: var(--prospector-slate-600);
}

.prospector-spinner__label--xs {
  font-size: 0.625rem;
}

.prospector-spinner__label--sm {
  font-size: var(--prospector-font-size-xs);
}

.prospector-spinner__label--md {
  font-size: var(--prospector-font-size-sm);
}

.prospector-spinner__label--lg {
  font-size: var(--prospector-font-size-base);
}

.prospector-spinner__label--xl {
  font-size: var(--prospector-font-size-lg);
}

@keyframes prospectorSpin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}
</style>
