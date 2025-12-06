<template>
  <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    <ResultCard
      v-for="(result, index) in results"
      :key="result.id || index"
      :title="getTitle(result)"
      :author="getAuthor(result)"
      :description="getDescription(result)"
      :artwork="getArtwork(result)"
      :website-url="getWebsiteUrl(result)"
      :rss-url="getRssUrl(result)"
      :episode-count="getEpisodeCount(result)"
      :language="result.language"
      :categories="getCategories(result)"
      :hydration="hydrationMap[index]"
      :is-selected="result._selected"
      :selectable="selectable"
      :show-import="showImport"
      :importing="importingIndices.includes(index)"
      @click="$emit('result-click', { result, index })"
      @select="$emit('toggle-select', index)"
      @import="$emit('import', { result, index })"
    />
  </div>
</template>

<script setup>
import ResultCard from './ResultCard.vue'

const props = defineProps({
  results: {
    type: Array,
    required: true
  },
  hydrationMap: {
    type: Object,
    default: () => ({})
  },
  selectable: {
    type: Boolean,
    default: true
  },
  showImport: {
    type: Boolean,
    default: true
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

// Helper functions to extract data from different API response formats
const getTitle = (result) => {
  return result.title || result.name || result.feedTitle || 'Untitled'
}

const getAuthor = (result) => {
  return result.author || result.publisher || result.ownerName || ''
}

const getDescription = (result) => {
  return result.description || result.summary || ''
}

const getArtwork = (result) => {
  return result.artwork || result.image || result.imageUrl || result.feedImage || ''
}

const getWebsiteUrl = (result) => {
  return result.link || result.websiteUrl || result.website || ''
}

const getRssUrl = (result) => {
  return result.url || result.feedUrl || result.rssUrl || ''
}

const getEpisodeCount = (result) => {
  return result.episodeCount || result.totalEpisodeCount || null
}

const getCategories = (result) => {
  if (result.genres) return result.genres
  if (result.categories) {
    if (Array.isArray(result.categories)) return result.categories
    if (typeof result.categories === 'object') return Object.values(result.categories)
  }
  return []
}
</script>
