<template>
  <div class="prospector-search-input">
    <MagnifyingGlassIcon class="prospector-search-input__icon" />
    <input
      type="text"
      :value="modelValue"
      @input="$emit('update:modelValue', $event.target.value)"
      @keydown.enter="$emit('search')"
      :placeholder="placeholder"
      :disabled="disabled"
      class="prospector-search-input__field"
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
  }
})

defineEmits(['update:modelValue', 'search'])
</script>
