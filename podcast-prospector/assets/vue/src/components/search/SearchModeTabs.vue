<template>
  <div class="flex flex-wrap gap-1 p-1 bg-slate-100 rounded-lg">
    <button
      v-for="mode in availableModes"
      :key="mode.value"
      @click="selectMode(mode)"
      :disabled="mode.disabled"
      :class="[
        'flex items-center gap-1.5 px-3 py-2 rounded-md text-sm font-medium transition-colors',
        modelValue === mode.value
          ? 'bg-white text-slate-800 shadow-sm'
          : 'text-slate-600 hover:text-slate-800 hover:bg-white/50',
        mode.disabled && 'opacity-50 cursor-not-allowed'
      ]"
    >
      <span>{{ mode.label }}</span>
      <LockClosedIcon
        v-if="mode.premium && !canUseAdvanced"
        class="w-3.5 h-3.5 text-slate-400"
      />
    </button>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { LockClosedIcon } from '@heroicons/vue/24/outline'
import { useUserStore } from '../../stores/userStore'
import { SEARCH_MODES, CHANNELS } from '../../utils/constants'

const props = defineProps({
  modelValue: {
    type: String,
    required: true
  },
  channel: {
    type: String,
    default: CHANNELS.PODCASTS
  }
})

const emit = defineEmits(['update:modelValue'])

const userStore = useUserStore()
const canUseAdvanced = computed(() => userStore.canUseAdvancedFilters)

const availableModes = computed(() => {
  const modes = SEARCH_MODES[props.channel] || SEARCH_MODES[CHANNELS.PODCASTS]
  return modes.map(mode => ({
    ...mode,
    disabled: mode.premium && !canUseAdvanced.value
  }))
})

const selectMode = (mode) => {
  if (!mode.disabled) {
    emit('update:modelValue', mode.value)
  }
}
</script>
