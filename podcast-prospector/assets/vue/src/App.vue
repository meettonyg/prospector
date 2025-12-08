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
        <!-- Traditional Search (Default) -->
        <TraditionalSearch
          v-if="mode === 'search'"
          v-model:mode="mode"
        />

        <!-- Chat Interface (Feature Flagged) -->
        <ChatInterface
          v-else-if="mode === 'chat' && features.chat"
          v-model:mode="mode"
        />
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
