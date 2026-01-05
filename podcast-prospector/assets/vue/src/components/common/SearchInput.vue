<template>
  <div class="prospector-search-input">
    <!-- Channel Dropdown -->
    <div class="prospector-search-input__channel-wrapper">
      <MicrophoneIcon class="prospector-search-input__channel-icon" />
      <select
        :value="channel"
        @change="$emit('update:channel', $event.target.value)"
        class="prospector-search-input__channel-select"
      >
        <option value="podcasts">Podcasts</option>
        <option value="youtube">YouTube</option>
        <option value="summit">Virtual Summits</option>
      </select>
    </div>
    <!-- Text Input -->
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
  display: flex;
  align-items: center;
  width: 100%;
}

/* Channel dropdown wrapper */
.prospector-search-input__channel-wrapper {
  position: relative;
  display: flex;
  align-items: center;
  flex-shrink: 0;
}

.prospector-search-input__channel-icon {
  position: absolute;
  left: 0.75rem;
  width: 1.125rem;
  height: 1.125rem;
  color: var(--prospector-slate-400);
  pointer-events: none;
  z-index: 1;
}

.prospector-search-input__channel-select {
  height: 2.75rem;
  padding-left: 2.25rem;
  padding-right: 2rem;
  background-color: var(--prospector-slate-50);
  border: 1px solid var(--prospector-slate-200);
  border-right: 0;
  border-radius: var(--prospector-radius-md) 0 0 var(--prospector-radius-md);
  font-family: var(--prospector-font-family);
  font-size: var(--prospector-font-size-sm);
  font-weight: 500;
  color: var(--prospector-slate-800);
  cursor: pointer;
  appearance: none;
  background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2'%3e%3cpath d='M6 9l6 6 6-6'/%3e%3c/svg%3e");
  background-repeat: no-repeat;
  background-position: right 0.5rem center;
  background-size: 1rem;
  transition: background-color var(--prospector-transition-fast);
}

.prospector-search-input__channel-select:hover {
  background-color: var(--prospector-slate-100);
}

.prospector-search-input__channel-select:focus {
  outline: none;
  box-shadow: 0 0 0 1px var(--prospector-primary-500);
}

/* Text input field */
.prospector-search-input__field {
  flex: 1;
  height: 2.75rem;
  padding: 0 1rem;
  font-family: var(--prospector-font-family);
  font-size: var(--prospector-font-size-sm);
  color: var(--prospector-slate-800);
  background: white;
  border: 1px solid var(--prospector-slate-200);
  border-radius: 0 var(--prospector-radius-md) var(--prospector-radius-md) 0;
  outline: none;
  transition: all var(--prospector-transition-fast);
}

.prospector-search-input__field::placeholder {
  color: var(--prospector-slate-400);
}

.prospector-search-input__field:focus {
  border-color: var(--prospector-primary-500);
  box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
}

.prospector-search-input__field:disabled {
  background: var(--prospector-slate-50);
  cursor: not-allowed;
}
</style>
