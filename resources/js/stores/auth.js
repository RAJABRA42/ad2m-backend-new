import { defineStore } from 'pinia'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    loading: false,
  }),

  getters: {
    isAuthenticated: (state) => !!state.user,
  },

  actions: {
    async fetchUser() {
      // ...
    },
    async login(payload) {
      // ...
    },

    async logout() {
      this.loading = true
      try {
        await window.axios.post('/logout')
      } catch (e) {
        // ignore
      } finally {
        this.user = null
        this.loading = false
      }
    },
  },
})
