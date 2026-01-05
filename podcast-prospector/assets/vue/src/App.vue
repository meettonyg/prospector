<template>
  <div class="prospector-root">
    <!-- Centered Container with Side Margins -->
    <div class="prospector-container">
      <!-- Guest Intel Check -->
      <div v-if="!guestIntelActive" class="prospector-alert prospector-alert--warning">
        <div class="prospector-alert__content">
          <ExclamationTriangleIcon class="prospector-alert__icon" />
          <div class="prospector-alert__text">
            <h3 class="prospector-alert__title">Guest Intel Required</h3>
            <p class="prospector-alert__description">
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
