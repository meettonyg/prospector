<template>
  <div
    class="fixed bottom-4 right-4 z-50 flex flex-col gap-2 max-w-sm"
    aria-live="polite"
    aria-atomic="true"
  >
    <TransitionGroup name="toast">
      <div
        v-for="toast in toasts"
        :key="toast.id"
        :class="[
          'flex items-start gap-3 p-4 rounded-xl shadow-lg border',
          typeClasses[toast.type]
        ]"
        role="alert"
      >
        <!-- Icon -->
        <component
          :is="typeIcons[toast.type]"
          class="w-5 h-5 flex-shrink-0 mt-0.5"
        />

        <!-- Content -->
        <div class="flex-1 min-w-0">
          <p v-if="toast.title" class="font-medium text-sm">{{ toast.title }}</p>
          <p v-if="toast.message" class="text-sm opacity-90 mt-0.5">{{ toast.message }}</p>

          <!-- Action -->
          <a
            v-if="toast.action?.url"
            :href="toast.action.url"
            class="inline-block mt-2 text-sm font-medium underline hover:no-underline"
          >
            {{ toast.action.label }}
          </a>
          <button
            v-else-if="toast.action?.onClick"
            @click="toast.action.onClick"
            class="mt-2 text-sm font-medium underline hover:no-underline"
          >
            {{ toast.action.label }}
          </button>
        </div>

        <!-- Close button -->
        <button
          @click="removeToast(toast.id)"
          class="flex-shrink-0 p-1 rounded-lg hover:bg-black/5 transition-colors"
          aria-label="Dismiss"
        >
          <XMarkIcon class="w-4 h-4" />
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

const typeClasses = {
  success: 'bg-emerald-50 border-emerald-200 text-emerald-800',
  error: 'bg-red-50 border-red-200 text-red-800',
  warning: 'bg-amber-50 border-amber-200 text-amber-800',
  info: 'bg-blue-50 border-blue-200 text-blue-800'
}

const typeIcons = {
  success: CheckCircleIcon,
  error: ExclamationCircleIcon,
  warning: ExclamationTriangleIcon,
  info: InformationCircleIcon
}
</script>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition: all 0.3s ease;
}

.toast-enter-from {
  opacity: 0;
  transform: translateX(100%);
}

.toast-leave-to {
  opacity: 0;
  transform: translateX(100%);
}

.toast-move {
  transition: transform 0.3s ease;
}
</style>
