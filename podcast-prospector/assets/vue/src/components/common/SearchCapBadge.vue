<template>
  <div 
    class="flex items-center gap-2 px-3 py-1.5 rounded-full text-sm font-medium"
    :class="badgeClasses"
  >
    <ClockIcon class="w-4 h-4" />
    <span>{{ searchesRemaining }} searches left</span>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { ClockIcon } from '@heroicons/vue/24/outline'
import { useUserStore } from '../../stores/userStore'

const userStore = useUserStore()

const searchesRemaining = computed(() => userStore.searchesRemaining)

const badgeClasses = computed(() => {
  if (searchesRemaining.value <= 0) {
    return 'bg-red-50 text-red-600 border border-red-200'
  } else if (searchesRemaining.value <= 3) {
    return 'bg-amber-50 text-amber-600 border border-amber-200'
  } else {
    return 'bg-emerald-50 text-emerald-600 border border-emerald-200'
  }
})
</script>
