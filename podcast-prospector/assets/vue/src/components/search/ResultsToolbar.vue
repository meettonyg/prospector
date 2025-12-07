<template>
  <div class="prospector-results-toolbar">
    <!-- Left side: Selection info and actions -->
    <div class="prospector-results-toolbar__left">
      <!-- Result count -->
      <p class="prospector-results-toolbar__count">
        <span class="prospector-results-toolbar__count-number">{{ total }}</span> results
        <span v-if="trackedCount > 0" class="prospector-results-toolbar__count-tracked">
          ({{ trackedCount }} already tracked)
        </span>
      </p>

      <!-- Selection actions -->
      <div v-if="hasResults" class="prospector-results-toolbar__select-actions">
        <button
          @click="$emit('select-all')"
          class="prospector-results-toolbar__link"
        >
          Select all new
        </button>
        <span class="prospector-results-toolbar__divider">|</span>
        <button
          @click="$emit('deselect-all')"
          class="prospector-results-toolbar__link prospector-results-toolbar__link--secondary"
        >
          Clear selection
        </button>
      </div>
    </div>

    <!-- Right side: View toggle and bulk import -->
    <div class="prospector-results-toolbar__right">
      <!-- Selected count & bulk import -->
      <div v-if="selectedCount > 0" class="prospector-results-toolbar__bulk">
        <span class="prospector-results-toolbar__selected-count">
          {{ selectedCount }} selected
        </span>
        <button
          @click="$emit('bulk-import')"
          :disabled="importing"
          class="prospector-btn prospector-btn--primary"
        >
          <LoadingSpinner v-if="importing" size="sm" />
          <ArrowDownTrayIcon v-else class="prospector-results-toolbar__btn-icon" />
          <span>Import Selected</span>
        </button>
      </div>

      <!-- View toggle -->
      <div class="prospector-view-toggle">
        <button
          @click="$emit('update:viewMode', 'grid')"
          :class="[
            'prospector-view-toggle__btn',
            viewMode === 'grid' && 'prospector-view-toggle__btn--active'
          ]"
          title="Grid view"
        >
          <Squares2X2Icon class="prospector-view-toggle__icon" />
        </button>
        <button
          @click="$emit('update:viewMode', 'table')"
          :class="[
            'prospector-view-toggle__btn',
            viewMode === 'table' && 'prospector-view-toggle__btn--active'
          ]"
          title="Table view"
        >
          <ListBulletIcon class="prospector-view-toggle__icon" />
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

<style scoped>
.prospector-results-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--prospector-space-md);
  padding: var(--prospector-space-md);
  background: white;
  border: 1px solid var(--prospector-slate-200);
  border-radius: var(--prospector-radius-xl);
}

.prospector-results-toolbar__left {
  display: flex;
  align-items: center;
  gap: var(--prospector-space-md);
}

.prospector-results-toolbar__count {
  font-size: var(--prospector-font-size-sm);
  color: var(--prospector-slate-600);
  margin: 0;
}

.prospector-results-toolbar__count-number {
  font-weight: 500;
}

.prospector-results-toolbar__count-tracked {
  color: var(--prospector-slate-400);
}

.prospector-results-toolbar__select-actions {
  display: flex;
  align-items: center;
  gap: var(--prospector-space-sm);
}

.prospector-results-toolbar__link {
  font-size: var(--prospector-font-size-sm);
  font-weight: 500;
  color: var(--prospector-primary-600);
  background: none;
  border: none;
  cursor: pointer;
  padding: 0;
  transition: color var(--prospector-transition-fast);
}

.prospector-results-toolbar__link:hover {
  color: var(--prospector-primary-700);
}

.prospector-results-toolbar__link--secondary {
  color: var(--prospector-slate-500);
}

.prospector-results-toolbar__link--secondary:hover {
  color: var(--prospector-slate-700);
}

.prospector-results-toolbar__divider {
  color: var(--prospector-slate-300);
}

.prospector-results-toolbar__right {
  display: flex;
  align-items: center;
  gap: var(--prospector-space-md);
}

.prospector-results-toolbar__bulk {
  display: flex;
  align-items: center;
  gap: var(--prospector-space-sm);
}

.prospector-results-toolbar__selected-count {
  font-size: var(--prospector-font-size-sm);
  font-weight: 500;
  color: var(--prospector-primary-600);
}

.prospector-results-toolbar__btn-icon {
  width: 1rem;
  height: 1rem;
}

.prospector-view-toggle {
  display: flex;
  align-items: center;
  gap: var(--prospector-space-xs);
  padding: var(--prospector-space-xs);
  background: var(--prospector-slate-100);
  border-radius: var(--prospector-radius-lg);
}

.prospector-view-toggle__btn {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--prospector-space-sm);
  background: transparent;
  border: none;
  border-radius: var(--prospector-radius-md);
  color: var(--prospector-slate-500);
  cursor: pointer;
  transition: all var(--prospector-transition-fast);
}

.prospector-view-toggle__btn:hover {
  color: var(--prospector-slate-700);
}

.prospector-view-toggle__btn--active {
  background: white;
  color: var(--prospector-slate-800);
  box-shadow: var(--prospector-shadow-sm);
}

.prospector-view-toggle__icon {
  width: 1.25rem;
  height: 1.25rem;
}
</style>
