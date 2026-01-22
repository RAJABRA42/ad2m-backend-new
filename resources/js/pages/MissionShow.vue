<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const id = computed(() => Number(route.params.id))

// ✅ mode venant de la liste : view | edit
const mode = computed(() => String(route.query.mode || 'view'))
const appliedMode = ref(false)

const loading = ref(false)
const saving = ref(false)
const submitting = ref(false)
const deleting = ref(false)

const error = ref('')
const notice = ref('')
const apiError = ref('')
const apiErrors = ref({})

const mission = ref(null)
const editing = ref(false)

const form = ref({
  // --- Champs DB ---
  objet: '',
  destination: '',
  motif: '',
  moyen_deplacement: '',
  date_debut: '',
  date_fin: '',
  montant_avance_demande: '',

  // --- Champs visuels (non DB) ---
  missionnaires: '',
  objets_mission: '',
  resultats_attendus: '',
  distance_itineraire: '',
  decompte_carburants: '',

  // --- Annexe (visuel) ---
  planning: [{ date: '', activite: '', trajet: '', distance_km: '', nuitee: '', observation: '' }],
})

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

const isOwner = computed(() => {
  const uid = auth.user?.id
  return !!uid && Number(mission.value?.demandeur_id) === Number(uid)
})

const isDraft = computed(() => norm(mission.value?.statut_actuel) === 'brouillon')
const canEdit = computed(() => isOwner.value && isDraft.value)

// ✅ lecture seule TOTAL : tout est désactivé si pas (owner + brouillon + editing)
const isReadonly = computed(() => !(canEdit.value && editing.value))

const controlBase =
  'w-full rounded-xl border border-slate-200 bg-white px-4 py-3 outline-none ' +
  'focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100 ' +
  'disabled:bg-slate-50 disabled:text-slate-700 disabled:cursor-not-allowed disabled:opacity-100'

const fieldError = (key) => {
  const errs = apiErrors.value?.[key]
  return Array.isArray(errs) && errs.length ? errs[0] : ''
}

const days = computed(() => {
  const d1 = form.value.date_debut ? new Date(form.value.date_debut) : null
  const d2 = form.value.date_fin ? new Date(form.value.date_fin) : null
  if (!d1 || !d2 || Number.isNaN(d1.getTime()) || Number.isNaN(d2.getTime())) return ''
  const diff = Math.floor((d2 - d1) / 86400000)
  if (diff < 0) return ''
  return diff + 1
})

const dateError = computed(() => {
  if (!form.value.date_debut || !form.value.date_fin) return ''
  const d1 = new Date(form.value.date_debut)
  const d2 = new Date(form.value.date_fin)
  if (Number.isNaN(d1.getTime()) || Number.isNaN(d2.getTime())) return ''
  return d2 < d1 ? 'La date de retour doit être ≥ la date de départ.' : ''
})

const syncFormFromMission = () => {
  const m = mission.value

  // DB
  form.value.objet = m?.objet ?? ''
  form.value.destination = m?.destination ?? ''
  form.value.motif = m?.motif ?? ''
  form.value.moyen_deplacement = m?.moyen_deplacement ?? ''
  form.value.date_debut = m?.date_debut ? String(m.date_debut).slice(0, 10) : ''
  form.value.date_fin = m?.date_fin ? String(m.date_fin).slice(0, 10) : ''
  form.value.montant_avance_demande =
    m?.montant_avance_demande === null || m?.montant_avance_demande === undefined ? '' : Number(m.montant_avance_demande)

  // visuels : on garde si déjà saisi dans la session, sinon on laisse vide
  if (!Array.isArray(form.value.planning) || form.value.planning.length === 0) {
    form.value.planning = [{ date: '', activite: '', trajet: '', distance_km: '', nuitee: '', observation: '' }]
  }
}

const applyModeOnce = () => {
  if (appliedMode.value) return
  appliedMode.value = true

  // ✏️ => edit direct si autorisé
  if (mode.value === 'edit' && canEdit.value) {
    editing.value = true
  } else {
    editing.value = false
  }
}

const load = async () => {
  loading.value = true
  error.value = ''
  notice.value = ''
  apiError.value = ''
  apiErrors.value = {}

  try {
    const r = await window.axios.get(`/api/missions/${id.value}`)
    mission.value = r.data?.mission ?? r.data
    syncFormFromMission()
    applyModeOnce()
  } catch (e) {
    mission.value = null
    error.value = e?.response?.data?.message || 'Erreur chargement mission.'
  } finally {
    loading.value = false
  }
}

onMounted(load)

// --- annexe
const addRow = () => {
  if (isReadonly.value) return
  form.value.planning.push({ date: '', activite: '', trajet: '', distance_km: '', nuitee: '', observation: '' })
}
const removeRow = (idx) => {
  if (isReadonly.value) return
  if (form.value.planning.length <= 1) return
  form.value.planning.splice(idx, 1)
}

// --- actions
const startEdit = () => {
  if (!canEdit.value) return
  editing.value = true
  notice.value = ''
  apiError.value = ''
  apiErrors.value = {}
}

const cancelEdit = () => {
  editing.value = false
  apiError.value = ''
  apiErrors.value = {}
  syncFormFromMission()
}

const save = async () => {
  if (!canEdit.value) return false
  if (dateError.value) {
    notice.value = dateError.value
    return false
  }

  saving.value = true
  notice.value = ''
  apiError.value = ''
  apiErrors.value = {}

  try {
    const payload = {
      objet: form.value.objet,
      destination: form.value.destination,
      motif: form.value.motif || null,
      moyen_deplacement: form.value.moyen_deplacement || null,
      date_debut: form.value.date_debut || null,
      date_fin: form.value.date_fin || null,
      montant_avance_demande: form.value.montant_avance_demande === '' ? null : Number(form.value.montant_avance_demande),
    }

    const r = await window.axios.put(`/api/missions/${id.value}`, payload)
    mission.value = r.data?.mission ?? r.data
    editing.value = false
    notice.value = '✅ Brouillon enregistré.'
    return true
  } catch (e) {
    if (e?.response?.status === 422) {
      apiErrors.value = e.response.data?.errors ?? {}
      apiError.value = 'Veuillez corriger les champs en erreur.'
    } else {
      apiError.value = e?.response?.data?.message || 'Erreur enregistrement.'
    }
    return false
  } finally {
    saving.value = false
  }
}

const submitMission = async () => {
  if (!canEdit.value) return
  notice.value = ''
  apiError.value = ''
  apiErrors.value = {}

  if (dateError.value) {
    notice.value = dateError.value
    return
  }

  // si on est en édition, on enregistre avant de soumettre
  if (editing.value) {
    const ok = await save()
    if (!ok) return
  }

  submitting.value = true
  try {
    await window.axios.post(`/api/missions/${id.value}/soumettre`)
    appliedMode.value = false
    await load()
    notice.value = '✅ Mission soumise au CH.'
  } catch (e) {
    apiError.value = e?.response?.data?.message || 'Erreur soumission.'
  } finally {
    submitting.value = false
  }
}

// --- delete modal custom
const confirmOpen = ref(false)

const askDelete = () => {
  if (!canEdit.value) return
  confirmOpen.value = true
}

const doDelete = async () => {
  if (!canEdit.value) return
  deleting.value = true
  apiError.value = ''
  try {
    await window.axios.delete(`/api/missions/${id.value}`)
    confirmOpen.value = false
    router.push({ name: 'missions' })
  } catch (e) {
    apiError.value = e?.response?.data?.message || 'Erreur suppression.'
  } finally {
    deleting.value = false
  }
}
</script>

<template>
  <div class="space-y-5">
    <!-- Header -->
    <div class="flex items-start justify-between gap-4">
      <div class="min-w-0">
        <h1 class="text-3xl font-extrabold text-slate-900 truncate">
          {{ mission?.objet || ('Mission #' + id) }}
        </h1>

        <div class="mt-2 flex items-center gap-2">
          <span class="text-xs font-semibold px-3 py-1 rounded-full" :class="badgeClass(mission?.statut_actuel)">
            {{ prettyStatus(mission?.statut_actuel) }}
          </span>

          <span v-if="!isDraft" class="text-xs text-slate-500">(Lecture seule — mission figée)</span>
          <span v-else-if="canEdit && !editing" class="text-xs text-slate-500">(Brouillon — cliquer “Modifier” pour éditer)</span>
        </div>
      </div>

      <div class="flex items-center gap-2">
        <button class="px-3 py-2 rounded-xl border bg-white hover:bg-slate-50" @click="router.back()">
          ← Retour
        </button>

        <button
          v-if="canEdit && !editing"
          class="px-3 py-2 rounded-xl border bg-white hover:bg-slate-50"
          @click="startEdit"
          title="Modifier"
        >
          ✎ Modifier
        </button>
      </div>
    </div>

    <div v-if="loading" class="bg-white rounded-2xl border shadow-sm p-6 text-slate-500">Chargement…</div>
    <div v-else-if="error" class="bg-white rounded-2xl border shadow-sm p-6 text-rose-700">{{ error }}</div>

    <template v-else>
      <div v-if="notice" class="rounded-2xl border bg-emerald-50 border-emerald-200 px-5 py-3 text-emerald-800">
        {{ notice }}
      </div>

      <div v-if="apiError" class="rounded-2xl border bg-rose-50 border-rose-200 px-5 py-3 text-rose-800">
        {{ apiError }}
      </div>

      <!-- FORM COMPLET (comme MissionCreate) -->
      <div class="space-y-4">
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
                :disabled="isReadonly"
                :class="[
                  controlBase,
                  fieldError('objet') ? 'border-rose-300 focus:border-rose-500 focus:ring-rose-100' : ''
                ]"
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
                :disabled="isReadonly"
                :class="[
                  controlBase,
                  fieldError('destination') ? 'border-rose-300 focus:border-rose-500 focus:ring-rose-100' : ''
                ]"
              />
              <p v-if="fieldError('destination')" class="text-xs text-rose-700 mt-1">{{ fieldError('destination') }}</p>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Montant avance demandée (MGA)</label>
              <input v-model="form.montant_avance_demande" type="number" min="0" step="1" :disabled="isReadonly" :class="controlBase" />
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Moyen de déplacement</label>
              <input v-model="form.moyen_deplacement" type="text" :disabled="isReadonly" :class="controlBase" />
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
              <label class="block text-sm font-medium text-slate-700 mb-1">Nom des missionnaires (visuel)</label>
              <textarea v-model="form.missionnaires" rows="2" :disabled="isReadonly" :class="[controlBase, 'min-h-[74px]']" />
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Contexte / Motif (DB)</label>
              <textarea
                v-model="form.motif"
                rows="3"
                :disabled="isReadonly"
                :class="[controlBase, 'min-h-[92px]']"
              />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Objets de mission (visuel)</label>
                <textarea v-model="form.objets_mission" rows="3" :disabled="isReadonly" :class="[controlBase, 'min-h-[92px]']" />
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Résultats attendus (visuel)</label>
                <textarea v-model="form.resultats_attendus" rows="3" :disabled="isReadonly" :class="[controlBase, 'min-h-[92px]']" />
              </div>
            </div>
          </div>
        </div>

        <!-- Carte 3 -->
        <div class="bg-white rounded-2xl border shadow-sm p-6 space-y-4">
          <div class="text-lg font-bold text-slate-900">Logistique & dates</div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Distance & itinéraire (visuel)</label>
              <textarea v-model="form.distance_itineraire" rows="3" :disabled="isReadonly" :class="[controlBase, 'min-h-[92px]']" />
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Décompte carburants (visuel)</label>
              <textarea v-model="form.decompte_carburants" rows="3" :disabled="isReadonly" :class="[controlBase, 'min-h-[92px]']" />
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Date départ (DB)</label>
              <input v-model="form.date_debut" type="date" :disabled="isReadonly" :class="controlBase" />
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Date retour (DB)</label>
              <input v-model="form.date_fin" type="date" :disabled="isReadonly" :class="controlBase" />
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
            <div class="font-bold text-slate-900">Annexe : planning de mission (visuel)</div>
            <button
              type="button"
              class="px-3 py-2 rounded-xl bg-slate-900 text-white text-sm font-semibold hover:opacity-95 disabled:opacity-60"
              :disabled="isReadonly"
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
                  <td class="px-4 py-3">
                    <input v-model="row.date" type="date" :disabled="isReadonly" class="w-full rounded-lg border border-slate-200 px-3 py-2 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100 disabled:bg-slate-50 disabled:opacity-100" />
                  </td>
                  <td class="px-4 py-3">
                    <input v-model="row.activite" type="text" :disabled="isReadonly" class="w-full rounded-lg border border-slate-200 px-3 py-2 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100 disabled:bg-slate-50 disabled:opacity-100" />
                  </td>
                  <td class="px-4 py-3">
                    <input v-model="row.trajet" type="text" :disabled="isReadonly" class="w-full rounded-lg border border-slate-200 px-3 py-2 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100 disabled:bg-slate-50 disabled:opacity-100" />
                  </td>
                  <td class="px-4 py-3">
                    <input v-model="row.distance_km" type="number" min="0" :disabled="isReadonly" class="w-full rounded-lg border border-slate-200 px-3 py-2 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100 disabled:bg-slate-50 disabled:opacity-100" />
                  </td>
                  <td class="px-4 py-3">
                    <input v-model="row.nuitee" type="text" :disabled="isReadonly" class="w-full rounded-lg border border-slate-200 px-3 py-2 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100 disabled:bg-slate-50 disabled:opacity-100" />
                  </td>
                  <td class="px-4 py-3">
                    <input v-model="row.observation" type="text" :disabled="isReadonly" class="w-full rounded-lg border border-slate-200 px-3 py-2 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100 disabled:bg-slate-50 disabled:opacity-100" />
                  </td>
                  <td class="px-4 py-3 text-right">
                    <button
                      type="button"
                      class="px-3 py-2 rounded-lg border text-sm font-semibold hover:bg-rose-50 text-rose-700 disabled:opacity-60"
                      :disabled="isReadonly || form.planning.length <= 1"
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

        <!-- Actions bas du formulaire (uniquement brouillon owner) -->
        <div class="flex items-center justify-end gap-2">
          <button
            v-if="canEdit && editing"
            class="px-4 py-2 rounded-xl border bg-white hover:bg-slate-50 disabled:opacity-60"
            :disabled="saving || submitting"
            @click="cancelEdit"
            type="button"
          >
            Annuler
          </button>

          <button
            v-if="canEdit && editing"
            class="px-5 py-2 rounded-xl bg-slate-900 text-white font-semibold hover:opacity-95 disabled:opacity-60"
            :disabled="saving || submitting"
            @click="save"
            type="button"
          >
            {{ saving ? 'Enregistrement…' : 'Enregistrer' }}
          </button>

          <button
            v-if="canEdit"
            class="px-5 py-2 rounded-xl bg-emerald-600 text-white font-semibold hover:opacity-95 disabled:opacity-60"
            :disabled="saving || submitting"
            @click="submitMission"
            type="button"
          >
            {{ submitting ? 'Soumission…' : 'Soumettre' }}
          </button>

          <button
            v-if="canEdit"
            class="px-4 py-2 rounded-xl border border-rose-300 text-rose-700 font-semibold hover:bg-rose-50 disabled:opacity-60"
            :disabled="saving || submitting"
            @click="askDelete"
            type="button"
          >
            Supprimer
          </button>
        </div>
      </div>
    </template>

    <!-- Modal suppression -->
    <div v-if="confirmOpen" class="fixed inset-0 z-50">
      <div class="absolute inset-0 bg-black/40" @click="confirmOpen = false"></div>
      <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="w-full max-w-md bg-white rounded-2xl shadow-lg border p-6">
          <div class="text-lg font-bold text-slate-900">Supprimer la mission ?</div>
          <p class="text-slate-600 mt-2">
            Cette action est définitive. Voulez-vous vraiment supprimer ce brouillon ?
          </p>

          <div class="mt-5 flex justify-end gap-2">
            <button class="px-4 py-2 rounded-xl border bg-white hover:bg-slate-50" :disabled="deleting" @click="confirmOpen = false">
              Annuler
            </button>
            <button class="px-4 py-2 rounded-xl bg-rose-600 text-white font-semibold hover:opacity-95 disabled:opacity-60" :disabled="deleting" @click="doDelete">
              {{ deleting ? 'Suppression…' : 'Supprimer' }}
            </button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>
