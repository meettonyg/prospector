<template>
  <div class="prospector-search-input">
    <!-- Channel Dropdown (integrated) -->
    <div class="prospector-search-input__channel">
      <MicrophoneIcon class="prospector-search-input__channel-icon" />
      <select
        :value="channel"
        @change="$emit('update:channel', $event.target.value)"
        class="prospector-search-input__channel-select"
      >
        <option value="podcasts">Podcasts</option>
      </select>
    </div>
    <div class="prospector-search-input__divider"></div>
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
import { MicrophoneIcon } from '@heroicons/vue/24/outline'

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
  channel: {
    type: String,
    default: 'podcasts'
  }
})

defineEmits(['update:modelValue', 'search', 'update:channel'])
</script>

<style scoped>
.prospector-search-input {
  position: relative;
  display: flex;
  align-items: center;
  width: 100%;
  height: 2.75rem;
  background: white;
  border: 1px solid var(--prospector-slate-200);
  border-radius: var(--prospector-radius-lg);
  transition: all var(--prospector-transition-fast);
}

.prospector-search-input:focus-within {
  border-color: var(--prospector-primary-500);
  box-shadow: 0 0 0 3px var(--prospector-primary-100);
}

/* Channel dropdown section */
.prospector-search-input__channel {
  display: flex;
  align-items: center;
  gap: var(--prospector-space-xs);
  padding: 0 var(--prospector-space-md);
  flex-shrink: 0;
  min-width: 120px;
}

.prospector-search-input__channel-icon {
  width: 1.125rem;
  height: 1.125rem;
  color: var(--prospector-slate-400);
  flex-shrink: 0;
}

.prospector-search-input__channel-select {
  appearance: none;
  border: none;
  background-color: transparent;
  background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2'%3e%3cpath d='M6 9l6 6 6-6'/%3e%3c/svg%3e");
  background-repeat: no-repeat;
  background-position: right 0 center;
  background-size: 1rem;
  padding: 0 1.5rem 0 0.25rem;
  font-family: var(--prospector-font-family);
  font-size: var(--prospector-font-size-sm);
  font-weight: 500;
  color: var(--prospector-slate-700);
  cursor: pointer;
  outline: none;
  min-width: 80px;
}

/* Divider between dropdown and input */
.prospector-search-input__divider {
  width: 1px;
  height: 1.5rem;
  background: var(--prospector-slate-200);
  flex-shrink: 0;
}

.prospector-search-input__field {
  flex: 1;
  height: 100%;
  padding: 0 var(--prospector-space-md);
  font-family: var(--prospector-font-family);
  font-size: var(--prospector-font-size-sm);
  color: var(--prospector-slate-800);
  background: transparent;
  border: none;
  outline: none;
}

.prospector-search-input__field::placeholder {
  color: var(--prospector-slate-400);
}

.prospector-search-input__field:disabled {
  background: var(--prospector-slate-50);
  cursor: not-allowed;
}
</style>
