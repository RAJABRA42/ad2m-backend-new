<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const auth = useAuthStore()

const email = ref('')
const password = ref('')
const message = ref('')

const login = async () => {
  message.value = ''

  if (!email.value || !password.value) {
    message.value = 'Veuillez remplir tous les champs.'
    return
  }

  const ok = await auth.login({ email: email.value, password: password.value })

  if (ok) {
    router.push('/home')
  } else {
    message.value = 'Email ou mot de passe incorrect.'
  }
}
</script>

<template>
  <div class="min-h-[70vh] flex items-center justify-center">
    <div class="w-full max-w-sm bg-white rounded-lg shadow p-6">
      <h1 class="text-xl font-bold text-center mb-4">AD2M - Connexion</h1>

      <div v-if="message" class="mb-3 text-sm text-red-600">
        {{ message }}
      </div>

      <form @submit.prevent="login" class="space-y-3">
        <div>
          <label class="block text-sm mb-1">Email</label>
          <input
            class="w-full border rounded px-3 py-2"
            type="email"
            v-model="email"
            autocomplete="username"
            required
          />
        </div>

        <div>
          <label class="block text-sm mb-1">Mot de passe</label>
          <input
            class="w-full border rounded px-3 py-2"
            type="password"
            v-model="password"
            autocomplete="current-password"
            required
          />
        </div>

        <button
          type="submit"
          class="w-full bg-indigo-600 text-white py-2 rounded font-semibold"
          :disabled="auth.loading"
        >
          {{ auth.loading ? 'Connexion...' : 'Se connecter' }}
        </button>
      </form>

      <p class="text-xs text-gray-500 mt-4">
        Astuce : crée un utilisateur test en DB si besoin.
      </p>
    </div>
  </div>
</template>
