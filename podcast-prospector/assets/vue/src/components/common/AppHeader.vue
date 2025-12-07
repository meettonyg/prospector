<template>
  <div class="prospector-header">
    <div class="prospector-header__content">
      <h1 class="prospector-header__title">Prospector</h1>
      <p class="prospector-header__subtitle">Find podcasts, channels, and events for your guests</p>
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
defineEmits(['openSavedSearches'])
</script>

<style scoped>
.prospector-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1.5rem 1.5rem 1.25rem;
  background: white;
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
</style>
