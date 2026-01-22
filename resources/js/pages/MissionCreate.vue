<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { onBeforeRouteLeave, useRouter } from 'vue-router'

const router = useRouter()

// --- Etat backend (brouillon créé)
const mission = ref(null) // { id, statut_actuel, ... }
const created = computed(() => !!mission.value?.id)
const status = computed(() => mission.value?.statut_actuel || 'brouillon')

// --- UI state
const notice = ref('')
const apiError = ref('')
const apiErrors = ref({})

const creating = ref(false)
const saving = ref(false)
const submitting = ref(false)

// --- Form data
const form = ref({
  // DB fields
  objet: '',
  destination: '',
  motif: '',
  moyen_deplacement: '',
  date_debut: '',
  date_fin: '',
  montant_avance_demande: '',

  // Visuel seulement (non DB)
  missionnaires: '',
  objets_mission: '',
  resultats_attendus: '',
  distance_itineraire: '',
  decompte_carburants: '',
  planning: [{ date: '', activite: '', trajet: '', distance_km: '', nuitee: '', observation: '' }],
})

const control =
  'w-full rounded-xl border border-slate-200 bg-white px-4 py-3 outline-none ' +
  'focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100'

const isDraft = computed(() => status.value === 'brouillon')
const readonly = computed(() => created.value && !isDraft.value) // après soumission => lecture seule

const dateError = computed(() => {
  if (!form.value.date_debut || !form.value.date_fin) return ''
  const d1 = new Date(form.value.date_debut)
  const d2 = new Date(form.value.date_fin)
  if (Number.isNaN(d1.getTime()) || Number.isNaN(d2.getTime())) return ''
  return d2 < d1 ? 'La date de retour doit être ≥ la date de départ.' : ''
})

const days = computed(() => {
  const d1 = form.value.date_debut ? new Date(form.value.date_debut) : null
  const d2 = form.value.date_fin ? new Date(form.value.date_fin) : null
  if (!d1 || !d2 || Number.isNaN(d1.getTime()) || Number.isNaN(d2.getTime())) return ''
  const diff = Math.floor((d2 - d1) / 86400000)
  if (diff < 0) return ''
  return diff + 1
})

const fieldError = (key) => {
  const errs = apiErrors.value?.[key]
  return Array.isArray(errs) && errs.length ? errs[0] : ''
}

const resetAlerts = () => {
  notice.value = ''
  apiError.value = ''
  apiErrors.value = {}
}

const requiredReady = computed(() => {
  // Condition minimale imposée par backend store(): required objet & destination
  return form.value.objet.trim().length > 0 && form.value.destination.trim().length > 0
})

const payloadDB = () => ({
  objet: form.value.objet,
  destination: form.value.destination,
  motif: form.value.motif || null,
  moyen_deplacement: form.value.moyen_deplacement || null,
  date_debut: form.value.date_debut || null,
  date_fin: form.value.date_fin || null,
  montant_avance_demande: form.value.montant_avance_demande === '' ? null : Number(form.value.montant_avance_demande),
})

/**
 * Auto-create + auto-save (debounce)
 */
let timer = null
let lastNoticeAt = 0

const setSoftNotice = (msg) => {
  // évite de spammer le user à chaque autosave
  const now = Date.now()
  if (now - lastNoticeAt < 2500) return
  lastNoticeAt = now
  notice.value = msg
}

const scheduleAutosave = () => {
  if (readonly.value) return
  if (timer) clearTimeout(timer)
  timer = setTimeout(runAutosave, 700)
}

const runAutosave = async () => {
  if (readonly.value) return
  if (dateError.value) return // on évite d’envoyer un date_fin invalide

  // Tant que objet + destination pas remplis => on ne peut pas créer (backend required)
  if (!requiredReady.value) return

  // 1) Si pas encore créé => POST /api/missions
  if (!created.value) {
    if (creating.value) return
    creating.value = true
    resetAlerts()

    try {
      const res = await window.axios.post('/api/missions', payloadDB())
      mission.value = res.data?.mission ?? res.data
      notice.value = '✅ Brouillon créé automatiquement.'
    } catch (e) {
      if (e?.response?.status === 422) {
        apiErrors.value = e.response.data?.errors ?? {}
        apiError.value = 'Veuillez corriger les champs en erreur.'
      } else {
        apiError.value = e?.response?.data?.message || 'Erreur création brouillon.'
      }
    } finally {
      creating.value = false
    }
    return
  }

  // 2) Si déjà créé => PUT /api/missions/{id}
  if (!isDraft.value) return // si plus brouillon, backend refuse update
  if (saving.value) return

  saving.value = true
  // ne reset pas systématiquement les erreurs si ça vient de l’autosave
  // (on garde un message d’erreur si ça arrive)
  apiErrors.value = {}

  try {
    const res = await window.axios.put(`/api/missions/${mission.value.id}`, payloadDB())
    mission.value = res.data?.mission ?? res.data
    setSoftNotice('✅ Brouillon enregistré.')
  } catch (e) {
    if (e?.response?.status === 422) {
      apiErrors.value = e.response.data?.errors ?? {}
      apiError.value = 'Veuillez corriger les champs en erreur.'
    } else {
      apiError.value = e?.response?.data?.message || 'Erreur sauvegarde brouillon.'
    }
  } finally {
    saving.value = false
  }
}

// Watch uniquement les champs DB (pas la partie annexe)
watch(
  () => [
    form.value.objet,
    form.value.destination,
    form.value.motif,
    form.value.moyen_deplacement,
    form.value.date_debut,
    form.value.date_fin,
    form.value.montant_avance_demande,
  ],
  () => scheduleAutosave()
)

// flush quand on quitte la page
const flushAutosave = async () => {
  if (timer) {
    clearTimeout(timer)
    timer = null
  }
  await runAutosave()
}

onBeforeRouteLeave(async () => {
  await flushAutosave()
})

onBeforeUnmount(() => {
  if (timer) clearTimeout(timer)
})

/**
 * Soumettre (vers CH)
 * - si brouillon pas encore créé, on le crée d’abord (si possible)
 */
const submitToCH = async () => {
  resetAlerts()

  if (readonly.value) return
  if (dateError.value) {
    notice.value = dateError.value
    return
  }
  if (!requiredReady.value) {
    notice.value = 'Veuillez au minimum remplir Objet et Destination avant de soumettre.'
    return
  }

  // s'assurer que le brouillon existe + est à jour
  await flushAutosave()
  if (!created.value) return

  submitting.value = true
  try {
    await window.axios.post(`/api/missions/${mission.value.id}/soumettre`)
    const res = await window.axios.get(`/api/missions/${mission.value.id}`)
    mission.value = res.data?.mission ?? res.data
    notice.value = '✅ Mission soumise au CH. (Lecture seule)'
  } catch (e) {
    apiError.value = e?.response?.data?.message || 'Erreur soumission.'
  } finally {
    submitting.value = false
  }
}

// Annexe
const addRow = () => {
  form.value.planning.push({ date: '', activite: '', trajet: '', distance_km: '', nuitee: '', observation: '' })
}
const removeRow = (idx) => {
  if (form.value.planning.length <= 1) return
  form.value.planning.splice(idx, 1)
}

// Badge statut
const prettyStatus = computed(() => {
  const map = {
    brouillon: 'Brouillon',
    en_attente_ch: 'En attente CH',
    valide_ch: 'Validée CH',
    valide_raf: 'Validée RAF',
    valide_cp: 'Validée CP',
    avance_payee: 'Avance payée',
    en_cours: 'En cours',
    cloturee: 'Clôturée',
  }
  return map[status.value] || status.value
})

const badgeClass = computed(() => {
  const s = status.value
  if (s === 'brouillon') return 'bg-slate-100 text-slate-700'
  if (s === 'en_attente_ch') return 'bg-blue-100 text-blue-700'
  if (['valide_ch', 'valide_raf', 'valide_cp'].includes(s)) return 'bg-amber-100 text-amber-700'
  if (s === 'avance_payee') return 'bg-emerald-100 text-emerald-700'
  if (s === 'en_cours') return 'bg-indigo-100 text-indigo-700'
  if (s === 'cloturee') return 'bg-slate-200 text-slate-700'
  return 'bg-slate-100 text-slate-700'
})
</script>

<template>
  <div class="space-y-5">
    <!-- Header -->
    <div class="flex items-start justify-between gap-4">
      <div>
        <h1 class="text-3xl font-extrabold text-slate-900">Nouvelle mission</h1>

        <div class="mt-2 flex items-center gap-2">
          <span class="text-xs font-semibold px-3 py-1 rounded-full" :class="badgeClass">
            {{ prettyStatus }}
          </span>

          <span v-if="created" class="text-xs text-slate-500">
            ID: {{ mission.id }} • Auto-enregistrement activé
          </span>

          <span v-else class="text-xs text-slate-500">
            Auto-enregistrement : démarre dès que Objet + Destination sont remplis
          </span>
        </div>
      </div>

      <button class="px-3 py-2 rounded-xl border bg-white hover:bg-slate-50" @click="router.back()">
        ← Retour
      </button>
    </div>

    <div v-if="notice" class="rounded-2xl border bg-emerald-50 border-emerald-200 px-5 py-3 text-emerald-800">
      {{ notice }}
    </div>

    <div v-if="apiError" class="rounded-2xl border bg-rose-50 border-rose-200 px-5 py-3 text-rose-800">
      {{ apiError }}
    </div>

    <!-- Form -->
    <form class="space-y-4" @submit.prevent>
      <!-- Carte 1 -->
      <div class="bg-white rounded-2xl border shadow-sm p-6 space-y-4">
        <div class="text-lg font-bold text-slate-900">Informations principales</div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Objet / Titre <span class="text-rose-600">*</span>
            </label>
            <input
              v-model="form.objet"
              type="text"
              :readonly="readonly"
              :class="[
                control,
                readonly ? 'bg-slate-50 text-slate-700' : '',
                fieldError('objet') ? 'border-rose-300 focus:border-rose-500 focus:ring-rose-100' : ''
              ]"
              placeholder="Ex : Supervision chantier – Moramanga"
            />
            <p v-if="fieldError('objet')" class="text-xs text-rose-700 mt-1">{{ fieldError('objet') }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Destination <span class="text-rose-600">*</span>
            </label>
            <input
              v-model="form.destination"
              type="text"
              :readonly="readonly"
              :class="[
                control,
                readonly ? 'bg-slate-50 text-slate-700' : '',
                fieldError('destination') ? 'border-rose-300 focus:border-rose-500 focus:ring-rose-100' : ''
              ]"
              placeholder="Ex : Ambatondrazaka…"
            />
            <p v-if="fieldError('destination')" class="text-xs text-rose-700 mt-1">{{ fieldError('destination') }}</p>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Montant avance demandée (MGA)</label>
            <input
              v-model="form.montant_avance_demande"
              type="number"
              min="0"
              step="1"
              :readonly="readonly"
              :class="[control, readonly ? 'bg-slate-50 text-slate-700' : '']"
              placeholder="Ex : 2500000"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Moyen de déplacement</label>
            <input
              v-model="form.moyen_deplacement"
              type="text"
              :readonly="readonly"
              :class="[control, readonly ? 'bg-slate-50 text-slate-700' : '']"
              placeholder="Ex : 4x4, moto, avion…"
            />
          </div>
        </div>
      </div>

      <!-- Carte 2 -->
      <div class="bg-white rounded-2xl border shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b flex items-center justify-between gap-3">
          <div class="font-bold text-slate-900">Contenu mission</div>
          <span class="text-xs font-semibold px-3 py-1 rounded-full bg-slate-100 text-slate-700">
            Contexte = “motif” (DB)
          </span>
        </div>

        <div class="p-6 space-y-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Nom des missionnaires</label>
            <textarea
              v-model="form.missionnaires"
              rows="2"
              :readonly="readonly"
              :class="[control, 'min-h-[74px]', readonly ? 'bg-slate-50 text-slate-700' : '']"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Contexte / Motif</label>
            <textarea
              v-model="form.motif"
              rows="3"
              :readonly="readonly"
              :class="[control, 'min-h-[92px]', readonly ? 'bg-slate-50 text-slate-700' : '']"
            />
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Objets de mission</label>
              <textarea v-model="form.objets_mission" rows="3" :readonly="readonly" :class="[control,'min-h-[92px]', readonly ? 'bg-slate-50 text-slate-700' : '']" />
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Résultats attendus</label>
              <textarea v-model="form.resultats_attendus" rows="3" :readonly="readonly" :class="[control,'min-h-[92px]', readonly ? 'bg-slate-50 text-slate-700' : '']" />
            </div>
          </div>
        </div>
      </div>

      <!-- Carte 3 -->
      <div class="bg-white rounded-2xl border shadow-sm p-6 space-y-4">
        <div class="text-lg font-bold text-slate-900">Logistique & dates</div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Distance & itinéraire</label>
            <textarea v-model="form.distance_itineraire" rows="3" :readonly="readonly" :class="[control,'min-h-[92px]', readonly ? 'bg-slate-50 text-slate-700' : '']" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Décompte carburants</label>
            <textarea v-model="form.decompte_carburants" rows="3" :readonly="readonly" :class="[control,'min-h-[92px]', readonly ? 'bg-slate-50 text-slate-700' : '']" />
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Date départ</label>
            <input v-model="form.date_debut" type="date" :readonly="readonly" :class="[control, readonly ? 'bg-slate-50 text-slate-700' : '']" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Date retour</label>
            <input v-model="form.date_fin" type="date" :readonly="readonly" :class="[control, readonly ? 'bg-slate-50 text-slate-700' : '']" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Nombre de jours</label>
            <input :value="days" type="text" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 outline-none" readonly />
          </div>
        </div>

        <div v-if="dateError" class="text-sm text-rose-700">{{ dateError }}</div>
      </div>

      <!-- Annexe -->
      <div class="bg-white rounded-2xl border shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b flex items-center justify-between gap-3">
          <div class="font-bold text-slate-900">Annexe : planning de mission</div>
          <button
            type="button"
            class="px-3 py-2 rounded-xl bg-slate-900 text-white text-sm font-semibold hover:opacity-95 disabled:opacity-60"
            :disabled="readonly"
            @click="addRow"
          >
            + Ajouter une ligne
          </button>
        </div>

        <div class="p-6 overflow-x-auto">
          <table class="min-w-[980px] w-full">
            <thead class="bg-slate-50 text-xs font-semibold text-slate-500">
              <tr class="text-left">
                <th class="px-4 py-3">DATE</th>
                <th class="px-4 py-3">ACTIVITÉ</th>
                <th class="px-4 py-3">TRAJET</th>
                <th class="px-4 py-3">DISTANCE</th>
                <th class="px-4 py-3">NUITÉE</th>
                <th class="px-4 py-3">OBS.</th>
                <th class="px-4 py-3 text-right"></th>
              </tr>
            </thead>

            <tbody class="divide-y">
              <tr v-for="(row, i) in form.planning" :key="i" class="hover:bg-slate-50">
                <td class="px-4 py-3"><input v-model="row.date" type="date" :disabled="readonly" class="w-full rounded-lg border border-slate-200 px-3 py-2 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100 disabled:bg-slate-50" /></td>
                <td class="px-4 py-3"><input v-model="row.activite" type="text" :disabled="readonly" class="w-full rounded-lg border border-slate-200 px-3 py-2 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100 disabled:bg-slate-50" /></td>
                <td class="px-4 py-3"><input v-model="row.trajet" type="text" :disabled="readonly" class="w-full rounded-lg border border-slate-200 px-3 py-2 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100 disabled:bg-slate-50" /></td>
                <td class="px-4 py-3"><input v-model="row.distance_km" type="number" min="0" :disabled="readonly" class="w-full rounded-lg border border-slate-200 px-3 py-2 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100 disabled:bg-slate-50" /></td>
                <td class="px-4 py-3"><input v-model="row.nuitee" type="text" :disabled="readonly" class="w-full rounded-lg border border-slate-200 px-3 py-2 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100 disabled:bg-slate-50" /></td>
                <td class="px-4 py-3"><input v-model="row.observation" type="text" :disabled="readonly" class="w-full rounded-lg border border-slate-200 px-3 py-2 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100 disabled:bg-slate-50" /></td>
                <td class="px-4 py-3 text-right">
                  <button
                    type="button"
                    class="px-3 py-2 rounded-lg border text-sm font-semibold hover:bg-rose-50 text-rose-700 disabled:opacity-60"
                    :disabled="readonly || form.planning.length <= 1"
                    @click="removeRow(i)"
                  >
                    Supprimer
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ✅ SEUL bouton action : Soumettre -->
      <div class="flex items-center justify-end">
        <button
          type="button"
          class="px-5 py-2 rounded-xl bg-emerald-600 text-white font-semibold shadow-sm hover:opacity-95 disabled:opacity-60"
          :disabled="submitting || readonly || !requiredReady"
          @click="submitToCH"
        >
          {{ submitting ? 'Soumission…' : 'Soumettre' }}
        </button>
      </div>
    </form>
  </div>
</template>
