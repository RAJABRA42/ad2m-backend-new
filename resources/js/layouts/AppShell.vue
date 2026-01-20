<script setup>
import { useAuthStore } from '../stores/auth'
import { useRouter } from 'vue-router'

const auth = useAuthStore()
const router = useRouter()

const logout = async () => {
  await auth.logout()
  router.push('/')
}
</script>

<template>
  <div class="min-h-screen bg-gray-100 flex flex-col">
    <!-- TOPBAR -->
    <header class="bg-white border-b h-14 flex items-center">
      <div class="w-full px-6 flex justify-between items-center">
        <div class="flex items-center gap-3 font-semibold">
          <div class="w-8 h-8 bg-blue-600 rounded"></div>
          <span>AD2M – Gestion des missions</span>
        </div>

        <div class="flex items-center gap-4 text-sm">
          <span class="bg-gray-100 px-3 py-1 rounded">
            {{ auth.user?.name ?? 'Utilisateur' }}
          </span>
          <button
            class="text-red-600 hover:underline"
            @click="logout"
          >
            Déconnexion
          </button>
        </div>
      </div>
    </header>

    <!-- BODY -->
    <div class="flex flex-1">
      <!-- MENU -->
      <aside class="w-60 bg-white border-r p-4 space-y-2">
        <RouterLink
          to="/dashboard"
          class="block px-3 py-2 rounded hover:bg-gray-100"
        >
          Tableau de bord
        </RouterLink>

        <RouterLink
          to="/missions"
          class="block px-3 py-2 rounded hover:bg-gray-100"
        >
          Missions
        </RouterLink>

        <RouterLink
          to="/validation"
          class="block px-3 py-2 rounded hover:bg-gray-100"
        >
          Validation
        </RouterLink>
      </aside>

      <!-- CONTENU -->
      <main class="flex-1 p-6">
        <router-view />
      </main>
    </div>
  </div>
</template>
