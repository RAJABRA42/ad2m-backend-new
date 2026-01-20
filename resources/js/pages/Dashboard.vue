<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const auth = useAuthStore()

const missions = ref([])
const loading = ref(false)

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

const isValidator = computed(() =>
  hasRole('administrateur', 'admin', 'chef_hierarchique', 'raf', 'coordonnateur_de_projet', 'accp')
)

/**
 * Quelle étape doit valider ce rôle ?
 * (sert juste à compter et afficher le bouton "X mission à valider")
 */
const pendingKey = computed(() => {
  if (hasRole('chef_hierarchique')) return 'en_attente_ch'
  if (hasRole('raf')) return 'valide_ch'
  if (hasRole('coordonnateur_de_projet')) return 'valide_raf'
  if (hasRole('accp')) return 'valide_cp'
  if (hasRole('administrateur', 'admin')) return 'en_attente_ch'
  return null
})

/**
 * ✅ IMPORTANT: dashboard = PERSONNEL
 * Donc on affiche uniquement "mes missions" même si l'API retourne tout
 */
const myMissions = computed(() => {
  const uid = auth.user?.id
  if (!uid) return []

  return missions.value.filter(m => {
    const demandeurId =
      m.demandeur_id ?? m.demandeur?.id ?? m.user_id ?? m.created_by
    return Number(demandeurId) === Number(uid)
  })
})

const counts = computed(() => {
  const list = myMissions.value
  const c = { total: list.length, brouillon: 0, en_attente: 0, approuvees: 0, rejetees: 0 }

  for (const m of list) {
    const s = String(m.statut_actuel || '').toLowerCase()

    if (s === 'brouillon') c.brouillon++
    if (['en_attente_ch', 'valide_ch', 'valide_raf', 'valide_cp'].includes(s)) c.en_attente++
    if (s.startsWith('rejet')) c.rejetees++
    if (['valide_cp', 'avance_payee', 'en_cours', 'cloturee'].includes(s)) c.approuvees++
  }

  return c
})

const pendingToValidate = computed(() => {
  if (!isValidator.value || !pendingKey.value) return 0
  const key = pendingKey.value
  return missions.value.filter(m => String(m.statut_actuel || '').toLowerCase() === key).length
})

const recent = computed(() => {
  const arr = [...myMissions.value]
  arr.sort((a, b) => (b.id || 0) - (a.id || 0))
  return arr.slice(0, 5)
})

const prettyStatus = (s) => {
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
  const k = String(s || '').toLowerCase()
  if (k.startsWith('rejet')) return 'Rejetée'
  return map[k] ?? (s || '—')
}

const statusBadgeClass = (s) => {
  const k = String(s || '').toLowerCase()
  if (k === 'brouillon') return 'bg-slate-100 text-slate-700'
  if (k === 'en_attente_ch') return 'bg-blue-50 text-blue-700'
  if (k === 'valide_ch' || k === 'valide_raf' || k === 'valide_cp') return 'bg-amber-50 text-amber-700'
  if (k.startsWith('rejet')) return 'bg-rose-50 text-rose-700'
  if (['avance_payee', 'en_cours', 'cloturee'].includes(k)) return 'bg-emerald-50 text-emerald-700'
  return 'bg-slate-100 text-slate-700'
}

const fmtMoney = (v) => {
  const n = Number(v || 0)
  if (!Number.isFinite(n)) return '—'
  return n.toLocaleString('fr-FR') + ' Ar'
}

const load = async () => {
  loading.value = true
  try {
    const res = await window.axios.get('/api/missions')
    missions.value = res.data?.missions ?? res.data ?? []
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div>
      <h1 class="text-3xl font-extrabold text-slate-900">
        Bienvenue, {{ auth.user?.name }}
      </h1>
      <p class="text-slate-600">
        Département: {{ auth.user?.unite || '—' }} • {{ auth.user?.poste || '—' }}
      </p>
    </div>

    <!-- Actions -->
    <div class="flex flex-wrap gap-3">
      <button
        class="px-4 py-2 rounded-xl bg-brand text-white font-semibold shadow-sm hover:opacity-95"
        @click="router.push({ name: 'missions.create' })"
      >
        + Nouvelle mission
      </button>

      <button
        v-if="isValidator && pendingToValidate > 0"
        class="px-4 py-2 rounded-xl bg-blue-600 text-white font-semibold shadow-sm hover:opacity-95"
        @click="router.push({ name: 'validation' })"
      >
        {{ pendingToValidate }} mission{{ pendingToValidate > 1 ? 's' : '' }} à valider
      </button>
    </div>

    <!-- Cards (MES MISSIONS) -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
      <div class="bg-white rounded-2xl border shadow-sm p-4">
        <div class="text-sm text-slate-500">Total missions</div>
        <div class="text-3xl font-extrabold text-slate-900">{{ counts.total }}</div>
      </div>
      <div class="bg-white rounded-2xl border shadow-sm p-4">
        <div class="text-sm text-slate-500">Brouillons</div>
        <div class="text-3xl font-extrabold text-slate-900">{{ counts.brouillon }}</div>
      </div>
      <div class="bg-white rounded-2xl border shadow-sm p-4">
        <div class="text-sm text-slate-500">En attente</div>
        <div class="text-3xl font-extrabold text-slate-900">{{ counts.en_attente }}</div>
      </div>
      <div class="bg-white rounded-2xl border shadow-sm p-4">
        <div class="text-sm text-slate-500">Approuvées</div>
        <div class="text-3xl font-extrabold text-slate-900">{{ counts.approuvees }}</div>
      </div>
      <div class="bg-white rounded-2xl border shadow-sm p-4">
        <div class="text-sm text-slate-500">Rejetées</div>
        <div class="text-3xl font-extrabold text-slate-900">{{ counts.rejetees }}</div>
      </div>
    </div>

    <!-- Recent (MES MISSIONS) -->
    <div class="bg-white rounded-2xl border shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b flex items-center justify-between">
        <div class="font-bold text-slate-900">Missions récentes</div>
        <button class="text-brand font-semibold hover:underline" @click="router.push({ name: 'missions' })">
          Voir tout →
        </button>
      </div>

      <div v-if="loading" class="p-8 text-slate-500">Chargement…</div>

      <div v-else-if="recent.length === 0" class="p-10 text-center text-slate-500">
        Aucune mission pour le moment
      </div>

      <div v-else class="divide-y">
        <button
          v-for="m in recent"
          :key="m.id"
          class="w-full text-left px-5 py-4 hover:bg-slate-50 flex items-center justify-between gap-4"
          @click="router.push({ name: 'missions.show', params: { id: m.id } })"
        >
          <div class="min-w-0">
            <div class="font-semibold text-slate-900 truncate">
              {{ m.objet || ('Mission #' + m.id) }}
              <span
                class="ml-2 inline-flex items-center px-2 py-1 rounded-full text-xs"
                :class="statusBadgeClass(m.statut_actuel)"
              >
                {{ prettyStatus(m.statut_actuel) }}
              </span>
            </div>

            <div class="text-sm text-slate-600">
              {{ m.destination || '—' }}
              <span v-if="m.date_debut && m.date_fin"> • {{ m.date_debut }} - {{ m.date_fin }}</span>
              <span v-if="m.budget"> • {{ fmtMoney(m.budget) }}</span>
            </div>
          </div>

          <div class="text-slate-400">→</div>
        </button>
      </div>
    </div>
  </div>
</template>
