import { defineStore } from 'pinia'
import { TOAST_TYPES, TOAST_DURATION } from '../utils/constants'

let toastId = 0

export const useToastStore = defineStore('toast', {
  state: () => ({
    toasts: []
  }),

  actions: {
    /**
     * Add a toast notification
     * @param {Object} options Toast options
     * @param {string} options.type - Toast type (success, error, warning, info)
     * @param {string} options.title - Toast title
     * @param {string} options.message - Toast message
     * @param {number} options.duration - Duration in ms (default: 5000)
     * @param {Object} options.action - Optional action { label, url, onClick }
     */
    addToast(options) {
      const id = ++toastId
      const toast = {
        id,
        type: options.type || TOAST_TYPES.INFO,
        title: options.title || '',
        message: options.message || '',
        action: options.action || null,
        createdAt: Date.now()
      }

      this.toasts.push(toast)

      // Auto-remove after duration
      const duration = options.duration || TOAST_DURATION
      if (duration > 0) {
        setTimeout(() => {
          this.removeToast(id)
        }, duration)
      }

      return id
    },

    /**
     * Remove a toast by ID
     */
    removeToast(id) {
      const index = this.toasts.findIndex(t => t.id === id)
      if (index > -1) {
        this.toasts.splice(index, 1)
      }
    },

    /**
     * Clear all toasts
     */
    clearAll() {
      this.toasts = []
    },

    /**
     * Show success toast
     */
    success(title, message, options = {}) {
      return this.addToast({
        type: TOAST_TYPES.SUCCESS,
        title,
        message,
        ...options
      })
    },

    /**
     * Show error toast
     */
    error(title, message, options = {}) {
      return this.addToast({
        type: TOAST_TYPES.ERROR,
        title,
        message,
        duration: options.duration || 8000, // Errors stay longer
        ...options
      })
    },

    /**
     * Show warning toast
     */
    warning(title, message, options = {}) {
      return this.addToast({
        type: TOAST_TYPES.WARNING,
        title,
        message,
        ...options
      })
    },

    /**
     * Show info toast
     */
    info(title, message, options = {}) {
      return this.addToast({
        type: TOAST_TYPES.INFO,
        title,
        message,
        ...options
      })
    }
  }
})

/**
 * Composable for using toast from components
 */
export function useToast() {
  const store = useToastStore()

  return {
    showToast: (options) => store.addToast(options),
    success: (title, message, options) => store.success(title, message, options),
    error: (title, message, options) => store.error(title, message, options),
    warning: (title, message, options) => store.warning(title, message, options),
    info: (title, message, options) => store.info(title, message, options),
    removeToast: (id) => store.removeToast(id),
    clearAll: () => store.clearAll()
  }
}
