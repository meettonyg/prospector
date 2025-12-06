<template>
  <input
    type="text"
    :value="modelValue"
    @input="$emit('update:modelValue', $event.target.value)"
    @keydown.enter="$emit('search')"
    :placeholder="placeholder"
    :disabled="disabled"
    class="w-full h-11 px-4 bg-white border border-slate-200 rounded-lg text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none focus:border-[#0ea5e9] focus:ring-2 focus:ring-[#e0f2fe] disabled:bg-slate-50 disabled:cursor-not-allowed transition-colors"
  />
</template>

<script setup>
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
  }
})

defineEmits(['update:modelValue', 'search'])
</script>
