<template>
  <div class="prospector-empty-state">
    <!-- Header Section -->
    <div class="prospector-empty-state__header">
      <div class="prospector-empty-state__icon-wrapper">
        <MagnifyingGlassIcon class="prospector-empty-state__icon" />
      </div>
      <h2 class="prospector-empty-state__title">Start Your Search</h2>
      <p class="prospector-empty-state__description">
        Find podcast opportunities by searching for people, shows, or topics. Here are some examples to get you started:
      </p>
    </div>
    
    <!-- Example Search Cards - 2x2 Grid -->
    <div class="prospector-empty-state__grid">
      
      <!-- Card 1: Search by Person -->
      <button
        @click="handleCardClick('Gary Vaynerchuk', 'byperson')"
        class="prospector-empty-state__card"
      >
        <div class="prospector-empty-state__card-icon">
          <UserIcon class="prospector-empty-state__card-icon-svg" />
        </div>
        <div class="prospector-empty-state__card-content">
          <div class="prospector-empty-state__card-label">Search by Person</div>
          <div class="prospector-empty-state__card-query">"Gary Vaynerchuk"</div>
          <p class="prospector-empty-state__card-description">Find which podcasts interview specific people</p>
        </div>
      </button>

      <!-- Card 2: Search by Show -->
      <button
        @click="handleCardClick('Joe Rogan Experience', 'bytitle')"
        class="prospector-empty-state__card"
      >
        <div class="prospector-empty-state__card-icon">
          <MicrophoneIcon class="prospector-empty-state__card-icon-svg" />
        </div>
        <div class="prospector-empty-state__card-content">
          <div class="prospector-empty-state__card-label">Search by Show</div>
          <div class="prospector-empty-state__card-query">"Joe Rogan Experience"</div>
          <p class="prospector-empty-state__card-description">Look up specific podcast shows by title</p>
        </div>
      </button>

      <!-- Card 3: Search by Topic -->
      <button
        @click="handleCardClick('business podcasts', 'bytitle')"
        class="prospector-empty-state__card"
      >
        <div class="prospector-empty-state__card-icon">
          <MagnifyingGlassIcon class="prospector-empty-state__card-icon-svg" />
        </div>
        <div class="prospector-empty-state__card-content">
          <div class="prospector-empty-state__card-label">Search by Topic</div>
          <div class="prospector-empty-state__card-query">"business podcasts" or "AI startups"</div>
          <p class="prospector-empty-state__card-description">Discover shows by topic, genre, or keywords</p>
        </div>
      </button>

      <!-- Card 4: Search with Filters (Premium) -->
      <button
        @click="handleCardClick('', 'byadvancedpodcast')"
        class="prospector-empty-state__card prospector-empty-state__card--premium"
      >
        <div class="prospector-empty-state__card-icon prospector-empty-state__card-icon--premium">
          <FunnelIcon class="prospector-empty-state__card-icon-svg" />
        </div>
        <div class="prospector-empty-state__card-content">
          <div class="prospector-empty-state__card-label-wrapper">
            <span class="prospector-empty-state__card-label prospector-empty-state__card-label--premium">Search with Filters</span>
            <span class="prospector-empty-state__card-badge">Premium</span>
          </div>
          <div class="prospector-empty-state__card-query">Filter by language, country, genre</div>
          <p class="prospector-empty-state__card-description">Narrow your search with advanced filtering options</p>
        </div>
      </button>
    </div>

    <!-- Pro Tip Banner -->
    <div class="prospector-empty-state__tip">
      <div class="prospector-empty-state__tip-icon">
        <EyeIcon class="prospector-empty-state__tip-icon-svg" />
      </div>
      <p class="prospector-empty-state__tip-text">
        <strong>Pro Tip:</strong> Use full names for better results. Search for competitors or industry leaders to discover which podcasts reach your target audience.
      </p>
    </div>
  </div>
</template>

<script setup>
import {
  MagnifyingGlassIcon,
  UserIcon,
  MicrophoneIcon,
  FunnelIcon,
  EyeIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
  channel: {
    type: String,
    default: 'podcasts'
  },
  searchMode: {
    type: String,
    default: 'byperson'
  }
})

const emit = defineEmits(['search'])

const handleCardClick = (query, mode) => {
  emit('search', { query, mode })
}
</script>

<style scoped>
.prospector-empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: var(--prospector-space-xl) var(--prospector-space-md);
  max-width: 64rem;
  margin: 0 auto;
}

.prospector-empty-state__header {
  text-align: center;
  margin-bottom: 2.5rem;
  max-width: 36rem;
}

.prospector-empty-state__icon-wrapper {
  width: 4rem;
  height: 4rem;
  background: white;
  color: var(--prospector-slate-400);
  border-radius: var(--prospector-radius-full);
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 1.25rem;
  box-shadow: var(--prospector-shadow-sm);
  border: 1px solid var(--prospector-slate-100);
}

.prospector-empty-state__icon {
  width: 2rem;
  height: 2rem;
}

.prospector-empty-state__title {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--prospector-slate-800);
  margin: 0 0 0.75rem;
  letter-spacing: -0.02em;
}

.prospector-empty-state__description {
  font-size: var(--prospector-font-size-sm);
  color: var(--prospector-slate-500);
  line-height: 1.6;
  margin: 0;
}

.prospector-empty-state__grid {
  display: grid;
  grid-template-columns: repeat(1, 1fr);
  gap: var(--prospector-space-md);
  width: 100%;
  margin-bottom: var(--prospector-space-xl);
}

@media (min-width: 768px) {
  .prospector-empty-state__grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

.prospector-empty-state__card {
  display: flex;
  align-items: flex-start;
  gap: var(--prospector-space-md);
  background: white;
  border: 1px solid var(--prospector-slate-200);
  border-radius: var(--prospector-radius-xl);
  padding: 1.25rem;
  text-align: left;
  cursor: pointer;
  transition: all var(--prospector-transition-fast);
}

.prospector-empty-state__card:hover {
  border-color: var(--prospector-primary-500);
  box-shadow: var(--prospector-shadow-md);
}

.prospector-empty-state__card:hover .prospector-empty-state__card-icon {
  background: var(--prospector-primary-100);
  color: var(--prospector-primary-500);
}

.prospector-empty-state__card:hover .prospector-empty-state__card-label {
  color: var(--prospector-primary-500);
}

.prospector-empty-state__card--premium:hover {
  border-color: #f59e0b;
}

.prospector-empty-state__card--premium:hover .prospector-empty-state__card-icon {
  background: #fef3c7;
}

.prospector-empty-state__card-icon {
  width: 2.5rem;
  height: 2.5rem;
  border-radius: var(--prospector-radius-full);
  background: var(--prospector-slate-50);
  color: var(--prospector-slate-500);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  border: 1px solid var(--prospector-slate-100);
  transition: all var(--prospector-transition-fast);
}

.prospector-empty-state__card-icon--premium {
  color: #f59e0b;
}

.prospector-empty-state__card-icon-svg {
  width: 1.25rem;
  height: 1.25rem;
}

.prospector-empty-state__card-content {
  flex: 1;
  min-width: 0;
}

.prospector-empty-state__card-label-wrapper {
  display: flex;
  align-items: center;
  gap: var(--prospector-space-sm);
  margin-bottom: var(--prospector-space-xs);
}

.prospector-empty-state__card-label {
  font-size: 0.625rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--prospector-slate-400);
  margin-bottom: var(--prospector-space-xs);
  transition: color var(--prospector-transition-fast);
}

.prospector-empty-state__card-label--premium {
  color: #d97706;
  margin-bottom: 0;
}

.prospector-empty-state__card-badge {
  font-size: 0.5625rem;
  font-weight: 700;
  text-transform: uppercase;
  color: #d97706;
  background: #fffbeb;
  border: 1px solid #fef3c7;
  padding: 0.125rem 0.375rem;
  border-radius: var(--prospector-radius-sm);
}

.prospector-empty-state__card-query {
  font-size: var(--prospector-font-size-base);
  font-weight: 600;
  color: var(--prospector-slate-800);
  margin-bottom: 0.125rem;
}

.prospector-empty-state__card-description {
  font-size: var(--prospector-font-size-xs);
  color: var(--prospector-slate-500);
  margin: 0;
}

.prospector-empty-state__tip {
  display: flex;
  align-items: center;
  gap: var(--prospector-space-md);
  background: var(--prospector-slate-50);
  color: var(--prospector-slate-600);
  padding: var(--prospector-space-md) 1.25rem;
  border-radius: var(--prospector-radius-lg);
  border: 1px solid var(--prospector-slate-200);
  width: 100%;
  max-width: 48rem;
}

.prospector-empty-state__tip-icon {
  flex-shrink: 0;
  color: var(--prospector-slate-400);
}

.prospector-empty-state__tip-icon-svg {
  width: 1rem;
  height: 1rem;
}

.prospector-empty-state__tip-text {
  font-size: var(--prospector-font-size-xs);
  line-height: 1.6;
  margin: 0;
}
</style>
