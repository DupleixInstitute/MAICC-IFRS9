<template>
    <app-layout>
        <template #header>
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                        Internal Grading Profiles
                    </h2>
                    <p class="text-sm text-gray-500">
                        Configure internal grades and PD term structures
                    </p>
                </div>

                <button
                    @click="showModal = true"
                    class="inline-flex items-center bg-maiic-600 hover:bg-maiic-700 text-white px-4 py-2 rounded-lg shadow transition"
                >
                    + New Profile
                </button>
            </div>
        </template>

        <div class="py-12 max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div v-if="profiles && profiles.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div
                    v-for="profile in profiles"
                    :key="profile.id"
                    @click="goToGrades(profile.id)"
                    class="bg-white rounded-xl shadow hover:shadow-lg transition cursor-pointer border border-gray-200"
                >
                    <div class="p-6">
                        <!-- Header -->
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
                                {{ profile.name }}

                                <span
                                    class="px-2 py-0.5 rounded-full text-xs font-medium"
                                    :class="profile.is_active ? activeClass : draftClass"
                                >
                                    {{ profile.is_active ? 'Active' : 'Draft' }}
                                </span>
                            </h3>

                            </div>

                            <!-- Actions -->
                            <div class="flex items-center space-x-2">
                                <!-- View Matrix -->
                                <button
                                @click.stop="openMatrix(profile)"
                                class="text-blue-500 hover:text-indigo-600 transition"
                                title="View PD Matrix"
                                >
                                <i class="fas fa-eye"></i>
                                </button>

                                <!-- Lock / Unlock -->
                                <button
                                    @click.stop="toggleStatus(profile)"
                                    class="transition"
                                    :title="profile.is_active ? 'Deactivate profile' : 'Activate profile'"
                                >
                                    <span v-if="profile.is_active" class="text-green-600">
                                        <i class="fas fa-unlock"></i>
                                    </span>
                                    <span v-else class="text-red-600">
                                        <li class="fas fa-lock"></li>
                                    </span>
                                </button>
                            </div>
                        </div>


                        <!-- Stats -->
                        <div class="flex items-center justify-between text-sm mb-4">
                            <div class="text-gray-600">Grades</div>
                            <div class="font-semibold text-gray-800">{{ profile.mappings_count || 0 }}</div>
                        </div>

                        <!-- Grade Preview -->
                        <div v-if="profile.mappings && profile.mappings.length" class="flex flex-wrap gap-2 mb-4">
                            <span
                                v-for="grade in profile.mappings.slice(0, 5)"
                                :key="grade.id"
                                class="px-2 py-1 text-xs bg-gray-100 text-gray-700 rounded"
                            >
                                {{ grade.grade_code }}
                            </span>
                            <span
                                v-if="profile.mappings.length > 5"
                                class="px-2 py-1 text-xs bg-gray-200 text-gray-600 rounded"
                            >
                                +{{ profile.mappings.length - 5 }}
                            </span>
                            
                        </div>

                        <!-- Footer -->
                        <div class="pt-4 border-t flex justify-between items-center text-sm">
                            <span class="font-medium flex items-center gap-2">
                                <template v-if="profile.is_active">
                                    <!-- <span class="text-gray-400">Locked (Active)</span> -->
                                    <button
                                            @click.stop="openLoanBookModal(profile)"
                                            class="bg-maiic-500 hover:bg-maiic-600 text-white text-xs px-2 py-1 rounded ml-2"
                                            title="Update loan book for this profile"
                                        >
                                            Update Loan Book
                                        </button>
                                </template>
                                <template v-else>
                                    <span class="text-indigo-600 cursor-pointer" @click="goToGrades(profile.id)">
                                        Manage Grades →
                                    </span>
                                </template>
                            </span>

                            <span class="text-gray-400">ID {{ profile.id }}</span>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="text-center py-20 text-gray-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-16 w-16 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 17v-6a2 2 0 012-2h2a2 2 0 012 2v6m4 0h-2a2 2 0 01-2-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 01-2 2H5m14 0v1a2 2 0 01-2 2H7a2 2 0 01-2-2v-1m14 0H5" />
                </svg>
                <p class="text-lg font-semibold">No grading profiles yet</p>
                <p class="text-sm text-gray-500">Click the button above to create your first profile</p>
            </div>
        </div>

        <!-- Create Profile Modal -->
    <div
    v-if="showModal"
    class="fixed inset-0 flex items-center justify-center z-50"
    @click.self="showModal = false"
>
    <!-- Backdrop with blur -->
    <div class="absolute inset-0 bg-black bg-opacity-50 backdrop-blur-sm"></div>

    <!-- Modal Content -->
    <div class="relative z-10 bg-white rounded-xl shadow-lg p-6 w-full max-w-md">
        <h3 class="text-lg font-semibold mb-4">Create Grading Profile</h3>

        <form @submit.prevent="submit" class="space-y-4">
            <input
                v-model="form.name"
                placeholder="Profile Name"
                class="w-full border rounded-lg px-3 py-2 focus:ring focus:ring-indigo-200"
            />

            <textarea
                v-model="form.description"
                placeholder="Description"
                class="w-full border rounded-lg px-3 py-2 focus:ring focus:ring-indigo-200"
            />

            <!-- Max Tenor Years -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Max Tenor Years</label>
                <input
                    v-model.number="form.max_tenor_years"
                    type="number"
                    min="1"
                    max="30"
                    placeholder="e.g., 5"
                    class="w-full border rounded-lg px-3 py-2 focus:ring focus:ring-indigo-200"
                />
                <p class="text-xs text-gray-500 mt-1">
                    Maximum number of years for PD curves. All grades in this profile will follow this limit.
                </p>
            </div>

            <div class="flex justify-end space-x-2 pt-2">
                <button
                    type="button"
                    @click="showModal = false"
                    class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300"
                >
                    Cancel
                </button>
                <button
                    type="submit"
                    class="px-4 py-2 bg-maiic-600 text-white rounded-lg hover:bg-maiic-700"
                >
                    Save
                </button>
            </div>
        </form>
    </div>
</div>
<!-- LOAN BOOK UPDATE MODAL -->
<div
    v-if="showLoanBookModal"
    class="fixed inset-0 flex items-center justify-center z-50"
    @click.self="showLoanBookModal = false"
>
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-black bg-opacity-50 backdrop-blur-sm"></div>

    <!-- Modal Content -->
    <div class="relative z-10 bg-white rounded-xl shadow-lg p-6 w-full max-w-md">
        <h3 class="text-lg font-semibold mb-4">
            Update Loan Book (PD Assignment)
        </h3>

        <form @submit.prevent="updateLoanBookWithPD" class="space-y-4">

            <!-- Reporting Period -->
            <div>
                <label class="block text-sm font-medium mb-1">Reporting Period</label>
                <input
                    type="month"
                    v-model="loanBookForm.reporting_period"
                    class="w-full border rounded-lg px-3 py-2"
                    required
                />
            </div>

            <!-- Scope -->
            <div>
                <label class="block text-sm font-medium mb-1">Update Level</label>
                <select
                    v-model="loanBookForm.scope"
                    class="w-full border rounded-lg px-3 py-2"
                    required
                >
                    <option value="" disabled>Select scope</option>
                    <option value="portfolio">Portfolio</option>
                    <option value="sector">Sector</option>
                </select>
            </div>

            <!-- Portfolio -->
            <div v-if="loanBookForm.scope === 'portfolio'">
                <label class="block text-sm font-medium mb-1">Portfolio</label>
                <select
                    v-model="loanBookForm.portfolio_id"
                    class="w-full border rounded-lg px-3 py-2"
                    required
                >
                    <option value="">Select portfolio</option>
                    <option
                        v-for="p in portfolios"
                        :key="p.id"
                        :value="p.id"
                    >
                        {{ p.name }}
                    </option>
                </select>
            </div>

            <!-- Sector -->
            <div v-if="loanBookForm.scope === 'sector'">
                <label class="block text-sm font-medium mb-1">Sector</label>
                <select
                    v-model="loanBookForm.sector_code"
                    class="w-full border rounded-lg px-3 py-2"
                    required
                >
                    <option value="">Select sector</option>
                    <option
                        v-for="s in sectors"
                        :key="s.code"
                        :value="s.code"
                    >
                        {{ s.name }}
                    </option>
                </select>
            </div>

            <!-- Actions -->
            <div class="flex justify-end space-x-2 pt-4">
                <button
                    type="button"
                    @click="showLoanBookModal = false"
                    class="px-4 py-2 bg-gray-200 rounded-lg"
                >
                    Cancel
                </button>
                <button
                    type="submit"
                    class="px-4 py-2 bg-maiic-600 text-white rounded-lg"
                >
                    Update
                </button>
            </div>
        </form>
    </div>
</div>


<GradesPdMatrix
  :show="showMatrix"
  :profile="selectedProfile"
  :grades="matrixGrades"
  :maxTenor="matrixMaxTenor"
  @close="showMatrix = false"
/>

    </app-layout>
</template>

<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import GradesPdMatrix from './GradesPdMatrix.vue'
import axios from 'axios'
import '@fortawesome/fontawesome-free/css/all.css';

defineProps(
    { profiles: Array,
        portfolios: Array,
        sectors: Array  
    }
     )

const showModal = ref(false)
const showMatrix = ref(false)
const selectedProfile = ref(null)
const matrixGrades = ref([])
const matrixMaxTenor = ref(0)
const showLoanBookModal = ref(false)


const form = ref({
    name: '',
    description: '',
     max_tenor_years: '' ?? 5,
})

const goToGrades = (id) => {
    router.visit(route('internal-grading.grades', id))
}

const viewMatrix = (id) => {
    router.visit(route('internal-grading.matrix.view', id))
}

const toggleStatus = (profile) => {
    router.put(
        route('internal-grading.profile.toggle', profile.id),
        {},
        { preserveScroll: true }
    )
}

const submit = () => {
    router.post(route('internal-grading.profile.store'), form.value, {
        onSuccess: () => {
            showModal.value = false
            form.value = { name: '', description: '' }
        }
    })
}

const openMatrix = async (profile) => {
  const { data } = await axios.get(
    route('internal-grading.matrix.view', profile.id)
  )

  selectedProfile.value = data.profile
  matrixGrades.value = data.grades
  matrixMaxTenor.value = data.maxTenor
  showMatrix.value = true
}

const handleManage = (profile) => {
    if (profile.is_active) {
        return
    }

    goToGrades(profile.id)
}


const loanBookForm = ref({
    reporting_period: '',
    scope: '',
    portfolio_id: null,
    sector_code: null,
})

const openLoanBookModal = (profile) => {
    selectedProfile.value = profile
    showLoanBookModal.value = true

    // reset form
    loanBookForm.value = {
        reporting_period: '',
        scope: '',
        portfolio_id: null,
        sector_code: null,
    }
}


const updateLoanBookWithPD = () => {
    router.post(
        route('internal-grading.loanbook.updateWithPD'),
        {
            profile_id: selectedProfile.value.id,
            reporting_period: loanBookForm.value.reporting_period,
            scope: loanBookForm.value.scope,
            portfolio_id: loanBookForm.value.portfolio_id,
            sector_code: loanBookForm.value.sector_code,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                showLoanBookModal.value = false
            }
        }
    )
}

const activeClass = 'bg-green-100 text-green-700'
const draftClass = 'bg-yellow-100 text-yellow-700'
</script>
