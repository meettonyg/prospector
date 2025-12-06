<template>
  <div 
    class="flex items-center gap-1.5 text-sm font-medium"
    :class="textClasses"
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

const textClasses = computed(() => {
  if (searchesRemaining.value <= 0) {
    return 'text-red-500'
  } else if (searchesRemaining.value <= 10) {
    return 'text-amber-500'
  } else {
    return 'text-emerald-500'
  }
})
</script>
