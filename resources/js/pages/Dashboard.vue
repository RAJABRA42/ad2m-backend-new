<script setup>
import { computed, onMounted, ref } from 'vue'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()

const loading = ref(true)
const error = ref('')
const data = ref(null)

const roleLabels = computed(() =>
  (auth.user?.roles ?? []).map(r => r.display_name || r.name)
)

const statusLabel = (s) => {
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
  return map[s] || s
}

const statusClass = (s) => {
  switch (s) {
    case 'brouillon':
      return 'bg-ad2m-muted/25 text-slate-700 border-ad2m-muted'
    case 'en_attente_ch':
      return 'bg-ad2m-warning/15 text-ad2m-warning border-ad2m-warning'
    case 'valide_ch':
      return 'bg-ad2m-accent/15 text-ad2m-accent border-ad2m-accent'
    case 'valide_raf':
      return 'bg-ad2m-primary2/15 text-ad2m-primary2 border-ad2m-primary2'
    case 'valide_cp':
      return 'bg-ad2m-accent2/15 text-ad2m-accent2 border-ad2m-accent2'
    case 'avance_payee':
      return 'bg-ad2m-success/15 text-ad2m-success border-ad2m-success'
    case 'en_cours':
      return 'bg-ad2m-primary/15 text-ad2m-primary border-ad2m-primary'
    case 'cloturee':
      return 'bg-ad2m-accent/15 text-ad2m-accent border-ad2m-accent'
    default:
      return 'bg-slate-100 text-slate-700 border-slate-200'
  }
}

const load = async () => {
  loading.value = true
  error.value = ''
  try {
    const res = await window.axios.get('/api/dashboard')
    data.value = res.data
  } catch (e) {
    error.value = "Impossible de charger le dashboard."
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">
          Tableau de bord
        </h1>
        <div class="mt-1 text-sm text-slate-600">
          Bonjour, <span class="font-semibold text-slate-900">{{ auth.user?.name }}</span>
        </div>

        <div class="mt-2 flex flex-wrap gap-2">
          <span
            v-for="r in roleLabels"
            :key="r"
            class="text-xs font-semibold px-2 py-1 rounded-full border bg-white text-slate-700 border-ad2m-muted"
          >
            {{ r }}
          </span>
        </div>
      </div>

      <div class="flex items-center gap-2">
        <button
          class="px-4 py-2 rounded-xl bg-ad2m-primary text-white font-semibold hover:bg-ad2m-primary2 transition"
          @click="load"
        >
          Actualiser
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="py-16 text-center text-slate-500">
      Chargement…
    </div>

    <div v-else>
      <div v-if="error" class="text-red-600 font-semibold">
        {{ error }}
      </div>

      <template v-else-if="data">
        <!-- Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <div class="bg-white rounded-2xl border border-ad2m-muted p-4">
            <div class="text-xs font-bold text-slate-600 uppercase">Total missions</div>
            <div class="mt-2 text-3xl font-extrabold text-slate-900">{{ data.stats?.total ?? 0 }}</div>
            <div class="mt-1 text-xs text-slate-500">Selon vos droits d’accès</div>
          </div>

          <div class="bg-white rounded-2xl border border-ad2m-muted p-4">
            <div class="text-xs font-bold text-slate-600 uppercase">À traiter</div>
            <div class="mt-2 text-3xl font-extrabold text-ad2m-primary">{{ data.a_traiter ?? 0 }}</div>
            <div class="mt-1 text-xs text-slate-500">
              {{ (data.todo_statuses ?? []).map(statusLabel).join(' · ') || '—' }}
            </div>
          </div>

          <div class="bg-white rounded-2xl border border-ad2m-muted p-4">
            <div class="text-xs font-bold text-slate-600 uppercase">En cours</div>
            <div class="mt-2 text-3xl font-extrabold text-slate-900">{{ data.stats?.en_cours ?? 0 }}</div>
            <div class="mt-1 text-xs text-slate-500">Missions actives</div>
          </div>

          <div class="bg-white rounded-2xl border border-ad2m-muted p-4">
            <div class="text-xs font-bold text-slate-600 uppercase">Clôturées</div>
            <div class="mt-2 text-3xl font-extrabold text-slate-900">{{ data.stats?.cloturee ?? 0 }}</div>
            <div class="mt-1 text-xs text-slate-500">Missions terminées</div>
          </div>
        </div>

        <!-- A traiter + Recent -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
          <!-- A traiter list -->
          <div class="bg-white rounded-2xl border border-ad2m-muted overflow-hidden">
            <div class="px-4 py-3 border-b border-ad2m-muted flex items-center justify-between">
              <div class="font-bold text-slate-900">Missions à traiter</div>
              <span class="text-xs font-semibold px-2 py-1 rounded-full border border-ad2m-muted text-slate-600">
                {{ data.a_traiter ?? 0 }}
              </span>
            </div>

            <div v-if="(data.to_process ?? []).length === 0" class="p-6 text-sm text-slate-500">
              Rien à traiter pour le moment.
            </div>

            <div v-else class="divide-y divide-slate-100">
              <div v-for="m in data.to_process" :key="m.id" class="p-4 hover:bg-ad2m-muted/10 transition">
                <div class="flex items-start justify-between gap-3">
                  <div>
                    <div class="font-semibold text-slate-900">#{{ m.id }} — {{ m.objet }}</div>
                    <div class="text-sm text-slate-600">{{ m.destination }}</div>
                  </div>

                  <span class="px-2 py-1 text-xs font-bold rounded-full border"
                        :class="statusClass(m.statut_actuel)">
                    {{ statusLabel(m.statut_actuel) }}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Recent missions -->
          <div class="bg-white rounded-2xl border border-ad2m-muted overflow-hidden">
            <div class="px-4 py-3 border-b border-ad2m-muted flex items-center justify-between">
              <div class="font-bold text-slate-900">Missions récentes</div>
              <span class="text-xs text-slate-500">Filtré automatiquement</span>
            </div>

            <div v-if="(data.recent ?? []).length === 0" class="p-6 text-sm text-slate-500">
              Aucune mission.
            </div>

            <div v-else class="divide-y divide-slate-100">
              <div v-for="m in data.recent" :key="m.id" class="p-4 hover:bg-ad2m-muted/10 transition">
                <div class="flex items-start justify-between gap-3">
                  <div>
                    <div class="font-semibold text-slate-900">#{{ m.id }} — {{ m.objet }}</div>
                    <div class="text-sm text-slate-600">
                      {{ m.destination }} • {{ m.demandeur?.name ?? '-' }}
                    </div>
                  </div>

                  <span class="px-2 py-1 text-xs font-bold rounded-full border"
                        :class="statusClass(m.statut_actuel)">
                    {{ statusLabel(m.statut_actuel) }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Répartition par statut -->
        <div class="bg-white rounded-2xl border border-ad2m-muted overflow-hidden">
          <div class="px-4 py-3 border-b border-ad2m-muted">
            <div class="font-bold text-slate-900">Répartition par statut</div>
            <div class="text-xs text-slate-500">Compteurs selon vos droits</div>
          </div>

          <div class="p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="flex items-center justify-between rounded-xl border border-ad2m-muted px-3 py-2">
              <span class="text-sm text-slate-700">Brouillon</span>
              <span class="font-bold text-slate-900">{{ data.stats?.brouillon ?? 0 }}</span>
            </div>
            <div class="flex items-center justify-between rounded-xl border border-ad2m-muted px-3 py-2">
              <span class="text-sm text-slate-700">En attente CH</span>
              <span class="font-bold text-slate-900">{{ data.stats?.en_attente_ch ?? 0 }}</span>
            </div>
            <div class="flex items-center justify-between rounded-xl border border-ad2m-muted px-3 py-2">
              <span class="text-sm text-slate-700">Validée CH</span>
              <span class="font-bold text-slate-900">{{ data.stats?.valide_ch ?? 0 }}</span>
            </div>
            <div class="flex items-center justify-between rounded-xl border border-ad2m-muted px-3 py-2">
              <span class="text-sm text-slate-700">Validée RAF</span>
              <span class="font-bold text-slate-900">{{ data.stats?.valide_raf ?? 0 }}</span>
            </div>
            <div class="flex items-center justify-between rounded-xl border border-ad2m-muted px-3 py-2">
              <span class="text-sm text-slate-700">Validée CP</span>
              <span class="font-bold text-slate-900">{{ data.stats?.valide_cp ?? 0 }}</span>
            </div>
            <div class="flex items-center justify-between rounded-xl border border-ad2m-muted px-3 py-2">
              <span class="text-sm text-slate-700">Avance payée</span>
              <span class="font-bold text-slate-900">{{ data.stats?.avance_payee ?? 0 }}</span>
            </div>
            <div class="flex items-center justify-between rounded-xl border border-ad2m-muted px-3 py-2">
              <span class="text-sm text-slate-700">En cours</span>
              <span class="font-bold text-slate-900">{{ data.stats?.en_cours ?? 0 }}</span>
            </div>
            <div class="flex items-center justify-between rounded-xl border border-ad2m-muted px-3 py-2">
              <span class="text-sm text-slate-700">Clôturée</span>
              <span class="font-bold text-slate-900">{{ data.stats?.cloturee ?? 0 }}</span>
            </div>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>
