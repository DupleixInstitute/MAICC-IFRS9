<template>
  <div>
    <h2 class="text-xl font-bold mb-4">
      {{ mode === 'view' ? 'View' : 'Edit' }} Transition Matrix ({{ type === 'cumulative' ? 'Cumulative' : 'Normal' }})
    </h2>

    <table class="table-auto border-collapse w-full text-sm">
      <thead>
        <tr>
          <th class="bg-blue-200 p-2">FROM/TO</th>
          <th
            v-for="end in endStages"
            :key="end.id"
            class="bg-blue-200 p-2 text-center leading-tight whitespace-nowrap"
          >
            {{ end.category_name }}
            <div>({{ parseFloat(end.min_value).toFixed(2) }} to {{ parseFloat(end.max_value).toFixed(2) }})</div>
            {{ end.text_value }}
          </th>
          <th class="bg-blue-300 p-2">Total Start</th>
          <th class="bg-red-500 text-white p-2">PD%</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="start in startStages" :key="start.id">
          <td class="bg-blue-200 p-2 font-semibold text-center leading-tight whitespace-nowrap">
            <div>From {{ start.category_name }}</div>
            <div>{{ parseFloat(start.min_value).toFixed(2) }} - {{ parseFloat(start.max_value).toFixed(2) }}</div>
            <div>{{ start.text_value }}</div>
          </td>
          <td v-for="end in endStages" :key="end.id" class="border p-2">
            <div v-if="mode === 'view'">
              {{
                matrix[start.category_name]?.[end.category_name]?.[valueKey] ?? 0
              }}
            </div>
            <div v-else>
              <input
                type="number"
                v-model.number="matrix[start.category_name][end.category_name][valueKey]"
                class="w-full border px-2 py-1 text-right"
              />
            </div>
          </td>
          <td class="bg-blue-100 text-right p-2">
            {{ startTotals[start.category_name]?.toLocaleString(undefined, { minimumFractionDigits: 2 }) ?? '0.00' }}
          </td>
         <td class="bg-red-100 text-right p-2">
            {{ parseFloat(pdPercentages[start.category_name] ?? 0).toFixed(2) }}%
          </td>
        </tr>
      </tbody>
      <tfoot>
        <tr>
          <th class="bg-blue-300 p-2 text-left">TOTAL</th>
          <th v-for="end in endStages" :key="end.id" class="bg-blue-300 text-right p-2">
            {{ endStageTotals[end.category_name]?.toLocaleString(undefined, { minimumFractionDigits: 2 }) ?? '0.00' }}
          </th>
          <th class="bg-blue-400 text-right p-2 font-semibold">
            {{ totalGrandStart().toLocaleString(undefined, { minimumFractionDigits: 2 }) }}
          </th>
          <th></th>
        </tr>
      </tfoot>
    </table>

    <div class="mt-4 flex justify-between">
      <button @click="$emit('close')" class="text-sm text-gray-600 hover:underline">Close</button>

      <button
        v-if="mode === 'edit'"
        @click="submit"
        class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
      >
        Save Updates
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'

const props = defineProps({
  transitionMatrix: Object,
  type: {
    type: String,
    default: 'normal', // or 'cumulative'
  },
  mode: String, // 'view' or 'edit'
})

const matrix = ref({})
const startStages = ref([])
const endStages = ref([])
const startTotals = ref({})
const pdPercentages = ref({})
const endStageTotals = ref({})
const grandTotal = ref(0)

// Determine field key depending on type
const valueKey = computed(() => (props.type === 'cumulative' ? 'transition_balance_cummulated' : 'transition_balance_month'))

onMounted(async () => {
  try {
    const url =
      props.type === 'cumulative'
        ? `/transition-matrix-cumulative/${props.transitionMatrix.id}/data`
        : `/transition-matrix/${props.transitionMatrix.id}/data`

    const res = await axios.get(url)

    matrix.value = res.data.matrix
    startStages.value = res.data.startStages
    endStages.value = res.data.endStages
    startTotals.value = res.data.startTotals
    pdPercentages.value = res.data.pdPercentages
    endStageTotals.value = res.data.endStageTotals
    grandTotal.value = res.data.grandTotal
  } catch (error) {
    console.error('Failed to load matrix data:', error)
    alert('Failed to load matrix data. Check console for details.')
  }
})

function totalGrandStart() {
  return Object.values(startTotals.value).reduce((sum, val) => sum + val, 0)
}

function submit() {
  const flattened = []

  for (const [start, ends] of Object.entries(matrix.value)) {
    for (const [end, cell] of Object.entries(ends)) {
      flattened.push({
        start_stage: start,
        end_stage: end,
        [valueKey.value]: cell[valueKey.value] ?? 0,
      })
    }
  }

  const updateUrl =
    props.type === 'cumulative'
      ? `/transition-matrix-cumulative/${props.transitionMatrix.id}/update-data`
      : `/transition-matrix/${props.transitionMatrix.id}/update-data`

  axios
    .post(updateUrl, { matrix: flattened })
    .then(() => {
      alert('Matrix updated successfully.')
    })
    .catch((error) => {
      console.error('Submission failed:', error.response?.data || error.message)
      alert('Failed to update matrix. See console for details.')
    })
}
</script>



<!-- <template>
  <div>
    <h2 class="text-xl font-bold mb-4">
      {{ mode === 'view' ? 'View' : 'Edit' }} Transition Matrix
    </h2>

    <table class="table-auto border-collapse w-full text-sm">
      <thead>
        <tr>
          <th class="bg-blue-200 p-2">FROM/TO</th>
          <th v-for="end in endStages" :key="end.id" class="bg-blue-200 p-2 text-center leading-tight whitespace-nowrap">
            {{ end.category_name }}
            <div>({{ parseFloat(end.min_value).toFixed(2) }} to {{ parseFloat(end.max_value).toFixed(2) }})</div>
            {{ end.text_value }}
          </th>
          <th class="bg-blue-300 p-2">Total Start</th>
          <th class="bg-red-500 text-white p-2">PD%</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="start in startStages" :key="start.id">
          <td class="bg-blue-200 p-2 font-semibold text-center leading-tight whitespace-nowrap">
            <div>From {{ start.category_name }}</div>
            <div>{{ parseFloat(start.min_value).toFixed(2) }} - {{ parseFloat(start.max_value).toFixed(2) }}</div>
            <div>{{ start.text_value }}</div>
          </td>
          <td v-for="end in endStages" :key="end.id" class="border p-2">
            <div v-if="mode === 'view'">
              {{ matrix[start.category_name]?.[end.category_name]?.transition_balance_month ?? 0 }}
            </div>
            <div v-else>
              <input
                type="number"
                v-model.number="matrix[start.category_name][end.category_name].transition_balance_month"
                class="w-full border px-2 py-1 text-right"
              />
            </div>
          </td>
          <td class="bg-blue-100 text-right p-2">
            {{ startTotals[start.category_name]?.toLocaleString(undefined, { minimumFractionDigits: 2 }) ?? '0.00' }}
          </td>
          <td class="bg-red-100 text-right p-2">
            {{ pdPercentages[start.category_name]?.toFixed(2) ?? '0.00' }}%
          </td>
        </tr>
      </tbody>
      <tfoot>
        <tr>
          <th class="bg-blue-300 p-2 text-left">TOTAL</th>
          <th v-for="end in endStages" :key="end.id" class="bg-blue-300 text-right p-2">
            {{ endStageTotals[end.category_name]?.toLocaleString(undefined, { minimumFractionDigits: 2 }) ?? '0.00' }}
          </th>
          <th class="bg-blue-400 text-right p-2 font-semibold">
            {{ totalGrandStart().toLocaleString(undefined, { minimumFractionDigits: 2 }) }}
          </th>
          <th></th>
        </tr>
      </tfoot>
    </table>

    <div class="mt-4 flex justify-between">
      <button @click="$emit('close')" class="text-sm text-gray-600 hover:underline">Close</button>

      <button
        v-if="mode === 'edit'"
        @click="submit"
        class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
      >
        Save Updates
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const props = defineProps({
  transitionMatrix: Object,
  type: {
    type: String,
    default: 'normal', // or 'cumulative'
  },
  mode: String, // 'view' or 'edit'
})

const matrix = ref({})
const startStages = ref([])
const endStages = ref([])
const startTotals = ref({})
const pdPercentages = ref({})
const endStageTotals = ref({})
const grandTotal = ref(0)

onMounted(async () => {
  try {
    let url =
      props.type === 'cumulative'
        ? `/transition-matrix-cumulative/${props.transitionMatrix.id}/data`
        : `/transition-matrix/${props.transitionMatrix.id}/data`

    const res = await axios.get(url)

    matrix.value = res.data.matrix
    startStages.value = res.data.startStages
    endStages.value = res.data.endStages
    startTotals.value = res.data.startTotals
    pdPercentages.value = res.data.pdPercentages
    endStageTotals.value = res.data.endStageTotals
    grandTotal.value = res.data.grandTotal
  } catch (error) {
    console.error('Failed to load matrix data:', error)
    alert('Failed to load matrix data. Check console for details.')
  }
})

function totalGrandStart() {
  return Object.values(startTotals.value).reduce((sum, val) => sum + val, 0)
}

function submit() {
  const flattened = []

  for (const [start, ends] of Object.entries(matrix.value)) {
    for (const [end, cell] of Object.entries(ends)) {
      flattened.push({
        start_stage: start,
        end_stage: end,
        transition_balance_month: cell.transition_balance_month,
      })
    }
  }

  let updateUrl =
    props.type === 'cumulative'
      ? `/transition-matrix-cumulative/${props.transitionMatrix.id}/update-data`
      : `/transition-matrix/${props.transitionMatrix.id}/update-data`

  axios
    .post(updateUrl, {
      matrix: flattened,
    })
    .then(() => {
      alert('Matrix updated successfully.')
    })
    .catch((error) => {
      console.error('Submission failed:', error.response?.data || error.message)
      alert('Failed to update matrix. See console for details.')
    })
}
</script> -->
