<script setup>
import { onMounted } from 'vue'
import { useAuthStore } from './stores/auth'

const auth = useAuthStore()

onMounted(async () => {
  // Charger l'utilisateur au démarrage (si session existe)
  await auth.fetchUser()
})
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <header class="bg-white border-b">
      <div class="max-w-5xl mx-auto px-4 py-3 flex items-center justify-between">
        <div class="font-bold">AD2M</div>

        <nav class="flex items-center gap-4">
          <router-link to="/home" class="text-sm text-indigo-700">Home</router-link>
          <router-link to="/about" class="text-sm text-indigo-700">About</router-link>

          <span v-if="auth.user" class="text-sm text-gray-600">
            Connecté : <strong>{{ auth.user.name }}</strong>
          </span>

          <button
            v-if="auth.user"
            class="text-sm px-3 py-1 rounded bg-gray-900 text-white"
            @click="auth.logout()"
          >
            Logout
          </button>
        </nav>
      </div>
    </header>

    <main class="max-w-5xl mx-auto px-4 py-6">
      <router-view />
    </main>
  </div>
</template>
