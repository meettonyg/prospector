import { defineStore } from 'pinia'

export const useChatStore = defineStore('chat', {
  state: () => ({
    messages: [],
    isTyping: false,
    pendingResults: [],
    sessionId: null,
    error: null
  }),

  getters: {
    /**
     * Get all messages
     */
    allMessages: (state) => state.messages,

    /**
     * Get message count
     */
    messageCount: (state) => state.messages.length,

    /**
     * Has any messages
     */
    hasMessages: (state) => state.messages.length > 0,

    /**
     * Get last message
     */
    lastMessage: (state) => state.messages[state.messages.length - 1] || null
  },

  actions: {
    /**
     * Add a user message
     */
    addUserMessage(content) {
      const message = {
        id: Date.now(),
        role: 'user',
        content,
        timestamp: new Date().toISOString()
      }
      this.messages.push(message)
      return message
    },

    /**
     * Add an assistant message
     */
    addAssistantMessage(content, results = []) {
      const message = {
        id: Date.now(),
        role: 'assistant',
        content,
        results,
        timestamp: new Date().toISOString()
      }
      this.messages.push(message)
      return message
    },

    /**
     * Add search results to a message
     */
    addResultsToMessage(messageId, results) {
      const message = this.messages.find(m => m.id === messageId)
      if (message) {
        message.results = results
      }
    },

    /**
     * Set typing indicator
     */
    setTyping(isTyping) {
      this.isTyping = isTyping
    },

    /**
     * Set error
     */
    setError(error) {
      this.error = error
    },

    /**
     * Clear error
     */
    clearError() {
      this.error = null
    },

    /**
     * Clear all messages
     */
    clearMessages() {
      this.messages = []
      this.error = null
    },

    /**
     * Start new session
     */
    startNewSession() {
      this.sessionId = Date.now().toString()
      this.messages = []
      this.error = null
    }
  }
})
