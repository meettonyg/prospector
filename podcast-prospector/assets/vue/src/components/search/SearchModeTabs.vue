<template>
  <div class="border-b border-slate-200 px-2 md:px-6 flex gap-1 overflow-x-auto scrollbar-hide">
    <button
      v-for="mode in availableModes"
      :key="mode.value"
      @click="selectMode(mode)"
      :disabled="mode.disabled"
      :class="[
        'py-4 px-4 text-sm font-medium transition-colors flex items-center gap-2 whitespace-nowrap border-b-2 -mb-px',
        modelValue === mode.value
          ? 'border-primary-500 text-primary-500'
          : 'border-transparent text-slate-500 hover:text-slate-800 hover:border-slate-300',
        mode.disabled && 'opacity-50 cursor-not-allowed'
      ]"
    >
      <component :is="mode.icon" class="w-4 h-4" />
      <span>{{ mode.label }}</span>
      <LockClosedIcon
        v-if="mode.premium && !canUseAdvanced"
        class="w-3.5 h-3.5 text-amber-500"
      />
    </button>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { 
  LockClosedIcon,
  UserIcon,
  MicrophoneIcon,
  RadioIcon,
  SignalIcon
} from '@heroicons/vue/24/outline'
import { useUserStore } from '../../stores/userStore'
import { CHANNELS } from '../../utils/constants'

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

// Define modes with icons based on channel
const SEARCH_MODES_CONFIG = {
  [CHANNELS.PODCASTS]: [
    { value: 'byperson', label: 'Episodes by Person', icon: UserIcon, premium: false },
    { value: 'bytitle', label: 'Podcasts by Title', icon: MicrophoneIcon, premium: false },
    { value: 'byadvancedpodcast', label: 'Adv. Podcasts', icon: RadioIcon, premium: true },
    { value: 'byadvancedepisode', label: 'Adv. Episodes', icon: SignalIcon, premium: true }
  ],
  [CHANNELS.YOUTUBE]: [
    { value: 'byyoutube', label: 'YouTube Channels', icon: RadioIcon, premium: false }
  ],
  [CHANNELS.SUMMITS]: [
    { value: 'bysummit', label: 'Virtual Summits', icon: SignalIcon, premium: false }
  ]
}

const availableModes = computed(() => {
  const modes = SEARCH_MODES_CONFIG[props.channel] || SEARCH_MODES_CONFIG[CHANNELS.PODCASTS]
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

<style scoped>
/* Hide scrollbar but allow scrolling */
.scrollbar-hide::-webkit-scrollbar {
  display: none;
}
.scrollbar-hide {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>
