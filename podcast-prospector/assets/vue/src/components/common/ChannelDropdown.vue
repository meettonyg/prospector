<template>
  <div class="prospector-select" ref="dropdownRef">
    <!-- Dropdown Button -->
    <button
      @click="toggleDropdown"
      class="prospector-select__trigger"
    >
      <component :is="currentChannel.icon" class="prospector-select__trigger-icon" :class="currentChannel.iconColor" />
      <span>{{ currentChannel.label }}</span>
      <ChevronDownIcon class="prospector-select__trigger-arrow" />
    </button>

    <!-- Dropdown Menu -->
    <Transition name="prospector-dropdown">
      <div
        v-if="isOpen"
        class="prospector-select__menu"
      >
        <button
          v-for="channel in channels"
          :key="channel.value"
          @click="selectChannel(channel)"
          class="prospector-select__option"
        >
          <component :is="channel.icon" class="prospector-select__option-icon" :class="channel.iconColor" />
          <span>{{ channel.label }}</span>
        </button>
        
        <div class="prospector-select__divider"></div>
        
        <button
          @click="selectChannel(allChannelsOption)"
          class="prospector-select__option"
        >
          <GlobeAltIcon class="prospector-select__option-icon text-slate-400" />
          <span>All Channels</span>
        </button>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, markRaw, h } from 'vue'
import {
  ChevronDownIcon,
  MicrophoneIcon,
  GlobeAltIcon,
  PresentationChartBarIcon
} from '@heroicons/vue/24/outline'
import { CHANNELS } from '../../utils/constants'

// YouTube icon component (Heroicons doesn't have YouTube)
const YouTubeIcon = {
  render() {
    return h('svg', {
      xmlns: 'http://www.w3.org/2000/svg',
      viewBox: '0 0 24 24',
      fill: 'currentColor',
      class: 'w-4 h-4'
    }, [
      h('path', {
        d: 'M19.615 3.184c-3.604-.246-11.631-.245-15.23 0-3.897.266-4.356 2.62-4.385 8.816.029 6.185.484 8.549 4.385 8.816 3.6.245 11.626.246 15.23 0 3.897-.266 4.356-2.62 4.385-8.816-.029-6.185-.484-8.549-4.385-8.816zm-10.615 12.816v-8l8 3.993-8 4.007z'
      })
    ])
  }
}

const props = defineProps({
  modelValue: {
    type: String,
    default: CHANNELS.PODCASTS
  }
})

const emit = defineEmits(['update:modelValue'])

const dropdownRef = ref(null)
const isOpen = ref(false)

const channels = [
  { 
    value: CHANNELS.PODCASTS, 
    label: 'Podcasts', 
    icon: markRaw(MicrophoneIcon), 
    iconColor: 'text-[#0ea5e9]' 
  },
  { 
    value: CHANNELS.YOUTUBE, 
    label: 'YouTube', 
    icon: markRaw(YouTubeIcon), 
    iconColor: 'text-red-500' 
  },
  { 
    value: CHANNELS.SUMMITS, 
    label: 'Summits', 
    icon: markRaw(PresentationChartBarIcon), 
    iconColor: 'text-orange-500' 
  }
]

const allChannelsOption = {
  value: 'all',
  label: 'All Channels',
  icon: markRaw(GlobeAltIcon),
  iconColor: 'text-slate-400'
}

const currentChannel = computed(() => {
  if (props.modelValue === 'all') return allChannelsOption
  return channels.find(c => c.value === props.modelValue) || channels[0]
})

const toggleDropdown = () => {
  isOpen.value = !isOpen.value
}

const selectChannel = (channel) => {
  emit('update:modelValue', channel.value)
  isOpen.value = false
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
