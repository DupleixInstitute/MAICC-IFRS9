<template>
  <app-layout title="Allocate Collateral" description="Spread each customer's discounted collateral over their loans for one loan book month">
    <template #actions>
      <Link :href="route('collateral.allocations.index')" class="secondary-btn">Back to allocations</Link>
    </template>

    <form @submit.prevent="submit" class="maiic-panel max-w-4xl">
      <div class="grid grid-cols-1 gap-5 p-6 md:grid-cols-2">
        <div>
          <label class="maiic-flabel" for="allocation_basis">Allocation basis</label>
          <select v-model="form.allocation_basis" id="allocation_basis" class="maiic-select">
            <option value="proportional">Proportional to exposure</option>
            <option value="descending">Largest exposure first</option>
            <option value="ascending">Smallest exposure first</option>
            <option value="equal">Equal shares</option>
          </select>
          <p v-if="form.errors.allocation_basis" class="mt-1 text-xs text-red-600">{{ form.errors.allocation_basis }}</p>
        </div>
        <div>
          <label class="maiic-flabel" for="registration_date">Collateral register date</label>
          <select v-model="form.registration_date" id="registration_date" class="maiic-select">
            <option disabled value="">Choose the register date</option>
            <option v-for="(date, index) in registerDates" :key="index" :value="date">{{ formatDate(date) }}</option>
          </select>
          <p v-if="form.errors.registration_date" class="mt-1 text-xs text-red-600">{{ form.errors.registration_date }}</p>
          <p v-else-if="!registerDates.length" class="mt-1 text-xs text-amber-700">No register has been imported yet. Import the collateral register first.</p>
        </div>
        <div>
          <label class="maiic-flabel" for="reporting_year">Loan book year</label>
          <input type="number" id="reporting_year" v-model="form.reporting_year" min="2000" :max="new Date().getFullYear()" class="maiic-input"/>
          <p v-if="form.errors.reporting_year" class="mt-1 text-xs text-red-600">{{ form.errors.reporting_year }}</p>
        </div>
        <div>
          <label class="maiic-flabel" for="reporting_month">Loan book month</label>
          <select v-model="form.reporting_month" id="reporting_month" class="maiic-select">
            <option disabled value="">Choose the month</option>
            <option v-for="(month, index) in months" :key="index" :value="index + 1">{{ month }}</option>
          </select>
          <p v-if="form.errors.reporting_month" class="mt-1 text-xs text-red-600">{{ form.errors.reporting_month }}</p>
        </div>
      </div>
      <div class="flex items-center justify-end gap-2 border-t border-gray-200 bg-gray-50 px-6 py-4">
        <button type="button" class="secondary-btn" @click="resetForm">Clear</button>
        <button type="submit" class="primary-btn" :disabled="form.processing">{{ form.processing ? 'Allocating...' : 'Allocate' }}</button>
      </div>
    </form>
  </app-layout>
</template>

<script>
import { Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

export default {
  props: {
    registerDates: { type: Array, default: () => [] },
  },
  components: {
    AppLayout,
    Link,
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
      // the server redirects to the allocations with a success message;
      // validation errors show under each field
      form.post(route('collateral.allocate.auto'), { preserveScroll: true })
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
