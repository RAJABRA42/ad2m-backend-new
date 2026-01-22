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

// éviter double-clic
const busyId = ref(null)

// modal rejet (custom)
const rejectOpen = ref(false)
const rejectTarget = ref(null)

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

const isAdmin = computed(() => hasRole('admin', 'administrateur'))

const isValidator = computed(() =>
  hasRole('administrateur', 'admin', 'chef_hierarchique', 'raf', 'coordonnateur_de_projet')
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
onMounted(() => (selectedKey.value = rolePendingKey.value))

const activeKey = computed(() => selectedKey.value || rolePendingKey.value)

// ---------- LABELS ----------
const stageLabel = (k) => {
  const map = {
    en_attente_ch: 'En attente Chef Hiérarchique',
    valide_ch: 'En attente RAF',
    valide_raf: 'En attente Chef de Projet',
    valide_cp: 'Transmises à l’ACCP',
  }
  return map[k] ?? k
}

const norm = (v) => String(v ?? '').toLowerCase().trim()

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
  return map[norm(s)] ?? s
}

const badgeClass = (s) => {
  const k = norm(s)
  if (k === 'en_attente_ch') return 'bg-blue-100 text-blue-700'
  if (k === 'valide_ch' || k === 'valide_raf') return 'bg-amber-100 text-amber-700'
  if (k === 'valide_cp') return 'bg-indigo-100 text-indigo-700'
  if (k === 'brouillon') return 'bg-slate-100 text-slate-700'
  return 'bg-slate-100 text-slate-700'
}

// ---------- LOAD ----------
const load = async () => {
  loading.value = true
  error.value = ''
  actionMsg.value = ''
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

// ---------- FILTRAGE FILE "A TRAITER" ----------
const filtered = computed(() => {
  if (!isValidator.value || !activeKey.value) return []
  const key = norm(activeKey.value)
  const query = norm(q.value)

  let arr = missions.value.filter(m => norm(m?.statut_actuel) === key)

  if (query) {
    arr = arr.filter(m => {
      const blob = [
        m?.objet,
        m?.destination,
        m?.motif,
        m?.demandeur?.name,
        m?.demandeur?.matricule,
        prettyStatus(m?.statut_actuel),
      ].filter(Boolean).join(' ').toLowerCase()
      return blob.includes(query)
    })
  }

  return arr.sort((a, b) => (b.id ?? 0) - (a.id ?? 0))
})

// ---------- HISTORIQUE (MES VALIDATIONS) ----------
const historyTitle = computed(() => {
  if (isAdmin.value) return 'Historique (validations système)'
  if (hasRole('chef_hierarchique')) return 'Historique (mes validations CH)'
  if (hasRole('raf')) return 'Historique (mes validations RAF)'
  if (hasRole('coordonnateur_de_projet')) return 'Historique (mes validations CP)'
  return 'Historique'
})

const history = computed(() => {
  if (!isValidator.value) return []
  const uid = Number(auth.user?.id || 0)
  const query = norm(q.value)

  let arr = missions.value

  if (!isAdmin.value) {
    if (hasRole('chef_hierarchique')) arr = arr.filter(m => Number(m?.validation_ch_id) === uid)
    else if (hasRole('raf')) arr = arr.filter(m => Number(m?.validation_raf_id) === uid)
    else if (hasRole('coordonnateur_de_projet')) arr = arr.filter(m => Number(m?.validation_cp_id) === uid)
    else arr = []
  } else {
    // admin : tout ce qui a déjà une validation
    arr = arr.filter(m => m?.validation_ch_id || m?.validation_raf_id || m?.validation_cp_id)
  }

  if (query) {
    arr = arr.filter(m => {
      const blob = [
        m?.objet,
        m?.destination,
        m?.demandeur?.name,
        prettyStatus(m?.statut_actuel),
      ].filter(Boolean).join(' ').toLowerCase()
      return blob.includes(query)
    })
  }

  return arr.sort((a, b) => (b.id ?? 0) - (a.id ?? 0)).slice(0, 30)
})

// ---------- NAV "VOIR" (LECTURE SEULE) ----------
const goShow = (m) => {
  if (!m?.id) return
  router.push({ name: 'missions.show', params: { id: m.id }, query: { mode: 'view' } })
}

// ---------- ENDPOINT VALIDATION ----------
const endpointForValidate = computed(() => {
  if (activeKey.value === 'en_attente_ch') return 'valider-ch'
  if (activeKey.value === 'valide_ch') return 'valider-raf'
  if (activeKey.value === 'valide_raf') return 'valider-cp'
  return null
})

// ---------- ACTIONS ----------
const doValidate = async (m) => {
  if (!m?.id) return

  // FIN WORKFLOW → ACCP
  if (String(activeKey.value) === 'valide_cp') {
    router.push({ name: 'accp.mission', params: { id: m.id } })
    return
  }

  const endpoint = endpointForValidate.value
  if (!endpoint) return

  busyId.value = m.id
  actionMsg.value = ''
  try {
    await window.axios.post(`/api/missions/${m.id}/${endpoint}`)
    actionMsg.value = '✅ Validation effectuée.'
    await load()
  } catch (e) {
    actionMsg.value = e?.response?.data?.message || 'Action refusée.'
  } finally {
    busyId.value = null
  }
}

// --- REJETER (modal)
const askReject = (m) => {
  rejectTarget.value = m
  rejectOpen.value = true
}

const doReject = async () => {
  const m = rejectTarget.value
  if (!m?.id) return

  busyId.value = m.id
  actionMsg.value = ''
  try {
    await window.axios.post(`/api/missions/${m.id}/rejeter`)
    actionMsg.value = '✅ Mission rejetée (retour en brouillon).'
    rejectOpen.value = false
    rejectTarget.value = null
    await load()
  } catch (e) {
    actionMsg.value = e?.response?.data?.message || 'Rejet refusé.'
  } finally {
    busyId.value = null
  }
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex justify-between items-start gap-4">
      <div>
        <h1 class="text-3xl font-extrabold text-slate-900">Validation des missions</h1>
        <p class="text-slate-600 mt-1">{{ stageLabel(activeKey) }}</p>
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
      <select v-model="selectedKey" class="mt-2 w-full rounded-xl border px-4 py-2">
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

    <!-- TABLE A TRAITER -->
    <div class="bg-white rounded-2xl border overflow-hidden">
      <div class="px-6 py-4 border-b font-bold text-slate-900">À traiter</div>

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
            <th class="px-5 py-3 text-right">Actions</th>
          </tr>
        </thead>

        <tbody class="divide-y">
          <tr v-for="m in filtered" :key="m.id" class="hover:bg-slate-50">
            <td class="px-5 py-4 font-semibold">
              {{ m.objet || ('Mission #' + m.id) }}
            </td>

            <td class="px-5 py-4">{{ m.destination }}</td>

            <td class="px-5 py-4">{{ m.demandeur?.name }}</td>

            <td class="px-5 py-4">
              <span class="px-3 py-1 rounded-full text-xs font-semibold" :class="badgeClass(m.statut_actuel)">
                {{ prettyStatus(m.statut_actuel) }}
              </span>
            </td>

            <td class="px-5 py-4">
              <div class="flex items-center justify-end gap-2">
                <!-- 👁 Voir (lecture seule) -->
                <button
                  class="w-9 h-9 rounded-lg hover:bg-slate-100 flex items-center justify-center text-slate-700"
                  title="Voir"
                  @click="goShow(m)"
                >
                  👁
                </button>

                <!-- Valider/Rejeter (sauf file ACCP) -->
                <template v-if="activeKey !== 'valide_cp'">
                  <button
                    class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-semibold disabled:opacity-60"
                    :disabled="busyId === m.id"
                    @click="doValidate(m)"
                  >
                    Valider
                  </button>

                  <button
                    class="px-4 py-2 rounded-lg border border-rose-300 text-rose-700 text-sm font-semibold hover:bg-rose-50 disabled:opacity-60"
                    :disabled="busyId === m.id"
                    @click="askReject(m)"
                  >
                    Rejeter
                  </button>
                </template>

                <template v-else>
                  <button
                    class="px-4 py-2 rounded-lg bg-slate-900 text-white text-sm font-semibold"
                    @click="doValidate(m)"
                  >
                    Ouvrir ACCP
                  </button>
                </template>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- HISTORIQUE -->
    <div class="bg-white rounded-2xl border overflow-hidden">
      <div class="px-6 py-4 border-b flex items-center justify-between">
        <div class="font-bold text-slate-900">{{ historyTitle }}</div>
        <div class="text-xs text-slate-500">Basé sur validation_ch_id / validation_raf_id / validation_cp_id</div>
      </div>

      <div v-if="history.length === 0" class="p-8 text-center text-slate-500">
        Aucun historique pour l’instant.
      </div>

      <div v-else class="overflow-x-auto">
        <table class="min-w-[980px] w-full">
          <thead class="bg-slate-50 text-xs font-semibold text-slate-500">
            <tr class="text-left">
              <th class="px-5 py-3">MISSION</th>
              <th class="px-5 py-3">DEMANDEUR</th>
              <th class="px-5 py-3">STATUT ACTUEL</th>
              <th class="px-5 py-3 text-right">VOIR</th>
            </tr>
          </thead>

          <tbody class="divide-y">
            <tr v-for="m in history" :key="m.id" class="hover:bg-slate-50">
              <td class="px-5 py-4 font-semibold text-slate-900">
                {{ m.objet || ('Mission #' + m.id) }}
              </td>
              <td class="px-5 py-4 text-slate-700">{{ m.demandeur?.name || '—' }}</td>
              <td class="px-5 py-4">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold" :class="badgeClass(m.statut_actuel)">
                  {{ prettyStatus(m.statut_actuel) }}
                </span>
              </td>
              <td class="px-5 py-4 text-right">
                <button
                  class="w-9 h-9 rounded-lg hover:bg-slate-100 inline-flex items-center justify-center text-slate-700"
                  title="Voir"
                  @click="goShow(m)"
                >
                  👁
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- MODAL REJET -->
    <div v-if="rejectOpen" class="fixed inset-0 z-50">
      <div class="absolute inset-0 bg-black/40" @click="rejectOpen = false"></div>
      <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="w-full max-w-md bg-white rounded-2xl shadow-lg border p-6">
          <div class="text-lg font-bold text-slate-900">Rejeter la mission ?</div>
          <p class="text-slate-600 mt-2">
            Cette action renverra la mission en <b>brouillon</b>. Le missionnaire pourra la modifier puis re-soumettre.
          </p>

          <div class="mt-5 flex justify-end gap-2">
            <button class="px-4 py-2 rounded-xl border bg-white hover:bg-slate-50" @click="rejectOpen = false">
              Annuler
            </button>
            <button
              class="px-4 py-2 rounded-xl bg-rose-600 text-white font-semibold hover:opacity-95 disabled:opacity-60"
              :disabled="busyId === (rejectTarget?.id ?? null)"
              @click="doReject"
            >
              {{ busyId === (rejectTarget?.id ?? null) ? 'Rejet…' : 'Rejeter' }}
            </button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>
