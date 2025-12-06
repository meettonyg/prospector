import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import './style.css'

// Get WordPress config
const config = window.PROSPECTOR_CONFIG || {}

// Create Vue app
const app = createApp(App)

// Install Pinia
app.use(createPinia())

// Provide global config
app.provide('config', config)

// Mount to WordPress container
app.mount('#prospector-app')

// Log initialization
console.log('Podcast Prospector Vue app initialized', {
  guestIntelActive: config.guestIntelActive,
  features: config.features
})
