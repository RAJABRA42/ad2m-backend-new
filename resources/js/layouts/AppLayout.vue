<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import logo from '../assets/logo_ad2m.png'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const doLogout = async () => {
  await auth.logout()
  router.push({ name: 'login' })
}

const initials = computed(() => {
  const name = auth.user?.name || ''
  const parts = name.trim().split(/\s+/).filter(Boolean)
  return parts.slice(0, 2).map(p => p[0]?.toUpperCase()).join('') || 'U'
})

const nav = [
  { label: 'Tableau de bord', to: '/dashboard' },
]

const isActive = (to) => route.path === to || route.path.startsWith(to + '/')
</script>

<template>
  <div class="min-h-screen bg-emerald-50/40">
    <!-- Topbar -->
    <header class="sticky top-0 z-20 bg-white/90 backdrop-blur border-b border-emerald-100">
      <div class="h-14 px-4 sm:px-6 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <img :src="logo" alt="AD2M" class="h-8 w-8 object-contain" draggable="false" />
          <div class="leading-tight">
            <div class="text-sm font-semibold text-slate-900">AD2M</div>
            <div class="text-xs text-slate-500">Gestion des missions</div>
          </div>
        </div>

        <div class="flex items-center gap-3">
          <div class="hidden sm:flex items-center gap-2 rounded-xl bg-emerald-50 border border-emerald-100 px-3 py-1.5">
            <div class="h-7 w-7 rounded-full bg-emerald-700 text-white text-xs font-semibold grid place-items-center">
              {{ initials }}
            </div>
            <div class="text-sm text-slate-700 max-w-[220px] truncate">
              {{ auth.user?.name ?? 'Utilisateur' }}
            </div>
          </div>

          <button class="text-sm font-medium text-emerald-700 hover:text-emerald-800 hover:underline" @click="doLogout">
            Déconnexion
          </button>
        </div>
      </div>
    </header>

    <div class="flex">
      <!-- Sidebar -->
      <aside class="w-64 hidden md:block bg-white border-r border-emerald-100 min-h-[calc(100vh-56px)]">
        <div class="p-4">
          <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">
            Navigation
          </div>

          <nav class="space-y-1">
            <RouterLink
              v-for="item in nav"
              :key="item.to"
              :to="item.to"
              class="group flex items-center gap-3 px-3 py-2 rounded-xl transition"
              :class="[
                isActive(item.to)
                  ? 'bg-emerald-50 text-emerald-800 border border-emerald-100'
                  : 'text-slate-700 hover:bg-slate-50'
              ]"
            >
              <span
                class="h-2 w-2 rounded-full"
                :class="isActive(item.to) ? 'bg-emerald-700' : 'bg-slate-300 group-hover:bg-slate-400'"
              ></span>
              <span class="text-sm font-medium">{{ item.label }}</span>
            </RouterLink>
          </nav>
        </div>
      </aside>

      <!-- Content -->
      <main class="flex-1 p-4 sm:p-6">
        <div class="max-w-6xl mx-auto">
          <router-view />
        </div>
      </main>
    </div>
  </div>
</template>
