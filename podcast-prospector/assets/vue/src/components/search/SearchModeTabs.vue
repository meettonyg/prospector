<template>
  <div class="prospector-tabs">
    <button
      v-for="mode in availableModes"
      :key="mode.value"
      type="button"
      @click="selectMode(mode)"
      :disabled="mode.disabled"
      :class="[
        'prospector-tabs__item',
        modelValue === mode.value && 'prospector-tabs__item--active',
        mode.disabled && 'prospector-tabs__item--disabled'
      ]"
    >
      <component :is="mode.icon" class="prospector-tabs__item-icon" />
      <span class="prospector-tabs__item-text">{{ mode.label }}</span>
      <LockClosedIcon
        v-if="mode.premium && !canUseAdvanced"
        class="prospector-tabs__item-lock"
      />
    </button>
  </div>
</template>

<script setup>
import { computed, markRaw } from 'vue'
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
    { value: 'byperson', label: 'Episodes by Person', icon: markRaw(UserIcon), premium: false },
    { value: 'bytitle', label: 'Podcasts by Title', icon: markRaw(MicrophoneIcon), premium: false },
    { value: 'byadvancedpodcast', label: 'Adv. Podcasts', icon: markRaw(RadioIcon), premium: true },
    { value: 'byadvancedepisode', label: 'Adv. Episodes', icon: markRaw(SignalIcon), premium: true }
  ],
  [CHANNELS.YOUTUBE]: [
    { value: 'byyoutube', label: 'YouTube Channels', icon: markRaw(RadioIcon), premium: false }
  ],
  [CHANNELS.SUMMITS]: [
    { value: 'bysummit', label: 'Virtual Summits', icon: markRaw(SignalIcon), premium: false }
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
.prospector-tabs {
  display: flex;
  gap: 0;
  padding: 0 1.5rem;
  border-bottom: 1px solid var(--prospector-slate-200);
  background: white;
  overflow-x: auto;
  scrollbar-width: none;
  -ms-overflow-style: none;
}

.prospector-tabs::-webkit-scrollbar {
  display: none;
}

.prospector-tabs__item {
  position: relative;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 1rem 1.25rem;
  font-family: var(--prospector-font-family);
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--prospector-slate-500);
  background: transparent;
  border: none;
  border-bottom: 2px solid transparent;
  margin-bottom: -1px;
  white-space: nowrap;
  cursor: pointer;
  transition: all var(--prospector-transition-fast);
}

.prospector-tabs__item:hover:not(:disabled) {
  color: var(--prospector-slate-700);
}

.prospector-tabs__item--active {
  color: var(--prospector-primary-500);
  border-bottom-color: var(--prospector-primary-500);
}

.prospector-tabs__item--disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.prospector-tabs__item-icon {
  width: 1rem;
  height: 1rem;
}

.prospector-tabs__item-text {
  /* Text inherits from parent */
}

.prospector-tabs__item-lock {
  width: 0.75rem;
  height: 0.75rem;
  color: var(--prospector-warning-500);
  margin-left: 0.125rem;
}
</style>
