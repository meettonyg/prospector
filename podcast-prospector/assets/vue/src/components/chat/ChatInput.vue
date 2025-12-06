<template>
  <div class="border-t border-slate-200 p-4 bg-white">
    <div class="flex items-end gap-2">
      <div class="flex-1 relative">
        <textarea
          ref="textareaRef"
          :value="modelValue"
          @input="handleInput"
          @keydown="handleKeydown"
          :placeholder="placeholder"
          :disabled="disabled"
          rows="1"
          class="w-full px-4 py-3 border border-slate-200 rounded-xl resize-none
                 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent
                 placeholder:text-slate-400 disabled:bg-slate-50 disabled:cursor-not-allowed"
          :style="{ height: textareaHeight }"
        />
      </div>

      <button
        @click="handleSend"
        :disabled="disabled || !modelValue.trim()"
        class="flex-shrink-0 p-3 bg-primary-500 text-white rounded-xl
               hover:bg-primary-600 disabled:bg-slate-300 disabled:cursor-not-allowed
               transition-colors"
        aria-label="Send message"
      >
        <PaperAirplaneIcon class="w-5 h-5" />
      </button>
    </div>

    <p v-if="!disabled" class="mt-2 text-xs text-slate-400 text-center">
      Press Enter to send, Shift+Enter for new line
    </p>
  </div>
</template>

<script setup>
import { ref, watch, nextTick } from 'vue'
import { PaperAirplaneIcon } from '@heroicons/vue/24/solid'

const props = defineProps({
  modelValue: {
    type: String,
    default: ''
  },
  placeholder: {
    type: String,
    default: 'Type a message...'
  },
  disabled: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update:modelValue', 'send'])

const textareaRef = ref(null)
const textareaHeight = ref('auto')

const handleInput = (event) => {
  emit('update:modelValue', event.target.value)
  adjustHeight()
}

const handleKeydown = (event) => {
  if (event.key === 'Enter' && !event.shiftKey) {
    event.preventDefault()
    handleSend()
  }
}

const handleSend = () => {
  if (props.modelValue.trim() && !props.disabled) {
    emit('send')
    // Reset height after sending
    nextTick(() => {
      textareaHeight.value = 'auto'
    })
  }
}

const adjustHeight = async () => {
  await nextTick()
  if (textareaRef.value) {
    textareaRef.value.style.height = 'auto'
    const newHeight = Math.min(textareaRef.value.scrollHeight, 120)
    textareaHeight.value = `${newHeight}px`
  }
}

// Watch for external value changes
watch(() => props.modelValue, () => {
  adjustHeight()
})

// Expose focus method
defineExpose({
  focus: () => textareaRef.value?.focus()
})
</script>
