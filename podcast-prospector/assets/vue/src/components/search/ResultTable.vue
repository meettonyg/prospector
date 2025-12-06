<template>
  <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
    <table class="w-full">
      <thead class="bg-slate-50 border-b border-slate-200">
        <tr>
          <th class="w-12 px-4 py-3">
            <span class="sr-only">Select</span>
          </th>
          <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
            Podcast
          </th>
          <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
            Episodes
          </th>
          <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
            Status
          </th>
          <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">
            Actions
          </th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <tr
          v-for="(result, index) in results"
          :key="result.id || index"
          :class="[
            'hover:bg-slate-50 transition-colors',
            result._selected && 'bg-primary-50'
          ]"
        >
          <!-- Checkbox -->
          <td class="px-4 py-3">
            <input
              type="checkbox"
              :checked="result._selected"
              @change="$emit('toggle-select', index)"
              :disabled="hydrationMap[index]?.tracked"
              class="w-4 h-4 rounded border-slate-300 text-primary-500 focus:ring-primary-500"
            />
          </td>

          <!-- Podcast info -->
          <td class="px-4 py-3">
            <div class="flex items-center gap-3">
              <img
                v-if="getArtwork(result)"
                :src="getArtwork(result)"
                :alt="getTitle(result)"
                class="w-10 h-10 rounded-lg object-cover bg-slate-100"
                loading="lazy"
              />
              <div
                v-else
                class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center"
              >
                <MicrophoneIcon class="w-5 h-5 text-slate-400" />
              </div>
              <div class="min-w-0">
                <p class="font-medium text-slate-800 truncate">{{ getTitle(result) }}</p>
                <p class="text-sm text-slate-500 truncate">{{ getAuthor(result) }}</p>
              </div>
            </div>
          </td>

          <!-- Episode count -->
          <td class="px-4 py-3 text-sm text-slate-600">
            {{ getEpisodeCount(result) || '—' }}
          </td>

          <!-- Status -->
          <td class="px-4 py-3">
            <span
              v-if="hydrationMap[index]?.hasOpportunity"
              class="badge-success"
            >
              In Pipeline
            </span>
            <span
              v-else-if="hydrationMap[index]?.tracked"
              class="badge-neutral"
            >
              Tracked
            </span>
            <span v-else class="badge-primary">
              New
            </span>
          </td>

          <!-- Actions -->
          <td class="px-4 py-3 text-right">
            <div class="flex items-center justify-end gap-2">
              <a
                v-if="getWebsiteUrl(result)"
                :href="getWebsiteUrl(result)"
                target="_blank"
                rel="noopener noreferrer"
                class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors"
                title="Visit website"
              >
                <GlobeAltIcon class="w-4 h-4" />
              </a>

              <ImportButton
                :is-tracked="hydrationMap[index]?.tracked"
                :has-opportunity="hydrationMap[index]?.hasOpportunity"
                :crm-url="hydrationMap[index]?.crm_url"
                :importing="importingIndices.includes(index)"
                :disabled="!canImport"
                @import="$emit('import', { result, index })"
              />
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
import { computed, inject } from 'vue'
import { MicrophoneIcon, GlobeAltIcon } from '@heroicons/vue/24/outline'
import ImportButton from '../common/ImportButton.vue'

defineProps({
  results: {
    type: Array,
    required: true
  },
  hydrationMap: {
    type: Object,
    default: () => ({})
  },
  importingIndices: {
    type: Array,
    default: () => []
  },
  searchMode: {
    type: String,
    default: 'byperson'
  }
})

defineEmits(['result-click', 'toggle-select', 'import'])

const config = inject('config', {})
const canImport = computed(() => config.guestIntelActive !== false)

// Helper functions
const getTitle = (result) => result.title || result.name || result.feedTitle || 'Untitled'
const getAuthor = (result) => result.author || result.publisher || result.ownerName || ''
const getArtwork = (result) => result.artwork || result.image || result.imageUrl || result.feedImage || ''
const getWebsiteUrl = (result) => result.link || result.websiteUrl || result.website || ''
const getEpisodeCount = (result) => result.episodeCount || result.totalEpisodeCount || null
</script>
