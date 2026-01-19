import { defineStore } from 'pinia'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,     // null = pas encore chargé / inconnu
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
        return true
      } catch (e) {
        this.user = null
        return false
      } finally {
        this.loading = false
      }
    },

    async login(payload) {
      this.loading = true
      try {
        // ✅ Important si tu utilises Sanctum / session
        await window.axios.get('/sanctum/csrf-cookie')

        const res = await window.axios.post('/login', payload)
        this.user = res.data.user
        return true
      } catch (e) {
        console.log('LOGIN ERROR STATUS =', e?.response?.status)
        console.log('LOGIN ERROR DATA =', e?.response?.data)
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
