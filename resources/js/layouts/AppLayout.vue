<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

// ✅ normalise: minuscules + pas d'accents + espaces/tirets => underscore
const normRole = (v) =>
  String(v ?? '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')   // enlève accents
    .toLowerCase()
    .trim()
    .replace(/[\s-]+/g, '_')           // espaces/tirets => _
    .replace(/_+/g, '_')

const roleNames = computed(() => {
  // supporte roles = ["raf"] ou roles=[{name:"RAF"}]
  const roles = auth.user?.roles ?? []
  return roles
    .map(r => (typeof r === 'string' ? r : r?.name))
    .filter(Boolean)
    .map(normRole)
})

const hasRole = (...names) => {
  const set = new Set(roleNames.value)
  return names.some(n => set.has(normRole(n)))
}

const canSeeValidation = computed(() =>
  hasRole('administrateur', 'admin', 'chef_hierarchique', 'raf', 'coordonnateur_de_projet', 'accp')
)

const isActive = (name) => route.name === name

const logout = async () => {
  await auth.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <div class="min-h-screen bg-slate-50">
    <header class="bg-white border-b">
      <div class="max-w-6xl mx-auto px-4">
        <div class="h-16 flex items-center justify-between gap-4">
          <!-- Left brand -->
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-brand flex items-center justify-center">
              <svg viewBox="0 0 24 24" class="w-6 h-6 text-white" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20 4c-6 0-12 4-12 12 0 2 0 4 2 6 8 0 12-6 12-12 0-2 0-4-2-6Z"/>
                <path d="M8 14c2-2 5-4 10-4"/>
              </svg>
            </div>
            <div class="leading-tight">
              <div class="font-bold text-ink">AD2M</div>
              <div class="text-xs text-slate-500">Gestion des Missions</div>
            </div>
          </div>

          <!-- Center nav -->
          <nav class="flex items-center gap-2">
            <router-link
              :to="{ name: 'dashboard' }"
              class="px-3 py-2 rounded-lg text-sm flex items-center gap-2"
              :class="isActive('dashboard') ? 'bg-brand-50 text-brand' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'"
            >
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M4 13h7V4H4v9Zm9 7h7V11h-7v9ZM4 20h7v-5H4v5Zm9-18v7h7V2h-7Z"/>
              </svg>
              Tableau de bord
            </router-link>

            <router-link
              :to="{ name: 'missions' }"
              class="px-3 py-2 rounded-lg text-sm flex items-center gap-2"
              :class="isActive('missions') ? 'bg-brand-50 text-brand' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'"
            >
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>
              </svg>
              Missions
            </router-link>

            <!-- ✅ Validation visible seulement si rôle validateur -->
            <router-link
              v-if="canSeeValidation"
              :to="{ name: 'validation' }"
              class="px-3 py-2 rounded-lg text-sm flex items-center gap-2"
              :class="isActive('validation') ? 'bg-brand-50 text-brand' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'"
            >
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 11l3 3L22 4"/>
                <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
              </svg>
              Validation
            </router-link>
          </nav>

          <!-- Right user -->
          <div class="flex items-center gap-3">
            <div v-if="auth.user" class="flex items-center gap-2">
              <div class="w-9 h-9 rounded-full bg-slate-100 flex items-center justify-center">
                <svg class="w-5 h-5 text-slate-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M20 21a8 8 0 0 0-16 0"/>
                  <circle cx="12" cy="7" r="4"/>
                </svg>
              </div>
              <div class="leading-tight hidden sm:block">
                <div class="text-sm font-semibold text-slate-900">{{ auth.user.name }}</div>
                <div class="text-xs text-slate-500">{{ auth.user.matricule || auth.user.email }}</div>
              </div>
            </div>

            <button
              v-if="auth.user"
              class="px-3 py-2 rounded-lg border bg-white text-sm hover:bg-slate-50"
              @click="logout"
            >
              Déconnexion
            </button>
          </div>
        </div>
      </div>
    </header>

    <main class="max-w-6xl mx-auto px-4 py-6">
      <router-view />
    </main>
  </div>
</template>
