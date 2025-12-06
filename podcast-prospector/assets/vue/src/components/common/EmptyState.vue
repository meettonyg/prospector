<template>
  <div class="text-center py-12 px-4">
    <div
      v-if="icon"
      class="mx-auto w-16 h-16 rounded-full bg-slate-100 flex items-center justify-center mb-4"
    >
      <component :is="icon" class="w-8 h-8 text-slate-400" />
    </div>

    <h3 class="text-lg font-medium text-slate-800 mb-2">{{ title }}</h3>

    <p v-if="description" class="text-slate-500 max-w-md mx-auto mb-6">
      {{ description }}
    </p>

    <div v-if="$slots.default" class="mt-4">
      <slot />
    </div>

    <button
      v-if="actionLabel"
      @click="$emit('action')"
      class="btn-primary"
    >
      {{ actionLabel }}
    </button>
  </div>
</template>

<script setup>
defineProps({
  icon: {
    type: [Object, Function],
    default: null
  },
  title: {
    type: String,
    required: true
  },
  description: {
    type: String,
    default: ''
  },
  actionLabel: {
    type: String,
    default: ''
  }
})

defineEmits(['action'])
</script>
