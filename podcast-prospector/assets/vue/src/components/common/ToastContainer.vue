<template>
  <div
    class="prospector-toast-container"
    aria-live="polite"
    aria-atomic="true"
  >
    <TransitionGroup name="prospector-toast">
      <div
        v-for="toast in toasts"
        :key="toast.id"
        :class="[
          'prospector-toast',
          `prospector-toast--${toast.type}`
        ]"
        role="alert"
      >
        <!-- Icon -->
        <component
          :is="typeIcons[toast.type]"
          class="prospector-toast__icon"
        />

        <!-- Content -->
        <div class="prospector-toast__content">
          <p v-if="toast.title" class="prospector-toast__title">{{ toast.title }}</p>
          <p v-if="toast.message" class="prospector-toast__message">{{ toast.message }}</p>

          <!-- Action -->
          <a
            v-if="toast.action?.url"
            :href="toast.action.url"
            class="prospector-toast__action"
          >
            {{ toast.action.label }}
          </a>
          <button
            v-else-if="toast.action?.onClick"
            @click="toast.action.onClick"
            class="prospector-toast__action"
          >
            {{ toast.action.label }}
          </button>
        </div>

        <!-- Close button -->
        <button
          @click="removeToast(toast.id)"
          class="prospector-toast__close"
          aria-label="Dismiss"
        >
          <XMarkIcon class="prospector-toast__close-icon" />
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useToastStore } from '../../stores/toastStore'
import {
  CheckCircleIcon,
  ExclamationCircleIcon,
  ExclamationTriangleIcon,
  InformationCircleIcon,
  XMarkIcon
} from '@heroicons/vue/24/outline'

const toastStore = useToastStore()

const toasts = computed(() => toastStore.toasts)

const removeToast = (id) => toastStore.removeToast(id)

const typeIcons = {
  success: CheckCircleIcon,
  error: ExclamationCircleIcon,
  warning: ExclamationTriangleIcon,
  info: InformationCircleIcon
}
</script>

<style scoped>
.prospector-toast-container {
  position: fixed;
  bottom: var(--prospector-space-lg);
  right: var(--prospector-space-lg);
  z-index: 9999;
  display: flex;
  flex-direction: column;
  gap: var(--prospector-space-sm);
  max-width: 24rem;
}

.prospector-toast {
  display: flex;
  align-items: flex-start;
  gap: var(--prospector-space-md);
  padding: var(--prospector-space-md);
  background: white;
  border-radius: var(--prospector-radius-xl);
  box-shadow: var(--prospector-shadow-lg);
  border: 1px solid var(--prospector-slate-200);
}

.prospector-toast--success {
  border-left: 4px solid var(--prospector-success-500);
}

.prospector-toast--error {
  border-left: 4px solid var(--prospector-error-500);
}

.prospector-toast--warning {
  border-left: 4px solid var(--prospector-warning-500);
}

.prospector-toast--info {
  border-left: 4px solid var(--prospector-primary-500);
}

.prospector-toast__icon {
  flex-shrink: 0;
  width: 1.25rem;
  height: 1.25rem;
  margin-top: 0.125rem;
}

.prospector-toast--success .prospector-toast__icon {
  color: var(--prospector-success-500);
}

.prospector-toast--error .prospector-toast__icon {
  color: var(--prospector-error-500);
}

.prospector-toast--warning .prospector-toast__icon {
  color: var(--prospector-warning-500);
}

.prospector-toast--info .prospector-toast__icon {
  color: var(--prospector-primary-500);
}

.prospector-toast__content {
  flex: 1;
  min-width: 0;
}

.prospector-toast__title {
  font-weight: 600;
  font-size: var(--prospector-font-size-sm);
  color: var(--prospector-slate-800);
  margin: 0 0 var(--prospector-space-xs);
}

.prospector-toast__message {
  font-size: var(--prospector-font-size-sm);
  color: var(--prospector-slate-600);
  margin: 0;
  opacity: 0.9;
}

.prospector-toast__action {
  display: inline-block;
  margin-top: var(--prospector-space-sm);
  font-size: var(--prospector-font-size-sm);
  font-weight: 500;
  color: inherit;
  text-decoration: underline;
  background: none;
  border: none;
  cursor: pointer;
  padding: 0;
}

.prospector-toast__action:hover {
  text-decoration: none;
}

.prospector-toast__close {
  flex-shrink: 0;
  padding: var(--prospector-space-xs);
  color: var(--prospector-slate-400);
  background: transparent;
  border: none;
  border-radius: var(--prospector-radius-sm);
  cursor: pointer;
  transition: all var(--prospector-transition-fast);
}

.prospector-toast__close:hover {
  color: var(--prospector-slate-600);
  background: var(--prospector-slate-100);
}

.prospector-toast__close-icon {
  width: 1rem;
  height: 1rem;
}

/* Vue transitions */
.prospector-toast-enter-active,
.prospector-toast-leave-active {
  transition: all 0.3s ease;
}

.prospector-toast-enter-from {
  opacity: 0;
  transform: translateX(100%);
}

.prospector-toast-leave-to {
  opacity: 0;
  transform: translateX(100%);
}

.prospector-toast-move {
  transition: transform 0.3s ease;
}
</style>
