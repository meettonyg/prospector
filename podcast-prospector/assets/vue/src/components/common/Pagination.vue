<template>
  <div class="prospector-pagination-wrapper">
    <!-- Mobile view -->
    <div class="prospector-pagination__mobile">
      <button
        @click="goToPage(currentPage - 1)"
        :disabled="currentPage === 1"
        class="prospector-btn prospector-btn--secondary"
      >
        Previous
      </button>
      <button
        @click="goToPage(currentPage + 1)"
        :disabled="currentPage === totalPages"
        class="prospector-btn prospector-btn--secondary"
      >
        Next
      </button>
    </div>

    <!-- Desktop view -->
    <div class="prospector-pagination__desktop">
      <div class="prospector-pagination__info">
        <p class="prospector-pagination__info-text">
          Showing
          <span class="prospector-pagination__info-highlight">{{ startItem }}</span>
          to
          <span class="prospector-pagination__info-highlight">{{ endItem }}</span>
          of
          <span class="prospector-pagination__info-highlight">{{ total }}</span>
          results
        </p>
      </div>

      <nav class="prospector-pagination" aria-label="Pagination">
        <!-- Previous button -->
        <button
          @click="goToPage(currentPage - 1)"
          :disabled="currentPage === 1"
          class="prospector-pagination__btn prospector-pagination__btn--nav"
          aria-label="Previous page"
        >
          <ChevronLeftIcon class="prospector-pagination__btn-icon" />
        </button>

        <!-- Page numbers -->
        <template v-for="page in visiblePages" :key="page">
          <span
            v-if="page === '...'"
            class="prospector-pagination__ellipsis"
          >
            ...
          </span>
          <button
            v-else
            @click="goToPage(page)"
            :class="[
              'prospector-pagination__btn',
              page === currentPage && 'prospector-pagination__btn--active'
            ]"
          >
            {{ page }}
          </button>
        </template>

        <!-- Next button -->
        <button
          @click="goToPage(currentPage + 1)"
          :disabled="currentPage === totalPages"
          class="prospector-pagination__btn prospector-pagination__btn--nav"
          aria-label="Next page"
        >
          <ChevronRightIcon class="prospector-pagination__btn-icon" />
        </button>
      </nav>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { ChevronLeftIcon, ChevronRightIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  currentPage: {
    type: Number,
    required: true
  },
  perPage: {
    type: Number,
    default: 20
  },
  total: {
    type: Number,
    required: true
  }
})

const emit = defineEmits(['update:currentPage'])

const totalPages = computed(() => Math.ceil(props.total / props.perPage))

const startItem = computed(() => {
  if (props.total === 0) return 0
  return (props.currentPage - 1) * props.perPage + 1
})

const endItem = computed(() => {
  return Math.min(props.currentPage * props.perPage, props.total)
})

const visiblePages = computed(() => {
  const pages = []
  const total = totalPages.value
  const current = props.currentPage

  if (total <= 7) {
    // Show all pages
    for (let i = 1; i <= total; i++) {
      pages.push(i)
    }
  } else {
    // Always show first page
    pages.push(1)

    if (current > 3) {
      pages.push('...')
    }

    // Show pages around current
    for (let i = Math.max(2, current - 1); i <= Math.min(total - 1, current + 1); i++) {
      pages.push(i)
    }

    if (current < total - 2) {
      pages.push('...')
    }

    // Always show last page
    if (total > 1) {
      pages.push(total)
    }
  }

  return pages
})

const goToPage = (page) => {
  if (page >= 1 && page <= totalPages.value && page !== props.currentPage) {
    emit('update:currentPage', page)
  }
}
</script>

<style scoped>
.prospector-pagination-wrapper {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--prospector-space-md) var(--prospector-space-lg);
  background: white;
  border-top: 1px solid var(--prospector-slate-200);
}

/* Mobile view */
.prospector-pagination__mobile {
  display: flex;
  flex: 1;
  justify-content: space-between;
}

@media (min-width: 640px) {
  .prospector-pagination__mobile {
    display: none;
  }
}

/* Desktop view */
.prospector-pagination__desktop {
  display: none;
  flex: 1;
  align-items: center;
  justify-content: space-between;
}

@media (min-width: 640px) {
  .prospector-pagination__desktop {
    display: flex;
  }
}

.prospector-pagination__info {
  /* wrapper for info text */
}

.prospector-pagination__info-text {
  font-size: var(--prospector-font-size-sm);
  color: var(--prospector-slate-600);
  margin: 0;
}

.prospector-pagination__info-highlight {
  font-weight: 500;
}

.prospector-pagination {
  display: flex;
  align-items: center;
  gap: var(--prospector-space-xs);
}

.prospector-pagination__btn {
  display: flex;
  align-items: center;
  justify-content: center;
  min-width: 2.5rem;
  height: 2.5rem;
  padding: 0 var(--prospector-space-md);
  font-family: var(--prospector-font-family);
  font-size: var(--prospector-font-size-sm);
  font-weight: 500;
  color: var(--prospector-slate-600);
  background: transparent;
  border: none;
  border-radius: var(--prospector-radius-lg);
  cursor: pointer;
  transition: all var(--prospector-transition-fast);
}

.prospector-pagination__btn:hover:not(:disabled) {
  background: var(--prospector-slate-100);
}

.prospector-pagination__btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.prospector-pagination__btn--active {
  color: white;
  background: var(--prospector-primary-500);
}

.prospector-pagination__btn--active:hover:not(:disabled) {
  background: var(--prospector-primary-600);
}

.prospector-pagination__btn--nav {
  padding: var(--prospector-space-sm);
}

.prospector-pagination__btn-icon {
  width: 1.25rem;
  height: 1.25rem;
}

.prospector-pagination__ellipsis {
  padding: 0 var(--prospector-space-xs);
  color: var(--prospector-slate-400);
}
</style>
