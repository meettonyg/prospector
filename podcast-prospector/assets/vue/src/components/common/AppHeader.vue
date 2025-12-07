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
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid #e2e8f0;
  background: white;
}

.prospector-header__content {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
}

.prospector-header__title {
  font-size: 1.125rem;
  font-weight: 600;
  color: #1e293b;
  margin: 0;
  line-height: 1.4;
}

.prospector-header__subtitle {
  font-size: 0.875rem;
  color: #64748b;
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
  font-size: 0.875rem;
  font-weight: 500;
  color: #0ea5e9;
  background: transparent;
  border: 1px solid #0ea5e9;
  border-radius: 0.375rem;
  cursor: pointer;
  transition: all 0.15s ease;
}

.prospector-header__btn:hover {
  background: #f0f9ff;
}
</style>
