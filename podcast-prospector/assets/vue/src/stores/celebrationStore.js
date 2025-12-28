import { defineStore } from 'pinia'
import confetti from 'canvas-confetti'
import { useToastStore } from './toastStore'

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
        { duration: 6000 }
      )

      // Reset celebrating flag after animation
      setTimeout(() => {
        this.celebrating = false
      }, 3000)
    },

    /**
     * Fire confetti animation
     */
    fireConfetti() {
      const duration = 3000
      const animationEnd = Date.now() + duration
      const defaults = { startVelocity: 30, spread: 360, ticks: 60, zIndex: 10000 }

      function randomInRange(min, max) {
        return Math.random() * (max - min) + min
      }

      const interval = setInterval(function() {
        const timeLeft = animationEnd - Date.now()

        if (timeLeft <= 0) {
          return clearInterval(interval)
        }

        const particleCount = 50 * (timeLeft / duration)

        // Fire from both sides
        confetti({
          ...defaults,
          particleCount,
          origin: { x: randomInRange(0.1, 0.3), y: Math.random() - 0.2 }
        })
        confetti({
          ...defaults,
          particleCount,
          origin: { x: randomInRange(0.7, 0.9), y: Math.random() - 0.2 }
        })
      }, 250)
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

  return {
    celebrate: (milestone) => store.celebrate(milestone),
    checkMilestone: (userStats) => store.checkMilestone(userStats),
    celebrating: () => store.celebrating,
    lastMilestone: () => store.lastMilestone
  }
}
