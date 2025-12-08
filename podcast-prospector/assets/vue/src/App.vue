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
        <div v-if="features.chat" class="flex items-center justify-between mb-4 gap-4">
          <div class="flex items-center gap-3">
            <div class="flex items-center rounded-2xl border border-slate-200 bg-white p-1 shadow-sm">
              <button
                @click="mode = 'search'"
                :class="[
                  'flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-xl transition-all',
                  mode === 'search'
                    ? 'bg-slate-100 text-slate-900 shadow-sm'
                    : 'text-slate-600 hover:text-slate-900'
                ]"
              >
                <MagnifyingGlassIcon class="w-5 h-5" />
                Search
              </button>
              <button
                @click="mode = 'chat'"
                :class="[
                  'flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-xl transition-all',
                  mode === 'chat'
                    ? 'bg-slate-100 text-slate-900 shadow-sm'
                    : 'text-slate-600 hover:text-slate-900'
                ]"
              >
                <ChatBubbleLeftRightIcon class="w-5 h-5" />
                Chat
              </button>
            </div>
          </div>

          <button
            type="button"
            class="text-primary-600 text-sm font-medium border-b border-transparent hover:border-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-200 rounded"
          >
            Saved Searches
          </button>
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
import { ExclamationTriangleIcon, ChatBubbleLeftRightIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline'
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
