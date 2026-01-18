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
  this.loading = true
  try {
    // Envoie une requête POST avec les identifiants utilisateur
    await window.axios.post('/login', payload)

    // Si la connexion réussit, récupère les données de l'utilisateur
    await this.fetchUser()
    return true
  } catch (e) {
    this.user = null
    // Si l'erreur est 401 (mauvais identifiant), affiche un message
    if (e.response && e.response.status === 401) {
      alert('Email ou mot de passe incorrect')
    }
    return false
  } finally {
    this.loading = false
  }
}

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
