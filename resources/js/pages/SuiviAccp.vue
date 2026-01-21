<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const router = useRouter()
const route = useRoute()

const missions = ref([])
const loading = ref(false)
const error = ref('')
const toast = ref('')

const q = ref('')
const tab = ref('paiement') // paiement | pj | cloture | historique

// Drawer
const drawerOpen = ref(false)
const selected = ref(null)
const selectedLoading = ref(false)
const drawerMsg = ref('')
const saving = ref(false)

const payForm = ref({
  montant: '',
  date_operation: new Date().toISOString().slice(0, 10),
  numero_piece_paiement: '',
})

const pjForm = ref({
  note_regularisation: '',
})

const statusKey = (s) => String(s || '').toLowerCase()

const prettyStatus = (s) => {
  const k = statusKey(s)
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
  const k = statusKey(s)
  if (k === 'valide_cp') return 'bg-amber-100 text-amber-700'
  if (k === 'avance_payee') return 'bg-emerald-100 text-emerald-700'
  if (k === 'en_cours') return 'bg-indigo-100 text-indigo-700'
  if (k === 'cloturee') return 'bg-slate-100 text-slate-700'
  return 'bg-slate-100 text-slate-700'
}

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

const listPaiement = computed(() =>
  missions.value.filter(m => statusKey(m?.statut_actuel) === 'valide_cp')
)

const listPJ = computed(() =>
  missions.value.filter(m => statusKey(m?.statut_actuel) === 'en_cours' && !m?.pj_regularise)
)

const listACloturer = computed(() =>
  missions.value.filter(m => statusKey(m?.statut_actuel) === 'en_cours' && !!m?.pj_regularise)
)

const listCloturees = computed(() =>
  missions.value.filter(m => statusKey(m?.statut_actuel) === 'cloturee')
)

const activeList = computed(() => {
  const query = String(q.value || '').trim().toLowerCase()
  let base = []

  if (tab.value === 'paiement') base = listPaiement.value
  else if (tab.value === 'pj') base = listPJ.value
  else if (tab.value === 'cloture') base = listACloturer.value
  else if (tab.value === 'historique') base = listCloturees.value
  else base = missions.value

  let arr = [...base].sort((a, b) => (Number(b?.id) || 0) - (Number(a?.id) || 0))

  if (!query) return arr

  return arr.filter(m => {
    const blob = [
      m?.objet, m?.destination, m?.motif,
      m?.demandeur?.name, m?.demandeur?.matricule,
      prettyStatus(m?.statut_actuel),
    ].filter(Boolean).join(' ').toLowerCase()
    return blob.includes(query)
  })
})

const fetchMission = async (id) => {
  if (!id) return
  selectedLoading.value = true
  drawerMsg.value = ''
  try {
    const res = await window.axios.get(`/api/missions/${id}`)
    selected.value = res.data?.mission ?? res.data
    payForm.value.montant = selected.value?.montant_avance_demande ?? ''
  } catch (e) {
    drawerMsg.value = e?.response?.data?.message || 'Erreur chargement mission.'
  } finally {
    selectedLoading.value = false
  }
}

const openDrawer = async (id) => {
  drawerOpen.value = true
  await fetchMission(id)
}

const closeDrawer = () => {
  drawerOpen.value = false
  selected.value = null
  drawerMsg.value = ''
  pjForm.value.note_regularisation = ''
  if (route.name === 'accp.mission') router.push({ name: 'accp' })
}

const goToDrawer = (m) => {
  if (!m?.id) return
  router.push({ name: 'accp.mission', params: { id: m.id } })
}

// Ouvrir automatiquement via /accp/:id
watch(
  () => route.params.id,
  async (id) => {
    if (id) await openDrawer(id)
    else if (drawerOpen.value) closeDrawer()
  },
  { immediate: true }
)

const avances = computed(() => selected.value?.avances ?? [])

const hasPaiement = computed(() =>
  avances.value.some(a => String(a?.type_operation || '').toLowerCase() === 'paiement')
)

const canSaveAvance = computed(() => statusKey(selected.value?.statut_actuel) === 'valide_cp')
const canConfirmPay = computed(() => canSaveAvance.value && hasPaiement.value)

const canRegulariserPJ = computed(() => {
  const k = statusKey(selected.value?.statut_actuel)
  return (k === 'avance_payee' || k === 'en_cours') && !selected.value?.pj_regularise
})

const canCloturer = computed(() =>
  statusKey(selected.value?.statut_actuel) === 'en_cours' && !!selected.value?.pj_regularise
)

const saveAvance = async () => {
  if (!selected.value?.id) return
  saving.value = true
  drawerMsg.value = ''
  try {
    await window.axios.post(`/api/missions/${selected.value.id}/avances`, {
      montant: payForm.value.montant,
      date_operation: payForm.value.date_operation,
      numero_piece_paiement: payForm.value.numero_piece_paiement || null,
    })
    drawerMsg.value = "Avance enregistrée."
    await load()
    await fetchMission(selected.value.id)
  } catch (e) {
    drawerMsg.value = e?.response?.data?.message || "Erreur enregistrement avance."
  } finally {
    saving.value = false
  }
}

const confirmPay = async () => {
  if (!selected.value?.id) return
  saving.value = true
  drawerMsg.value = ''
  try {
    await window.axios.post(`/api/missions/${selected.value.id}/payer`)
    drawerMsg.value = "Paiement confirmé (statut: avance_payee)."
    await load()
    await fetchMission(selected.value.id)
  } catch (e) {
    drawerMsg.value = e?.response?.data?.message || "Erreur confirmation paiement."
  } finally {
    saving.value = false
  }
}

const regulariserPJ = async () => {
  if (!selected.value?.id) return
  saving.value = true
  drawerMsg.value = ''
  try {
    await window.axios.post(`/api/missions/${selected.value.id}/regulariser-pj`, {
      note_regularisation: pjForm.value.note_regularisation || null,
    })
    drawerMsg.value = "PJ marquées comme régularisées."
    await load()
    await fetchMission(selected.value.id)
  } catch (e) {
    drawerMsg.value = e?.response?.data?.message || "Erreur régularisation PJ."
  } finally {
    saving.value = false
  }
}

const cloturer = async () => {
  if (!selected.value?.id) return
  saving.value = true
  drawerMsg.value = ''
  try {
    await window.axios.post(`/api/missions/${selected.value.id}/cloturer`)
    drawerMsg.value = "Mission clôturée."
    await load()
    await fetchMission(selected.value.id)
  } catch (e) {
    drawerMsg.value = e?.response?.data?.message || "Erreur clôture."
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-start justify-between gap-4">
      <div>
        <h1 class="text-3xl font-extrabold text-slate-900">Suivi ACCP</h1>
        <p class="text-slate-600 mt-1">Central financier : avances, paiement, PJ, clôture.</p>
      </div>

      <button class="px-3 py-2 rounded-xl border bg-white hover:bg-slate-50" @click="load" :disabled="loading">
        Rafraîchir
      </button>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <div class="bg-white rounded-2xl border p-4">
        <div class="text-sm text-slate-500">Paiements à faire</div>
        <div class="text-3xl font-extrabold">{{ listPaiement.length }}</div>
      </div>
      <div class="bg-white rounded-2xl border p-4">
        <div class="text-sm text-slate-500">PJ à régulariser</div>
        <div class="text-3xl font-extrabold">{{ listPJ.length }}</div>
      </div>
      <div class="bg-white rounded-2xl border p-4">
        <div class="text-sm text-slate-500">À clôturer</div>
        <div class="text-3xl font-extrabold">{{ listACloturer.length }}</div>
      </div>
      <div class="bg-white rounded-2xl border p-4">
        <div class="text-sm text-slate-500">Clôturées</div>
        <div class="text-3xl font-extrabold">{{ listCloturees.length }}</div>
      </div>
    </div>

    <!-- Tabs + search -->
    <div class="bg-white rounded-2xl border shadow-sm p-4 space-y-3">
      <div class="flex flex-wrap gap-2">
        <button class="px-3 py-2 rounded-xl text-sm font-semibold"
                :class="tab==='paiement' ? 'bg-emerald-100 text-emerald-800' : 'hover:bg-slate-50'"
                @click="tab='paiement'">Paiements</button>

        <button class="px-3 py-2 rounded-xl text-sm font-semibold"
                :class="tab==='pj' ? 'bg-emerald-100 text-emerald-800' : 'hover:bg-slate-50'"
                @click="tab='pj'">PJ à régulariser</button>

        <button class="px-3 py-2 rounded-xl text-sm font-semibold"
                :class="tab==='cloture' ? 'bg-emerald-100 text-emerald-800' : 'hover:bg-slate-50'"
                @click="tab='cloture'">À clôturer</button>

        <button class="px-3 py-2 rounded-xl text-sm font-semibold"
                :class="tab==='historique' ? 'bg-emerald-100 text-emerald-800' : 'hover:bg-slate-50'"
                @click="tab='historique'">Historique</button>
      </div>

      <input
        v-model="q"
        type="text"
        placeholder="Rechercher (objet, destination, demandeur, statut...)"
        class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 outline-none
               focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
      />
    </div>

    <div v-if="error" class="bg-white rounded-2xl border p-4 text-rose-700">{{ error }}</div>

    <!-- Table -->
    <div class="bg-white rounded-2xl border shadow-sm overflow-hidden">
      <div v-if="loading" class="p-8 text-slate-500">Chargement…</div>

      <div v-else-if="activeList.length === 0" class="p-10 text-center text-slate-500">
        Aucun élément dans cette vue.
      </div>

      <div v-else class="overflow-x-auto">
        <table class="min-w-[980px] w-full">
          <thead class="bg-slate-50 text-xs font-semibold text-slate-500">
            <tr class="text-left">
              <th class="px-5 py-3">MISSION</th>
              <th class="px-5 py-3">DESTINATION</th>
              <th class="px-5 py-3">DEMANDEUR</th>
              <th class="px-5 py-3">STATUT</th>
              <th class="px-5 py-3">PJ</th>
              <th class="px-5 py-3 text-right">ACTIONS</th>
            </tr>
          </thead>

          <tbody class="divide-y">
            <tr v-for="m in activeList" :key="m.id" class="hover:bg-slate-50">
              <td class="px-5 py-4">
                <div class="font-semibold text-slate-900">
                  {{ m.objet || ('Mission #' + m.id) }}
                </div>
                <div class="text-sm text-slate-600">
                  {{ m.motif || '—' }}
                </div>
              </td>

              <td class="px-5 py-4 text-slate-700">{{ m.destination || '—' }}</td>

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
                      :class="m.pj_regularise ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'">
                  {{ m.pj_regularise ? 'PJ OK' : 'PJ à régulariser' }}
                </span>
              </td>

              <td class="px-5 py-4">
                <div class="flex items-center justify-end gap-2">
                  <button
                    class="px-3 py-2 rounded-lg border bg-white text-sm font-semibold hover:bg-slate-50"
                    @click="router.push({ name: 'missions.show', params: { id: m.id } })"
                  >
                    Voir
                  </button>

                  <button
                    class="px-3 py-2 rounded-lg bg-emerald-600 text-white text-sm font-semibold hover:opacity-95"
                    @click="goToDrawer(m)"
                  >
                    Traiter
                  </button>
                </div>
              </td>
            </tr>
          </tbody>

        </table>
      </div>
    </div>

    <!-- Drawer -->
    <div v-if="drawerOpen" class="fixed inset-0 z-50">
      <div class="absolute inset-0 bg-black/30" @click="closeDrawer"></div>

      <div class="absolute right-0 top-0 h-full w-full max-w-xl bg-white shadow-2xl flex flex-col">
        <div class="p-5 border-b flex items-start justify-between gap-3">
          <div>
            <div class="text-sm text-slate-500">Dossier mission</div>
            <div class="text-xl font-extrabold text-slate-900">
              {{ selected?.objet || (selected ? ('Mission #' + selected.id) : '—') }}
            </div>
            <div class="mt-2">
              <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold"
                    :class="badgeClass(selected?.statut_actuel)">
                {{ prettyStatus(selected?.statut_actuel) }}
              </span>
            </div>
          </div>

          <button class="px-3 py-2 rounded-xl border hover:bg-slate-50" @click="closeDrawer">Fermer</button>
        </div>

        <div class="flex-1 overflow-y-auto p-5 space-y-5">
          <div v-if="selectedLoading" class="text-slate-500">Chargement…</div>

          <template v-else>
            <div v-if="drawerMsg" class="rounded-2xl border bg-slate-50 px-4 py-3 text-sm text-slate-700">
              {{ drawerMsg }}
            </div>

            <!-- Résumé -->
            <div class="bg-slate-50 rounded-2xl p-4">
              <div class="text-sm text-slate-600">
                <div><span class="font-semibold">Demandeur :</span> {{ selected?.demandeur?.name || '—' }}</div>
                <div><span class="font-semibold">Destination :</span> {{ selected?.destination || '—' }}</div>
                <div><span class="font-semibold">Montant demandé :</span> {{ selected?.montant_avance_demande ?? '—' }} Ar</div>
              </div>
            </div>

            <!-- 1) Avance + Paiement -->
            <div class="bg-white rounded-2xl border p-4 space-y-3">
              <div class="font-bold text-slate-900">Avance / Paiement</div>

              <div class="text-sm text-slate-600">
                <span class="font-semibold">Avance enregistrée :</span>
                <span :class="hasPaiement ? 'text-emerald-700 font-semibold' : 'text-rose-700 font-semibold'">
                  {{ hasPaiement ? 'OUI' : 'NON' }}
                </span>
              </div>

              <div v-if="avances.length" class="text-sm text-slate-600">
                <div class="font-semibold text-slate-800 mb-1">Historique avances</div>
                <ul class="space-y-1">
                  <li v-for="a in avances" :key="a.id" class="flex justify-between">
                    <span>{{ a.type_operation }} • {{ a.date_operation }}</span>
                    <span class="font-semibold">{{ a.montant }} Ar</span>
                  </li>
                </ul>
              </div>

              <div v-if="canSaveAvance" class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                  <label class="block text-xs font-semibold text-slate-600 mb-1">Montant</label>
                  <input v-model="payForm.montant" type="number"
                         class="w-full rounded-xl border border-slate-200 px-3 py-2 outline-none
                                focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100" />
                </div>

                <div>
                  <label class="block text-xs font-semibold text-slate-600 mb-1">Date</label>
                  <input v-model="payForm.date_operation" type="date"
                         class="w-full rounded-xl border border-slate-200 px-3 py-2 outline-none
                                focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100" />
                </div>

                <div>
                  <label class="block text-xs font-semibold text-slate-600 mb-1">Pièce (optionnel)</label>
                  <input v-model="payForm.numero_piece_paiement" type="text"
                         class="w-full rounded-xl border border-slate-200 px-3 py-2 outline-none
                                focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100" />
                </div>

                <div class="md:col-span-3 flex gap-2">
                  <button
                    class="px-4 py-2 rounded-xl bg-emerald-600 text-white font-semibold hover:opacity-95 disabled:opacity-60"
                    :disabled="saving"
                    @click="saveAvance"
                  >
                    Enregistrer l’avance
                  </button>

                  <button
                    class="px-4 py-2 rounded-xl border font-semibold hover:bg-slate-50 disabled:opacity-60"
                    :disabled="saving || !canConfirmPay"
                    @click="confirmPay"
                    title="Bloqué tant qu’il n’y a pas d’avance enregistrée (type paiement)"
                  >
                    Confirmer paiement
                  </button>
                </div>
              </div>

              <div v-else class="text-sm text-slate-500">
                (Cette section est active uniquement quand la mission est en <b>valide_cp</b>.)
              </div>
            </div>

            <!-- 2) PJ -->
            <div class="bg-white rounded-2xl border p-4 space-y-3">
              <div class="font-bold text-slate-900">Pièces justificatives (PJ)</div>

              <div class="text-sm">
                <span class="font-semibold">État :</span>
                <span :class="selected?.pj_regularise ? 'text-emerald-700 font-semibold' : 'text-rose-700 font-semibold'">
                  {{ selected?.pj_regularise ? 'Régularisées' : 'À régulariser' }}
                </span>
              </div>

              <div v-if="canRegulariserPJ" class="space-y-2">
                <label class="block text-xs font-semibold text-slate-600">Note (optionnel)</label>
                <textarea v-model="pjForm.note_regularisation" rows="3"
                          class="w-full rounded-xl border border-slate-200 px-3 py-2 outline-none
                                 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"></textarea>

                <button
                  class="px-4 py-2 rounded-xl bg-emerald-600 text-white font-semibold hover:opacity-95 disabled:opacity-60"
                  :disabled="saving"
                  @click="regulariserPJ"
                >
                  Marquer PJ régularisées
                </button>
              </div>

              <div v-else class="text-sm text-slate-500">
                (Disponible quand la mission est <b>avance_payee</b> ou <b>en_cours</b> et PJ non régularisées.)
              </div>
            </div>

            <!-- 3) Clôture -->
            <div class="bg-white rounded-2xl border p-4 space-y-3">
              <div class="font-bold text-slate-900">Clôture</div>

              <div class="text-sm text-slate-600">
                Clôture possible uniquement si la mission est <b>en_cours</b> et <b>PJ régularisées</b>.
              </div>

              <button
                class="px-4 py-2 rounded-xl bg-slate-900 text-white font-semibold hover:opacity-95 disabled:opacity-60"
                :disabled="saving || !canCloturer"
                @click="cloturer"
              >
                Clôturer la mission
              </button>
            </div>
          </template>
        </div>
      </div>
    </div>
  </div>
</template>
