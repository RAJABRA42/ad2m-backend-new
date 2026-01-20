<script setup>
import { onMounted, ref } from 'vue'

const missions = ref([])
const error = ref(null)

onMounted(async () => {
  try {
    const res = await window.axios.get('/api/missions')
    missions.value = res.data.missions
  } catch (e) {
    error.value = 'API non accessible'
  }
})
</script>

<template>
  <div class="bg-white rounded border p-6 space-y-4">
    <h1 class="text-xl font-semibold">Test API missions</h1>

    <div v-if="error" class="text-red-600">
      {{ error }}
    </div>

    <ul v-else>
      <li
        v-for="m in missions"
        :key="m.id"
        class="border-b py-1"
      >
        #{{ m.id }} — {{ m.objet }} ({{ m.statut_actuel }})
      </li>
    </ul>
  </div>
</template>
