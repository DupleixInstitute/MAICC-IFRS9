<template>
  <app-layout>
    <template #header>
      <div>
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        <inertia-link class="text-indigo-400 hover:text-indigo-600" :href="route('collateral.allocations.index')">
          Collateral
        </inertia-link>
        <span class="text-indigo-400 font-medium">/</span>Auto Allocate Collateral
        </h2>
        <p class="mt-1 text-sm text-gray-600">
          Select Allocation Basis, Reporting Period, and Collateral Reporting Period
        </p>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-8">
          <form @submit.prevent="submit" class="space-y-8">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

              <!-- Allocation Basis -->
              <div>
                <jet-label for="allocation_basis" value="Allocation Basis" />
                <select
                  v-model="form.allocation_basis"
                  id="allocation_basis"
                  class="mt-2 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-300 rounded-lg shadow-sm py-2.5"
                >
                  <option value="proportional">Proportional</option>
                  <option value="descending">Descending Exposure</option>
                  <option value="ascending">Ascending Exposure</option>
                  <option value="equal">Equal Distribution</option>
                </select>
                <p v-if="form.errors.allocation_basis" class="text-red-500 text-sm mt-1">
                  {{ form.errors.allocation_basis }}
                </p>
              </div>

              <!-- Reporting Year -->
              <div>
                <jet-label for="reporting_year" value="Loan Book Reporting Year" />
                <input
                  type="number"
                  id="reporting_year"
                  v-model="form.reporting_year"
                  min="2000"
                  :max="new Date().getFullYear()"
                  class="mt-2 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-300 rounded-lg shadow-sm py-2.5"
                />
                <p v-if="form.errors.reporting_year" class="text-red-500 text-sm mt-1">
                  {{ form.errors.reporting_year }}
                </p>
              </div>

              <!-- Reporting Month -->
              <div>
                <jet-label for="reporting_month" value="Loan Book Reporting Month" />
                <select
                  v-model="form.reporting_month"
                  id="reporting_month"
                  class="mt-2 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-300 rounded-lg shadow-sm py-2.5"
                >
                  <option disabled value="">Select Month</option>
                  <option v-for="(month, index) in months" :key="index" :value="index + 1">
                    {{ month }}
                  </option>
                </select>
                <p v-if="form.errors.reporting_month" class="text-red-500 text-sm mt-1">
                  {{ form.errors.reporting_month }}
                </p>
              </div>

              <!-- Registration Date -->
              <div class="md:col-span-2 lg:col-span-1">
                <jet-label for="registration_date" value="Collateral Reporting Date" />
                <select
                  v-model="form.registration_date"
                  id="registration_date"
                  class="mt-2 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-300 rounded-lg shadow-sm py-2.5"
                >
                  <option disabled value="">Select Reporting Date</option>
                  <option v-for="(date, index) in registerDates" :key="index" :value="date">
                    {{ formatDate(date) }}
                  </option>
                </select>
                <p v-if="form.errors.registration_date" class="text-red-500 text-sm mt-1">
                  {{ form.errors.registration_date }}
                </p>
              </div>

            </div>

            <div class="flex items-center justify-end mt-8 space-x-3">
              <button
                type="button"
                class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition"
                @click="resetForm"
              >
                Reset
              </button>

              <jet-button
                class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 transition text-white rounded-lg shadow"
                :class="{ 'opacity-25': form.processing }"
                :disabled="form.processing"
              >
                <span v-if="!form.processing">Allocate</span>
                <span v-else>Processing...</span>
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
  props: {
    registerDates: Array, // from backend
  },
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
      reporting_month: currentDate.getMonth() + 1,
      registration_date: '',
    })

    const months = [
      'January', 'February', 'March', 'April', 'May', 'June',
      'July', 'August', 'September', 'October', 'November', 'December'
    ]

    const formatDate = (date) => 
      {
        if (!date) return ''
        const d = new Date(date + 'T00:00:00Z') // force UTC midnight
        return d.toLocaleDateString('en-US', { year: 'numeric', month: 'long', timeZone: 'UTC' })
      }

    const resetForm = () => {
      form.allocation_basis = 'proportional'
      form.reporting_year = currentDate.getFullYear()
      form.reporting_month = currentDate.getMonth() + 1
      form.registration_date = ''
      form.clearErrors()
    }

    const submit = () => {
      form.post('/collateral/allocate/auto', {
        preserveScroll: true,
        onSuccess: () => {
          alert(' Collateral allocated successfully.')
          resetForm()
        },
        onError: () => {
          alert(' There was an error allocating collateral.')
        },
      })
    }

    return {
      form,
      months,
      formatDate,
      submit,
      resetForm,
    }
  }
}
</script>
