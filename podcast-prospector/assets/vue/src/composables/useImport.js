import { ref } from 'vue'
import api from '../api/prospectorApi'
import { useSearchStore } from '../stores/searchStore'
import { useToast } from '../stores/toastStore'

/**
 * Composable for handling podcast import functionality
 */
export function useImport() {
  const importing = ref(false)
  const importError = ref(null)
  const { success, error: showError, warning } = useToast()

  /**
   * Import a single podcast to the pipeline
   * @param {Object} podcast - Podcast data to import
   * @param {Object} searchMeta - Search metadata (term, type)
   * @returns {Promise<Object>} Import result
   */
  async function importSingle(podcast, searchMeta) {
    importing.value = true
    importError.value = null

    try {
      const response = await api.importToPipeline([podcast], searchMeta)

      if (response.success_count > 0) {
        success(
          'Added to Pipeline',
          `${podcast.title || podcast.name || 'Podcast'} is now in your Interview Tracker.`,
          {
            action: response.details?.[0]?.crm_url ? {
              label: 'View',
              url: response.details[0].crm_url
            } : null
          }
        )

        // Refresh hydration
        const searchStore = useSearchStore()
        await searchStore.refreshHydration()

        return response.details?.[0] || { success: true }
      } else {
        throw new Error(response.message || 'Import failed')
      }
    } catch (err) {
      importError.value = err.message
      showError(
        'Import Failed',
        err.response?.data?.message || err.message
      )
      throw err
    } finally {
      importing.value = false
    }
  }

  /**
   * Import multiple podcasts to the pipeline
   * @param {Array} podcasts - Array of podcast data to import
   * @param {Object} searchMeta - Search metadata (term, type)
   * @returns {Promise<Object>} Import results
   */
  async function importBulk(podcasts, searchMeta) {
    if (!podcasts || podcasts.length === 0) {
      return { success_count: 0, fail_count: 0 }
    }

    importing.value = true
    importError.value = null

    try {
      const response = await api.importToPipeline(podcasts, searchMeta)

      const isPartial = response.fail_count > 0

      if (isPartial) {
        warning(
          `Imported ${response.success_count} of ${podcasts.length}`,
          `${response.fail_count} failed to import.`,
          {
            action: {
              label: 'View Pipeline',
              url: '/app/interview/board/'
            }
          }
        )
      } else if (response.success_count > 0) {
        success(
          `Imported ${response.success_count} podcasts`,
          'All podcasts added to your pipeline.',
          {
            action: {
              label: 'View Pipeline',
              url: '/app/interview/board/'
            }
          }
        )
      }

      // Refresh hydration
      const searchStore = useSearchStore()
      await searchStore.refreshHydration()

      // Deselect imported items
      searchStore.deselectAll()

      return response
    } catch (err) {
      importError.value = err.message
      showError(
        'Bulk Import Failed',
        err.response?.data?.message || err.message
      )
      throw err
    } finally {
      importing.value = false
    }
  }

  /**
   * Check if Guest Intel is available for imports
   * @returns {boolean}
   */
  function canImport() {
    const config = window.PROSPECTOR_CONFIG || {}
    return config.guestIntelActive !== false
  }

  return {
    importing,
    importError,
    importSingle,
    importBulk,
    canImport
  }
}
