<template>
  <div class="relative">
    <div class="relative">
      <MagnifyingGlassIcon
        class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400"
      />
      <input
        ref="inputRef"
        type="text"
        :value="modelValue"
        @input="$emit('update:modelValue', $event.target.value)"
        @keydown.enter="$emit('search')"
        :placeholder="placeholder"
        :disabled="disabled"
        class="w-full pl-11 pr-24 py-3 border border-slate-200 rounded-xl
               focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent
               placeholder:text-slate-400 bg-white disabled:bg-slate-50 disabled:cursor-not-allowed"
      />
      <div class="absolute right-2 top-1/2 -translate-y-1/2 flex items-center gap-2">
        <!-- Clear button -->
        <button
          v-if="modelValue"
          @click="$emit('update:modelValue', ''); $emit('clear')"
          class="p-1.5 text-slate-400 hover:text-slate-600 transition-colors"
          title="Clear search"
        >
          <XMarkIcon class="w-5 h-5" />
        </button>

        <!-- Search button -->
        <button
          @click="$emit('search')"
          :disabled="disabled || !modelValue.trim()"
          class="px-4 py-1.5 bg-primary-500 text-white text-sm font-medium rounded-lg
                 hover:bg-primary-600 disabled:bg-slate-300 disabled:cursor-not-allowed
                 transition-colors"
        >
          Search
        </button>
      </div>
    </div>

    <!-- Keyboard hint -->
    <p v-if="showHint && modelValue" class="mt-1.5 text-xs text-slate-400">
      Press <kbd class="px-1.5 py-0.5 bg-slate-100 rounded text-slate-600 font-mono">Enter</kbd> to search
    </p>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { MagnifyingGlassIcon, XMarkIcon } from '@heroicons/vue/24/outline'

defineProps({
  modelValue: {
    type: String,
    default: ''
  },
  placeholder: {
    type: String,
    default: 'Search for podcasts...'
  },
  disabled: {
    type: Boolean,
    default: false
  },
  showHint: {
    type: Boolean,
    default: true
  }
})

defineEmits(['update:modelValue', 'search', 'clear'])

const inputRef = ref(null)

// Expose focus method
defineExpose({
  focus: () => inputRef.value?.focus()
})
</script>
