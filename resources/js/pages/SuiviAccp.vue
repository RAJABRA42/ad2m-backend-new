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
const tab = ref('pj') // pj | cloture | history | all

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

const isAccp = computed(() => hasRole('accp', 'admin', 'administrateur'))

const norm = (v) => String(v ?? '').toLowerCase().trim()

const prettyStatus = (s) => {
  const k = norm(s)
  const map = {
    brouillon: 'Brouillon',
    en_attente_ch: 'En attente CH',
    valide_ch: 'En attente RAF',
    valide_raf: 'En attente CP',
    valide_cp: 'En attente ACCP (paiement)',
    avance_payee: 'Avance payée',
    en_cours: 'En cours',
    cloturee: 'Clôturée',
  }
  return map[k] ?? (s || '—')
}

const badgeClass = (s) => {
  const k = norm(s)
  if (k === 'valide_cp') return 'bg-amber-100 text-amber-700'
  if (k === 'avance_payee') return 'bg-emerald-100 text-emerald-700'
  if (k === 'en_cours') return 'bg-indigo-100 text-indigo-700'
  if (k === 'cloturee') return 'bg-slate-200 text-slate-700'
  return 'bg-slate-100 text-slate-700'
}

const pjState = (m) => {
  const st = norm(m?.statut_actuel)
  const pj = !!m?.pj_regularise

  if (st === 'en_cours' && pj) return { label: 'Prête à clôturer', cls: 'bg-emerald-100 text-emerald-700' }
  if ((st === 'avance_payee' || st === 'en_cours') && !pj) return { label: 'PJ à régulariser', cls: 'bg-rose-100 text-rose-700' }
  if (st === 'cloturee') return { label: 'Clôturée', cls: 'bg-slate-200 text-slate-700' }
  if (st === 'valide_cp') return { label: 'Paiement à faire', cls: 'bg-amber-100 text-amber-700' }
  return { label: '—', cls: 'bg-slate-100 text-slate-700' }
}

// --- load
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

// --- buckets ACCP
const relevant = computed(() => {
  // l’ACCP s’intéresse surtout à ces statuts :
  const arr = missions.value.filter(m => {
    const st = norm(m?.statut_actuel)
    return ['valide_cp', 'avance_payee', 'en_cours', 'cloturee'].includes(st)
  })

  const query = norm(q.value)
  if (!query) return arr

  return arr.filter(m => {
    const blob = [
      m?.objet,
      m?.destination,
      m?.moyen_deplacement,
      m?.motif,
      m?.demandeur?.name,
      m?.demandeur?.matricule,
      prettyStatus(m?.statut_actuel),
      pjState(m)?.label,
    ].filter(Boolean).join(' ').toLowerCase()
    return blob.includes(query)
  })
})

const toPay = computed(() =>
  relevant.value.filter(m => norm(m?.statut_actuel) === 'valide_cp')
)

const pjToRegularize = computed(() =>
  relevant.value.filter(m => {
    const st = norm(m?.statut_actuel)
    return (st === 'avance_payee' || st === 'en_cours') && !m?.pj_regularise
  })
)

const toClose = computed(() =>
  relevant.value.filter(m => norm(m?.statut_actuel) === 'en_cours' && !!m?.pj_regularise)
)

const history = computed(() =>
  relevant.value.filter(m => norm(m?.statut_actuel) === 'cloturee')
)

const activeList = computed(() => {
  if (tab.value === 'pj') return pjToRegularize.value
  if (tab.value === 'cloture') return toClose.value
  if (tab.value === 'history') return history.value
  return relevant.value
})

const counts = computed(() => ({
  pay: toPay.value.length,
  pj: pjToRegularize.value.length,
  cloture: toClose.value.length,
  history: history.value.length,
}))

// --- actions
const goPay = (m) => {
  router.push({ name: 'missions.payer', params: { id: m.id } })
}

const goShow = (m) => {
  // si tu as missions.show
  router.push({ name: 'missions.show', params: { id: m.id } })
}

const regulariserPJ = async (m) => {
  if (!m?.id) return
  const note = prompt('Note de régularisation (optionnel) :', m?.note_regularisation || '')
  if (note === null) return

  try {
    await window.axios.post(`/api/missions/${m.id}/regulariser-pj`, {
      note_regularisation: note,
    })
    actionMsg.value = 'PJ régularisées.'
    await load()
  } catch (e) {
    actionMsg.value = e?.response?.data?.message || 'Erreur régularisation PJ.'
  }
}

const cloturer = async (m) => {
  if (!m?.id) return
  if (!confirm('Clôturer cette mission ?')) return

  try {
    await window.axios.post(`/api/missions/${m.id}/cloturer`)
    actionMsg.value = 'Mission clôturée.'
    await load()
  } catch (e) {
    actionMsg.value = e?.response?.data?.message || 'Erreur clôture.'
  }
}
</script>

<template>
  <div class="space-y-5">
    <div class="flex items-start justify-between gap-4">
      <div>
        <h1 class="text-3xl font-extrabold text-slate-900">Suivi ACCP</h1>
        <p class="text-slate-600 mt-1">
          Paiements, régularisation des PJ, clôture.
        </p>
      </div>

      <button class="px-3 py-2 rounded-xl border bg-white hover:bg-slate-50" @click="load" :disabled="loading">
        Rafraîchir
      </button>
    </div>

    <div v-if="!isAccp" class="bg-white rounded-2xl border p-6 text-slate-700">
      Accès réservé ACCP / administrateur.
    </div>

    <template v-else>
      <!-- cards -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl border shadow-sm p-4">
          <div class="text-sm text-slate-500">Paiements à faire</div>
          <div class="text-3xl font-extrabold text-slate-900">{{ counts.pay }}</div>
        </div>
        <div class="bg-white rounded-2xl border shadow-sm p-4">
          <div class="text-sm text-slate-500">PJ à régulariser</div>
          <div class="text-3xl font-extrabold text-slate-900">{{ counts.pj }}</div>
        </div>
        <div class="bg-white rounded-2xl border shadow-sm p-4">
          <div class="text-sm text-slate-500">Prêtes à clôturer</div>
          <div class="text-3xl font-extrabold text-slate-900">{{ counts.cloture }}</div>
        </div>
        <div class="bg-white rounded-2xl border shadow-sm p-4">
          <div class="text-sm text-slate-500">Clôturées</div>
          <div class="text-3xl font-extrabold text-slate-900">{{ counts.history }}</div>
        </div>
      </div>

      <!-- tabs + search -->
      <div class="bg-white rounded-2xl border shadow-sm p-4 space-y-3">
        <div class="flex flex-wrap gap-2">
          <button
            class="px-3 py-2 rounded-xl text-sm font-semibold"
            :class="tab==='pj' ? 'bg-brand-50 text-brand' : 'bg-slate-50 text-slate-700 hover:bg-slate-100'"
            @click="tab='pj'"
          >
            PJ à régulariser
          </button>
          <button
            class="px-3 py-2 rounded-xl text-sm font-semibold"
            :class="tab==='cloture' ? 'bg-brand-50 text-brand' : 'bg-slate-50 text-slate-700 hover:bg-slate-100'"
            @click="tab='cloture'"
          >
            À clôturer
          </button>
          <button
            class="px-3 py-2 rounded-xl text-sm font-semibold"
            :class="tab==='history' ? 'bg-brand-50 text-brand' : 'bg-slate-50 text-slate-700 hover:bg-slate-100'"
            @click="tab='history'"
          >
            Historique
          </button>
          <button
            class="px-3 py-2 rounded-xl text-sm font-semibold"
            :class="tab==='all' ? 'bg-brand-50 text-brand' : 'bg-slate-50 text-slate-700 hover:bg-slate-100'"
            @click="tab='all'"
          >
            Tout
          </button>
        </div>

        <input
          v-model="q"
          type="text"
          placeholder="Rechercher (objet, destination, demandeur, statut...)"
          class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 outline-none
                 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
        />
      </div>

      <div v-if="actionMsg" class="rounded-2xl border bg-slate-50 px-4 py-3 text-sm text-slate-700">
        {{ actionMsg }}
      </div>

      <!-- table -->
      <div class="bg-white rounded-2xl border shadow-sm overflow-hidden">
        <div v-if="loading" class="p-8 text-slate-500">Chargement…</div>
        <div v-else-if="error" class="p-6 text-rose-700">{{ error }}</div>
        <div v-else-if="activeList.length === 0" class="p-10 text-center text-slate-500">
          Aucun élément dans cette vue.
        </div>

        <div v-else class="overflow-x-auto">
          <table class="min-w-[1050px] w-full">
            <thead class="bg-slate-50 text-xs font-semibold text-slate-500">
              <tr class="text-left">
                <th class="px-5 py-3">MISSION</th>
                <th class="px-5 py-3">DESTINATION</th>
                <th class="px-5 py-3">DEMANDEUR</th>
                <th class="px-5 py-3">STATUT</th>
                <th class="px-5 py-3">ÉTAT</th>
                <th class="px-5 py-3 text-right">ACTIONS</th>
              </tr>
            </thead>

            <tbody class="divide-y">
              <tr v-for="m in activeList" :key="m.id" class="hover:bg-slate-50">
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
                  <div class="text-xs text-slate-500">{{ m.moyen_deplacement || '—' }}</div>
                </td>

                <td class="px-5 py-4">
                  <div class="text-slate-900">{{ m.demandeur?.name || '—' }}</div>
                  <div class="text-xs text-slate-500">{{ m.demandeur?.matricule || '—' }}</div>
                </td>

                <td class="px-5 py-4">
                  <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold"
                        :class="badgeClass(m.statut_actuel)">
                    {{ prettyStatus(m.statut_actuel) }}
                  </span>
                </td>

                <td class="px-5 py-4">
                  <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold"
                        :class="pjState(m).cls">
                    {{ pjState(m).label }}
                  </span>
                </td>

                <td class="px-5 py-4">
                  <div class="flex items-center justify-end gap-2">
                    <!-- Voir (si route show existe) -->
                    <button
                      class="w-9 h-9 rounded-lg hover:bg-slate-100 flex items-center justify-center text-slate-600"
                      title="Voir"
                      @click="goShow(m)"
                    >
                      👁
                    </button>

                    <!-- Paiement : redirige vers page payer -->
                    <button
                      v-if="norm(m.statut_actuel)==='valide_cp'"
                      class="px-3 py-2 rounded-lg bg-emerald-600 text-white text-sm font-semibold hover:opacity-95"
                      @click="goPay(m)"
                    >
                      Payer
                    </button>

                    <!-- PJ à régulariser -->
                    <button
                      v-if="(norm(m.statut_actuel)==='avance_payee' || norm(m.statut_actuel)==='en_cours') && !m.pj_regularise"
                      class="px-3 py-2 rounded-lg border text-sm font-semibold hover:bg-rose-50 text-rose-700"
                      @click="regulariserPJ(m)"
                    >
                      Régulariser PJ
                    </button>

                    <!-- Clôturer -->
                    <button
                      v-if="norm(m.statut_actuel)==='en_cours' && m.pj_regularise"
                      class="px-3 py-2 rounded-lg bg-indigo-600 text-white text-sm font-semibold hover:opacity-95"
                      @click="cloturer(m)"
                    >
                      Clôturer
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>

          </table>
        </div>
      </div>
    </template>
  </div>
</template>
