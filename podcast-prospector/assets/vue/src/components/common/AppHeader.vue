<template>
  <div class="prospector-header">
    <div class="prospector-header__content">
      <h1 class="prospector-header__title">Prospector</h1>
      <p class="prospector-header__subtitle">Find podcasts, channels, and events for your guests</p>
    </div>
    
    <!-- Mode Toggle -->
    <div class="mode-toggle">
      <button
        v-for="m in modes"
        :key="m.name"
        type="button"
        @click="$emit('update:mode', m.name)"
        :class="[
          'mode-toggle__btn',
          mode === m.name && 'mode-toggle__btn--active'
        ]"
      >
        <component :is="m.icon" class="mode-toggle__icon" />
        <span>{{ m.label }}</span>
      </button>
    </div>

    <div class="prospector-header__actions">
      <button 
        type="button" 
        class="prospector-header__btn"
        @click="$emit('openSavedSearches')"
      >
        Saved Searches
      </button>
    </div>
  </div>
</template>

<script setup>
import { MagnifyingGlassIcon, ChatBubbleLeftRightIcon } from '@heroicons/vue/24/outline'

defineProps({
  mode: {
    type: String,
    default: 'search'
  }
})

defineEmits(['openSavedSearches', 'update:mode'])

const modes = [
  { name: 'search', label: 'Search', icon: MagnifyingGlassIcon },
  { name: 'chat', label: 'Chat', icon: ChatBubbleLeftRightIcon }
]
</script>

<style scoped>
.prospector-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1.5rem 1.5rem 1.25rem;
  background: white;
  gap: 2rem;
}

.prospector-header__content {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.prospector-header__title {
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--prospector-slate-800);
  margin: 0;
  line-height: 1.3;
}

.prospector-header__subtitle {
  font-size: 0.875rem;
  color: var(--prospector-slate-500);
  margin: 0;
  line-height: 1.4;
}

/* Mode Toggle Styles */
.mode-toggle {
  display: inline-flex;
  background: var(--prospector-slate-100);
  border-radius: var(--prospector-radius-lg);
  padding: 0.25rem;
  gap: 0.25rem;
}

.mode-toggle__btn {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 1rem;
  font-family: var(--prospector-font-family);
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--prospector-slate-600);
  background: transparent;
  border: none;
  border-radius: var(--prospector-radius-md);
  cursor: pointer;
  transition: all var(--prospector-transition-fast);
}

.mode-toggle__btn:hover:not(.mode-toggle__btn--active) {
  color: var(--prospector-slate-700);
  background: var(--prospector-slate-50);
}

.mode-toggle__btn--active {
  color: var(--prospector-slate-900);
  background: white;
  box-shadow: var(--prospector-shadow-sm);
}

.mode-toggle__icon {
  width: 1rem;
  height: 1rem;
}

.prospector-header__actions {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.prospector-header__btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 1rem;
  font-family: var(--prospector-font-family);
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--prospector-primary-500);
  background: transparent;
  border: 1px solid var(--prospector-primary-500);
  border-radius: var(--prospector-radius-md);
  cursor: pointer;
  transition: all var(--prospector-transition-fast);
}

.prospector-header__btn:hover {
  background: var(--prospector-primary-50);
}

/* Responsive */
@media (max-width: 768px) {
  .prospector-header {
    flex-wrap: wrap;
    gap: 1rem;
  }

  .mode-toggle {
    order: 3;
    width: 100%;
  }

  .mode-toggle__btn {
    flex: 1;
    justify-content: center;
  }
}
</style>
