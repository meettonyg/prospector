<template>
  <div class="relative" ref="dropdownRef">
    <button
      @click="isOpen = !isOpen"
      :disabled="disabled"
      class="flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-200 rounded-lg
             hover:bg-slate-50 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
    >
      <component :is="currentChannelIcon" class="w-5 h-5 text-slate-600" />
      <span class="font-medium text-slate-700">{{ currentChannelLabel }}</span>
      <ChevronDownIcon
        class="w-4 h-4 text-slate-400 transition-transform"
        :class="{ 'rotate-180': isOpen }"
      />
    </button>

    <!-- Dropdown menu -->
    <Transition name="fade">
      <div
        v-if="isOpen"
        class="absolute top-full left-0 mt-1 w-48 bg-white border border-slate-200
               rounded-xl shadow-lg py-1 z-20"
      >
        <button
          v-for="channel in availableChannels"
          :key="channel.value"
          @click="selectChannel(channel.value)"
          :disabled="channel.disabled"
          :class="[
            'w-full flex items-center gap-3 px-4 py-2.5 text-left transition-colors',
            modelValue === channel.value
              ? 'bg-primary-50 text-primary-700'
              : 'hover:bg-slate-50 text-slate-700',
            channel.disabled && 'opacity-50 cursor-not-allowed'
          ]"
        >
          <component :is="channelIcons[channel.value]" class="w-5 h-5" />
          <span class="font-medium">{{ channel.label }}</span>
          <LockClosedIcon
            v-if="channel.disabled"
            class="w-4 h-4 ml-auto text-slate-400"
          />
        </button>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import {
  ChevronDownIcon,
  MicrophoneIcon,
  PlayIcon,
  UsersIcon,
  LockClosedIcon
} from '@heroicons/vue/24/outline'
import { useUserStore } from '../../stores/userStore'
import { CHANNELS } from '../../utils/constants'

const props = defineProps({
  modelValue: {
    type: String,
    default: CHANNELS.PODCASTS
  },
  disabled: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update:modelValue'])

const userStore = useUserStore()
const isOpen = ref(false)
const dropdownRef = ref(null)

const channelIcons = {
  [CHANNELS.PODCASTS]: MicrophoneIcon,
  [CHANNELS.YOUTUBE]: PlayIcon,
  [CHANNELS.SUMMITS]: UsersIcon
}

const availableChannels = computed(() => [
  {
    value: CHANNELS.PODCASTS,
    label: 'Podcasts',
    disabled: false
  },
  {
    value: CHANNELS.YOUTUBE,
    label: 'YouTube',
    disabled: !userStore.youtubeEnabled
  },
  {
    value: CHANNELS.SUMMITS,
    label: 'Summits',
    disabled: !userStore.summitsEnabled
  }
])

const currentChannelLabel = computed(() => {
  const channel = availableChannels.value.find(c => c.value === props.modelValue)
  return channel?.label || 'Podcasts'
})

const currentChannelIcon = computed(() => {
  return channelIcons[props.modelValue] || MicrophoneIcon
})

const selectChannel = (value) => {
  const channel = availableChannels.value.find(c => c.value === value)
  if (channel && !channel.disabled) {
    emit('update:modelValue', value)
    isOpen.value = false
  }
}

// Close dropdown when clicking outside
const handleClickOutside = (event) => {
  if (dropdownRef.value && !dropdownRef.value.contains(event.target)) {
    isOpen.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: all 0.15s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>
