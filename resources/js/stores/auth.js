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
      this.loading = true
      try {
        const res = await window.axios.get('/api/user')
        this.user = res.data
      } catch (e) {
        this.user = null
      } finally {
        this.loading = false
      }
    },

    async login(payload) {
      // payload = { email, password }
      this.loading = true
      try {
        await window.axios.post('/login', payload)
        await this.fetchUser()
        return true
      } catch (e) {
        this.user = null
        return false
      } finally {
        this.loading = false
      }
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
