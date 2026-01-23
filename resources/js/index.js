import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from './stores/auth'

import AuthLayout from './layouts/AuthLayout.vue'
import AppLayout from './layouts/AppLayout.vue'

import Login from './pages/Login.vue'
import Dashboard from './pages/Dashboard.vue'
import Mission from './pages/Mission.vue'
import MissionShow from './pages/MissionShow.vue'
import Validation from './pages/Validation.vue'
import SuiviAccp from './pages/SuiviAccp.vue'
import MissionCreate from './pages/MissionCreate.vue'
import Admin from './pages/Admin.vue'



// ✅ normalisation robuste
const normRole = (v) =>
  String(v ?? '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .trim()
    .replace(/[\s-]+/g, '_')
    .replace(/_+/g, '_')

const hasRole = (user, ...names) => {
  const roles = user?.roles ?? []
  const set = new Set(
    roles
      .map(r => (typeof r === 'string' ? r : r?.name))
      .filter(Boolean)
      .map(normRole)
  )
  return names.some(n => set.has(normRole(n)))
}

const routes = [
  // AUTH
  {
    path: '/',
    component: AuthLayout,
    children: [{ path: '', name: 'login', component: Login }],
  },

  // APP
  {
    path: '/',
    component: AppLayout,
    meta: { requiresAuth: true },
    children: [
      { path: 'dashboard', name: 'dashboard', component: Dashboard },

      { path: 'missions', name: 'missions', component: Mission },
      { path: 'missions/:id', name: 'missions.show', component: MissionShow },

      { path: 'missions/create', name: 'missions.create', component: MissionCreate },

      { path: 'admin', name: 'admin', component: Admin, meta: { requiresAdmin: true } },



      {
        path: 'validation',
        name: 'validation',
        component: Validation,
        meta: { requiresValidator: true }, // workflow uniquement
      },

      // ✅ Central financier
      {
        path: 'accp',
        name: 'accp',
        component: SuiviAccp,
        meta: { requiresAccp: true },
      },
      {
        path: 'accp/:id',
        name: 'accp.mission',
        component: SuiviAccp,
        meta: { requiresAccp: true },
      },

      // compat ancien lien
      {
        path: 'missions/:id/payer',
        name: 'missions.payer',
        redirect: to => ({ name: 'accp.mission', params: { id: to.params.id } }),
        meta: { requiresAccp: true },
      },

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

  if (!auth.checked) {
    await auth.fetchUser()
    auth.checked = true
  }

  const needsAuth = to.matched.some(r => r.meta.requiresAuth)
  if (needsAuth && !auth.isAuthenticated) return { name: 'login' }

  if (to.name === 'login' && auth.isAuthenticated) return { name: 'dashboard' }

  // ✅ Validation = CH/RAF/CP/Admin (on exclut ACCP)
  const needsValidator = to.matched.some(r => r.meta.requiresValidator)
  if (needsValidator) {
    const ok = hasRole(
      auth.user,
      'administrateur', 'admin',
      'chef_hierarchique',
      'raf',
      'coordonnateur_de_projet'
    )
    if (!ok) return { name: 'dashboard' }
  }

  // ✅ ACCP central
  const needsAccp = to.matched.some(r => r.meta.requiresAccp)
  if (needsAccp) {
    const ok = hasRole(auth.user, 'accp', 'admin', 'administrateur')
    if (!ok) return { name: 'dashboard' }
  }

  const needsAdmin = to.matched.some(r => r.meta.requiresAdmin)
if (needsAdmin) {
  const ok = hasRole(auth.user, 'admin', 'administrateur')
  if (!ok) return { name: 'dashboard' }
}

})

export default router
