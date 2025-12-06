<template>
  <div class="flex flex-wrap gap-2">
    <button
      v-for="action in actions"
      :key="action.action"
      @click="$emit('action', action.action)"
      class="inline-flex items-center px-3 py-1.5 bg-slate-100 hover:bg-slate-200
             text-slate-700 text-sm rounded-full transition-colors"
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
