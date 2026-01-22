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

// Pour éviter double-clic sur une action
const actionBusyId = ref(null)

// --- suppression modal custom (pas confirm navigateur)
const confirmOpen = ref(false)
const toDelete = ref(null)

// --- helpers
const norm = (v) => String(v ?? '').toLowerCase().trim()

const prettyStatus = (s) => {
  const k = norm(s)
  if (k.startsWith('rejet')) return 'Rejetée'
  const map = {
    brouillon: 'Brouillon',
    en_attente_ch: 'En attente CH',
    valide_ch: 'En attente RAF',
    valide_raf: 'En attente CP',
    valide_cp: 'En attente ACCP',
    avance_payee: 'Avance payée',
    en_cours: 'En cours',
    cloturee: 'Clôturée',
  }
  return map[k] ?? (s || '—')
}

const badgeClass = (s) => {
  const k = norm(s)
  if (k.startsWith('rejet')) return 'bg-rose-100 text-rose-700'
  if (k === 'brouillon') return 'bg-slate-100 text-slate-700'
  if (k === 'en_attente_ch') return 'bg-blue-100 text-blue-700'
  if (['valide_ch', 'valide_raf', 'valide_cp'].includes(k)) return 'bg-amber-100 text-amber-700'
  if (k === 'avance_payee') return 'bg-emerald-100 text-emerald-700'
  if (k === 'en_cours') return 'bg-indigo-100 text-indigo-700'
  if (k === 'cloturee') return 'bg-slate-200 text-slate-700'
  return 'bg-slate-100 text-slate-700'
}

const isOwner = (m) => {
  const uid = auth.user?.id
  return !!uid && Number(m?.demandeur_id) === Number(uid)
}

const isDraft = (m) => isOwner(m) && norm(m?.statut_actuel) === 'brouillon'
const canStart = (m) => isOwner(m) && norm(m?.statut_actuel) === 'avance_payee'

// --- load
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

// --- filtre "mes missions"
const mine = computed(() => {
  const query = norm(q.value)
  let arr = missions.value.filter(isOwner)

  if (query) {
    arr = arr.filter((m) => {
      const blob = [
        m?.objet,
        m?.destination,
        m?.moyen_deplacement,
        m?.motif,
        prettyStatus(m?.statut_actuel),
      ]
        .filter(Boolean)
        .join(' ')
        .toLowerCase()
      return blob.includes(query)
    })
  }

  arr.sort((a, b) => (Number(b?.id) || 0) - (Number(a?.id) || 0))
  return arr
})

// --- navigation
const goCreate = () => router.push({ name: 'missions.create' })

// 👁 Voir => MissionShow en lecture
const goShow = (m) =>
  router.push({
    name: 'missions.show',
    params: { id: m.id },
    query: { mode: 'view' },
  })

// ✏️ Modifier => MissionShow direct en édition
const goEdit = (m) =>
  router.push({
    name: 'missions.show',
    params: { id: m.id },
    query: { mode: 'edit' },
  })

// --- actions
const submitMission = async (m) => {
  if (!m?.id) return
  if (!isDraft(m)) return

  actionBusyId.value = m.id
  try {
    await window.axios.post(`/api/missions/${m.id}/soumettre`)
    await load()
  } catch (e) {
    alert(e?.response?.data?.message || 'Erreur soumission.')
  } finally {
    actionBusyId.value = null
  }
}

const startMission = async (m) => {
  if (!m?.id) return

  actionBusyId.value = m.id
  try {
    await window.axios.post(`/api/missions/${m.id}/commencer`)
    await load()
  } catch (e) {
    alert(e?.response?.data?.message || 'Erreur démarrage.')
  } finally {
    actionBusyId.value = null
  }
}

// --- suppression (modal custom)
const askDelete = (m) => {
  if (!m?.id) return
  if (!isDraft(m)) return
  toDelete.value = m
  confirmOpen.value = true
}

const doDelete = async () => {
  const m = toDelete.value
  if (!m?.id) return
  if (!isDraft(m)) return

  actionBusyId.value = m.id
  try {
    await window.axios.delete(`/api/missions/${m.id}`)
    confirmOpen.value = false
    toDelete.value = null
    await load()
  } catch (e) {
    alert(e?.response?.data?.message || 'Erreur suppression.')
  } finally {
    actionBusyId.value = null
  }
}
</script>

<template>
  <div class="space-y-5">
    <div class="flex items-start justify-between gap-4">
      <div>
        <h1 class="text-3xl font-extrabold text-slate-900">Mes missions</h1>
        <p class="text-slate-600 mt-1">
          Liste des missions dont vous êtes le demandeur.
        </p>
      </div>

      <div class="flex items-center gap-2">
        <button
          class="px-3 py-2 rounded-xl border bg-white hover:bg-slate-50"
          @click="load"
          :disabled="loading"
        >
          Rafraîchir
        </button>

        <button
          class="px-4 py-2 rounded-xl bg-brand text-white font-semibold shadow-sm hover:opacity-95"
          @click="goCreate"
        >
          + Nouvelle mission
        </button>
      </div>
    </div>

    <div class="bg-white rounded-2xl border shadow-sm p-4">
      <input
        v-model="q"
        type="text"
        placeholder="Rechercher (objet, destination, statut...)"
        class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 outline-none
               focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
      />
    </div>

    <div class="bg-white rounded-2xl border shadow-sm overflow-hidden">
      <div v-if="loading" class="p-8 text-slate-500">Chargement…</div>
      <div v-else-if="error" class="p-6 text-rose-700">{{ error }}</div>
      <div v-else-if="mine.length === 0" class="p-10 text-center text-slate-500">
        Aucune mission pour le moment.
      </div>

      <div v-else class="overflow-x-auto">
        <table class="min-w-[980px] w-full">
          <thead class="bg-slate-50 text-xs font-semibold text-slate-500">
            <tr class="text-left">
              <th class="px-5 py-3">MISSION</th>
              <th class="px-5 py-3">DESTINATION</th>
              <th class="px-5 py-3">DATES</th>
              <th class="px-5 py-3">STATUT</th>
              <th class="px-5 py-3 text-right">ACTIONS</th>
            </tr>
          </thead>

          <tbody class="divide-y">
            <tr v-for="m in mine" :key="m.id" class="hover:bg-slate-50">
              <td class="px-5 py-4">
                <div class="font-semibold text-slate-900">
                  {{ m.objet || ('Mission #' + m.id) }}
                </div>
                <div class="text-sm text-slate-600 line-clamp-1">
                  {{ m.motif || '—' }}
                </div>
              </td>

              <td class="px-5 py-4 text-slate-700">
                {{ m.destination || '—' }}
                <div class="text-xs text-slate-500">
                  {{ m.moyen_deplacement || '—' }}
                </div>
              </td>

              <td class="px-5 py-4 text-slate-700">
                <div>{{ m.date_debut || '—' }} → {{ m.date_fin || '—' }}</div>
              </td>

              <td class="px-5 py-4">
                <span
                  class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold"
                  :class="badgeClass(m.statut_actuel)"
                >
                  {{ prettyStatus(m.statut_actuel) }}
                </span>
              </td>

              <td class="px-5 py-4">
                <div class="flex items-center justify-end gap-2">
                  <!-- 👁 Voir -->
                  <button
                    class="w-9 h-9 rounded-lg hover:bg-slate-100 flex items-center justify-center text-slate-700"
                    title="Voir"
                    @click="goShow(m)"
                  >
                    👁
                  </button>

                  <!-- BROUILLON : modifier + soumettre + supprimer -->
                  <template v-if="isDraft(m)">
                    <button
                      class="w-9 h-9 rounded-lg hover:bg-slate-100 flex items-center justify-center text-slate-700"
                      title="Modifier"
                      @click="goEdit(m)"
                    >
                      ✏️
                    </button>

                    <button
                      class="px-3 py-2 rounded-lg bg-emerald-600 text-white text-sm font-semibold hover:opacity-95"
                      :disabled="actionBusyId === m.id"
                      @click="submitMission(m)"
                    >
                      Soumettre
                    </button>

                    <button
                      class="px-3 py-2 rounded-lg border text-sm font-semibold hover:bg-rose-50 text-rose-700"
                      :disabled="actionBusyId === m.id"
                      @click="askDelete(m)"
                    >
                      Supprimer
                    </button>
                  </template>

                  <!-- AVANCE PAYÉE : Commencer -->
                  <template v-else-if="canStart(m)">
                    <button
                      class="px-3 py-2 rounded-lg bg-indigo-600 text-white text-sm font-semibold hover:opacity-95"
                      :disabled="actionBusyId === m.id"
                      @click="startMission(m)"
                    >
                      ▶ Commencer
                    </button>
                  </template>
                </div>
              </td>
            </tr>
          </tbody>

        </table>
      </div>
    </div>

    <!-- ✅ Modal suppression (custom) -->
    <div v-if="confirmOpen" class="fixed inset-0 z-50">
      <div class="absolute inset-0 bg-black/40" @click="confirmOpen = false"></div>
      <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="w-full max-w-md bg-white rounded-2xl shadow-lg border p-6">
          <div class="text-lg font-bold text-slate-900">Supprimer la mission ?</div>
          <p class="text-slate-600 mt-2">
            Cette action est définitive. Voulez-vous vraiment supprimer ce brouillon ?
          </p>

          <div class="mt-5 flex justify-end gap-2">
            <button
              class="px-4 py-2 rounded-xl border bg-white hover:bg-slate-50"
              :disabled="actionBusyId === (toDelete?.id ?? null)"
              @click="confirmOpen = false"
            >
              Annuler
            </button>

            <button
              class="px-4 py-2 rounded-xl bg-rose-600 text-white font-semibold hover:opacity-95 disabled:opacity-60"
              :disabled="actionBusyId === (toDelete?.id ?? null)"
              @click="doDelete"
            >
              {{ actionBusyId === (toDelete?.id ?? null) ? 'Suppression…' : 'Supprimer' }}
            </button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>
