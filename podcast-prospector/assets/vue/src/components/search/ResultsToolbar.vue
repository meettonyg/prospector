<template>
  <div class="flex flex-wrap items-center justify-between gap-4 p-4 bg-white border border-slate-200 rounded-xl">
    <!-- Left side: Selection info and actions -->
    <div class="flex items-center gap-4">
      <!-- Result count -->
      <p class="text-sm text-slate-600">
        <span class="font-medium">{{ total }}</span> results
        <span v-if="trackedCount > 0" class="text-slate-400">
          ({{ trackedCount }} already tracked)
        </span>
      </p>

      <!-- Selection actions -->
      <div v-if="hasResults" class="flex items-center gap-2">
        <button
          @click="$emit('select-all')"
          class="text-sm text-primary-600 hover:text-primary-700 font-medium"
        >
          Select all new
        </button>
        <span class="text-slate-300">|</span>
        <button
          @click="$emit('deselect-all')"
          class="text-sm text-slate-500 hover:text-slate-700"
        >
          Clear selection
        </button>
      </div>
    </div>

    <!-- Right side: View toggle and bulk import -->
    <div class="flex items-center gap-3">
      <!-- Selected count & bulk import -->
      <div v-if="selectedCount > 0" class="flex items-center gap-2">
        <span class="text-sm font-medium text-primary-600">
          {{ selectedCount }} selected
        </span>
        <button
          @click="$emit('bulk-import')"
          :disabled="importing"
          class="btn-primary flex items-center gap-2"
        >
          <LoadingSpinner v-if="importing" size="sm" />
          <ArrowDownTrayIcon v-else class="w-4 h-4" />
          <span>Import Selected</span>
        </button>
      </div>

      <!-- View toggle -->
      <div class="flex items-center gap-1 p-1 bg-slate-100 rounded-lg">
        <button
          @click="$emit('update:viewMode', 'grid')"
          :class="[
            'p-2 rounded-md transition-colors',
            viewMode === 'grid'
              ? 'bg-white text-slate-800 shadow-sm'
              : 'text-slate-500 hover:text-slate-700'
          ]"
          title="Grid view"
        >
          <Squares2X2Icon class="w-5 h-5" />
        </button>
        <button
          @click="$emit('update:viewMode', 'table')"
          :class="[
            'p-2 rounded-md transition-colors',
            viewMode === 'table'
              ? 'bg-white text-slate-800 shadow-sm'
              : 'text-slate-500 hover:text-slate-700'
          ]"
          title="Table view"
        >
          <ListBulletIcon class="w-5 h-5" />
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import {
  Squares2X2Icon,
  ListBulletIcon,
  ArrowDownTrayIcon
} from '@heroicons/vue/24/outline'
import LoadingSpinner from '../common/LoadingSpinner.vue'

defineProps({
  total: {
    type: Number,
    default: 0
  },
  trackedCount: {
    type: Number,
    default: 0
  },
  selectedCount: {
    type: Number,
    default: 0
  },
  viewMode: {
    type: String,
    default: 'grid'
  },
  hasResults: {
    type: Boolean,
    default: false
  },
  importing: {
    type: Boolean,
    default: false
  }
})

defineEmits(['update:viewMode', 'select-all', 'deselect-all', 'bulk-import'])
</script>
