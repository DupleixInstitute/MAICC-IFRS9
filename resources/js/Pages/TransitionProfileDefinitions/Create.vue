  <template>
    <app-layout>
      <template #header>
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
          ADD - Transition Profile
        </h2>
      </template>
  
      <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
          <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
            <form @submit.prevent="submitForm">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
  
                <!-- Profile Code Section -->
                <div>
                  <jet-label for="profile_code" value="Profile Code" />
                  <input v-model="form.profile_code"
                         type="text"
                         maxlength="15"
                         placeholder="Enter Profile Code"
                         class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm" />
                  <jet-input-error :message="form.errors.profile_code" class="mt-2" />
                </div>
  
                <!-- Short Name Section -->
                <div>
                  <jet-label for="short_name" value="Short Name" />
                  <input v-model="form.short_name"
                         type="text"
                         maxlength="60"
                         placeholder="Enter Short Name"
                         class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm" />
                  <jet-input-error :message="form.errors.short_name" class="mt-2" />
                </div>
  
                <!-- Description Section -->
                <div class="col-span-2">
                  <jet-label for="description" value="Description" />
                  <textarea v-model="form.description"
                            placeholder="Enter Description"
                            class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm"></textarea>
                  <jet-input-error :message="form.errors.description" class="mt-2" />
                </div>
  
                <!-- Start Table Section -->
                <div>
                  <jet-label for="start_table" value="Mapped Start Table" />
                  <select v-model="form.start_table"
                          @change="fetchStartTableColumns"
                          class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm">
                    <option v-for="table in tables" :key="table" :value="table">
                      {{ table }}
                    </option>
                  </select>
                  <jet-input-error :message="form.errors.start_table" class="mt-2" />
                </div>

                <!-- End Table Section -->
                <div>
                  <jet-label for="end_table" value="Mapped End Table" />
                  <select v-model="form.end_table" @change="fetchEndTableColumns"
                          class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm">
                    <option v-for="table in tables" :key="table" :value="table">
                      {{ table }}
                    </option>
                  </select>
                  <jet-input-error :message="form.errors.end_table" class="mt-2" />
                </div>

                <!-- Start Client ID Column Section -->
                <div>
                  <jet-label for="start_col" value="Start Client Column" />
                  <select v-model="form.start_client_id_col" class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm">
                    <option v-for="column in startTableColumns" :key="column" :value="column">
                      {{ column }}
                    </option>
                  </select>
                  <jet-input-error :message="form.errors.start_col" class="mt-2" />
                </div>

                <!-- End Client ID Column Section -->
                <div>
                  <jet-label for="end_col" value="End Client ID Column" />
                  <select v-model="form.end_client_id_col" class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm">
                    <option v-for="column in endTableColumns" :key="column" :value="column">
                      {{ column }}
                    </option>
                  </select>
                  <jet-input-error :message="form.errors.end_grading_col" class="mt-2" />
                </div>
  
                <!-- Start Client ID Column Section -->
                <div>
                  <jet-label for="start_col" value="Start Grading Column" />
                  <select v-model="form.start_grading_col" class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm">
                    <option v-for="column in startTableColumns" :key="column" :value="column">
                      {{ column }}
                    </option>
                  </select>
                  <jet-input-error :message="form.errors.start_col" class="mt-2" />
                </div>

                <!-- End Client ID Column Section -->
                <div>
                  <jet-label for="end_col" value="End Grading Column" />
                  <select v-model="form.end_grading_col" class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm">
                    <option v-for="column in endTableColumns" :key="column" :value="column">
                      {{ column }}
                    </option>
                  </select>
                  <jet-input-error :message="form.errors.end_grading_col" class="mt-2" />
                </div>
  
                <!-- Start Value Type Section -->
                <div>
                  <jet-label for="start_value_type" value="Value Type" />
                  <select v-model="form.start_value_type"
                          class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm">
                    <option value="text">Text</option>
                    <option value="number">Range</option>
                  </select>
                  <jet-input-error :message="form.errors.start_value_type" class="mt-2" />
                </div>
  
                <!-- End Value Type Section -->
                <div>
                  <jet-label for="end_value_type" value="End Value Type" />
                  <select v-model="form.end_value_type"
                          class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm">
                    <option value="text">Text</option>
                    <option value="number">Range</option>
                  </select>
                  <jet-input-error :message="form.errors.end_value_type" class="mt-2" />
                </div>
  
                <!-- Aggregation Criteria Section -->
                <div class="col-span-2">
                  <jet-label value="Aggregation Criteria" />
                  <div class="flex space-x-4">
                    <div>
                      <input type="radio" id="balance" value="Balance" v-model="form.aggregation_criteria" />
                      <label for="balance" class="ml-2">Balance</label>
                    </div>
                    <div>
                      <input type="radio" id="count" value="Count" v-model="form.aggregation_criteria" />
                      <label for="count" class="ml-2">Count</label>
                    </div>
                  </div>
                  <jet-input-error :message="form.errors.aggregation_criteria" class="mt-2" />
                </div>
  
              </div>
  
              <!-- Submit Button -->
              <div class="flex justify-end mt-6">
                <jet-button :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                  Save Transition Profile
                </jet-button>
              </div>
            </form>
            <transition-profile-data-table :profile="{ profile_code: form.profile_code, short_name: form.short_name }" />
          </div>
        </div>
      </div>

      <help-manual />
    </app-layout>
</template>
  
<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import JetButton from '@/Jetstream/Button.vue';
import JetLabel from '@/Jetstream/Label.vue';
import JetInputError from '@/Jetstream/InputError.vue';
import HelpManual from '../../Components/HelpManual.vue';

const props = defineProps({
  profile: {
    type: Object,
    default: () => ({})
  }
});

const isEditing = computed(() => !!props.profile);

// Initialize the form with default values
const form = useForm({
  profile_code: '',
  short_name: '',
  description: '',
  start_table: '',
  start_grading_col: '',
  start_client_id_col: '',
  start_value_type: 'text',
  end_table: '',
  end_grading_col: '',
  end_client_id_col: '',
  end_value_type: 'text',
  aggregation_criteria: 'Balance',
});

// Watch for `profile` changes and update the form dynamically
watch(() => props.profile, (newProfile) => {
  if (newProfile) {
    Object.assign(form, newProfile);
  }
}, { immediate: true });

console.log("Profile Data", props.profile);

const tables = ref([]);
const startTableColumns = ref([]);
const endTableColumns = ref([]);

// Fetch available tables when component is mounted
const fetchTables = async () => {
  try {
    const response = await axios.get('/api/get-tables');
    tables.value = response.data.tables;
  } catch (error) {
    console.error('Error fetching tables:', error);
  }
};

// Fetch columns for start and end tables
const fetchStartTableColumns = async () => {
  if (!form.start_table) return;
  try {
    const response = await axios.get(`/api/get-columns/${form.start_table}`);
    startTableColumns.value = response.data;
  } catch (error) {
    console.error('Error fetching start table columns:', error);
  }
};

const fetchEndTableColumns = async () => {
  if (!form.end_table) return;
  try {
    const response = await axios.get(`/api/get-columns/${form.end_table}`);
    endTableColumns.value = response.data;
  } catch (error) {
    console.error('Error fetching end table columns:', error);
  }
};

onMounted(() => {
  fetchTables();
  fetchStartTableColumns();
  fetchEndTableColumns();
});

const submitForm = () => {
  const routeName = form.id ? 'transition-profiles.update' : 'transition-profiles.store';
  const method = form.id ? 'put' : 'post';

  form[method](route(routeName, form.id ? { id: form.id } : {}), {
    preserveScroll: true,
    onSuccess: () => {
      console.log('Transition profile saved successfully');
    },
    onError: (errors) => {
      console.error('Error:', errors);
      alert('There was an error submitting the form. Please check your inputs.');
    }
  });
};

</script>
