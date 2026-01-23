<script setup>
import { computed, onMounted, ref } from 'vue'

const loading = ref(false)
const error = ref('')
const toast = ref('')

const chs = ref([])
const missionnaires = ref([])

const roles = ref([])
const users = ref([])

const busyCreate = ref(false)
const busyRowId = ref(null)
const busyChefId = ref(null)

const tab = ref('create') // create | affectations | users

const form = ref({
  matricule: '',
  nom: '',
  name: '',
  email: '',
  telephone: '',
  unite: '',
  poste: '',
  password: '',
  role: 'missionnaire',
  chef_hierarchique_id: '',
  status: 'active',
})

const needChef = computed(() => String(form.value.role || '').toLowerCase() === 'missionnaire')

const missingCount = computed(() =>
  missionnaires.value.filter(u => !u.chef_hierarchique_id).length
)

const roleNames = (u) =>
  (u?.roles ?? [])
    .map(r => (typeof r === 'string' ? r : r?.name))
    .filter(Boolean)
    .map(r => String(r).toLowerCase())

const isMissionnaire = (u) => roleNames(u).includes('missionnaire')

const notify = (msg) => {
  toast.value = msg
  setTimeout(() => (toast.value = ''), 2500)
}

const loadRoles = async () => {
  const res = await window.axios.get('/api/admin/roles')
  roles.value = res.data?.roles ?? []
}

const loadHierarchy = async () => {
  const res = await window.axios.get('/api/admin/hierarchy')
  chs.value = res.data?.chs ?? []
  missionnaires.value = res.data?.missionnaires ?? []
}

const loadUsers = async () => {
  const res = await window.axios.get('/api/admin/users')
  users.value = res.data?.users ?? []
}

const loadAll = async () => {
  loading.value = true
  error.value = ''
  try {
    await Promise.all([loadRoles(), loadHierarchy(), loadUsers()])
  } catch (e) {
    error.value = e?.response?.data?.message || 'Erreur chargement admin.'
  } finally {
    loading.value = false
  }
}

onMounted(loadAll)

// -------------------- CREATE USER --------------------
const createUser = async () => {
  // mini validation front
  if (!form.value.matricule || !form.value.nom || !form.value.name || !form.value.email || !form.value.password) {
    alert('Matricule, Nom, Prénom/Name, Email et Mot de passe sont requis.')
    return
  }
  if (needChef.value && !form.value.chef_hierarchique_id) {
    alert('Choisis un CH pour ce missionnaire.')
    return
  }

  busyCreate.value = true
  try {
    const payload = { ...form.value }

    // normalize chef id
    payload.chef_hierarchique_id = payload.chef_hierarchique_id
      ? Number(payload.chef_hierarchique_id)
      : null

    if (!needChef.value) payload.chef_hierarchique_id = null

    await window.axios.post('/api/admin/users', payload)

    // reset minimal
    form.value.matricule = ''
    form.value.nom = ''
    form.value.name = ''
    form.value.email = ''
    form.value.telephone = ''
    form.value.unite = ''
    form.value.poste = ''
    form.value.password = ''
    form.value.role = 'missionnaire'
    form.value.chef_hierarchique_id = ''
    form.value.status = 'active'

    await loadAll()
    notify('Utilisateur créé.')
    tab.value = 'users'
  } catch (e) {
    alert(e?.response?.data?.message || 'Erreur création utilisateur.')
  } finally {
    busyCreate.value = false
  }
}

// -------------------- AFFECTATIONS CH (quick) --------------------
const setChefLocal = (u, chefId) => {
  u.chef_hierarchique_id = chefId ? Number(chefId) : null
}

const saveChefQuick = async (u) => {
  if (!u?.id) return
  if (!u.chef_hierarchique_id) {
    alert('Choisis un CH.')
    return
  }

  busyChefId.value = u.id
  try {
    await window.axios.patch(`/api/admin/users/${u.id}/chef`, {
      chef_hierarchique_id: Number(u.chef_hierarchique_id),
    })
    await loadAll()
    notify('Affectation enregistrée.')
  } catch (e) {
    alert(e?.response?.data?.message || 'Erreur affectation.')
  } finally {
    busyChefId.value = null
  }
}

// -------------------- USERS TABLE UPDATE --------------------
const saveUserRow = async (u) => {
  if (!u?.id) return

  const role = roleNames(u)[0] || '' // on force 1 rôle côté UI
  if (!role) {
    alert('Role manquant sur cet utilisateur.')
    return
  }

  // si missionnaire => chef requis
  if (role === 'missionnaire' && !u.chef_hierarchique_id) {
    alert('Missionnaire : chef obligatoire.')
    return
  }

  busyRowId.value = u.id
  try {
    const payload = {
      matricule: u.matricule,
      nom: u.nom,
      name: u.name,
      email: u.email,
      telephone: u.telephone,
      unite: u.unite,
      poste: u.poste,
      status: u.status,
      role: role,
      chef_hierarchique_id: role === 'missionnaire' ? Number(u.chef_hierarchique_id) : null,
    }

    await window.axios.patch(`/api/admin/users/${u.id}`, payload)
    await loadAll()
    notify('Utilisateur mis à jour.')
  } catch (e) {
    alert(e?.response?.data?.message || 'Erreur mise à jour.')
  } finally {
    busyRowId.value = null
  }
}

const setUserRole = (u, newRole) => {
  const r = String(newRole || '').toLowerCase()
  // on garde 1 rôle : on remplace localement
  u.roles = [{ name: r }]
  if (r !== 'missionnaire') {
    u.chef_hierarchique_id = null
  }
}
</script>

<template>
  <div class="space-y-5">
    <div class="flex items-start justify-between gap-4">
      <div>
        <h1 class="text-3xl font-extrabold text-slate-900">Administration</h1>
        <p class="text-slate-600 mt-1">
          Création utilisateurs, rôles, et affectations CH.
        </p>
      </div>

      <button
        class="px-3 py-2 rounded-xl border bg-white hover:bg-slate-50"
        @click="loadAll"
        :disabled="loading"
      >
        Rafraîchir
      </button>
    </div>

    <div
      v-if="toast"
      class="bg-emerald-50 border border-emerald-100 text-emerald-800 px-4 py-3 rounded-2xl"
    >
      {{ toast }}
    </div>

    <div v-if="error" class="p-4 rounded-2xl border bg-rose-50 text-rose-700">
      {{ error }}
    </div>

    <!-- Tabs -->
    <div class="bg-white rounded-2xl border shadow-sm p-3 flex flex-wrap gap-2">
      <button
        class="px-4 py-2 rounded-xl text-sm font-semibold"
        :class="tab==='create' ? 'bg-brand text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
        @click="tab='create'"
      >
        + Créer utilisateur
      </button>
      <button
        class="px-4 py-2 rounded-xl text-sm font-semibold"
        :class="tab==='affectations' ? 'bg-brand text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
        @click="tab='affectations'"
      >
        Affectations CH
        <span v-if="missingCount" class="ml-2 text-xs bg-rose-100 text-rose-700 px-2 py-0.5 rounded-full">
          {{ missingCount }}
        </span>
      </button>
      <button
        class="px-4 py-2 rounded-xl text-sm font-semibold"
        :class="tab==='users' ? 'bg-brand text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
        @click="tab='users'"
      >
        Utilisateurs
      </button>
    </div>

    <!-- TAB: CREATE -->
    <div v-if="tab==='create'" class="bg-white rounded-2xl border shadow-sm p-4">
      <div class="font-bold text-slate-900">Créer un utilisateur</div>
      <div class="text-sm text-slate-600 mt-1">
        Règles : missionnaire = chef obligatoire. CH/RAF/CP/ACCP/Admin = pas de chef.
      </div>

      <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-3">
        <input v-model="form.matricule" class="rounded-xl border px-3 py-2" placeholder="Matricule" />
        <input v-model="form.nom" class="rounded-xl border px-3 py-2" placeholder="Nom" />
        <input v-model="form.name" class="rounded-xl border px-3 py-2" placeholder="Prénom / Name" />

        <input v-model="form.email" class="rounded-xl border px-3 py-2" placeholder="Email" />
        <input v-model="form.telephone" class="rounded-xl border px-3 py-2" placeholder="Téléphone" />
        <input v-model="form.password" type="password" class="rounded-xl border px-3 py-2" placeholder="Mot de passe" />

        <input v-model="form.unite" class="rounded-xl border px-3 py-2" placeholder="Unité" />
        <input v-model="form.poste" class="rounded-xl border px-3 py-2" placeholder="Poste" />

        <select v-model="form.role" class="rounded-xl border px-3 py-2">
          <option v-for="r in roles" :key="r.id" :value="r.name">
            {{ r.display_name || r.name }}
          </option>
        </select>

        <select v-if="needChef" v-model="form.chef_hierarchique_id" class="rounded-xl border px-3 py-2">
          <option value="">— Choisir un CH —</option>
          <option v-for="ch in chs" :key="ch.id" :value="String(ch.id)">
            {{ ch.name }}{{ ch.matricule ? ' (' + ch.matricule + ')' : '' }}
          </option>
        </select>

        <select v-model="form.status" class="rounded-xl border px-3 py-2">
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>
      </div>

      <div class="mt-4 flex justify-end">
        <button
          class="px-4 py-2 rounded-xl bg-brand text-white font-semibold hover:opacity-95 disabled:opacity-60"
          :disabled="busyCreate"
          @click="createUser"
        >
          {{ busyCreate ? 'Création…' : 'Créer' }}
        </button>
      </div>
    </div>

    <!-- TAB: AFFECTATIONS -->
    <div v-else-if="tab==='affectations'" class="bg-white rounded-2xl border shadow-sm overflow-hidden">
      <div class="p-4 border-b bg-slate-50">
        <div class="font-bold text-slate-900">Affectations CH</div>
        <div class="text-sm text-slate-600 mt-1">
          Un CH ne voit que les missions où <b>missions.chef_hierarchique_id = son id</b>.
        </div>
      </div>

      <div v-if="loading" class="p-8 text-slate-500">Chargement…</div>

      <div v-else class="overflow-x-auto">
        <table class="min-w-[980px] w-full">
          <thead class="bg-white text-xs font-semibold text-slate-500 border-b">
            <tr class="text-left">
              <th class="px-5 py-3">MISSIONNAIRE</th>
              <th class="px-5 py-3">UNITÉ / POSTE</th>
              <th class="px-5 py-3">CHEF (CH)</th>
              <th class="px-5 py-3 text-right">ACTION</th>
            </tr>
          </thead>

          <tbody class="divide-y">
            <tr v-for="u in missionnaires" :key="u.id" class="hover:bg-slate-50">
              <td class="px-5 py-4">
                <div class="font-semibold text-slate-900">{{ u.name }}</div>
                <div class="text-xs text-slate-500">
                  {{ u.matricule }} • {{ u.email }}
                </div>
              </td>

              <td class="px-5 py-4 text-slate-700">
                <div>{{ u.unite || '—' }}</div>
                <div class="text-xs text-slate-500">{{ u.poste || '—' }}</div>
              </td>

              <td class="px-5 py-4">
                <select
                  class="w-full max-w-xs rounded-xl border border-slate-200 bg-white px-3 py-2 outline-none
                         focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                  :value="u.chef_hierarchique_id || ''"
                  @change="setChefLocal(u, $event.target.value)"
                >
                  <option value="">— Choisir un CH —</option>
                  <option v-for="ch in chs" :key="ch.id" :value="ch.id">
                    {{ ch.name }}{{ ch.matricule ? ' (' + ch.matricule + ')' : '' }}
                  </option>
                </select>

                <div v-if="u.chef" class="text-xs text-slate-500 mt-1">
                  Actuel : {{ u.chef.name }}
                </div>
                <div v-else class="text-xs text-rose-600 mt-1">
                  Aucun chef assigné
                </div>
              </td>

              <td class="px-5 py-4">
                <div class="flex justify-end">
                  <button
                    class="px-3 py-2 rounded-xl bg-brand text-white text-sm font-semibold hover:opacity-95 disabled:opacity-60"
                    :disabled="busyChefId === u.id"
                    @click="saveChefQuick(u)"
                  >
                    {{ busyChefId === u.id ? 'Enregistrement…' : 'Enregistrer' }}
                  </button>
                </div>
              </td>
            </tr>

            <tr v-if="missionnaires.length === 0">
              <td colspan="4" class="p-10 text-center text-slate-500">
                Aucun missionnaire.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- TAB: USERS -->
    <div v-else class="bg-white rounded-2xl border shadow-sm overflow-hidden">
      <div class="p-4 border-b bg-slate-50">
        <div class="font-bold text-slate-900">Utilisateurs</div>
        <div class="text-sm text-slate-600 mt-1">
          Modifier rôle / statut / chef (si missionnaire).
        </div>
      </div>

      <div v-if="loading" class="p-8 text-slate-500">Chargement…</div>

      <div v-else class="overflow-x-auto">
        <table class="min-w-[1100px] w-full">
          <thead class="bg-white text-xs font-semibold text-slate-500 border-b">
            <tr class="text-left">
              <th class="px-5 py-3">UTILISATEUR</th>
              <th class="px-5 py-3">RÔLE</th>
              <th class="px-5 py-3">CHEF (si missionnaire)</th>
              <th class="px-5 py-3">STATUT</th>
              <th class="px-5 py-3 text-right">ACTION</th>
            </tr>
          </thead>

          <tbody class="divide-y">
            <tr v-for="u in users" :key="u.id" class="hover:bg-slate-50">
              <td class="px-5 py-4">
                <div class="font-semibold text-slate-900">{{ u.name }}</div>
                <div class="text-xs text-slate-500">
                  {{ u.matricule }} • {{ u.email }}
                </div>
              </td>

              <td class="px-5 py-4">
                <select
                  class="rounded-xl border px-3 py-2"
                  :value="roleNames(u)[0] || ''"
                  @change="setUserRole(u, $event.target.value)"
                >
                  <option value="">—</option>
                  <option v-for="r in roles" :key="r.id" :value="r.name">
                    {{ r.display_name || r.name }}
                  </option>
                </select>
              </td>

              <td class="px-5 py-4">
                <select
                  v-if="isMissionnaire(u)"
                  class="w-full max-w-xs rounded-xl border px-3 py-2"
                  :value="u.chef_hierarchique_id || ''"
                  @change="u.chef_hierarchique_id = $event.target.value ? Number($event.target.value) : null"
                >
                  <option value="">— Choisir un CH —</option>
                  <option v-for="ch in chs" :key="ch.id" :value="ch.id">
                    {{ ch.name }}{{ ch.matricule ? ' (' + ch.matricule + ')' : '' }}
                  </option>
                </select>
                <div v-else class="text-sm text-slate-500">—</div>
              </td>

              <td class="px-5 py-4">
                <select v-model="u.status" class="rounded-xl border px-3 py-2">
                  <option value="active">active</option>
                  <option value="inactive">inactive</option>
                </select>
              </td>

              <td class="px-5 py-4">
                <div class="flex justify-end">
                  <button
                    class="px-3 py-2 rounded-xl bg-brand text-white text-sm font-semibold hover:opacity-95 disabled:opacity-60"
                    :disabled="busyRowId === u.id"
                    @click="saveUserRow(u)"
                  >
                    {{ busyRowId === u.id ? 'Sauvegarde…' : 'Sauvegarder' }}
                  </button>
                </div>
              </td>
            </tr>

            <tr v-if="users.length === 0">
              <td colspan="5" class="p-10 text-center text-slate-500">
                Aucun utilisateur.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</template>
