import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from './stores/auth'

import Home from './pages/Home.vue'
import About from './pages/About.vue'
import Login from './pages/Login.vue'

const routes = [
  { path: '/', name: 'login', component: Login },
  { path: '/login', redirect: '/' },
  { path: '/Login', redirect: '/' },

  { path: '/home', name: 'home', component: Home, meta: { requiresAuth: true } },
  { path: '/about', name: 'about', component: About, meta: { requiresAuth: true } },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

// ✅ Guard simple : si route protégée et pas connecté → redirect login
router.beforeEach(async (to) => {
  const auth = useAuthStore()

  // Si on n’a pas encore essayé de charger l’utilisateur
  if (auth.user === null && !auth.loading) {
    await auth.fetchUser()
  }

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login' }
  }

  // Si déjà connecté et essaie d’aller sur login → redirect home
  if ((to.name === 'login') && auth.isAuthenticated) {
    return { name: 'home' }
  }
})

export default router
