import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'

export default defineConfig({
  plugins: [vue()],

  // Relative paths for WordPress compatibility
  base: './',

  build: {
    outDir: 'dist',
    manifest: true,
    rollupOptions: {
      input: resolve(__dirname, 'src/main.js'),
      output: {
        entryFileNames: 'prospector-[hash].js',
        chunkFileNames: 'prospector-[hash].js',
        assetFileNames: 'prospector-[hash].[ext]'
      }
    }
  },

  server: {
    port: 5173,
    cors: true,
    // Allow WordPress domain during development
    origin: 'http://localhost:5173'
  }
})
