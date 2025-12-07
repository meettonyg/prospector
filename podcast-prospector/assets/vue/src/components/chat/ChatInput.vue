<template>
  <div class="prospector-chat-input">
    <div class="prospector-chat-input__wrapper">
      <textarea
        ref="textareaRef"
        :value="modelValue"
        @input="handleInput"
        @keydown="handleKeydown"
        :placeholder="placeholder"
        :disabled="disabled"
        rows="1"
        class="prospector-chat-input__field"
        :style="{ height: textareaHeight }"
      />
    </div>

    <button
      @click="handleSend"
      :disabled="disabled || !modelValue.trim()"
      class="prospector-chat-input__send"
      aria-label="Send message"
    >
      <PaperAirplaneIcon class="prospector-chat-input__send-icon" />
    </button>
  </div>

  <p v-if="!disabled" class="prospector-chat-input__hint">
    Press Enter to send, Shift+Enter for new line
  </p>
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

<style scoped>
.prospector-chat-input {
  display: flex;
  align-items: flex-end;
  gap: var(--prospector-space-sm);
  padding: var(--prospector-space-md);
  border-top: 1px solid var(--prospector-slate-200);
  background: white;
}

.prospector-chat-input__wrapper {
  flex: 1;
  position: relative;
}

.prospector-chat-input__field {
  width: 100%;
  padding: var(--prospector-space-md);
  font-family: var(--prospector-font-family);
  font-size: var(--prospector-font-size-sm);
  color: var(--prospector-slate-800);
  background: white;
  border: 1px solid var(--prospector-slate-200);
  border-radius: var(--prospector-radius-xl);
  outline: none;
  resize: none;
  transition: all var(--prospector-transition-fast);
}

.prospector-chat-input__field::placeholder {
  color: var(--prospector-slate-400);
}

.prospector-chat-input__field:focus {
  border-color: var(--prospector-primary-500);
  box-shadow: 0 0 0 2px var(--prospector-primary-100);
}

.prospector-chat-input__field:disabled {
  background: var(--prospector-slate-50);
  cursor: not-allowed;
}

.prospector-chat-input__send {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2.75rem;
  height: 2.75rem;
  color: white;
  background: var(--prospector-primary-500);
  border: none;
  border-radius: var(--prospector-radius-xl);
  cursor: pointer;
  transition: background var(--prospector-transition-fast);
}

.prospector-chat-input__send:hover:not(:disabled) {
  background: var(--prospector-primary-600);
}

.prospector-chat-input__send:disabled {
  background: var(--prospector-slate-300);
  cursor: not-allowed;
}

.prospector-chat-input__send-icon {
  width: 1.25rem;
  height: 1.25rem;
}

.prospector-chat-input__hint {
  margin: var(--prospector-space-sm) 0 0;
  padding: 0 var(--prospector-space-md);
  font-size: var(--prospector-font-size-xs);
  color: var(--prospector-slate-400);
  text-align: center;
}
</style>
