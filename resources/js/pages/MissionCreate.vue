<script setup>
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()

const saving = ref(false)
const notice = ref('')
const apiError = ref('')
const apiErrors = ref({})

const form = ref({
  // Champs “DB” (envoyés au backend)
  objet: '',
  destination: '',
  motif: '', // ✅ Option A : Contexte -> motif
  moyen_deplacement: '',
  date_debut: '',
  date_fin: '',
  montant_avance_demande: '',

  // Champs “visuels” (non DB)
  missionnaires: '',
  objets_mission: '',
  resultats_attendus: '',
  distance_itineraire: '',
  decompte_carburants: '',

  // Annexe (visuel)
  planning: [
    { date: '', activite: '', trajet: '', distance_km: '', nuitee: '', observation: '' },
  ],
})

const control =
  'w-full rounded-xl border border-slate-200 bg-white px-4 py-3 outline-none ' +
  'focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100'

const days = computed(() => {
  const d1 = form.value.date_debut ? new Date(form.value.date_debut) : null
  const d2 = form.value.date_fin ? new Date(form.value.date_fin) : null
  if (!d1 || !d2 || Number.isNaN(d1.getTime()) || Number.isNaN(d2.getTime())) return ''
  const diff = Math.floor((d2 - d1) / 86400000)
  if (diff < 0) return ''
  return diff + 1 // inclusif
})

const dateError = computed(() => {
  if (!form.value.date_debut || !form.value.date_fin) return ''
  const d1 = new Date(form.value.date_debut)
  const d2 = new Date(form.value.date_fin)
  if (Number.isNaN(d1.getTime()) || Number.isNaN(d2.getTime())) return ''
  return d2 < d1 ? 'La date de retour doit être supérieure ou égale à la date de départ.' : ''
})

const addRow = () => {
  form.value.planning.push({ date: '', activite: '', trajet: '', distance_km: '', nuitee: '', observation: '' })
}

const removeRow = (idx) => {
  if (form.value.planning.length <= 1) return
  form.value.planning.splice(idx, 1)
}

const fieldError = (key) => {
  const errs = apiErrors.value?.[key]
  return Array.isArray(errs) && errs.length ? errs[0] : ''
}

const submit = async () => {
  notice.value = ''
  apiError.value = ''
  apiErrors.value = {}

  // validations front minimum
  if (!form.value.objet.trim() || !form.value.destination.trim()) {
    notice.value = 'Veuillez remplir au minimum : Objet et Destination.'
    return
  }
  if (dateError.value) {
    notice.value = dateError.value
    return
  }

  saving.value = true
  try {
    const payload = {
      objet: form.value.objet,
      destination: form.value.destination,
      motif: form.value.motif || null,
      moyen_deplacement: form.value.moyen_deplacement || null,
      date_debut: form.value.date_debut || null,
      date_fin: form.value.date_fin || null,
      montant_avance_demande:
        form.value.montant_avance_demande === '' ? null : Number(form.value.montant_avance_demande),
    }

    const res = await window.axios.post('/api/missions', payload)
    const mission = res.data?.mission ?? res.data

    router.push({ name: 'missions.show', params: { id: mission.id } })
  } catch (e) {
    if (e?.response?.status === 422) {
      apiErrors.value = e.response.data?.errors ?? {}
      apiError.value = 'Veuillez corriger les champs en erreur.'
    } else if (e?.response?.status === 401) {
      apiError.value = "Vous n'êtes pas authentifié. Veuillez vous reconnecter."
    } else {
      apiError.value = e?.response?.data?.message || 'Erreur lors de la création.'
    }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="space-y-5">
    <!-- Header -->
    <div class="flex items-start justify-between gap-4">
      <div>
        <h1 class="text-3xl font-extrabold text-slate-900">Nouvelle mission</h1>
        <p class="text-slate-600 mt-1">
          Remplir la demande de mission (les validateurs vérifieront le dossier physique).
        </p>
      </div>

      <div class="flex items-center gap-2">
        <button
          class="px-3 py-2 rounded-xl border bg-white hover:bg-slate-50"
          :disabled="saving"
          @click="router.back()"
        >
          ← Retour
        </button>

        <button
          class="px-4 py-2 rounded-xl bg-brand text-white font-semibold shadow-sm hover:opacity-95 disabled:opacity-60"
          :disabled="saving"
          @click="submit"
        >
          {{ saving ? 'Création…' : 'Créer la mission' }}
        </button>
      </div>
    </div>

    <div
      v-if="notice"
      class="rounded-2xl border shadow-sm px-5 py-3"
      :class="notice.startsWith('✅') ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-rose-50 border-rose-200 text-rose-800'"
    >
      {{ notice }}
    </div>

    <div v-if="apiError" class="rounded-2xl border bg-rose-50 border-rose-200 px-5 py-3 text-rose-800">
      {{ apiError }}
    </div>

    <!-- Form -->
    <form class="space-y-4" @submit.prevent="submit">
      <!-- Carte 1: Informations principales (DB) -->
      <div class="bg-white rounded-2xl border shadow-sm p-6 space-y-4">
        <div class="text-lg font-bold text-slate-900">Informations principales</div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Objet / Titre de mission <span class="text-rose-600">*</span>
            </label>
            <input
              v-model="form.objet"
              type="text"
              :class="[control, fieldError('objet') ? 'border-rose-300 focus:border-rose-500 focus:ring-rose-100' : '']"
              placeholder="Ex : Supervision chantier – Moramanga"
            />
            <p v-if="fieldError('objet')" class="text-xs text-rose-700 mt-1">{{ fieldError('objet') }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Lieu de mission / Destination <span class="text-rose-600">*</span>
            </label>
            <input
              v-model="form.destination"
              type="text"
              :class="[control, fieldError('destination') ? 'border-rose-300 focus:border-rose-500 focus:ring-rose-100' : '']"
              placeholder="Ex : Ambatondrazaka, Moramanga…"
            />
            <p v-if="fieldError('destination')" class="text-xs text-rose-700 mt-1">{{ fieldError('destination') }}</p>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Montant avance demandée (MGA)
            </label>
            <input
              v-model="form.montant_avance_demande"
              type="number"
              min="0"
              step="1"
              :class="[control, fieldError('montant_avance_demande') ? 'border-rose-300 focus:border-rose-500 focus:ring-rose-100' : '']"
              placeholder="Ex : 2500000"
            />
            <p v-if="fieldError('montant_avance_demande')" class="text-xs text-rose-700 mt-1">
              {{ fieldError('montant_avance_demande') }}
            </p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Moyen de déplacement
            </label>
            <input
              v-model="form.moyen_deplacement"
              type="text"
              :class="[control, fieldError('moyen_deplacement') ? 'border-rose-300 focus:border-rose-500 focus:ring-rose-100' : '']"
              placeholder="Ex : 4x4 projet, moto, avion…"
            />
            <p v-if="fieldError('moyen_deplacement')" class="text-xs text-rose-700 mt-1">
              {{ fieldError('moyen_deplacement') }}
            </p>
          </div>
        </div>
      </div>

      <!-- Carte 2: Contenu mission (Contexte -> motif) -->
      <div class="bg-white rounded-2xl border shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b flex items-center justify-between gap-3">
          <div class="font-bold text-slate-900">Contenu mission</div>
          <span class="text-xs font-semibold px-3 py-1 rounded-full bg-slate-100 text-slate-700">
            Contexte = “motif” (DB)
          </span>
        </div>

        <div class="p-6 space-y-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Nom des missionnaires
            </label>
            <textarea
              v-model="form.missionnaires"
              rows="2"
              :class="[control, 'min-h-[74px]']"
              placeholder="Ex : RAKOTO Jean, RANDRIA Marie, chauffeur…"
            />
            <p class="text-xs text-slate-500 mt-1">Saisie visuelle (non enregistrée en base pour l’instant).</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Contexte / Motif
            </label>
            <textarea
              v-model="form.motif"
              rows="3"
              :class="[
                control,
                'min-h-[92px]',
                fieldError('motif') ? 'border-rose-300 focus:border-rose-500 focus:ring-rose-100' : ''
              ]"
              placeholder="Décris brièvement pourquoi la mission est nécessaire…"
            />
            <p v-if="fieldError('motif')" class="text-xs text-rose-700 mt-1">{{ fieldError('motif') }}</p>
            <p class="text-xs text-slate-500 mt-1">✅ Ce champ sera sauvegardé dans “motif”.</p>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Objets de mission</label>
              <textarea
                v-model="form.objets_mission"
                rows="3"
                :class="[control, 'min-h-[92px]']"
                placeholder="Ex : formation, supervision, collecte données…"
              />
              <p class="text-xs text-slate-500 mt-1">Non enregistrée en base pour l’instant.</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Résultats attendus</label>
              <textarea
                v-model="form.resultats_attendus"
                rows="3"
                :class="[control, 'min-h-[92px]']"
                placeholder="Ex : rapport mission, PV signé, liste bénéficiaires…"
              />
              <p class="text-xs text-slate-500 mt-1">Non enregistrée en base pour l’instant.</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Carte 3: Logistique & dates -->
      <div class="bg-white rounded-2xl border shadow-sm p-6 space-y-4">
        <div class="text-lg font-bold text-slate-900">Logistique & dates</div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Distance & itinéraire
            </label>
            <textarea
              v-model="form.distance_itineraire"
              rows="3"
              :class="[control, 'min-h-[92px]']"
              placeholder="Ex : Tana → Antsirabe (170 km) → Betafo…"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Décompte carburants
            </label>
            <textarea
              v-model="form.decompte_carburants"
              rows="3"
              :class="[control, 'min-h-[92px]']"
              placeholder="Ex : 195 km / conso 10L/100km ≈ 20L…"
            />
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Date départ</label>
            <input
              v-model="form.date_debut"
              type="date"
              :class="[control, fieldError('date_debut') ? 'border-rose-300 focus:border-rose-500 focus:ring-rose-100' : '']"
            />
            <p v-if="fieldError('date_debut')" class="text-xs text-rose-700 mt-1">{{ fieldError('date_debut') }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Date retour</label>
            <input
              v-model="form.date_fin"
              type="date"
              :class="[control, fieldError('date_fin') ? 'border-rose-300 focus:border-rose-500 focus:ring-rose-100' : '']"
            />
            <p v-if="fieldError('date_fin')" class="text-xs text-rose-700 mt-1">{{ fieldError('date_fin') }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Nombre de jours</label>
            <input
              :value="days"
              type="text"
              class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 outline-none"
              readonly
              placeholder="—"
            />
          </div>
        </div>

        <div v-if="dateError" class="text-sm text-rose-700">
          {{ dateError }}
        </div>
      </div>

      <!-- Carte 4: Annexe planning -->
      <div class="bg-white rounded-2xl border shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b flex items-center justify-between gap-3">
          <div class="min-w-0">
            <div class="font-bold text-slate-900">Annexe : planning de mission</div>
            <div class="text-sm text-slate-500">Tableau dynamique (saisie visuelle).</div>
          </div>

          <button
            type="button"
            class="px-3 py-2 rounded-xl bg-slate-900 text-white text-sm font-semibold hover:opacity-95"
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
                <th class="px-4 py-3">DISTANCE (KM)</th>
                <th class="px-4 py-3">NUITÉE</th>
                <th class="px-4 py-3">OBS.</th>
                <th class="px-4 py-3 text-right"></th>
              </tr>
            </thead>

            <tbody class="divide-y">
              <tr v-for="(row, i) in form.planning" :key="i" class="hover:bg-slate-50">
                <td class="px-4 py-3">
                  <input v-model="row.date" type="date" class="w-full rounded-lg border border-slate-200 px-3 py-2 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100" />
                </td>
                <td class="px-4 py-3">
                  <input v-model="row.activite" type="text" class="w-full rounded-lg border border-slate-200 px-3 py-2 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100" />
                </td>
                <td class="px-4 py-3">
                  <input v-model="row.trajet" type="text" class="w-full rounded-lg border border-slate-200 px-3 py-2 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100" />
                </td>
                <td class="px-4 py-3">
                  <input v-model="row.distance_km" type="number" min="0" class="w-full rounded-lg border border-slate-200 px-3 py-2 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100" />
                </td>
                <td class="px-4 py-3">
                  <input v-model="row.nuitee" type="text" class="w-full rounded-lg border border-slate-200 px-3 py-2 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100" />
                </td>
                <td class="px-4 py-3">
                  <input v-model="row.observation" type="text" class="w-full rounded-lg border border-slate-200 px-3 py-2 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100" />
                </td>
                <td class="px-4 py-3 text-right">
                  <button
                    type="button"
                    class="px-3 py-2 rounded-lg border text-sm font-semibold hover:bg-rose-50 text-rose-700 disabled:opacity-60"
                    :disabled="form.planning.length <= 1"
                    @click="removeRow(i)"
                    title="Supprimer la ligne"
                  >
                    Supprimer
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Footer actions -->
      <div class="flex items-center justify-end gap-2">
        <button
          type="button"
          class="px-4 py-2 rounded-xl border bg-white hover:bg-slate-50 disabled:opacity-60"
          :disabled="saving"
          @click="router.back()"
        >
          Annuler
        </button>

        <button
          type="submit"
          class="px-5 py-2 rounded-xl bg-brand text-white font-semibold shadow-sm hover:opacity-95 disabled:opacity-60"
          :disabled="saving"
        >
          {{ saving ? 'Création…' : 'Créer la mission' }}
        </button>
      </div>
    </form>
  </div>
</template>
