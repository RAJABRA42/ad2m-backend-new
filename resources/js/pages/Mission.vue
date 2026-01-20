<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const auth = useAuthStore()

const loading = ref(false)
const missions = ref([])

const q = ref('')
const status = ref('all')
const myOnly = ref(true)

const norm = (v) => String(v ?? '').trim().toLowerCase()
const isRejectedStatus = (s) => norm(s).startsWith('rejet')

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

/**
 * "Staff" = ceux qui peuvent voir toutes les missions côté backend (décideurs/validateurs/admin)
 * => eux seuls voient le toggle "Mes missions uniquement"
 */
const isStaff = computed(() =>
  hasRole('administrateur', 'admin', 'chef_hierarchique', 'raf', 'coordonnateur_de_projet', 'accp')
)

const isOwner = (m) => {
  const uid = auth.user?.id
  if (!uid) return false
  const demandeurId = m?.demandeur_id ?? m?.demandeur?.id ?? m?.user_id ?? m?.created_by
  return Number(demandeurId) === Number(uid)
}

const prettyStatus = (s) => {
  const k = norm(s)
  if (isRejectedStatus(k)) return 'Rejetée'

  const map = {
    brouillon: 'Brouillon',
    en_attente_ch: 'Soumise',
    valide_ch: 'Validée CH',
    valide_raf: 'Validée RAF',
    valide_cp: 'Approuvée',
    avance_payee: 'Avance payée',
    en_cours: 'En cours',
    cloturee: 'Clôturée',
  }
  return map[k] ?? (s || '—')
}

const badgeClass = (s) => {
  const k = norm(s)
  if (isRejectedStatus(k)) return 'bg-rose-100 text-rose-700'
  if (k === 'brouillon') return 'bg-slate-100 text-slate-700'
  if (k === 'en_attente_ch') return 'bg-blue-100 text-blue-700'
  if (k === 'valide_cp' || k === 'avance_payee') return 'bg-emerald-100 text-emerald-700'
  if (k === 'en_cours') return 'bg-indigo-100 text-indigo-700'
  if (k === 'cloturee') return 'bg-slate-200 text-slate-800'
  return 'bg-slate-100 text-slate-700'
}

const formatDate = (d) => {
  if (!d) return '—'
  const dt = new Date(d)
  if (Number.isNaN(dt.getTime())) return d
  return dt.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' })
}

const formatMoney = (v) => {
  if (v === null || v === undefined || v === '') return '—'
  const n = Number(String(v).replace(/\s/g, '').replace(',', '.'))
  if (Number.isNaN(n)) return String(v)
  return new Intl.NumberFormat('fr-FR').format(n)
}

const matchesStatusFilter = (m) => {
  const s = norm(m?.statut_actuel)
  if (status.value === 'all') return true
  if (status.value === 'rejet') return isRejectedStatus(s)
  return s === status.value
}

const filtered = computed(() => {
  const query = norm(q.value)

  let arr = [...missions.value]

  if (myOnly.value) {
    arr = arr.filter(m => isOwner(m))
  }

  arr = arr.filter(matchesStatusFilter)

  if (query) {
    arr = arr.filter(m => {
      const blob = [
        m?.objet,
        m?.titre,
        m?.destination,
        m?.description,
        m?.demandeur?.name,
        m?.demandeur?.matricule,
        prettyStatus(m?.statut_actuel),
      ]
        .filter(Boolean)
        .join(' ')
        .toLowerCase()

      return blob.includes(query)
    })
  }

  // tri: plus récent (id desc)
  arr.sort((a, b) => (Number(b?.id) || 0) - (Number(a?.id) || 0))
  return arr
})

const countLabel = computed(() => `${filtered.value.length} mission${filtered.value.length > 1 ? 's' : ''} trouvée${filtered.value.length > 1 ? 's' : ''}`)

const load = async () => {
  loading.value = true
  try {
    const res = await window.axios.get('/api/missions')
    // supporte plusieurs formats
    const data = res.data
    missions.value = Array.isArray(data) ? data : (data?.missions ?? [])
  } catch (e) {
    missions.value = []
  } finally {
    loading.value = false
  }
}

onMounted(load)

// actions
const goCreate = () => router.push({ name: 'missions.create' })
const goShow = (id) => router.push({ name: 'missions.show', params: { id } })
const goEdit = (id) => router.push({ name: 'missions.edit', params: { id } })

const submitMission = async (m) => {
  if (!m?.id) return
  if (!confirm('Soumettre cette mission pour validation ?')) return

  try {
    await window.axios.post(`/api/missions/${m.id}/soumettre`)
    await load()
  } catch (e) {
    alert("Impossible de soumettre. Vérifie que la mission est complète.")
  }
}

const deleteMission = async (m) => {
  if (!m?.id) return
  if (!confirm('Supprimer ce brouillon ?')) return

  try {
    await window.axios.delete(`/api/missions/${m.id}`)
    missions.value = missions.value.filter(x => x.id !== m.id)
  } catch (e) {
    alert("Impossible de supprimer.")
  }
}
</script>

<template>
  <div class="space-y-5">
    <!-- Header -->
    <div class="flex items-start justify-between gap-4">
      <div>
        <h1 class="text-3xl font-extrabold text-slate-900">Missions</h1>
        <p class="text-slate-600 mt-1">Gestion et suivi des missions</p>
      </div>

      <button
        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-brand text-white font-semibold shadow-sm hover:opacity-95"
        @click="goCreate"
      >
        <span class="text-xl leading-none">+</span>
        Nouvelle mission
      </button>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-2xl border shadow-sm p-4">
      <div class="flex flex-col lg:flex-row lg:items-center gap-3">
        <div class="flex-1">
          <div class="relative">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
              <!-- search icon -->
              <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="7"/>
                <path d="M21 21l-4.3-4.3"/>
              </svg>
            </span>
            <input
              v-model="q"
              type="text"
              placeholder="Rechercher par titre, destination, description..."
              class="w-full pl-10 pr-3 py-3 rounded-xl border border-slate-200 bg-white outline-none
                     focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
            />
          </div>
        </div>

        <div class="w-full lg:w-64">
          <div class="relative">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
              <!-- filter icon -->
              <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M3 5h18l-7 8v6l-4 2v-8L3 5Z"/>
              </svg>
            </span>
            <select
              v-model="status"
              class="w-full pl-10 pr-3 py-3 rounded-xl border border-slate-200 bg-white outline-none
                     focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
            >
              <option value="all">Tous les statuts</option>
              <option value="brouillon">Brouillon</option>
              <option value="en_attente_ch">Soumise</option>
              <option value="valide_cp">Approuvée</option>
              <option value="rejet">Rejetée</option>
              <option value="en_cours">En cours</option>
              <option value="cloturee">Clôturée</option>
            </select>
          </div>
        </div>
      </div>

      <div class="mt-3 flex items-center gap-4">
        <label v-if="isStaff" class="inline-flex items-center gap-2 text-sm text-slate-700">
          <input v-model="myOnly" type="checkbox" class="rounded border-slate-300" />
          Mes missions uniquement
        </label>

        <div class="text-sm text-slate-500">
          {{ countLabel }}
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-2xl border shadow-sm overflow-hidden">
      <div v-if="loading" class="p-10 text-center text-slate-500">
        Chargement…
      </div>

      <div v-else-if="filtered.length === 0" class="p-10 text-center text-slate-500">
        Aucune mission trouvée
      </div>

      <div v-else class="overflow-x-auto">
        <table class="min-w-[980px] w-full">
          <thead class="bg-slate-50 text-xs font-semibold text-slate-500">
            <tr class="text-left">
              <th class="px-5 py-3">MISSION</th>
              <th class="px-5 py-3">DESTINATION</th>
              <th class="px-5 py-3">DATES</th>
              <th class="px-5 py-3">BUDGET</th>
              <th class="px-5 py-3">RESPONSABLE</th>
              <th class="px-5 py-3">STATUT</th>
              <th class="px-5 py-3 text-right">ACTIONS</th>
            </tr>
          </thead>

          <tbody class="divide-y">
            <tr v-for="m in filtered" :key="m.id" class="hover:bg-slate-50">
              <!-- Mission -->
              <td class="px-5 py-4">
                <div class="font-semibold text-slate-900">
                  {{ m.objet || m.titre || ('Mission #' + m.id) }}
                </div>
                <div class="text-sm text-slate-600 line-clamp-1">
                  {{ m.description || '—' }}
                </div>
              </td>

              <!-- Destination -->
              <td class="px-5 py-4">
                <div class="flex items-center gap-2 text-slate-700">
                  <svg class="w-4 h-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 21s7-4.4 7-11a7 7 0 0 0-14 0c0 6.6 7 11 7 11Z"/>
                    <circle cx="12" cy="10" r="2.5"/>
                  </svg>
                  <span>{{ m.destination || '—' }}</span>
                </div>
              </td>

              <!-- Dates -->
              <td class="px-5 py-4 text-slate-700">
                <div class="flex items-center gap-2">
                  <svg class="w-4 h-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M8 2v4M16 2v4"/>
                    <rect x="3" y="4" width="18" height="18" rx="2"/>
                    <path d="M3 10h18"/>
                  </svg>
                  <span>
                    {{ formatDate(m.date_debut) }}
                    <span v-if="m.date_fin">au {{ formatDate(m.date_fin) }}</span>
                  </span>
                </div>
              </td>

              <!-- Budget -->
              <td class="px-5 py-4 text-slate-700">
                {{ formatMoney(m.budget) }} <span class="text-slate-500">Ar</span>
              </td>

              <!-- Responsable -->
              <td class="px-5 py-4">
                <div class="text-slate-900">
                  {{ m.demandeur?.name || auth.user?.name || '—' }}
                </div>
                <div class="text-xs text-slate-500">
                  {{ m.demandeur?.matricule || m.matricule || '—' }}
                </div>
              </td>

              <!-- Statut -->
              <td class="px-5 py-4">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold"
                      :class="badgeClass(m.statut_actuel)">
                  {{ prettyStatus(m.statut_actuel) }}
                </span>
              </td>

              <!-- Actions -->
              <td class="px-5 py-4">
                <div class="flex items-center justify-end gap-2">
                  <!-- Voir -->
                  <button
                    class="w-9 h-9 rounded-lg hover:bg-slate-100 flex items-center justify-center text-slate-600"
                    title="Voir"
                    @click="goShow(m.id)"
                  >
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/>
                      <circle cx="12" cy="12" r="3"/>
                    </svg>
                  </button>

                  <!-- Brouillon + propriétaire => Modifier / Supprimer / Soumettre -->
                  <template v-if="isOwner(m) && norm(m.statut_actuel) === 'brouillon'">
                    <button
                      class="w-9 h-9 rounded-lg hover:bg-slate-100 flex items-center justify-center text-slate-600"
                      title="Modifier"
                      @click="goEdit(m.id)"
                    >
                      <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 20h9"/>
                        <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/>
                      </svg>
                    </button>

                    <button
                      class="w-9 h-9 rounded-lg hover:bg-rose-50 flex items-center justify-center text-rose-600"
                      title="Supprimer"
                      @click="deleteMission(m)"
                    >
                      <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 6h18"/>
                        <path d="M8 6V4h8v2"/>
                        <path d="M19 6l-1 14H6L5 6"/>
                        <path d="M10 11v6M14 11v6"/>
                      </svg>
                    </button>

                    <button
                      class="w-9 h-9 rounded-lg hover:bg-emerald-50 flex items-center justify-center text-emerald-700"
                      title="Soumettre"
                      @click="submitMission(m)"
                    >
                      <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 2 11 13"/>
                        <path d="M22 2 15 22l-4-9-9-4 20-7Z"/>
                      </svg>
                    </button>
                  </template>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
