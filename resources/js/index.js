import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from './stores/auth'

import AuthLayout from './layouts/AuthLayout.vue'
import AppLayout from './layouts/AppLayout.vue'

import Login from './pages/Login.vue'
import Dashboard from './pages/Dashboard.vue'
import Mission from './pages/Mission.vue'
import Validation from './pages/Validation.vue'
import PayerAvance from './pages/PayerAvance.vue'
import MissionShow from './pages/MissionShow.vue' // ✅ AJOUT

// ✅ normalisation robuste (accents, espaces, tirets, etc.)
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

      // ✅ Mes missions
      { path: 'missions', name: 'missions', component: Mission },

      // ✅ Détail mission (évite page blanche sur 👁)
      { path: 'missions/:id', name: 'missions.show', component: MissionShow },

      // ✅ Validation (file de traitement)
      {
        path: 'validation',
        name: 'validation',
        component: Validation,
        meta: { requiresValidator: true },
      },

      // ✅ page ACCP dédiée (enregistrer avance -> confirmer paiement)
      {
        path: 'missions/:id/payer',
        name: 'missions.payer',
        component: PayerAvance,
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

  // ✅ évite fetchUser en boucle
  if (!auth.checked) {
    await auth.fetchUser()
    auth.checked = true
  }

  // ✅ auth guard
  const needsAuth = to.matched.some(r => r.meta.requiresAuth)
  if (needsAuth && !auth.isAuthenticated) return { name: 'login' }

  if (to.name === 'login' && auth.isAuthenticated) return { name: 'dashboard' }

  // ✅ validator guard
  const needsValidator = to.matched.some(r => r.meta.requiresValidator)
  if (needsValidator) {
    const ok = hasRole(
      auth.user,
      'administrateur',
      'admin',
      'chef_hierarchique',
      'raf',
      'coordonnateur_de_projet',
      'accp'
    )
    if (!ok) return { name: 'dashboard' }
  }

  // ✅ accp guard
  const needsAccp = to.matched.some(r => r.meta.requiresAccp)
  if (needsAccp) {
    const ok = hasRole(auth.user, 'accp', 'admin', 'administrateur')
    if (!ok) return { name: 'dashboard' }
  }
})

export default router
