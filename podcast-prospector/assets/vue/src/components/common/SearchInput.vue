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

<style scoped>
.prospector-search-input {
  position: relative;
  display: flex;
  align-items: center;
  width: 100%;
}

.prospector-search-input__icon {
  position: absolute;
  left: 1rem;
  width: 1.25rem;
  height: 1.25rem;
  color: var(--prospector-slate-400);
  pointer-events: none;
}

.prospector-search-input__field {
  width: 100%;
  height: 2.75rem;
  padding: 0 var(--prospector-space-md) 0 3rem;
  font-family: var(--prospector-font-family);
  font-size: var(--prospector-font-size-sm);
  color: var(--prospector-slate-800);
  background: white;
  border: 1px solid var(--prospector-slate-200);
  border-radius: var(--prospector-radius-lg);
  outline: none;
  transition: all var(--prospector-transition-fast);
}

.prospector-search-input__field::placeholder {
  color: var(--prospector-slate-400);
}

.prospector-search-input__field:focus {
  border-color: var(--prospector-primary-500);
  box-shadow: 0 0 0 3px var(--prospector-primary-100);
}

.prospector-search-input__field:disabled {
  background: var(--prospector-slate-50);
  cursor: not-allowed;
}
</style>
