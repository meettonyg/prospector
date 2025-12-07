<template>
  <div class="prospector-empty-state">
    <div class="prospector-empty-state__icon-wrapper">
      <MicrophoneIcon class="prospector-empty-state__icon" />
    </div>

    <h3 class="prospector-empty-state__title">
      Podcast Discovery Assistant
    </h3>

    <p class="prospector-empty-state__description">
      Ask me to find podcasts by guest name, topic, or title. I'll search and
      help you discover new opportunities.
    </p>

    <div class="prospector-empty-state__examples">
      <p class="prospector-empty-state__examples-label">
        Try asking:
      </p>

      <button
        v-for="example in examples"
        :key="example.query"
        @click="$emit('example-click', example.query)"
        class="prospector-empty-state__example-btn"
      >
        <component
          :is="example.icon"
          class="prospector-empty-state__example-icon"
        />
        <span class="prospector-empty-state__example-text">{{ example.label }}</span>
      </button>
    </div>
  </div>
</template>

<script setup>
import {
  MicrophoneIcon,
  UserIcon,
  TagIcon,
  MagnifyingGlassIcon
} from '@heroicons/vue/24/outline'

defineEmits(['example-click'])

const examples = [
  {
    query: 'Find podcasts featuring Brené Brown',
    label: '"Find podcasts featuring Brené Brown"',
    icon: UserIcon
  },
  {
    query: 'Podcasts about artificial intelligence',
    label: '"Podcasts about artificial intelligence"',
    icon: TagIcon
  },
  {
    query: 'Look up How I Built This podcast',
    label: '"Look up How I Built This podcast"',
    icon: MagnifyingGlassIcon
  }
]
</script>

<style scoped>
.prospector-empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 100%;
  text-align: center;
  padding: var(--prospector-space-md);
}

.prospector-empty-state__icon-wrapper {
  width: 4rem;
  height: 4rem;
  border-radius: var(--prospector-radius-full);
  background: var(--prospector-primary-100);
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: var(--prospector-space-md);
}

.prospector-empty-state__icon {
  width: 2rem;
  height: 2rem;
  color: var(--prospector-primary-500);
}

.prospector-empty-state__title {
  font-size: var(--prospector-font-size-lg);
  font-weight: 600;
  color: var(--prospector-slate-800);
  margin: 0 0 var(--prospector-space-sm);
}

.prospector-empty-state__description {
  color: var(--prospector-slate-500);
  max-width: 28rem;
  margin: 0 0 var(--prospector-space-lg);
}

.prospector-empty-state__examples {
  width: 100%;
  max-width: 24rem;
  display: flex;
  flex-direction: column;
  gap: var(--prospector-space-sm);
}

.prospector-empty-state__examples-label {
  font-size: var(--prospector-font-size-xs);
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--prospector-slate-400);
  margin: 0 0 var(--prospector-space-md);
}

.prospector-empty-state__example-btn {
  width: 100%;
  display: flex;
  align-items: center;
  gap: var(--prospector-space-md);
  padding: var(--prospector-space-md);
  background: var(--prospector-slate-50);
  border: 1px solid var(--prospector-slate-200);
  border-radius: var(--prospector-radius-xl);
  text-align: left;
  cursor: pointer;
  transition: all var(--prospector-transition-fast);
}

.prospector-empty-state__example-btn:hover {
  background: var(--prospector-slate-100);
}

.prospector-empty-state__example-btn:hover .prospector-empty-state__example-icon {
  color: var(--prospector-primary-500);
}

.prospector-empty-state__example-icon {
  width: 1.25rem;
  height: 1.25rem;
  color: var(--prospector-slate-400);
  transition: color var(--prospector-transition-fast);
}

.prospector-empty-state__example-text {
  font-size: var(--prospector-font-size-sm);
  color: var(--prospector-slate-700);
}
</style>
