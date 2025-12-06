<template>
  <div class="relative">
    <input
      type="text"
      :value="modelValue"
      @input="$emit('update:modelValue', $event.target.value)"
      @keydown.enter="$emit('search')"
      :placeholder="placeholder"
      :disabled="disabled"
      class="w-full h-12 bg-white border border-slate-200 rounded-lg px-4 text-slate-800 placeholder:text-slate-400 focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all shadow-sm disabled:bg-slate-50 disabled:cursor-not-allowed"
    />
    <!-- Optional search icon -->
    <MagnifyingGlassIcon 
      v-if="showIcon" 
      class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400 pointer-events-none" 
    />
  </div>
</template>

<script setup>
import { MagnifyingGlassIcon } from '@heroicons/vue/24/outline'

defineProps({
  modelValue: {
    type: String,
    default: ''
  },
  placeholder: {
    type: String,
    default: 'Search...'
  },
  disabled: {
    type: Boolean,
    default: false
  },
  showIcon: {
    type: Boolean,
    default: false
  }
})

defineEmits(['update:modelValue', 'search'])
</script>
