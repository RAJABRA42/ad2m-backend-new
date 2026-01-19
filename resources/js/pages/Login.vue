
<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const router = useRouter();
const auth = useAuthStore();

const email = ref('');
const password = ref('');
const error = ref('');
const loading = ref(false);

const validateEmail = (val) => /.+@.+\..+/.test(val);

const handleLogin = async () => {
  error.value = '';
  if (!email.value || !password.value) {
    error.value = 'Veuillez remplir tous les champs.';
    return;
  }
  if (!validateEmail(email.value)) {
    error.value = 'Veuillez entrer un email valide.';
    return;
  }
  loading.value = true;
  const ok = await auth.login({ email: email.value, password: password.value });
  loading.value = false;
  if (ok) {
    router.push('/home');
  } else {
    error.value = 'Email ou mot de passe incorrect.';
  }
};
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-indigo-50 to-blue-100">
    <div class="w-full max-w-md bg-white rounded-xl shadow-lg p-8">
      <div class="flex flex-col items-center mb-6">
        <h1 class="text-2xl font-bold text-indigo-700">Connexion à AD2M</h1>
      </div>
      <form @submit.prevent="handleLogin" class="space-y-5">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
          <input
            v-model="email"
            type="email"
            autocomplete="username"
            class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-400"
            placeholder="votre@email.com"
            required
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Mot de passe</label>
          <input
            v-model="password"
            type="password"
            autocomplete="current-password"
            class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-400"
            placeholder="••••••••"
            required
          />
        </div>
        <div v-if="error" class="text-red-600 text-sm text-center">{{ error }}</div>
        <button
          type="submit"
          class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-2 rounded font-semibold transition"
          :disabled="loading || auth.loading"
        >
          <span v-if="loading || auth.loading">Connexion...</span>
          <span v-else>Se connecter</span>
        </button>
      </form>
      <div class="text-xs text-gray-400 mt-6 text-center">
        Besoin d'un compte test ? Contactez l'administrateur.<br />
        <span class="italic">© {{ new Date().getFullYear() }} AD2M</span>
      </div>
    </div>
  </div>
</template>
