<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Configuration
            </h2>
        <div v-if="successMessage" class="mt-4 px-4 py-3 rounded-md bg-green-500 text-white-700 text-sm">
          {{ successMessage }}
        </div>
        </template>
    <div class="p-6 bg-white shadow-md rounded-lg">
        <h2 class="font-bold text-2xl mb-4 text-gray-800">Transition Profile Details</h2>
        <div class="bg-gray-50 p-6 rounded-lg shadow-sm">
            <p class="text-gray-700"><strong class="text-gray-900">Profile Code:</strong> {{ profile.profile_code }}</p>
            <p class="text-gray-700"><strong class="text-gray-900">Short Name:</strong> {{ profile.short_name }}</p>
            <div>
            <p class="text-gray-700"><strong class="text-gray-900">Mapped Tables:</strong> </p>
               <li>{{ profile.start_table }}</li>
                <li>{{ profile.start_grading_col }}</li>
                </div>


                <div class="flex justify-end ">
          <button @click="openModal" type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg shadow-md transition duration-300">
            Re-Order Categories
          
                <CategoryReorder
                      v-if="isModalOpen"
                      :categories="categories"
                      :profile="profile"
                      :isOpen="isModalOpen"
                      @onClose="closeModal"
                  />

        </button>
        </div>
        </div>

        <h2 class="font-bold text-xl mt-8 mb-4 text-gray-800">Start Table Configuration</h2>
        <div>
    <!-- Dropdown and Add Button -->
    <div class="mb-4">
      <dropdown
        v-model="selectedCategory"
        :options="categories"
        optionLabel="matrix_category_name"
        optionValue="id"
        placeholder="Select a category"
        class="w-full"
      />
      <button
        @click="toggleAddForm('start')"
        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow-md transition duration-300 mt-2"
      >
        {{ isStartFormOpen ? 'Cancel' : '+ Add Category' }}
      </button>
    </div>
    <!-- Add Category Form -->

    <div v-if="isStartFormOpen" class="bg-gray-50 p-6 rounded-lg shadow-sm mt-4">
      <form @submit.prevent="saveCategory" class="space-y-4">
        <input type="hidden" v-model="newCategory.profile_id" />
        <!-- Configure Period and Default Flag -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700"><strong>Configure Period:</strong></label>
            <select
              v-model="newCategory.is_start_or_end"
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
            >
              <option value="Start">Start</option>
              <option value="End">End</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700"><strong>Default Flag:</strong></label>
            <input
              v-model="newCategory.default_value"
              type="checkbox"
              class="mt-2 h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
            />
          </div>
        </div>

        <!-- Category Name -->
        <div>
          <label class="block text-sm font-medium text-gray-700"><strong>Category Name:</strong></label>
          <input
            v-model="newCategory.category_name"
            type="text"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
          />
        </div>

        <!-- Min Value, Max Value, and Text Value -->
        <div class="grid grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700"><strong>Min Value:</strong></label>
            <input
              v-model="newCategory.min_value"
              type="number"
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700"><strong>Max Value:</strong></label>
            <input
              v-model="newCategory.max_value"
              type="number"
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700"><strong>Text Value:</strong></label>
            <input
              v-model="newCategory.text_value"
              type="text"
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
            />
          </div>
        </div>

        <!-- Save Button -->
        <div class="flex justify-end">
          <button
            type="submit"
            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow-md transition duration-300"
          >
            Save Category
          </button>
        </div>
      </form>
    </div>
  </div>
       
        <table class="min-w-full border mt-6 rounded-lg overflow-hidden shadow-sm">
            <thead>
                <tr class="bg-gray-100">
                    <th class="border px-4 py-3 text-left text-gray-700">ID</th>
                    <th class="border px-4 py-3 text-left text-gray-700">Ordering Index</th>
                    <th class="border px-4 py-3 text-left text-gray-700">Matrix Category Name</th>
                    <th class="border px-4 py-3 text-left text-gray-700">Min Value</th>
                    <th class="border px-4 py-3 text-left text-gray-700">Max Value</th>
                    <th class="border px-4 py-3 text-left text-gray-700">Text Value</th>
                    <th class="border px-4 py-3 text-left text-gray-700">Default Flag</th>
                    <th class="border px-4 py-3 text-left text-gray-700">Created</th>
                    <th class="border px-4 py-3 text-left text-gray-700">Actions</th>
                </tr>
              </thead>
            <tbody>
                <tr v-for="(category, index) in startCategories" :key="category.id" class="hover:bg-gray-50 transition duration-200">
                    <td class="border px-4 py-3 text-gray-600">{{ category.id }}</td>
                    <td class="border px-4 py-3 text-gray-600">{{ category.ordering_index }}</td>
                    <td class="border px-4 py-3">
                        <span v-if="!category.isEditing">{{ category.category_name }}</span>
                        <input
                          v-else
                          type="text"
                          v-model="category.category_name"
                          class="border rounded px-2 py-1"
                        />
                      </td>
                    <td class="border px-4 py-3">
                        <span v-if="!category.isEditing">{{ category.min_value }}</span>
                        <input
                          v-else
                          type="number"
                          v-model="category.min_value"
                          class="border rounded px-2 py-1"
                        />
                      </td>
                    <td class="border px-4 py-3">
                        <span v-if="!category.isEditing">{{ category.max_value }}</span>
                        <input
                          v-else
                          type="number"
                          v-model="category.max_value"
                          class="border rounded px-2 py-1"
                        />
                      </td>
                    <td class="border px-4 py-3">
                        <span v-if="!category.isEditing">{{ category.text_value }}</span>
                        <input
                          v-else
                          type="text"
                          v-model="category.text_value"
                          class="border rounded px-2 py-1"
                        />
                      </td>
                    <td class="border px-4 py-3">
                        <span v-if="!category.isEditing">{{ category.default_value ? 'Yes' : 'No'  }}</span>
                        <input
                          v-else
                          type="checkbox"
                          v-model="category.default_value"
                          :true-value="1"
                          :false-value="0"
                          class="border rounded px-2 py-1"
                        />
                      </td>
                    <td class="border px-4 py-3 text-gray-600">{{ formatDate(category.created_at) }}</td>
                    <td class="border px-4 py-3 text-gray-600">
                        <template v-if="category.isEditing">
                          <button @click="saveRow(category)" class="text-green-600 hover:text-green-700">Save</button>
                          <button @click="cancelEdit(category)" class="text-red-600 hover:text-red-700">Cancel</button>
                        </template>
                        <template v-else>
                          <button @click="editRow(category)" class="text-blue-600 hover:text-blue-700">Edit</button>
                          <button @click="handleDelete(category.id)" class="text-red-600 hover:text-red-700">Delete</button>
                        </template>
                      </td>
                </tr>
            </tbody>
        </table>
        <h2 class="font-bold text-xl mt-8 mb-4 text-gray-800">End Table Configuration</h2>
        <div>
    <!-- Dropdown and Add Button -->
    <div class="mb-4">
      <dropdown
        v-model="selectedCategory"
        :options="categories"
        optionLabel="matrix_category_name"
        optionValue="id"
        placeholder="Select a category"
        class="w-full"
      />
      <button
        @click="toggleAddForm('end')"
        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow-md transition duration-300 mt-2"
      >
        {{ isEndFormOpen ? 'Cancel' : '+ Add Category' }}
      </button>
    </div>

    <!-- Add Category Form -->
    <div v-if="isEndFormOpen" class="bg-gray-50 p-6 rounded-lg shadow-sm mt-4">
      <form @submit.prevent="saveCategory" class="space-y-4">

        <input type="hidden" v-model="newCategory.profile_id" />

        <!-- Configure Period and Default Flag -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700"><strong>Configure Period:</strong></label>
            <select
              v-model="newCategory.is_start_or_end"
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
            >
              <option value="Start">Start</option>
              <option value="End">End</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700"><strong>Default Flag:</strong></label>
            <input
              v-model="newCategory.default_value"
              type="checkbox"
              class="mt-2 h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
            />
          </div>
        </div>

        <!-- Category Name -->
        <div>
          <label class="block text-sm font-medium text-gray-700"><strong>Category Name:</strong></label>
          <input
            v-model="newCategory.category_name"
            type="text"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
          />
        </div>

        <!-- Min Value, Max Value, and Text Value -->
        <div class="grid grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700"><strong>Min Value:</strong></label>
            <input
              v-model="newCategory.min_value"
              type="number"
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700"><strong>Max Value:</strong></label>
            <input
              v-model="newCategory.max_value"
              type="number"
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700"><strong>Text Value:</strong></label>
            <input
              v-model="newCategory.text_value"
              type="text"
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
            />
          </div>
        </div>

        <!-- Save Button -->
        <div class="flex justify-end">
          <button
            type="submit"
            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow-md transition duration-300"
          >
            Save Category
          </button>
        </div>
      </form>
    </div>
  </div>
        <table class="min-w-full border mt-6 rounded-lg overflow-hidden shadow-sm">
            <thead>
                <tr class="bg-gray-100">
                    <th class="border px-4 py-3 text-left text-gray-700">ID</th>
                    <th class="border px-4 py-3 text-left text-gray-700">Ordering Index</th>
                    <th class="border px-4 py-3 text-left text-gray-700">Matrix Category Name</th>
                    <th class="border px-4 py-3 text-left text-gray-700">Min Value</th>
                    <th class="border px-4 py-3 text-left text-gray-700">Max Value</th>
                    <th class="border px-4 py-3 text-left text-gray-700">Text Value</th>
                    <th class="border px-4 py-3 text-left text-gray-700">Default Flag</th>
                    <th class="border px-4 py-3 text-left text-gray-700">Created</th>
                    <th class="border px-4 py-3 text-left text-gray-700">Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="category in endCategories" :key="category.id" class="hover:bg-gray-50 transition duration-200">
                    <td class="border px-4 py-3 text-gray-600">{{ category.id }}</td>
                    <td class="border px-4 py-3 text-gray-600">{{ category.ordering_index }}</td>
                    <td class="border px-4 py-3">
                        <span v-if="!category.isEditing">{{ category.category_name }}</span>
                        <input
                          v-else
                          type="text"
                          v-model="category.category_name"
                          class="border rounded px-2 py-1"
                        />
                      </td>
                    <td class="border px-4 py-3">
                        <span v-if="!category.isEditing">{{ category.min_value }}</span>
                        <input
                          v-else
                          type="number"
                          v-model="category.min_value"
                          class="border rounded px-2 py-1"
                        />
                      </td>
                    <td class="border px-4 py-3">
                        <span v-if="!category.isEditing">{{ category.max_value}}</span>
                        <input
                          v-else
                          type="number"
                          v-model="category.max_value"
                          class="border rounded px-2 py-1"
                        />
                      </td>
                    <td class="border px-4 py-3">
                        <span v-if="!category.isEditing">{{ category.text_value }}</span>
                        <input
                          v-else
                          type="text"
                          v-model="category.text_value"
                          class="border rounded px-2 py-1"
                        />
                      </td>
                    <td class="border px-4 py-3">
                        <span v-if="!category.isEditing">{{ category.default_value ? 'Yes' : 'No'  }}</span>
                        <input
                          v-else
                          type="checkbox"
                          v-model="category.default_value"
                          :true-value="1"
                          :false-value="0"
                          class="border rounded px-2 py-1"
                        />
                      </td>
                      <td class="border px-4 py-3 text-gray-600">{{ formatDate(category.created_at) }}</td>
                      <td class="border px-4 py-3 text-gray-600">
                    <template v-if="category.isEditing">
                      <button @click="saveRow(category)" class="text-green-600 hover:text-green-700">Save</button>
                      <button @click="cancelEdit(category)" class="text-red-600 hover:text-red-700">Cancel</button>
                    </template>
                    <template v-else>
                      <button @click="editRow(category)" class="text-blue-600 hover:text-blue-700">Edit</button>
                      <button @click="handleDelete(category.id)" class="text-red-600 hover:text-red-700">Delete</button>
                    </template>
                  </td>
                    </tr>
            </tbody>
        </table>
    </div>
</app-layout>
</template>

<script setup>
import { defineProps, ref } from 'vue';
import { format } from 'date-fns';
import { router } from '@inertiajs/vue3';
import { Inertia } from '@inertiajs/inertia';
import AppLayout from '@/Layouts/AppLayout.vue';
import CategoryReorder from '@/Pages/TransitionProfileDefinitions/Components/CategoryReorder.vue';

const props = defineProps({
    profile: {
        type: Object,
        required: true,
    },

    startCategories: Array,
    endCategories: Array,
    categories: Array,

});

const formatDate = (dateString) => format(new Date(dateString), 'yyyy-MM-dd');

const isModalOpen = ref(false);
const openModal = () => (isModalOpen.value = true);
const closeModal = () => (isModalOpen.value = false);
const categories = ref([
  ...props.startCategories.map((c) => ({ ...c, isEditing: false })),
  ...props.endCategories.map((c) => ({ ...c, isEditing: false })),
]);

const profileId = ''; // Replace with dynamic profile ID if necessary

const isStartFormOpen = ref(false);
const isEndFormOpen = ref(false);
const selectedCategory = ref(null);

const startCategories = ref(props.startCategories || []);
const endCategories = ref(props.endCategories || []);
const successMessage = ref('');


const newCategory = ref({
    profile_id: '',
    ordering_index: '',
    matrix_category_name: '',
    min_value: '',
    max_value: '',
    text_value: '',
    default_value: false,
    is_start_or_end: '',
    isEditing: false,
});

const toggleAddForm = (section) => {
  if (props.profile) {
    newCategory.value.profile_id = props.profile.id; 
  } else {
    console.error('Profile is not defined');
    return;
  }

  if (section === 'start') {
    isStartFormOpen.value = !isStartFormOpen.value;
    if (isStartFormOpen.value) {
      isEndFormOpen.value = false; 
    }
  } else if (section === 'end') {
    isEndFormOpen.value = !isEndFormOpen.value;
    if (isEndFormOpen.value) {
      isStartFormOpen.value = false; 
    }
  }
};

const resetForm = () => { 
  newCategory.value = { 
    profile_id: props.profile.id, 
    ordering_index: '', 
    matrix_category_name: '',
    min_value: '', 
    max_value: '', 
    text_value: '', 
    default_value: false, 
    is_start_or_end: '',
   };
   Inertia.reload()
   };

   const saveCategory = () => {
  newCategory.value.profile_id = props.profile.id;
      router.post('/transition-profile/categories', newCategory.value, {
        onSuccess: () => {
          successMessage.value = 'Category saved successfully!';
          resetForm();
          isStartFormOpen.value = false;
          isEndFormOpen.value = false;
        },
      });
};

    // Enable edit mode for a row
    const editRow = (category) => {
      category.isEditing = true;
    };

    // Save the edited row
    const saveRow = (category) => {
      category.isEditing = false;
      // Send update request to Laravel backend
      Inertia.put(`/transition-profile/categories/${category.id}`, {
        category_name: category.category_name,
        min_value: category.min_value,
        max_value: category.max_value,
        text_value: category.text_value,
        default_value: category.default_value,
      });
    };

    // Cancel editing (reset to original values)
    const cancelEdit = (category) => {
      category.isEditing = false;

      // Also collapse the form if it's open
      isStartFormOpen.value = false;
      isEndFormOpen.value = false;

      Inertia.reload();
    };


const handleDelete = (id) => {
  const route = `/transition-profile/categories/${id}/delete`;
  router.delete(route, {
    onSuccess: () => {
      successMessage.value = "Configuration Deleted Successfully";
      startCategories.value = startCategories.value.filter((category) => category.id !== id);
      endCategories.value = endCategories.value.filter((category) => category.id !== id);
    },
  });
  console.log('Delete category', id);
  // Use category.id for submission
  const categoryId = id;
  console.log('Category ID:', categoryId);
  Inertia.reload();
};

</script>
