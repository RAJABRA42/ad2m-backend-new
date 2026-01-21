<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const auth = useAuthStore()

const missions = ref([])
const loading = ref(false)
const error = ref('')
const actionMsg = ref('')
const q = ref('')

// ---------- ROLES ----------
const roleNames = computed(() => {
  const roles = auth.user?.roles ?? []
  return roles
    .map(r => (typeof r === 'string' ? r : r?.name))
    .filter(Boolean)
    .map(r => String(r).toLowerCase())
})

const hasRole = (...names) => {
  const set = new Set(roleNames.value)
  return names.some(n => set.has(String(n).toLowerCase()))
}

const isAdmin = computed(() =>
  hasRole('admin', 'administrateur')
)

const isValidator = computed(() =>
  hasRole(
    'administrateur',
    'admin',
    'chef_hierarchique',
    'raf',
    'coordonnateur_de_projet'
  )
)

// ---------- FILE À TRAITER SELON RÔLE ----------
const rolePendingKey = computed(() => {
  if (hasRole('chef_hierarchique')) return 'en_attente_ch'
  if (hasRole('raf')) return 'valide_ch'
  if (hasRole('coordonnateur_de_projet')) return 'valide_raf'
  if (isAdmin.value) return 'en_attente_ch'
  return null
})

// Admin peut changer la file
const selectedKey = ref(null)

onMounted(() => {
  selectedKey.value = rolePendingKey.value
})

const activeKey = computed(() =>
  selectedKey.value || rolePendingKey.value
)

// ---------- LABELS ----------
const stageLabel = (k) => {
  const map = {
    en_attente_ch: 'En attente Chef Hiérarchique',
    valide_ch: 'En attente RAF',
    valide_raf: 'En attente Chef de Projet',
    valide_cp: 'Transmise à la comptabilité (ACCP)',
  }
  return map[k] ?? k
}

const prettyStatus = (s) => {
  const map = {
    brouillon: 'Brouillon',
    en_attente_ch: 'En attente CH',
    valide_ch: 'En attente RAF',
    valide_raf: 'En attente CP',
    valide_cp: 'Validée CP (vers ACCP)',
    avance_payee: 'Avance payée',
    en_cours: 'En cours',
    cloturee: 'Clôturée',
  }
  return map[String(s || '').toLowerCase()] ?? s
}

const badgeClass = (s) => {
  const k = String(s || '').toLowerCase()
  if (k === 'en_attente_ch') return 'bg-blue-100 text-blue-700'
  if (k === 'valide_ch' || k === 'valide_raf') return 'bg-amber-100 text-amber-700'
  if (k === 'valide_cp') return 'bg-indigo-100 text-indigo-700'
  return 'bg-slate-100 text-slate-700'
}

// ---------- LOAD ----------
const load = async () => {
  loading.value = true
  error.value = ''
  try {
    const res = await window.axios.get('/api/missions')
    missions.value = res.data?.missions ?? res.data ?? []
  } catch (e) {
    missions.value = []
    error.value = e?.response?.data?.message || 'Erreur chargement.'
  } finally {
    loading.value = false
  }
}

onMounted(load)

// ---------- FILTRAGE ----------
const filtered = computed(() => {
  if (!isValidator.value || !activeKey.value) return []

  const key = String(activeKey.value).toLowerCase()
  const query = q.value.trim().toLowerCase()

  let arr = missions.value.filter(
    m => String(m?.statut_actuel).toLowerCase() === key
  )

  if (query) {
    arr = arr.filter(m => {
      const blob = [
        m?.objet,
        m?.destination,
        m?.motif,
        m?.demandeur?.name,
        m?.demandeur?.matricule,
        prettyStatus(m?.statut_actuel),
      ].join(' ').toLowerCase()
      return blob.includes(query)
    })
  }

  return arr.sort((a, b) => b.id - a.id)
})

// ---------- ENDPOINT DE VALIDATION ----------
const endpointForValidate = computed(() => {
  if (activeKey.value === 'en_attente_ch') return 'valider-ch'
  if (activeKey.value === 'valide_ch') return 'valider-raf'
  if (activeKey.value === 'valide_raf') return 'valider-cp'
  return null
})

// ---------- ACTION ----------
const doValidate = async (m) => {
  if (!m?.id) return

  // ✅ FIN DU WORKFLOW → ACCP
  if (String(activeKey.value) === 'valide_cp') {
    router.push({ name: 'accp.mission', params: { id: m.id } })
    return
  }

  const endpoint = endpointForValidate.value
  if (!endpoint) return

  actionMsg.value = ''
  try {
    await window.axios.post(`/api/missions/${m.id}/${endpoint}`)
    actionMsg.value = 'Validation effectuée.'
    await load()
  } catch (e) {
    actionMsg.value = e?.response?.data?.message || 'Action refusée.'
  }
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex justify-between items-start gap-4">
      <div>
        <h1 class="text-3xl font-extrabold text-slate-900">Validation des missions</h1>
        <p class="text-slate-600 mt-1">
          {{ stageLabel(activeKey) }}
        </p>
      </div>

      <button
        class="px-3 py-2 rounded-xl border bg-white hover:bg-slate-50"
        @click="load"
        :disabled="loading"
      >
        Rafraîchir
      </button>
    </div>

    <!-- ADMIN : changer la file -->
    <div v-if="isAdmin" class="bg-white rounded-2xl border p-4">
      <label class="text-sm font-semibold text-slate-700">Étape :</label>
      <select
        v-model="selectedKey"
        class="mt-2 w-full rounded-xl border px-4 py-2"
      >
        <option value="en_attente_ch">En attente CH</option>
        <option value="valide_ch">En attente RAF</option>
        <option value="valide_raf">En attente CP</option>
        <option value="valide_cp">Transmises à l’ACCP</option>
      </select>
    </div>

    <!-- Recherche -->
    <input
      v-model="q"
      placeholder="Rechercher mission..."
      class="w-full rounded-xl border px-4 py-3"
    />

    <div v-if="actionMsg" class="bg-slate-50 border rounded-xl p-3 text-sm">
      {{ actionMsg }}
    </div>

    <!-- TABLE -->
    <div class="bg-white rounded-2xl border overflow-hidden">
      <div v-if="loading" class="p-6 text-slate-500">Chargement…</div>
      <div v-else-if="filtered.length === 0" class="p-8 text-center text-slate-500">
        Aucune mission à traiter
      </div>

      <table v-else class="w-full">
        <thead class="bg-slate-50 text-xs font-semibold text-slate-500">
          <tr>
            <th class="px-5 py-3 text-left">Mission</th>
            <th class="px-5 py-3">Destination</th>
            <th class="px-5 py-3">Demandeur</th>
            <th class="px-5 py-3">Statut</th>
            <th class="px-5 py-3 text-right">Action</th>
          </tr>
        </thead>

        <tbody class="divide-y">
          <tr v-for="m in filtered" :key="m.id" class="hover:bg-slate-50">
            <td class="px-5 py-4 font-semibold">
              {{ m.objet || ('Mission #' + m.id) }}
            </td>

            <td class="px-5 py-4">{{ m.destination }}</td>

            <td class="px-5 py-4">
              {{ m.demandeur?.name }}
            </td>

            <td class="px-5 py-4">
              <span
                class="px-3 py-1 rounded-full text-xs font-semibold"
                :class="badgeClass(m.statut_actuel)"
              >
                {{ prettyStatus(m.statut_actuel) }}
              </span>
            </td>

            <td class="px-5 py-4 text-right">
              <button
                class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-semibold"
                @click="doValidate(m)"
              >
                {{ activeKey === 'valide_cp' ? 'Ouvrir ACCP' : 'Valider' }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
