<template>
  <div class="prospector-root font-sans">
    <!-- Centered Container with Side Margins -->
    <div class="max-w-[1600px] mx-auto px-6 lg:px-8 py-6">
      <!-- Guest Intel Check -->
      <div v-if="!guestIntelActive" class="p-6 bg-amber-50 border border-amber-200 rounded-xl mb-6">
        <div class="flex items-start gap-3">
          <ExclamationTriangleIcon class="w-6 h-6 text-amber-500 flex-shrink-0" />
          <div>
            <h3 class="font-semibold text-amber-800">Guest Intel Required</h3>
            <p class="text-sm text-amber-700 mt-1">
              The Guest Intel plugin is required for importing podcasts to your pipeline.
              Search functionality is available, but imports are disabled.
            </p>
          </div>
        </div>
      </div>

      <!-- Main Content -->
      <div class="prospector-main">
        <!-- Mode Toggle (Future: Chat/Traditional) -->
        <div v-if="features.chat" class="flex justify-end mb-4">
          <div class="inline-flex rounded-lg border border-slate-200 p-1 bg-white">
            <button
              @click="mode = 'search'"
              :class="[
                'px-4 py-2 text-sm font-medium rounded-md transition-colors',
                mode === 'search'
                  ? 'bg-primary-500 text-white'
                  : 'text-slate-600 hover:text-slate-800'
              ]"
            >
              Search
            </button>
            <button
              @click="mode = 'chat'"
              :class="[
                'px-4 py-2 text-sm font-medium rounded-md transition-colors',
                mode === 'chat'
                  ? 'bg-primary-500 text-white'
                  : 'text-slate-600 hover:text-slate-800'
              ]"
            >
              Chat
            </button>
          </div>
        </div>

        <!-- Traditional Search (Default) -->
        <TraditionalSearch v-if="mode === 'search'" />

        <!-- Chat Interface (Feature Flagged) -->
        <ChatInterface v-else-if="mode === 'chat' && features.chat" />
      </div>
    </div>

    <!-- Toast Notifications -->
    <ToastContainer />
  </div>
</template>

<script setup>
import { ref, inject, computed } from 'vue'
import { ExclamationTriangleIcon } from '@heroicons/vue/24/outline'
import TraditionalSearch from './components/search/TraditionalSearch.vue'
import ChatInterface from './components/chat/ChatInterface.vue'
import ToastContainer from './components/common/ToastContainer.vue'

const config = inject('config', {})

const mode = ref('search')
const guestIntelActive = computed(() => config.guestIntelActive !== false)
const features = computed(() => config.features || {})
</script>

<style scoped>
/* Component-specific styles */
</style>
