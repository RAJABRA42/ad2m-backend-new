<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()
const id = computed(() => Number(route.params.id))

const loading = ref(false)
const error = ref('')
const mission = ref(null)

const load = async () => {
  loading.value = true
  error.value = ''
  mission.value = null

  try {
    // ✅ si tu as GET /api/missions/{id}, c'est le mieux
    try {
      const r = await window.axios.get(`/api/missions/${id.value}`)
      mission.value = r.data?.mission ?? r.data
      return
    } catch (e) {
      // fallback si l’endpoint n’existe pas encore
    }

    const r = await window.axios.get('/api/missions')
    const list = r.data?.missions ?? r.data ?? []
    mission.value = list.find(m => Number(m.id) === id.value) || null

    if (!mission.value) error.value = 'Mission introuvable.'
  } catch (e) {
    error.value = e?.response?.data?.message || 'Erreur chargement mission.'
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl font-extrabold text-slate-900">Détail mission</h1>
        <p class="text-slate-600">Mission #{{ id }}</p>
      </div>

      <button class="px-3 py-2 rounded-xl border bg-white hover:bg-slate-50" @click="router.back()">
        ← Retour
      </button>
    </div>

    <div v-if="loading" class="bg-white rounded-2xl border shadow-sm p-6 text-slate-500">Chargement…</div>
    <div v-else-if="error" class="bg-white rounded-2xl border shadow-sm p-6 text-rose-700">{{ error }}</div>

    <div v-else class="bg-white rounded-2xl border shadow-sm p-6 space-y-3">
      <div class="text-lg font-bold text-slate-900">
        {{ mission?.objet || mission?.titre || ('Mission #' + mission?.id) }}
      </div>

      <div class="text-sm text-slate-700">
        <div><b>Destination:</b> {{ mission?.destination || '—' }}</div>
        <div><b>Statut:</b> {{ mission?.statut_actuel || '—' }}</div>
        <div><b>Demandeur:</b> {{ mission?.demandeur?.name || '—' }}</div>
        <div><b>Description:</b> {{ mission?.description || '—' }}</div>
      </div>
    </div>
  </div>
</template>
