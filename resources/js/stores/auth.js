// resources/js/stores/auth.js
import { defineStore } from 'pinia'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,      // null = pas connecté / inconnu
    loading: false,  // état de chargement global
    checked: false,  // ✅ indique si on a déjà tenté fetchUser au démarrage
  }),

  getters: {
    isAuthenticated: (state) => !!state.user,
  },

  actions: {
    /**
     * Récupère l'utilisateur connecté (session existante)
     * Utilisé par le router guard au démarrage.
     */
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

    /**
     * Connexion
     * - Sanctum CSRF cookie
     * - POST /login
     * - stocke res.data.user
     */
    async login(payload) {
      this.loading = true
      try {
        // ✅ Nécessaire si tu utilises Sanctum / session cookies
        await window.axios.get('/sanctum/csrf-cookie')

        const res = await window.axios.post('/login', payload)
        this.user = res.data.user
        this.checked = true // ✅ on sait qu'on est connecté
        return true
      } catch (e) {
        console.log('LOGIN ERROR STATUS =', e?.response?.status)
        console.log('LOGIN ERROR DATA =', e?.response?.data)
        this.user = null
        this.checked = true // ✅ on a tenté
        return false
      } finally {
        this.loading = false
      }
    },

    /**
     * Déconnexion
     * - POST /logout (ignore erreurs)
     * - reset user
     */
    async logout() {
      this.loading = true
      try {
        await window.axios.post('/logout')
      } catch (e) {
        // ignore
      } finally {
        this.user = null
        this.checked = true
        this.loading = false
      }
    },
  },
})
