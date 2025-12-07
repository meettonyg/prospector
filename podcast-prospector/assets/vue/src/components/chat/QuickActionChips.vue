<template>
  <div class="prospector-quick-actions">
    <button
      v-for="action in actions"
      :key="action.action"
      @click="$emit('action', action.action)"
      class="prospector-quick-actions__chip"
    >
      {{ action.label }}
    </button>
  </div>
</template>

<script setup>
defineProps({
  actions: {
    type: Array,
    required: true,
    validator: (value) => {
      return value.every(action =>
        typeof action.label === 'string' &&
        typeof action.action === 'string'
      )
    }
  }
})

defineEmits(['action'])
</script>

<style scoped>
.prospector-quick-actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--prospector-space-sm);
}

.prospector-quick-actions__chip {
  display: inline-flex;
  align-items: center;
  padding: var(--prospector-space-sm) var(--prospector-space-md);
  font-size: var(--prospector-font-size-sm);
  color: var(--prospector-slate-700);
  background: var(--prospector-slate-100);
  border: none;
  border-radius: var(--prospector-radius-full);
  cursor: pointer;
  transition: all var(--prospector-transition-fast);
}

.prospector-quick-actions__chip:hover {
  background: var(--prospector-slate-200);
  color: var(--prospector-slate-800);
}
</style>
