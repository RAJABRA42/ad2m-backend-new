<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const missions = ref([])
const loading = ref(true)

const getStatusStyles = (status) => {
  switch (status) {
    case 'EN_COURS':
      return 'bg-yellow-100 text-yellow-700 border-yellow-300'
    case 'TERMINEE':
      return 'bg-green-100 text-green-700 border-green-300'
    case 'ANNULEE':
      return 'bg-red-100 text-red-700 border-red-300'
    default:
      return 'bg-gray-100 text-gray-600 border-gray-300'
  }
}

const loadMissions = async () => {
  try {
    const response = await axios.get('/api/missions')
    missions.value = response.data.missions || response.data || []
  } catch (error) {
    console.error('Erreur lors du chargement des missions :', error)
    missions.value = []
  } finally {
    loading.value = false
  }
}

onMounted(loadMissions)
</script>

<template>
  <div class="p-6">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold">Tableau de Bord des Missions</h1>
      <button class="bg-indigo-600 text-white px-4 py-2 rounded shadow">+ Nouvelle Mission</button>
    </div>

    <div v-if="loading" class="text-center py-10">Chargement...</div>

    <div v-else class="bg-white shadow rounded-lg overflow-hidden">
      <table class="min-w-full">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Objet / ID</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Destination</th>
            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Statut</th>
            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
          </tr>
        </thead>

        <tbody class="divide-y divide-gray-200">
          <tr v-for="mission in missions" :key="mission.id">
            <td class="px-6 py-4">
              <div class="text-sm font-bold text-gray-900">{{ mission.objet }}</div>
              <div class="text-xs text-gray-400">ID: #{{ mission.id }}</div>
            </td>
            <td class="px-6 py-4 text-sm text-gray-600">{{ mission.destination }}</td>
            <td class="px-6 py-4 text-center">
              <span :class="['px-2 py-1 rounded-full text-xs font-medium border', getStatusStyles(mission.statut_actuel)]">
                {{ mission.statut_actuel }}
              </span>
            </td>
            <td class="px-6 py-4 text-right text-sm">
              <router-link :to="`/missions/${mission.id}`" class="text-indigo-600 font-bold">Détails</router-link>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="missions.length === 0" class="text-center py-10 text-gray-500">
        Aucune mission trouvée.
      </div>
    </div>
  </div>
</template>
