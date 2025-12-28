import { defineStore, storeToRefs } from 'pinia'
import confetti from 'canvas-confetti'
import { useToastStore } from './toastStore'

// Duration constants
const CELEBRATION_DURATION_MS = 3000
const TOAST_DURATION_MS = 6000
const CONFETTI_INTERVAL_MS = 250

// Confetti configuration
const CONFETTI_DEFAULTS = {
  startVelocity: 30,
  spread: 360,
  ticks: 60,
  zIndex: 10000
}

export const useCelebrationStore = defineStore('celebration', {
  state: () => ({
    lastMilestone: null,
    celebrating: false
  }),

  actions: {
    /**
     * Celebrate a milestone achievement with confetti and toast
     * @param {number} milestone - The milestone number reached
     */
    celebrate(milestone) {
      if (this.celebrating) return

      this.celebrating = true
      this.lastMilestone = milestone

      // Fire confetti
      this.fireConfetti()

      // Show celebration toast
      const toastStore = useToastStore()
      toastStore.success(
        `${milestone} Searches!`,
        `Congratulations! You've reached ${milestone} total searches!`,
        { duration: TOAST_DURATION_MS }
      )

      // Reset celebrating flag after animation
      setTimeout(() => {
        this.celebrating = false
      }, CELEBRATION_DURATION_MS)
    },

    /**
     * Fire confetti animation
     */
    fireConfetti() {
      const animationEnd = Date.now() + CELEBRATION_DURATION_MS

      function randomInRange(min, max) {
        return Math.random() * (max - min) + min
      }

      const interval = setInterval(function() {
        const timeLeft = animationEnd - Date.now()

        if (timeLeft <= 0) {
          return clearInterval(interval)
        }

        const particleCount = 50 * (timeLeft / CELEBRATION_DURATION_MS)

        // Fire from both sides
        confetti({
          ...CONFETTI_DEFAULTS,
          particleCount,
          origin: { x: randomInRange(0.1, 0.3), y: Math.random() - 0.2 }
        })
        confetti({
          ...CONFETTI_DEFAULTS,
          particleCount,
          origin: { x: randomInRange(0.7, 0.9), y: Math.random() - 0.2 }
        })
      }, CONFETTI_INTERVAL_MS)
    },

    /**
     * Check response for milestone and celebrate if found
     * @param {Object} userStats - User stats from API response
     */
    checkMilestone(userStats) {
      if (userStats?.milestone_reached) {
        this.celebrate(userStats.milestone_reached)
      }
    }
  }
})

/**
 * Composable for using celebration from components
 */
export function useCelebration() {
  const store = useCelebrationStore()
  const { celebrating, lastMilestone } = storeToRefs(store)

  return {
    celebrate: store.celebrate,
    checkMilestone: store.checkMilestone,
    celebrating,
    lastMilestone
  }
}
