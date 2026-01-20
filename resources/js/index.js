import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from './stores/auth'

// Layouts
import AuthLayout from './layouts/AuthLayout.vue'
import AppLayout from './layouts/AppLayout.vue'

// Pages
import Login from './pages/Login.vue'
import Dashboard from './pages/Dashboard.vue'

const routes = [
  // GUEST: LOGIN
  {
    path: '/login',
    component: AuthLayout,
    children: [
      { path: '', name: 'login', component: Login, meta: { guestOnly: true } },
    ],
  },

  // AUTH: APP
  {
    path: '/',
    component: AppLayout,
    meta: { requiresAuth: true },
    children: [
      { path: '', redirect: { name: 'dashboard' } },
      { path: 'dashboard', name: 'dashboard', component: Dashboard },
    ],
  },

  // fallback
  { path: '/:pathMatch(.*)*', redirect: '/login' },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  // Charger l’utilisateur une seule fois
  if (!auth.checked && !auth.loading) {
    await auth.fetchUser()
    auth.checked = true
  }

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login' }
  }

  if (to.meta.guestOnly && auth.isAuthenticated) {
    return { name: 'dashboard' }
  }
})

export default router
