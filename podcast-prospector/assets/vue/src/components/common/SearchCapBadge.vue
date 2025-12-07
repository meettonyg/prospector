<template>
  <div 
    :class="[
      'prospector-search-cap',
      colorClass
    ]"
  >
    <ClockIcon class="prospector-search-cap__icon" />
    <span class="prospector-search-cap__text">{{ searchesRemaining }} searches left</span>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { ClockIcon } from '@heroicons/vue/24/outline'
import { useUserStore } from '../../stores/userStore'

const userStore = useUserStore()

const searchesRemaining = computed(() => userStore.searchesRemaining)

const colorClass = computed(() => {
  if (searchesRemaining.value <= 0) {
    return 'prospector-search-cap--danger'
  } else if (searchesRemaining.value <= 10) {
    return 'prospector-search-cap--warning'
  } else {
    return 'prospector-search-cap--success'
  }
})
</script>

<style scoped>
.prospector-search-cap {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  font-size: var(--prospector-font-size-sm);
  font-weight: 500;
}

.prospector-search-cap--success {
  color: var(--prospector-success-500);
}

.prospector-search-cap--warning {
  color: var(--prospector-warning-500);
}

.prospector-search-cap--danger {
  color: var(--prospector-error-500);
}

.prospector-search-cap__icon {
  width: 1rem;
  height: 1rem;
}

.prospector-search-cap__text {
  white-space: nowrap;
}
</style>
