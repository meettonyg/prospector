import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import './style.css'
import { useSearchStore } from './stores/searchStore'

// Get WordPress config
const config = window.PROSPECTOR_CONFIG || {}

// Create Vue app
const app = createApp(App)

// Install Pinia
const pinia = createPinia()
app.use(pinia)

// Provide global config
app.provide('config', config)

// Mount to WordPress container
app.mount('#prospector-app')

// Initialize from URL parameters
const initFromUrlParams = (piniaInstance) => {
  const params = new URLSearchParams(window.location.search)
  const searchStore = useSearchStore(piniaInstance)

  // Valid tab values mapped to their channels
  const tabToChannel = {
    byperson: 'podcasts',
    bytitle: 'podcasts',
    byadvancedpodcast: 'podcasts',
    byadvancedepisode: 'podcasts',
    byyoutube: 'youtube',
    bysummit: 'summits'
  }

  // Read channel param (optional)
  const channelParam = params.get('channel')
  if (channelParam && ['podcasts', 'youtube', 'summits'].includes(channelParam)) {
    searchStore.setChannel(channelParam)
  }

  // Read tab param
  const tabParam = params.get('tab')
  if (tabParam && tabToChannel[tabParam]) {
    // If channel wasn't explicitly set, derive it from the tab
    if (!channelParam) {
      const derivedChannel = tabToChannel[tabParam]
      if (derivedChannel !== searchStore.channel) {
        searchStore.channel = derivedChannel
      }
    }
    searchStore.mode = tabParam
  }
}

initFromUrlParams(pinia)

// Log initialization
console.log('Podcast Prospector Vue app initialized', {
  guestIntelActive: config.guestIntelActive,
  features: config.features
})
