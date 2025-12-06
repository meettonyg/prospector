<template>
  <div
    :class="[
      'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium',
      badgeClass
    ]"
  >
    <component :is="badgeIcon" class="w-4 h-4" />
    <span>{{ badgeText }}</span>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useUserStore } from '../../stores/userStore'
import {
  SparklesIcon,
  ExclamationTriangleIcon,
  CheckCircleIcon
} from '@heroicons/vue/24/outline'

const userStore = useUserStore()

const badgeText = computed(() => {
  if (userStore.isUnlimited) {
    return 'Unlimited searches'
  }
  if (userStore.searchesRemaining <= 0) {
    return 'No searches left'
  }
  if (userStore.searchesRemaining === 1) {
    return '1 search left'
  }
  return `${userStore.searchesRemaining} searches left`
})

const badgeClass = computed(() => {
  if (userStore.isUnlimited) {
    return 'bg-primary-100 text-primary-700'
  }
  if (userStore.searchesRemaining <= 0) {
    return 'bg-red-100 text-red-700'
  }
  if (userStore.searchesRemaining <= 3) {
    return 'bg-amber-100 text-amber-700'
  }
  return 'bg-emerald-100 text-emerald-700'
})

const badgeIcon = computed(() => {
  if (userStore.isUnlimited) {
    return SparklesIcon
  }
  if (userStore.searchesRemaining <= 0) {
    return ExclamationTriangleIcon
  }
  return CheckCircleIcon
})
</script>
