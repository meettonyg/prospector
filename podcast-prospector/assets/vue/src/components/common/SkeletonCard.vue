<template>
  <div class="skeleton-card">
    <!-- Thumbnail skeleton -->
    <div class="skeleton-card__thumbnail">
      <div class="skeleton skeleton--image"></div>
    </div>
    
    <!-- Content skeleton -->
    <div class="skeleton-card__content">
      <!-- Title line -->
      <div class="skeleton skeleton--title"></div>
      
      <!-- Meta lines -->
      <div class="skeleton-card__meta">
        <div class="skeleton skeleton--meta skeleton--meta-short"></div>
        <div class="skeleton skeleton--meta skeleton--meta-medium"></div>
      </div>
      
      <!-- Description lines -->
      <div class="skeleton-card__description">
        <div class="skeleton skeleton--text"></div>
        <div class="skeleton skeleton--text skeleton--text-short"></div>
      </div>
      
      <!-- Footer skeleton -->
      <div class="skeleton-card__footer">
        <div class="skeleton-card__badges">
          <div class="skeleton skeleton--badge"></div>
          <div class="skeleton skeleton--badge"></div>
        </div>
        <div class="skeleton skeleton--button"></div>
      </div>
    </div>
  </div>
</template>

<script setup>
/**
 * SkeletonCard Component
 * 
 * Provides a loading placeholder that matches the ResultCard layout.
 * Uses CSS animations for a shimmer effect during loading states.
 * 
 * @component
 * @example
 * <SkeletonCard v-for="i in 6" :key="i" />
 */
defineOptions({
  name: 'SkeletonCard'
})
</script>

<style scoped>
/* ==============================================
   SKELETON CARD COMPONENT
   ============================================== */

.skeleton-card {
  display: flex;
  flex-direction: column;
  background: white;
  border: 1px solid var(--prospector-slate-200);
  border-radius: var(--prospector-radius-xl);
  overflow: hidden;
  padding: var(--prospector-space-lg);
}

/* Thumbnail area */
.skeleton-card__thumbnail {
  width: 100%;
  aspect-ratio: 1;
  margin-bottom: var(--prospector-space-md);
  border-radius: var(--prospector-radius-lg);
  overflow: hidden;
}

/* Content area */
.skeleton-card__content {
  display: flex;
  flex-direction: column;
  gap: var(--prospector-space-sm);
  flex: 1;
}

/* Meta section */
.skeleton-card__meta {
  display: flex;
  gap: var(--prospector-space-sm);
  margin-top: var(--prospector-space-xs);
}

/* Description section */
.skeleton-card__description {
  display: flex;
  flex-direction: column;
  gap: var(--prospector-space-xs);
  margin-top: var(--prospector-space-sm);
}

/* Footer section */
.skeleton-card__footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: auto;
  padding-top: var(--prospector-space-md);
}

.skeleton-card__badges {
  display: flex;
  gap: var(--prospector-space-xs);
}

/* ==============================================
   SKELETON BASE STYLES
   ============================================== */

.skeleton {
  background: linear-gradient(
    90deg,
    var(--prospector-slate-100) 25%,
    var(--prospector-slate-200) 50%,
    var(--prospector-slate-100) 75%
  );
  background-size: 200% 100%;
  animation: skeletonShimmer 1.5s infinite;
  border-radius: var(--prospector-radius-md);
}

/* ==============================================
   SKELETON VARIANTS
   ============================================== */

/* Image skeleton */
.skeleton--image {
  width: 100%;
  height: 100%;
  border-radius: var(--prospector-radius-lg);
}

/* Title skeleton */
.skeleton--title {
  height: 24px;
  width: 85%;
}

/* Meta skeleton */
.skeleton--meta {
  height: 16px;
}

.skeleton--meta-short {
  width: 60px;
}

.skeleton--meta-medium {
  width: 100px;
}

/* Text skeleton */
.skeleton--text {
  height: 14px;
  width: 100%;
}

.skeleton--text-short {
  width: 70%;
}

/* Badge skeleton */
.skeleton--badge {
  height: 22px;
  width: 60px;
  border-radius: var(--prospector-radius-full);
}

/* Button skeleton */
.skeleton--button {
  height: 32px;
  width: 100px;
  border-radius: var(--prospector-radius-md);
}

/* ==============================================
   ANIMATION
   ============================================== */

@keyframes skeletonShimmer {
  0% {
    background-position: 200% 0;
  }
  100% {
    background-position: -200% 0;
  }
}
</style>
