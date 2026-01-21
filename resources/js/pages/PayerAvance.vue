<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()

const id = computed(() => Number(route.params.id))

const loading = ref(false)
const error = ref('')

const mission = ref(null)
const avances = ref([])

const saving = ref(false)
const confirming = ref(false)
const regularising = ref(false)
const closing = ref(false)

const msg = ref('')

// Form avance (paiement)
const form = ref({
  montant: '',
  date_operation: new Date().toISOString().slice(0, 10),
  numero_piece_paiement: '',
})

// Note régularisation PJ (optionnel)
const note = ref('')

const paiementAvance = computed(() => {
  return avances.value.find(a => String(a.type_operation).toLowerCase() === 'paiement') || null
})

const canConfirmPay = computed(() => {
  return mission.value && String(mission.value.statut_actuel).toLowerCase() === 'valide_cp' && !!paiementAvance.value
})

const canRegularise = computed(() => {
  if (!mission.value) return false
  const s = String(mission.value.statut_actuel).toLowerCase()
  return (s === 'avance_payee' || s === 'en_cours') && !mission.value.pj_regularise
})

const canClose = computed(() => {
  if (!mission.value) return false
  const s = String(mission.value.statut_actuel).toLowerCase()
  return s === 'en_cours' && !!mission.value.pj_regularise
})

// Chargement mission (on tente /api/missions/:id, sinon fallback /api/missions)
const loadMission = async () => {
  error.value = ''
  msg.value = ''
  loading.value = true
  try {
    try {
      const r = await window.axios.get(`/api/missions/${id.value}`)
      mission.value = r.data?.mission ?? r.data
    } catch (e) {
      const r = await window.axios.get('/api/missions')
      const list = r.data?.missions ?? []
      mission.value = list.find(m => Number(m.id) === id.value) || null
    }

    if (!mission.value) {
      error.value = "Mission introuvable."
      return
    }
  } catch (e) {
    error.value = e?.response?.data?.message || 'Erreur chargement mission.'
  } finally {
    loading.value = false
  }
}

const loadAvances = async () => {
  if (!id.value) return
  try {
    const r = await window.axios.get(`/api/missions/${id.value}/avances`)
    avances.value = r.data?.avances ?? []
  } catch (e) {
    avances.value = []
  }
}

const refresh = async () => {
  await loadMission()
  await loadAvances()

  // préremplissage montant si possible
  if (mission.value && !paiementAvance.value && !form.value.montant) {
    form.value.montant = mission.value.montant_avance_demande ?? ''
  }
}

onMounted(refresh)

const saveAvance = async () => {
  msg.value = ''
  error.value = ''
  saving.value = true
  try {
    const montant = Number(form.value.montant)
    if (!montant || montant <= 0) {
      error.value = 'Montant invalide.'
      return
    }

    await window.axios.post(`/api/missions/${id.value}/avances`, {
      montant,
      date_operation: form.value.date_operation,
      numero_piece_paiement: form.value.numero_piece_paiement || null,
    })

    msg.value = 'Avance enregistrée.'
    await loadAvances()
  } catch (e) {
    error.value = e?.response?.data?.message || "Erreur enregistrement avance."
  } finally {
    saving.value = false
  }
}

const confirmPay = async () => {
  msg.value = ''
  error.value = ''
  confirming.value = true
  try {
    await window.axios.post(`/api/missions/${id.value}/payer`, {
      avance_id: paiementAvance.value.id,
    })

    msg.value = 'Paiement confirmé.'
    await loadMission()
  } catch (e) {
    error.value = e?.response?.data?.message || 'Paiement impossible.'
  } finally {
    confirming.value = false
  }
}

const regularisePJ = async () => {
  msg.value = ''
  error.value = ''
  regularising.value = true
  try {
    await window.axios.post(`/api/missions/${id.value}/regulariser-pj`, {
      note_regularisation: note.value || null,
    })

    msg.value = 'PJ marquées comme régularisées.'
    await loadMission()
  } catch (e) {
    error.value = e?.response?.data?.message || 'Action impossible.'
  } finally {
    regularising.value = false
  }
}

const cloturer = async () => {
  msg.value = ''
  error.value = ''
  closing.value = true
  try {
    await window.axios.post(`/api/missions/${id.value}/cloturer`)
    msg.value = 'Mission clôturée.'
    await loadMission()
  } catch (e) {
    error.value = e?.response?.data?.message || 'Clôture impossible.'
  } finally {
    closing.value = false
  }
}
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-start justify-between gap-4">
      <div>
        <h1 class="text-2xl font-extrabold text-slate-900">Traitement ACCP</h1>
        <p class="text-slate-600 mt-1">
          Enregistrer l’avance → Confirmer paiement → Régulariser PJ → Clôturer
        </p>
      </div>

      <div class="flex items-center gap-2">
        <button class="px-3 py-2 rounded-xl border bg-white hover:bg-slate-50" @click="router.push({ name: 'validation' })">
          ← Retour
        </button>
        <button class="px-3 py-2 rounded-xl border bg-white hover:bg-slate-50" @click="refresh" :disabled="loading">
          Rafraîchir
        </button>
      </div>
    </div>

    <div v-if="loading" class="bg-white rounded-2xl border shadow-sm p-6 text-slate-500">
      Chargement…
    </div>

    <div v-else-if="error" class="bg-white rounded-2xl border shadow-sm p-6">
      <div class="text-rose-700 font-semibold">{{ error }}</div>
    </div>

    <template v-else>
      <!-- Infos mission -->
      <div class="bg-white rounded-2xl border shadow-sm p-6 space-y-2">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
          <div class="min-w-0">
            <div class="text-lg font-extrabold text-slate-900 truncate">
              {{ mission?.objet || ('Mission #' + mission?.id) }}
            </div>
            <div class="text-sm text-slate-600">
              Destination: <span class="font-semibold text-slate-900">{{ mission?.destination || '—' }}</span>
              <span class="mx-2">•</span>
              Avance demandée: <span class="font-semibold text-slate-900">{{ mission?.montant_avance_demande ?? '—' }}</span>
            </div>
          </div>

          <div class="text-sm">
            <div class="text-slate-500">Statut</div>
            <div class="font-bold text-slate-900">{{ mission?.statut_actuel }}</div>
            <div class="text-xs mt-1"
                 :class="mission?.pj_regularise ? 'text-emerald-700' : 'text-amber-700'">
              PJ régularisées : {{ mission?.pj_regularise ? 'Oui' : 'Non' }}
            </div>
          </div>
        </div>
      </div>

      <!-- Messages -->
      <div v-if="msg" class="rounded-2xl border bg-emerald-50 border-emerald-200 px-5 py-3 text-emerald-800">
        {{ msg }}
      </div>

      <!-- Avance paiement -->
      <div class="bg-white rounded-2xl border shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b flex items-center justify-between">
          <div class="font-bold text-slate-900">Enregistrer l’avance (paiement)</div>
          <div class="text-sm text-slate-500">
            {{ paiementAvance ? '✅ Avance enregistrée' : '⏳ Aucune avance enregistrée' }}
          </div>
        </div>

        <div class="p-6 space-y-4">
          <div v-if="paiementAvance" class="rounded-xl border bg-slate-50 px-4 py-3">
            <div class="text-sm text-slate-700">
              Montant: <b>{{ paiementAvance.montant }}</b> • Date: <b>{{ paiementAvance.date_operation }}</b> • Pièce: <b>{{ paiementAvance.numero_piece_paiement ?? '—' }}</b>
            </div>
          </div>

          <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Montant (MGA)</label>
              <input
                v-model="form.montant"
                type="number"
                min="0"
                class="w-full rounded-xl border border-slate-200 px-4 py-2 outline-none
                       focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Date de paiement</label>
              <input
                v-model="form.date_operation"
                type="date"
                class="w-full rounded-xl border border-slate-200 px-4 py-2 outline-none
                       focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
              />
            </div>

            <div class="sm:col-span-2">
              <label class="block text-sm font-medium text-slate-700 mb-1">N° pièce (BR/Chèque/Virement) (optionnel)</label>
              <input
                v-model="form.numero_piece_paiement"
                type="text"
                class="w-full rounded-xl border border-slate-200 px-4 py-2 outline-none
                       focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
              />
            </div>

            <div class="sm:col-span-2 flex justify-end">
              <button
                class="px-4 py-2 rounded-xl bg-brand text-white font-semibold hover:opacity-95 disabled:opacity-60"
                :disabled="saving"
                @click="saveAvance"
              >
                <span v-if="saving">Enregistrement…</span>
                <span v-else>Enregistrer l’avance</span>
              </button>
            </div>
          </div>

          <!-- Confirmer paiement -->
          <div class="border-t pt-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="text-sm text-slate-600">
              Le statut ne peut passer en <b>avance_payee</b> que si une avance “paiement” existe.
            </div>

            <button
              class="px-4 py-2 rounded-xl bg-emerald-600 text-white font-semibold hover:opacity-95 disabled:opacity-60"
              :disabled="confirming || !canConfirmPay"
              @click="confirmPay"
            >
              <span v-if="confirming">Confirmation…</span>
              <span v-else>Confirmer paiement</span>
            </button>
          </div>
        </div>
      </div>

      <!-- PJ régularisées -->
      <div class="bg-white rounded-2xl border shadow-sm p-6 space-y-3">
        <div class="font-bold text-slate-900">Régularisation PJ</div>

        <div class="text-sm text-slate-600">
          Marquer les PJ comme régularisées (contrôle réel) — obligatoire avant clôture.
        </div>

        <textarea
          v-model="note"
          rows="3"
          placeholder="Note (optionnel) : exemple 'PJ OK' / 'Manque facture hôtel'..."
          class="w-full rounded-xl border border-slate-200 px-4 py-2 outline-none
                 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
        ></textarea>

        <div class="flex justify-end">
          <button
            class="px-4 py-2 rounded-xl border font-semibold hover:bg-slate-50 disabled:opacity-60"
            :disabled="regularising || !canRegularise"
            @click="regularisePJ"
          >
            <span v-if="regularising">En cours…</span>
            <span v-else>Marquer PJ régularisées</span>
          </button>
        </div>
      </div>

      <!-- Clôture -->
      <div class="bg-white rounded-2xl border shadow-sm p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
          <div>
            <div class="font-bold text-slate-900">Clôture</div>
            <div class="text-sm text-slate-600 mt-1">
              Clôture possible uniquement si statut = <b>en_cours</b> et PJ régularisées.
            </div>
          </div>

          <button
            class="px-4 py-2 rounded-xl bg-slate-900 text-white font-semibold hover:opacity-95 disabled:opacity-60"
            :disabled="closing || !canClose"
            @click="cloturer"
          >
            <span v-if="closing">Clôture…</span>
            <span v-else>Clôturer la mission</span>
          </button>
        </div>
      </div>

      <div v-if="error" class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-3 text-rose-700">
        {{ error }}
      </div>
    </template>
  </div>
</template>
