<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const auth = useAuthStore()

const missions = ref([])
const loading = ref(false)
const error = ref('')
const notice = ref('')
const apiError = ref('')
const q = ref('')

// tabs
const tab = ref('queue') // queue | history | all

// busy action
const busyId = ref(null)

// modal rejet
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
const isCH = computed(() => hasRole('chef_hierarchique'))
const isRAF = computed(() => hasRole('raf'))
const isCP = computed(() => hasRole('coordonnateur_de_projet'))

// ---------- FILE À TRAITER ----------
const rolePendingKey = computed(() => {
  if (isCH.value) return 'en_attente_ch'
  if (isRAF.value) return 'valide_ch'
  if (isCP.value) return 'valide_raf'
  if (isAdmin.value) return 'en_attente_ch'
  return null
})

// Admin peut choisir une file
const selectedKey = ref(null)
onMounted(() => {
  selectedKey.value = rolePendingKey.value
})
const activeKey = computed(() => selectedKey.value || rolePendingKey.value)

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
  return map[norm(s)] ?? (s || '—')
}

const badgeClass = (s) => {
  const k = norm(s)
  if (k === 'brouillon') return 'bg-slate-100 text-slate-700'
  if (k === 'en_attente_ch') return 'bg-blue-100 text-blue-700'
  if (k === 'valide_ch' || k === 'valide_raf' || k === 'valide_cp') return 'bg-amber-100 text-amber-700'
  if (k === 'avance_payee') return 'bg-emerald-100 text-emerald-700'
  if (k === 'en_cours') return 'bg-indigo-100 text-indigo-700'
  if (k === 'cloturee') return 'bg-slate-200 text-slate-700'
  return 'bg-slate-100 text-slate-700'
}

// ---------- LOAD ----------
const load = async () => {
  loading.value = true
  error.value = ''
  notice.value = ''
  apiError.value = ''
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

// ---------- SEARCH ----------
const matchesSearch = (m) => {
  const query = norm(q.value)
  if (!query) return true
  const blob = [
    m?.objet,
    m?.destination,
    m?.motif,
    m?.demandeur?.name,
    m?.demandeur?.matricule,
    prettyStatus(m?.statut_actuel),
  ].filter(Boolean).join(' ').toLowerCase()
  return blob.includes(query)
}

const sortDesc = (a, b) => (Number(b?.id) || 0) - (Number(a?.id) || 0)

// ---------- QUEUE ----------
const queue = computed(() => {
  if (!activeKey.value) return []
  return missions.value
    .filter(m => norm(m?.statut_actuel) === norm(activeKey.value))
    .filter(matchesSearch)
    .sort(sortDesc)
})

// ---------- HISTORIQUE (par validateur) ----------
const meId = computed(() => Number(auth.user?.id || 0))

const history = computed(() => {
  const uid = meId.value
  if (!uid) return []

  let arr = []

  // ✅ “uniquement les missions qui lui sont soumises” :
  // on se base sur validation_*_id = “il a traité” (valider ou rejeter)
  if (isCH.value) {
    arr = missions.value.filter(m => Number(m?.validation_ch_id) === uid)
  } else if (isRAF.value) {
    arr = missions.value.filter(m => Number(m?.validation_raf_id) === uid)
  } else if (isCP.value) {
    arr = missions.value.filter(m => Number(m?.validation_cp_id) === uid)
  } else if (isAdmin.value) {
    arr = missions.value.filter(m =>
      Number(m?.validation_ch_id) === uid ||
      Number(m?.validation_raf_id) === uid ||
      Number(m?.validation_cp_id) === uid
    )
  }

  // on enlève celles encore “dans sa file à traiter” (sinon doublon)
  arr = arr.filter(m => norm(m?.statut_actuel) !== norm(activeKey.value))

  return arr.filter(matchesSearch).sort(sortDesc)
})

const decisionLabel = (m) => {
  // ✅ si rejet : on retourne au brouillon
  if (norm(m?.statut_actuel) === 'brouillon') return 'Rejetée (retournée)'
  return 'Validée (traitée)'
}

// ---------- RAF : TOUTES + filtre ----------
const statusFilter = ref('all')

const allMissions = computed(() => {
  let arr = missions.value.slice()
  if (statusFilter.value !== 'all') {
    arr = arr.filter(m => norm(m?.statut_actuel) === norm(statusFilter.value))
  }
  return arr.filter(matchesSearch).sort(sortDesc)
})

// ---------- ENDPOINT VALIDATION ----------
const endpointForValidate = computed(() => {
  if (activeKey.value === 'en_attente_ch') return 'valider-ch'
  if (activeKey.value === 'valide_ch') return 'valider-raf'
  if (activeKey.value === 'valide_raf') return 'valider-cp'
  return null
})

// ---------- NAV (lecture seule) ----------
const viewMission = (m) => {
  router.push({ name: 'missions.show', params: { id: m.id }, query: { mode: 'view' } })
}

// ---------- ACTIONS ----------
const doValidate = async (m) => {
  if (!m?.id) return

  // fin workflow → ACCP
  if (String(activeKey.value) === 'valide_cp') {
    router.push({ name: 'accp.mission', params: { id: m.id } })
    return
  }

  const endpoint = endpointForValidate.value
  if (!endpoint) return

  busyId.value = m.id
  notice.value = ''
  apiError.value = ''
  try {
    await window.axios.post(`/api/missions/${m.id}/${endpoint}`)
    await load()
    notice.value = '✅ Validée. (Elle reste dans Historique avec “Valider” désactivé)'
  } catch (e) {
    apiError.value = e?.response?.data?.message || 'Action refusée.'
  } finally {
    busyId.value = null
  }
}

const askReject = (m) => {
  rejectTarget.value = m
  rejectOpen.value = true
}

const doReject = async () => {
  const m = rejectTarget.value
  if (!m?.id) return

  busyId.value = m.id
  notice.value = ''
  apiError.value = ''
  try {
    await window.axios.post(`/api/missions/${m.id}/rejeter`)
    rejectOpen.value = false
    rejectTarget.value = null
    await load()
    notice.value = '✅ Rejetée → retour au missionnaire (et conservée dans Historique).'
  } catch (e) {
    apiError.value = e?.response?.data?.message || 'Erreur rejet.'
  } finally {
    busyId.value = null
  }
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex justify-between items-start gap-4">
      <div>
        <h1 class="text-3xl font-extrabold text-slate-900">Workflow validations</h1>
        <p class="text-slate-600 mt-1">Voir mission = lecture seule. Valider / rejeter depuis ici.</p>
      </div>

      <button class="px-3 py-2 rounded-xl border bg-white hover:bg-slate-50" @click="load" :disabled="loading">
        Rafraîchir
      </button>
    </div>

    <div v-if="notice" class="rounded-2xl border bg-emerald-50 border-emerald-200 px-5 py-3 text-emerald-800">
      {{ notice }}
    </div>
    <div v-if="apiError" class="rounded-2xl border bg-rose-50 border-rose-200 px-5 py-3 text-rose-800">
      {{ apiError }}
    </div>

    <div class="bg-white rounded-2xl border shadow-sm p-4 space-y-3">
      <div class="flex flex-col md:flex-row md:items-center gap-3 md:justify-between">
        <div class="flex items-center gap-2">
          <button class="px-4 py-2 rounded-xl border bg-white hover:bg-slate-50" :class="tab==='queue' ? 'border-slate-900' : ''" @click="tab='queue'">
            À traiter
          </button>

          <button class="px-4 py-2 rounded-xl border bg-white hover:bg-slate-50" :class="tab==='history' ? 'border-slate-900' : ''" @click="tab='history'">
            Historique
          </button>

          <button v-if="isRAF || isAdmin" class="px-4 py-2 rounded-xl border bg-white hover:bg-slate-50" :class="tab==='all' ? 'border-slate-900' : ''" @click="tab='all'">
            Toutes (RAF)
          </button>
        </div>

        <div class="flex flex-col md:flex-row gap-2 md:items-center">
          <select
            v-if="isAdmin && tab==='queue'"
            v-model="selectedKey"
            class="rounded-xl border border-slate-200 bg-white px-3 py-2 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
          >
            <option value="en_attente_ch">En attente CH</option>
            <option value="valide_ch">En attente RAF</option>
            <option value="valide_raf">En attente CP</option>
            <option value="valide_cp">Vers ACCP</option>
          </select>

          <select
            v-if="(isRAF || isAdmin) && tab==='all'"
            v-model="statusFilter"
            class="rounded-xl border border-slate-200 bg-white px-3 py-2 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
          >
            <option value="all">Tous statuts</option>
            <option value="brouillon">Brouillon</option>
            <option value="en_attente_ch">En attente CH</option>
            <option value="valide_ch">En attente RAF</option>
            <option value="valide_raf">En attente CP</option>
            <option value="valide_cp">En attente ACCP</option>
            <option value="avance_payee">Avance payée</option>
            <option value="en_cours">En cours</option>
            <option value="cloturee">Clôturée</option>
          </select>

          <input
            v-model="q"
            type="text"
            placeholder="Rechercher (objet, destination, demandeur, statut...)"
            class="w-full md:w-[420px] rounded-xl border border-slate-200 bg-white px-4 py-3 outline-none
                   focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
          />
        </div>
      </div>

      <div v-if="tab==='queue'" class="text-sm text-slate-600">
        File : <b>{{ stageLabel(activeKey) }}</b>
      </div>
    </div>

    <div class="bg-white rounded-2xl border shadow-sm overflow-hidden">
      <div v-if="loading" class="p-8 text-slate-500">Chargement…</div>
      <div v-else-if="error" class="p-6 text-rose-700">{{ error }}</div>

      <!-- QUEUE -->
      <template v-else-if="tab==='queue'">
        <div v-if="queue.length === 0" class="p-10 text-center text-slate-500">Aucune mission à traiter.</div>

        <div v-else class="overflow-x-auto">
          <table class="min-w-[980px] w-full">
            <thead class="bg-slate-50 text-xs font-semibold text-slate-500">
              <tr class="text-left">
                <th class="px-5 py-3">MISSION</th>
                <th class="px-5 py-3">DEMANDEUR</th>
                <th class="px-5 py-3">DESTINATION</th>
                <th class="px-5 py-3">STATUT</th>
                <th class="px-5 py-3 text-right">ACTIONS</th>
              </tr>
            </thead>

            <tbody class="divide-y">
              <tr v-for="m in queue" :key="m.id" class="hover:bg-slate-50">
                <td class="px-5 py-4">
                  <div class="font-semibold text-slate-900">{{ m.objet || ('Mission #' + m.id) }}</div>
                  <div class="text-sm text-slate-600 line-clamp-1">{{ m.motif || '—' }}</div>
                </td>

                <td class="px-5 py-4 text-slate-700">{{ m?.demandeur?.name || '—' }}</td>

                <td class="px-5 py-4 text-slate-700">
                  {{ m.destination || '—' }}
                  <div class="text-xs text-slate-500">{{ m.moyen_deplacement || '—' }}</div>
                </td>

                <td class="px-5 py-4">
                  <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold" :class="badgeClass(m.statut_actuel)">
                    {{ prettyStatus(m.statut_actuel) }}
                  </span>
                </td>

                <td class="px-5 py-4">
                  <div class="flex items-center justify-end gap-2">
                    <button class="px-3 py-2 rounded-lg border bg-white hover:bg-slate-50" @click="viewMission(m)">
                      👁 Voir
                    </button>

                    <button
                      class="px-3 py-2 rounded-lg bg-emerald-600 text-white text-sm font-semibold hover:opacity-95 disabled:opacity-60"
                      :disabled="busyId === m.id"
                      @click="doValidate(m)"
                    >
                      {{ busyId === m.id ? 'Validation…' : 'Valider' }}
                    </button>

                    <button
                      class="px-3 py-2 rounded-lg border border-rose-300 text-rose-700 text-sm font-semibold hover:bg-rose-50 disabled:opacity-60"
                      :disabled="busyId === m.id"
                      @click="askReject(m)"
                    >
                      Rejeter
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>

          </table>
        </div>
      </template>

      <!-- HISTORIQUE -->
      <template v-else-if="tab==='history'">
        <div v-if="history.length === 0" class="p-10 text-center text-slate-500">Aucun historique.</div>

        <div v-else class="overflow-x-auto">
          <table class="min-w-[980px] w-full">
            <thead class="bg-slate-50 text-xs font-semibold text-slate-500">
              <tr class="text-left">
                <th class="px-5 py-3">MISSION</th>
                <th class="px-5 py-3">DEMANDEUR</th>
                <th class="px-5 py-3">STATUT ACTUEL</th>
                <th class="px-5 py-3">DÉCISION</th>
                <th class="px-5 py-3 text-right">ACTIONS</th>
              </tr>
            </thead>

            <tbody class="divide-y">
              <tr v-for="m in history" :key="m.id" class="hover:bg-slate-50">
                <td class="px-5 py-4">
                  <div class="font-semibold text-slate-900">{{ m.objet || ('Mission #' + m.id) }}</div>
                  <div class="text-sm text-slate-600 line-clamp-1">{{ m.destination || '—' }}</div>
                </td>

                <td class="px-5 py-4 text-slate-700">{{ m?.demandeur?.name || '—' }}</td>

                <td class="px-5 py-4">
                  <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold" :class="badgeClass(m.statut_actuel)">
                    {{ prettyStatus(m.statut_actuel) }}
                  </span>
                </td>

                <td class="px-5 py-4">
                  <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700">
                    {{ decisionLabel(m) }}
                  </span>
                </td>

                <td class="px-5 py-4">
                  <div class="flex items-center justify-end gap-2">
                    <button class="px-3 py-2 rounded-lg border bg-white hover:bg-slate-50" @click="viewMission(m)">
                      👁 Voir (lecture seule)
                    </button>

                    <!-- ✅ Valider visible mais jamais cliquable dans l'historique -->
                    <button class="px-3 py-2 rounded-lg bg-slate-200 text-slate-600 text-sm font-semibold cursor-not-allowed" disabled>
                      Valider (désactivé)
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>

          </table>
        </div>
      </template>

      <!-- RAF : TOUTES -->
      <template v-else>
        <div v-if="allMissions.length === 0" class="p-10 text-center text-slate-500">Aucune mission.</div>

        <div v-else class="overflow-x-auto">
          <table class="min-w-[980px] w-full">
            <thead class="bg-slate-50 text-xs font-semibold text-slate-500">
              <tr class="text-left">
                <th class="px-5 py-3">MISSION</th>
                <th class="px-5 py-3">DEMANDEUR</th>
                <th class="px-5 py-3">DESTINATION</th>
                <th class="px-5 py-3">STATUT</th>
                <th class="px-5 py-3 text-right">ACTIONS</th>
              </tr>
            </thead>

            <tbody class="divide-y">
              <tr v-for="m in allMissions" :key="m.id" class="hover:bg-slate-50">
                <td class="px-5 py-4">
                  <div class="font-semibold text-slate-900">{{ m.objet || ('Mission #' + m.id) }}</div>
                  <div class="text-sm text-slate-600 line-clamp-1">{{ m.motif || '—' }}</div>
                </td>

                <td class="px-5 py-4 text-slate-700">{{ m?.demandeur?.name || '—' }}</td>

                <td class="px-5 py-4 text-slate-700">
                  {{ m.destination || '—' }}
                  <div class="text-xs text-slate-500">{{ m.moyen_deplacement || '—' }}</div>
                </td>

                <td class="px-5 py-4">
                  <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold" :class="badgeClass(m.statut_actuel)">
                    {{ prettyStatus(m.statut_actuel) }}
                  </span>
                </td>

                <td class="px-5 py-4">
                  <div class="flex items-center justify-end gap-2">
                    <button class="px-3 py-2 rounded-lg border bg-white hover:bg-slate-50" @click="viewMission(m)">
                      👁 Voir (lecture seule)
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>

          </table>
        </div>
      </template>
    </div>

    <!-- MODAL REJET -->
    <div v-if="rejectOpen" class="fixed inset-0 z-50">
      <div class="absolute inset-0 bg-black/40" @click="rejectOpen = false"></div>
      <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="w-full max-w-md bg-white rounded-2xl shadow-lg border p-6">
          <div class="text-lg font-bold text-slate-900">Rejeter la mission ?</div>
          <p class="text-slate-600 mt-2">
            Elle retournera au <b>brouillon</b> chez le missionnaire et restera dans votre <b>Historique</b>.
          </p>

          <div class="mt-5 flex justify-end gap-2">
            <button class="px-4 py-2 rounded-xl border bg-white hover:bg-slate-50" :disabled="busyId === rejectTarget?.id" @click="rejectOpen = false">
              Annuler
            </button>
            <button class="px-4 py-2 rounded-xl bg-rose-600 text-white font-semibold hover:opacity-95 disabled:opacity-60" :disabled="busyId === rejectTarget?.id" @click="doReject">
              {{ busyId === rejectTarget?.id ? 'Rejet…' : 'Rejeter' }}
            </button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>
