
<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { useRouter } from 'vue-router';

const missions = ref([]);
const loading = ref(true);
const error = ref('');
const router = useRouter();

const getStatusStyles = (status) => {
  switch (status) {
    case 'EN_COURS':
      return 'bg-yellow-100 text-yellow-700 border-yellow-300';
    case 'TERMINEE':
      return 'bg-green-100 text-green-700 border-green-300';
    case 'ANNULEE':
      return 'bg-red-100 text-red-700 border-red-300';
    default:
      return 'bg-gray-100 text-gray-600 border-gray-300';
  }
};

const loadMissions = async () => {
  loading.value = true;
  error.value = '';
  try {
    const response = await axios.get('/api/missions');
    missions.value = response.data.missions || response.data || [];
  } catch (e) {
    error.value = "Erreur lors du chargement des missions.";
    missions.value = [];
  } finally {
    loading.value = false;
  }
};

const goToCreate = () => {
  router.push('/missions/create');
};

onMounted(loadMissions);
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 p-6">
    <div class="max-w-5xl mx-auto">
      <div class="flex flex-col sm:flex-row justify-between items-center mb-8 gap-4">
        <h1 class="text-3xl font-bold text-indigo-800">Tableau de bord des missions</h1>
        <button @click="goToCreate" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-lg shadow transition font-semibold">
          + Nouvelle mission
        </button>
      </div>

      <div v-if="loading" class="flex justify-center items-center py-20">
        <span class="text-lg text-gray-500 animate-pulse">Chargement des missions...</span>
      </div>
      <div v-else>
        <div v-if="error" class="text-red-600 text-center mb-4">{{ error }}</div>
        <div class="bg-white shadow rounded-xl overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-600 uppercase">Objet / ID</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-600 uppercase">Destination</th>
                <th class="px-6 py-3 text-center text-xs font-bold text-gray-600 uppercase">Statut</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-gray-600 uppercase">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="mission in missions" :key="mission.id" class="hover:bg-indigo-50 transition">
                <td class="px-6 py-4">
                  <div class="text-base font-semibold text-gray-900">{{ mission.objet }}</div>
                  <div class="text-xs text-gray-400">ID: #{{ mission.id }}</div>
                </td>
                <td class="px-6 py-4 text-sm text-gray-700">{{ mission.destination }}</td>
                <td class="px-6 py-4 text-center">
                  <span :class="['px-2 py-1 rounded-full text-xs font-medium border', getStatusStyles(mission.statut_actuel)]">
                    {{ mission.statut_actuel }}
                  </span>
                </td>
                <td class="px-6 py-4 text-right text-sm">
                  <router-link :to="`/missions/${mission.id}`" class="text-indigo-600 hover:underline font-bold">Détails</router-link>
                </td>
              </tr>
            </tbody>
          </table>
          <div v-if="missions.length === 0" class="text-center py-10 text-gray-500">
            Aucune mission trouvée.
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
