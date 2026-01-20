<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const auth = useAuthStore()

const missions = ref([])
const loading = ref(false)
const error = ref('')

const q = ref('')
const status = ref('') // '' = tous
const onlyMine = ref(false)

const load = async () => {
  loading.value = true
  error.value = ''
  try {
    const res = await window.axios.get('/api/missions')
    missions.value = res.data?.missions ?? []
  } catch (e) {
    error.value = e?.response?.data?.message || 'Erreur chargement des missions.'
    missions.value = []
  } finally {
    loading.value = false
  }
}

onMounted(load)

// --- helpers
const normalize = (v) => String(v ?? '').toLowerCase().trim()

const statusLabel = (s) => {
  const k = normalize(s)
  const map = {
    brouillon: 'Brouillon',
    en_attente_ch: 'Soumise',
    valide_ch: 'Validée CH',
    valide_raf: 'Validée RAF',
    valide_cp: 'Approuvée',
    avance_payee: 'Avance payée',
    en_cours: 'En cours',
    cloturee: 'Clôturée',
    rejetee: 'Rejetée',
    rejeté: 'Rejetée',
    rejetee_ch: 'Rejetée',
    rejetee_raf: 'Rejetée',
    rejetee_cp: 'Rejetée',
  }
  if (k.startsWith('rejet')) return 'Rejetée'
  return map[k] || (s ?? '—')
}

const statusPillClass = (s) => {
  const k = normalize(s)
  if (k === 'brouillon') return 'bg-slate-100 text-slate-700'
  if (k === 'en_attente_ch') return 'bg-blue-100 text-blue-700'
  if (k.startsWith('rejet')) return 'bg-rose-100 text-rose-700'
  if (['valide_cp', 'avance_payee', 'en_cours', 'cloturee'].includes(k)) return 'bg-emerald-100 text-emerald-700'
  // validée ch/raf
  return 'bg-emerald-50 text-emerald-800'
}

const dateRange = (m) => {
  const d1 = m.date_debut || m.dateDebut || m.debut || ''
  const d2 = m.date_fin || m.dateFin || m.fin || ''
  if (!d1 && !d2) return '—'
  if (d1 && d2) return `${d1} au ${d2}`
  return d1 || d2
}

const money = (v) => {
  if (v === null || v === undefined || v === '') return '—'
  // simple format (tu peux améliorer plus tard)
  return `${v} Ar`
}

// options filtre statut (UI)
const statusOptions = [
  { value: '', label: 'Tous les statuts' },
  { value: 'brouillon', label: 'Brouillon' },
  { value: 'en_attente_ch', label: 'Soumise' },
  { value: 'valide_ch', label: 'Validée CH' },
  { value: 'valide_raf', label: 'Validée RAF' },
  { value: 'valide_cp', label: 'Approuvée' },
  { value: 'avance_payee', label: 'Avance payée' },
  { value: 'en_cours', label: 'En cours' },
  { value: 'cloturee', label: 'Clôturée' },
  { value: 'rejet', label: 'Rejetée' },
]

const filtered = computed(() => {
  const query = normalize(q.value)
  const s = normalize(status.value)

  return missions.value.filter((m) => {
    const obj = normalize(m.objet)
    const dest = normalize(m.destination)
    const desc = normalize(m.description)
    const st = normalize(m.statut_actuel)

    // checkbox "mes missions uniquement"
    if (onlyMine.value) {
      if (m.demandeur_id !== auth.user?.id) return false
    }

    // filtre statut
    if (s) {
      if (s === 'rejet') {
        if (!st.startsWith('rejet')) return false
      } else if (st !== s) {
        return false
      }
    }

    // recherche
    if (!query) return true
    return obj.includes(query) || dest.includes(query) || desc.includes(query)
  })
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-start justify-between gap-4">
      <div>
        <h1 class="text-3xl font-extrabold text-slate-900">Missions</h1>
        <p class="text-slate-600 mt-1">Gestion et suivi des missions</p>
      </div>

      <!-- bouton (pour l’instant on le laisse, il peut pointer vers create plus tard) -->
      <button
        class="px-4 py-2 rounded-xl bg-brand text-white font-semibold shadow-sm hover:opacity-95"
        @click="router.push({ name: 'missions' })"
        title="On ajoutera la création juste après"
      >
        + Nouvelle mission
      </button>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-2xl border shadow-sm p-5">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <!-- Search -->
        <div class="lg:col-span-2">
          <div class="relative">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
              🔎
            </span>
            <input
              v-model="q"
              type="text"
              placeholder="Rechercher par titre, destination, description..."
              class="w-full rounded-xl border border-slate-200 bg-white pl-10 pr-4 py-3 outline-none
                     focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
            />
          </div>
          <div class="mt-3 flex items-center gap-3 text-sm text-slate-600">
            <label class="inline-flex items-center gap-2 cursor-pointer select-none">
              <input v-model="onlyMine" type="checkbox" class="rounded border-slate-300" />
              Mes missions uniquement
            </label>
            <span class="text-slate-400">•</span>
            <span>{{ filtered.length }} mission(s) trouvée(s)</span>
          </div>
        </div>

        <!-- Status dropdown -->
        <div>
          <select
            v-model="status"
            class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 outline-none
                   focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
          >
            <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
              {{ opt.label }}
            </option>
          </select>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-2xl border shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b flex items-center justify-between">
        <div class="font-bold text-slate-900">Liste des missions</div>
        <button class="text-slate-600 hover:underline text-sm" @click="load" :disabled="loading">
          Rafraîchir
        </button>
      </div>

      <div v-if="loading" class="p-8 text-slate-500">Chargement…</div>

      <div v-else-if="error" class="p-6">
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
          {{ error }}
        </div>
      </div>

      <div v-else-if="filtered.length === 0" class="p-10 text-center text-slate-500">
        Aucune mission.
      </div>

      <div v-else class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-50 text-slate-600">
            <tr>
              <th class="text-left font-semibold px-5 py-3">MISSION</th>
              <th class="text-left font-semibold px-5 py-3">DESTINATION</th>
              <th class="text-left font-semibold px-5 py-3">DATES</th>
              <th class="text-left font-semibold px-5 py-3">BUDGET</th>
              <th class="text-left font-semibold px-5 py-3">RESPONSABLE</th>
              <th class="text-left font-semibold px-5 py-3">STATUT</th>
              <th class="text-right font-semibold px-5 py-3">ACTIONS</th>
            </tr>
          </thead>

          <tbody class="divide-y">
            <tr v-for="m in filtered" :key="m.id" class="hover:bg-slate-50">
              <!-- mission -->
              <td class="px-5 py-4">
                <div class="font-semibold text-slate-900">{{ m.objet || ('Mission #' + m.id) }}</div>
                <div class="text-slate-500 text-xs mt-1">
                  {{ m.description ? (m.description.length > 60 ? m.description.slice(0,60)+'…' : m.description) : '—' }}
                </div>
              </td>

              <!-- destination -->
              <td class="px-5 py-4 text-slate-700">
                {{ m.destination || '—' }}
              </td>

              <!-- dates -->
              <td class="px-5 py-4 text-slate-700">
                {{ dateRange(m) }}
              </td>

              <!-- budget -->
              <td class="px-5 py-4 text-slate-700">
                {{ money(m.budget) }}
              </td>

              <!-- responsable -->
              <td class="px-5 py-4">
                <div class="text-slate-900 font-medium">
                  {{ m.demandeur?.name || m.user?.name || m.responsable?.name || '—' }}
                </div>
                <div class="text-xs text-slate-500">
                  {{ m.demandeur?.matricule || m.user?.matricule || ' ' }}
                </div>
              </td>

              <!-- statut -->
              <td class="px-5 py-4">
                <span
                  class="inline-flex items-center px-3 py-1 rounded-full font-semibold text-xs"
                  :class="statusPillClass(m.statut_actuel)"
                >
                  {{ statusLabel(m.statut_actuel) }}
                </span>
              </td>

              <!-- actions -->
              <td class="px-5 py-4 text-right">
                <button
                  class="inline-flex items-center justify-center w-9 h-9 rounded-lg border hover:bg-white bg-slate-50"
                  title="Voir"
                  @click="router.push({ name: 'missions.show', params: { id: m.id } })"
                >
                  👁
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
