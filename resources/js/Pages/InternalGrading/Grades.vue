<template>
    <app-layout>
        <!-- HEADER -->
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800">
                    {{ profile.name }} — Internal Grades
                </h2>

                <button
                    @click="showModal = true"
                    class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg shadow"
                >
                    Add Grade
                </button>
            </div>
        </template>

        <div class="py-10 max-w-7xl mx-auto sm:px-6 lg:px-8">

            <!-- INFO BANNER -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                <p class="text-sm text-blue-800">
                    <strong>Profile:</strong> {{ profile.name }} |
                    <strong>Max Tenor:</strong> {{ maxTenor }} years |
                    <strong>Total Grades:</strong> {{ grades.length }}
                </p>
                <p class="text-xs text-blue-600 mt-1">
                    PD curves are defined per grade and per tenor year.
                </p>
            </div>

            <!-- EMPTY STATE -->
            <div
                v-if="grades.length === 0"
                class="flex flex-col items-center justify-center py-16 bg-white rounded-lg shadow"
            >
                <svg class="w-12 h-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 17v-6h13M9 5v6h13M5 7h.01M5 17h.01" />
                </svg>
                <p class="text-sm font-semibold text-gray-600">
                    No grades have been added
                </p>
                <p class="text-xs text-gray-500 mt-1">
                    Click <strong>Add Grade</strong> to define PD curves.
                </p>
            </div>

            <!-- GRADE CARDS -->
            <div v-else class="space-y-4">
                <div
                    v-for="grade in grades"
                    :key="grade.id"
                    class="border rounded-lg shadow-sm bg-white"
                >
                    <!-- GRADE HEADER -->
                    <button
                        class="w-full flex justify-between items-center px-4 py-3 bg-gray-50 hover:bg-gray-100"
                        @click="toggleGrade(grade.id)"
                    >
                        <div>
                            <p class="font-semibold text-gray-900">
                                {{ grade.grade_code }} — {{ grade.grade_name }}
                            </p>
                            <p class="text-xs text-gray-500">
                                Click to view PD curve
                            </p>
                        </div>

                        <svg
                            class="w-5 h-5 transition-transform"
                            :class="{ 'rotate-180': openGrades[grade.id] }"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <!-- PD CURVE -->
                    <div v-if="openGrades[grade.id]" class="p-4">
                        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                            <div
                                v-for="y in maxTenor"
                                :key="y"
                                class="bg-gray-50 rounded-md p-3 text-center"
                            >
                                <p class="text-xs text-gray-500">Year {{ y }}</p>
                                    <input
                                        v-model.number="editablePds[grade.id][y]"
                                        type="number"
                                        step="0.0001"
                                        min="0"
                                        max="1"
                                        :disabled="profile.is_active"
                                        class="w-full text-center border rounded px-2 py-1 text-sm
                                            focus:ring focus:ring-indigo-200"
                                    />

                            </div>

                            <div class="flex justify-end mt-4">
                                <button
                                    @click="saveGrade(grade)"
                                    :disabled="profile.is_active"
                                    class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm
                                            hover:bg-indigo-700 disabled:opacity-50"
                                >
                                    Save PD Curve
                                </button>
                                </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ADD GRADE MODAL -->
        <AddGradeModal
            :show="showModal"
            :profile="profile"
            @close="showModal = false"
            @saved="reloadPage"
        />
    </app-layout>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import AddGradeModal from './AddGrade.vue'

const props = defineProps({
    profile: Object,
    grades: Array,
})

const showModal = ref(false)
const openGrades = reactive({})
const editablePds = reactive({})


const maxTenor = computed(() => props.profile.max_tenor_years)

function toggleGrade(id) {
    openGrades[id] = !openGrades[id]
}

function reloadPage() {
    window.location.reload()
}

watch(
    () => props.grades,
    (grades) => {
        grades.forEach(grade => {
            editablePds[grade.id] = {}

            grade.tenor_pds.forEach(pd => {
                editablePds[grade.id][pd.tenor_years] = pd.pd_probability
            })
        })
    },
    { immediate: true }
)

function saveGrade(grade) {
    const tenorPds = Object.keys(editablePds[grade.id]).map(year => ({
        tenor_years: Number(year),
        pd_probability: editablePds[grade.id][year],
    }))

    router.put(
        route('internal-grading.grade.update', [props.profile.id, grade.id]),
        {
            grade_name: grade.grade_name,
            grade_code: grade.grade_code,
            tenor_pds: tenorPds,
        },
        {
            preserveScroll: true,
        }
    )
}


</script>
