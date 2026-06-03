<template>
  <div
    v-if="show"
    class="fixed inset-0 z-50 flex items-center justify-center"
    @click.self="$emit('close')"
  >
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-black bg-opacity-50 backdrop-blur-sm"></div>

    <!-- Modal -->
    <div class="relative bg-white rounded-xl shadow-xl w-full max-w-6xl max-h-[90vh] overflow-hidden">
      
      <!-- Header -->
      <div class="px-6 py-4 border-b flex justify-between items-center">
        <div>
          <h3 class="text-lg font-semibold text-gray-900">
            Internal Grade PD Matrix
          </h3>
          <p class="text-xs text-gray-500">
            {{ profile.name }} • Max tenor {{ maxTenor }} years
          </p>
        </div>

        <button
          @click="$emit('close')"
          class="text-gray-500 hover:text-gray-700 text-xl"
        >
          ×
        </button>
      </div>

      <!-- Table -->
      <div class="overflow-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50 sticky top-0 z-10">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                Grade
              </th>
              <th
                v-for="year in maxTenor"
                :key="year"
                class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase"
              >
                Y{{ year }}
              </th>
            </tr>
          </thead>

          <tbody class="divide-y divide-gray-200">
            <tr v-for="grade in grades" :key="grade.id">
              <td class="px-4 py-3 font-semibold text-gray-900">
                {{ grade.grade_code }}
              </td>

              <td
                v-for="year in maxTenor"
                :key="year"
                class="px-4 py-3 text-center text-sm"
              >
                <span v-if="grade.pds[year] !== null">
                  {{ formatPd(grade.pds[year]) }}
                </span>
                <span v-else class="text-gray-400">–</span>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Empty -->
        <div
          v-if="grades.length === 0"
          class="p-12 text-center text-gray-500"
        >
          No grades configured for this profile.
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  show: Boolean,
  profile: Object,
  grades: Array,
  maxTenor: Number
})

function formatPd(value) {
  return (value * 100).toFixed(2) + '%'
}
</script>
