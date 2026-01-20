import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from './stores/auth'

import AuthLayout from './layouts/AuthLayout.vue' // ✅ tu gardes ton AuthLayout
import AppLayout from './layouts/AppLayout.vue'

import Login from './pages/Login.vue'             // ✅ tu gardes ton Login
import Dashboard from './pages/Dashboard.vue'
import Mission from './pages/Mission.vue'         // ou Missions.vue si c'est ton nom
import Validation from './pages/Validation.vue'



const routes = [
  // AUTH (login)
  {
    path: '/',
    component: AuthLayout,
    children: [
      { path: '', name: 'login', component: Login },
    ],
  },

  // APP (protégé)
  {
    path: '/',
    component: AppLayout,
    meta: { requiresAuth: true },
    children: [
      { path: 'dashboard', name: 'dashboard', component: Dashboard },
      { path: 'missions', name: 'missions', component: Mission },
      { path: 'validation', name: 'validation', component: Validation },

      
      

      { path: 'home', redirect: { name: 'dashboard' } },
    ],
  },

  { path: '/:pathMatch(.*)*', redirect: { name: 'login' } },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  // ✅ évite de spam fetchUser
  if (!auth.checked) {
    await auth.fetchUser()
    auth.checked = true
  }

  const needsAuth = to.matched.some(r => r.meta.requiresAuth)

  if (needsAuth && !auth.isAuthenticated) {
    return { name: 'login' }
  }

  if (to.name === 'login' && auth.isAuthenticated) {
    return { name: 'dashboard' }
  }
})

export default router
