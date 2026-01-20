<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const auth = useAuthStore()

const email = ref('')
const password = ref('')
const error = ref('')

const handleLogin = async () => {
  error.value = ''

  if (!email.value || !password.value) {
    error.value = 'Veuillez remplir tous les champs.'
    return
  }

  const ok = await auth.login({ email: email.value, password: password.value })
  if (ok) router.push({ name: 'dashboard' })
  else error.value = 'Identifiants incorrects.'
}
</script>

<template>
  <form @submit.prevent="handleLogin" class="space-y-5">
    <div>
      <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
      <input
        v-model="email"
        type="email"
        autocomplete="username"
        placeholder="votre@email.com"
        class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-900 shadow-sm outline-none transition
               focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
      />
    </div>

    <div>
      <label class="block text-sm font-medium text-slate-700 mb-1">Mot de passe</label>
      <input
        v-model="password"
        type="password"
        autocomplete="current-password"
        placeholder="••••••••"
        class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-900 shadow-sm outline-none transition
               focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
      />
    </div>

    <div v-if="error" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
      {{ error }}
    </div>

    <!-- Bouton brand AD2M (vert -> vert -> orange) -->
    <button
      type="submit"
      :disabled="auth.loading"
      class="w-full rounded-2xl px-4 py-3 font-semibold text-white shadow-xl transition
             hover:brightness-110 active:scale-[0.99]
             disabled:opacity-60 disabled:cursor-not-allowed"
      style="background: linear-gradient(90deg, var(--ad2m-green) 0%, var(--ad2m-green2) 70%, var(--ad2m-orange) 130%);"
    >
      <span v-if="auth.loading">Connexion...</span>
      <span v-else>Se connecter</span>
    </button>
  </form>
</template>
