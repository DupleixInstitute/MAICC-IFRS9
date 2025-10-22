<template>
  <app-layout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Auto Allocate Collateral
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
          <form @submit.prevent="submit">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <!-- Collateral Selection -->
              <div>
                <jet-label for="collateral_register_id" value="Collateral" />
                <select v-model="form.collateral_register_id"
                        class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm">
                  <option value="">Select Collateral</option>
                  <option v-for="item in collateralList" :key="item.id" :value="item.id">
                    {{ item.description }} ({{ item.execution_value }})
                  </option>
                </select>
              </div>

              <!-- Allocation Basis -->
              <div>
                <jet-label for="allocation_basis" value="Allocation Basis" />
                <select v-model="form.allocation_basis"
                        class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm">
                  <option value="proportional">Proportional</option>
                  <option value="descending">Descending Exposure</option>
                  <option value="ascending">Ascending Exposure</option>
                  <option value="equal">Equal Distribution</option>
                </select>
              </div>
            </div>

            <div class="flex justify-end mt-6">
              <jet-button type="submit" class="bg-green-600 hover:bg-green-500 text-white">
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
import { ref, onMounted } from 'vue'
import axios from 'axios'
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
    const form = ref({
      collateral_register_id: '',
      customer_id: '',
      allocation_basis: 'proportional',
    })

    const collateralList = ref([])

    onMounted(async () => {
      const res = await axios.get('/api/collateral-registers')
      collateralList.value = res.data
    })

    const submit = () => {
      axios.post('/api/collateral/allocate-by-client', form.value)
        .then(res => alert(res.data.message))
        .catch(() => alert('Error allocating'))
    }

    return {
      form,
      collateralList,
      submit,
    }
  }
}
</script>
