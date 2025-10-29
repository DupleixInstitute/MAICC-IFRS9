<template>
  <app-layout>
    <template #header>
      <div>
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
          Auto Allocate Collateral
        </h2>
        <p class="mt-1 text-sm text-gray-600">Select Basis and Reporting Period</p>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
          <form @submit.prevent="submit">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

              <!-- Allocation Basis -->
              <div>
                <jet-label for="allocation_basis" value="Allocation Basis" />
                <select v-model="form.allocation_basis"
                        id="allocation_basis"
                        class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm">
                  <option value="proportional">Proportional</option>
                  <option value="descending">Descending Exposure</option>
                  <option value="ascending">Ascending Exposure</option>
                  <option value="equal">Equal Distribution</option>
                </select>
                <div v-if="form.errors.allocation_basis" class="text-red-500 text-sm mt-1">
                  {{ form.errors.allocation_basis }}
                </div>
              </div>

              <!-- Reporting Year -->
              <div>
                <jet-label for="reporting_year" value="Reporting Year" />
                <input type="number"
                       id="reporting_year"
                       v-model="form.reporting_year"
                       min="2000"
                       :max="new Date().getFullYear()"
                       class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm" />
                <div v-if="form.errors.reporting_year" class="text-red-500 text-sm mt-1">
                  {{ form.errors.reporting_year }}
                </div>
              </div>

              <!-- Reporting Month -->
              <div>
                <jet-label for="reporting_month" value="Reporting Month" />
                <select v-model="form.reporting_month"
                        id="reporting_month"
                        class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm">
                  <option disabled value="">Select Month</option>
                  <option v-for="(month, index) in months" :key="index" :value="index + 1">
                    {{ month }}
                  </option>
                </select>
                <div v-if="form.errors.reporting_month" class="text-red-500 text-sm mt-1">
                  {{ form.errors.reporting_month }}
                </div>
              </div>

            </div>

            <div class="flex justify-end mt-6">
              <jet-button :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                Allocate
              </jet-button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </app-layout>
</template>

<script>
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import JetButton from '@/Jetstream/Button.vue'
import JetLabel from '@/Jetstream/Label.vue'

export default {
  components: {
    AppLayout,
    JetButton,
    JetLabel,
  },
  setup() {
    const currentDate = new Date()

    const form = useForm({
      allocation_basis: 'proportional',
      reporting_year: currentDate.getFullYear(),
      reporting_month: currentDate.getMonth() + 1, // default to current month
    })

    const months = [
      'January', 'February', 'March', 'April', 'May', 'June',
      'July', 'August', 'September', 'October', 'November', 'December'
    ]

    const submit = () => {
      form.post('/collateral/allocate/auto', {
        preserveScroll: true,
        onSuccess: () => {
          alert('Collateral allocated successfully.')
        },
        onError: () => {
          alert('There was an error allocating collateral.')
        },
      })
    }

    return {
      form,
      months,
      submit,
    }
  }
}
</script>
