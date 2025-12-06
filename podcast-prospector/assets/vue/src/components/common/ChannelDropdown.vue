<template>
  <div class="relative" ref="dropdownRef">
    <!-- Dropdown Button -->
    <button
      @click="toggleDropdown"
      class="h-12 px-4 bg-white border border-slate-200 rounded-lg flex items-center gap-2 text-slate-800 hover:border-slate-300 transition-colors min-w-[150px]"
    >
      <component :is="currentChannel.icon" class="w-4 h-4" :class="currentChannel.iconColor" />
      <span class="text-sm font-medium">{{ currentChannel.label }}</span>
      <ChevronDownIcon class="w-4 h-4 text-slate-400 ml-auto" />
    </button>

    <!-- Dropdown Menu -->
    <Transition name="dropdown">
      <div
        v-if="isOpen"
        class="absolute top-full left-0 mt-1 w-48 bg-white border border-slate-200 rounded-lg shadow-lg z-50 py-1 overflow-hidden"
      >
        <button
          v-for="channel in channels"
          :key="channel.value"
          @click="selectChannel(channel)"
          class="w-full px-4 py-2.5 flex items-center gap-3 hover:bg-slate-50 transition-colors text-left"
        >
          <component :is="channel.icon" class="w-4 h-4" :class="channel.iconColor" />
          <span class="text-sm text-slate-800">{{ channel.label }}</span>
        </button>
        
        <div class="border-t border-slate-200 my-1"></div>
        
        <button
          @click="selectChannel(allChannelsOption)"
          class="w-full px-4 py-2.5 flex items-center gap-3 hover:bg-slate-50 transition-colors text-left"
        >
          <GlobeAltIcon class="w-4 h-4 text-slate-500" />
          <span class="text-sm text-slate-800">All Channels</span>
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
  GlobeAltIcon,
  PresentationChartBarIcon
} from '@heroicons/vue/24/outline'
import { CHANNELS } from '../../utils/constants'

// YouTube icon component (Heroicons doesn't have YouTube)
const YouTubeIcon = {
  template: `
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4">
      <path d="M19.615 3.184c-3.604-.246-11.631-.245-15.23 0-3.897.266-4.356 2.62-4.385 8.816.029 6.185.484 8.549 4.385 8.816 3.6.245 11.626.246 15.23 0 3.897-.266 4.356-2.62 4.385-8.816-.029-6.185-.484-8.549-4.385-8.816zm-10.615 12.816v-8l8 3.993-8 4.007z"/>
    </svg>
  `
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
    icon: MicrophoneIcon, 
    iconColor: 'text-primary-500' 
  },
  { 
    value: CHANNELS.YOUTUBE, 
    label: 'YouTube', 
    icon: YouTubeIcon, 
    iconColor: 'text-red-500' 
  },
  { 
    value: CHANNELS.SUMMITS, 
    label: 'Summits', 
    icon: PresentationChartBarIcon, 
    iconColor: 'text-orange-500' 
  }
]

const allChannelsOption = {
  value: 'all',
  label: 'All Channels',
  icon: GlobeAltIcon,
  iconColor: 'text-slate-500'
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

<style scoped>
.dropdown-enter-active,
.dropdown-leave-active {
  transition: all 0.2s ease;
}

.dropdown-enter-from,
.dropdown-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>
