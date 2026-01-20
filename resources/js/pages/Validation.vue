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

// --- roles
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
  hasRole('administrateur', 'admin', 'chef_hierarchique', 'raf', 'coordonnateur_de_projet', 'accp')
)

/**
 * Etape à traiter selon rôle (la “file” de validation)
 */
const rolePendingKey = computed(() => {
  if (hasRole('chef_hierarchique')) return 'en_attente_ch'
  if (hasRole('raf')) return 'valide_ch'
  if (hasRole('coordonnateur_de_projet')) return 'valide_raf'
  if (hasRole('accp')) return 'valide_cp'
  if (isAdmin.value) return 'en_attente_ch'
  return null
})

/**
 * Admin peut choisir l’étape (optionnel mais très utile)
 */
const selectedKey = ref(null)

onMounted(() => {
  selectedKey.value = rolePendingKey.value
})

const activeKey = computed(() => {
  // admin peut changer
  return selectedKey.value || rolePendingKey.value
})

const stageLabel = (k) => {
  const map = {
    en_attente_ch: 'En attente CH',
    valide_ch: 'En attente RAF',
    valide_raf: 'En attente CP',
    valide_cp: 'En attente ACCP (paiement)',
  }
  return map[k] ?? k
}

const prettyStatus = (s) => {
  const k = String(s || '').toLowerCase()
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
  const k = String(s || '').toLowerCase()
  if (k.startsWith('rejet')) return 'bg-rose-100 text-rose-700'
  if (k === 'en_attente_ch') return 'bg-blue-100 text-blue-700'
  if (k === 'valide_ch' || k === 'valide_raf' || k === 'valide_cp') return 'bg-amber-100 text-amber-700'
  return 'bg-slate-100 text-slate-700'
}

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

const filtered = computed(() => {
  if (!isValidator.value || !activeKey.value) return []
  const key = String(activeKey.value).toLowerCase()
  const query = String(q.value || '').trim().toLowerCase()

  let arr = missions.value.filter(m => String(m?.statut_actuel || '').toLowerCase() === key)

  if (query) {
    arr = arr.filter(m => {
      const blob = [
        m?.objet, m?.titre, m?.destination, m?.description,
        m?.demandeur?.name, m?.demandeur?.matricule,
        prettyStatus(m?.statut_actuel)
      ].filter(Boolean).join(' ').toLowerCase()
      return blob.includes(query)
    })
  }

  arr.sort((a, b) => (Number(b?.id) || 0) - (Number(a?.id) || 0))
  return arr
})

const endpointForValidate = computed(() => {
  const key = String(activeKey.value || '')
  if (key === 'en_attente_ch') return 'valider-ch'
  if (key === 'valide_ch') return 'valider-raf'
  if (key === 'valide_raf') return 'valider-cp'
  if (key === 'valide_cp') return 'payer'
  return null
})

const actionLabel = computed(() => {
  const key = String(activeKey.value || '')
  if (key === 'valide_cp') return 'Payer'
  return 'Valider'
})

const doValidate = async (m) => {
  if (!m?.id) return
  const ep = endpointForValidate.value
  if (!ep) return

  actionMsg.value = ''
  try {
    await window.axios.post(`/api/missions/${m.id}/${ep}`)
    actionMsg.value = 'Action effectuée.'
    await load()
  } catch (e) {
    actionMsg.value = e?.response?.data?.message || 'Action refusée / erreur.'
  }
}

const doReject = async (m) => {
  if (!m?.id) return
  if (!confirm('Rejeter cette mission ?')) return

  actionMsg.value = ''
  try {
    await window.axios.post(`/api/missions/${m.id}/rejeter`)
    actionMsg.value = 'Mission rejetée.'
    await load()
  } catch (e) {
    actionMsg.value = e?.response?.data?.message || 'Erreur rejet.'
  }
}
</script>

<template>
  <div class="space-y-5">
    <div class="flex items-start justify-between gap-4">
      <div>
        <h1 class="text-3xl font-extrabold text-slate-900">Validation</h1>
        <p class="text-slate-600 mt-1">
          {{ isValidator ? `File: ${stageLabel(activeKey)}` : 'Accès réservé aux validateurs.' }}
        </p>
      </div>

      <button class="px-3 py-2 rounded-xl border bg-white hover:bg-slate-50" @click="load" :disabled="loading">
        Rafraîchir
      </button>
    </div>

    <div v-if="!isValidator" class="bg-white rounded-2xl border p-6 text-slate-700">
      Vous n’avez pas le rôle nécessaire pour valider des missions.
    </div>

    <template v-else>
      <!-- Admin: choix étape -->
      <div v-if="isAdmin" class="bg-white rounded-2xl border shadow-sm p-4 flex flex-col lg:flex-row gap-3 lg:items-center">
        <div class="text-sm font-semibold text-slate-700">Étape :</div>
        <select
          v-model="selectedKey"
          class="w-full lg:w-80 rounded-xl border border-slate-200 bg-white px-4 py-3 outline-none
                 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
        >
          <option value="en_attente_ch">En attente CH</option>
          <option value="valide_ch">En attente RAF</option>
          <option value="valide_raf">En attente CP</option>
          <option value="valide_cp">En attente ACCP (paiement)</option>
        </select>

        <div class="text-sm text-slate-500">
          {{ filtered.length }} mission(s) dans cette file
        </div>
      </div>

      <!-- Search -->
      <div class="bg-white rounded-2xl border shadow-sm p-4">
        <input
          v-model="q"
          type="text"
          placeholder="Rechercher (objet, destination, demandeur...)"
          class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 outline-none
                 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
        />
      </div>

      <div v-if="actionMsg" class="rounded-2xl border bg-slate-50 px-4 py-3 text-sm text-slate-700">
        {{ actionMsg }}
      </div>

      <div class="bg-white rounded-2xl border shadow-sm overflow-hidden">
        <div v-if="loading" class="p-8 text-slate-500">Chargement…</div>
        <div v-else-if="error" class="p-6 text-rose-700">{{ error }}</div>
        <div v-else-if="filtered.length === 0" class="p-10 text-center text-slate-500">
          Aucune mission à traiter dans cette file.
        </div>

        <div v-else class="overflow-x-auto">
          <table class="min-w-[980px] w-full">
            <thead class="bg-slate-50 text-xs font-semibold text-slate-500">
              <tr class="text-left">
                <th class="px-5 py-3">MISSION</th>
                <th class="px-5 py-3">DESTINATION</th>
                <th class="px-5 py-3">DEMANDEUR</th>
                <th class="px-5 py-3">STATUT</th>
                <th class="px-5 py-3 text-right">ACTIONS</th>
              </tr>
            </thead>

            <tbody class="divide-y">
              <tr v-for="m in filtered" :key="m.id" class="hover:bg-slate-50">
                <td class="px-5 py-4">
                  <div class="font-semibold text-slate-900">
                    {{ m.objet || m.titre || ('Mission #' + m.id) }}
                  </div>
                  <div class="text-sm text-slate-600 line-clamp-1">
                    {{ m.description || '—' }}
                  </div>
                </td>

                <td class="px-5 py-4 text-slate-700">
                  {{ m.destination || '—' }}
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
                  <div class="flex items-center justify-end gap-2">
                    <button
                      class="w-9 h-9 rounded-lg hover:bg-slate-100 flex items-center justify-center text-slate-600"
                      title="Voir"
                      @click="router.push({ name: 'missions.show', params: { id: m.id } })"
                    >
                      👁
                    </button>

                    <button
                      class="px-3 py-2 rounded-lg bg-emerald-600 text-white text-sm font-semibold hover:opacity-95"
                      @click="doValidate(m)"
                    >
                      {{ actionLabel }}
                    </button>

                    <button
                      class="px-3 py-2 rounded-lg border text-sm font-semibold hover:bg-rose-50 text-rose-700"
                      @click="doReject(m)"
                    >
                      Rejeter
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
