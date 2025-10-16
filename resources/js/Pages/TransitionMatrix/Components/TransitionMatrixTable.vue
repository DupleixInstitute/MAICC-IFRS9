<template>
    <div class="mt-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium">Transition Matrix Entries</h3>
            <div class="space-x-2">
                <jet-button @click="addNewRow" class="bg-green-600 hover:bg-green-500">
                    Add Transition
                </jet-button>
                <jet-button @click="saveEntries" :disabled="!hasChanges" class="bg-blue-600 hover:bg-blue-500">
                    Save Changes
                </jet-button>
            </div>
        </div>

        <!-- Matrix Table -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Portfolio Group
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Start State
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            End State
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Start Balance
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Start Count
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            End Balance
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            End Count
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Transitional Probability
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Default
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="(entry, index) in sortedEntries" :key="index">
                        <!-- Portfolio Group -->
                        <td class="px-6 py-4 whitespace-nowrap">
                            <select v-model="entry.portfolio_group" 
                                    @change="updateEntry(index)"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm">
                                <option v-for="group in portfolioGroups" 
                                        :key="group.id" 
                                        :value="group.id">
                                    {{ group.name }}
                                </option>
                            </select>
                        </td>

                        <!-- Start State -->
                        <td class="px-6 py-4 whitespace-nowrap">
                            <select v-model="entry.start_state"
                                    @change="updateEntry(index)"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm">
                                <option v-for="(description, state) in states" 
                                        :key="state" 
                                        :value="state">
                                    {{ state.name }} - {{ description.name }}
                                </option>
                            </select>
                        </td>

                        <!-- End State -->
                        <td class="px-6 py-4 whitespace-nowrap">
                            <select v-model="entry.end_state"
                                    @change="updateEntry(index)"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm">
                                <option v-for="(description, state) in states" 
                                        :key="state" 
                                        :value="state">
                                    {{ state.name }} - {{ description.name }}
                                </option>
                            </select>
                        </td>

                        <!-- Start Balance -->
                        <td class="px-6 py-4 whitespace-nowrap">
                            <input type="number" 
                                   v-model.number="entry.start_balance"
                                   @change="updateEntry(index)"
                                   class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm"
                                   :disabled="aggregationCriteria === 'count'" />
                        </td>

                        <!-- Start Count -->
                        <td class="px-6 py-4 whitespace-nowrap">
                            <input type="number" 
                                   v-model.number="entry.start_count"
                                   @change="updateEntry(index)"
                                   class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm"
                                   :disabled="aggregationCriteria === 'balance'" />
                        </td>

                        <!-- End Balance -->
                        <td class="px-6 py-4 whitespace-nowrap">
                            <input type="number" 
                                   v-model.number="entry.end_balance"
                                   @change="updateEntry(index)"
                                   class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm"
                                   :disabled="aggregationCriteria === 'count'" />
                        </td>

                        <!-- End Count -->
                        <td class="px-6 py-4 whitespace-nowrap">
                            <input type="number" 
                                   v-model.number="entry.end_count"
                                   @change="updateEntry(index)"
                                   class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm"
                                   :disabled="aggregationCriteria === 'balance'" />
                        </td>

                        <!-- Transitional Probability -->
                        <td class="px-6 py-4 whitespace-nowrap">
                            {{ formatProbability(calculateProbability(entry)) }}%
                        </td>

                        <!-- Default Flag -->
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div :class="{'text-red-600': isDefaultState(entry.end_state), 'text-green-600': !isDefaultState(entry.end_state)}">
                                {{ isDefaultState(entry.end_state) ? 'Yes' : 'No' }}
                            </div>
                        </td>

                        <!-- Actions -->
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <button @click="removeRow(index)" 
                                    class="text-red-600 hover:text-red-900">
                                Delete
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script>
import { ref, computed } from 'vue'
import JetButton from '@/Jetstream/Button.vue'
import { useForm } from '@inertiajs/vue3'

export default {
    components: {
        JetButton
    },

    props: {
        matrix: {
            type: Object,
            required: true
        },
        profile: {
            type: Object,
            required: true
        },
        portfolioGroups: {
            type: Array,
            required: true
        },
        states: {
            type: Object,
            required: true
        }
    },

    setup(props) {
        const entries = ref([])
        const hasChanges = ref(false)

        // Get aggregation criteria with fallback
        const aggregationCriteria = computed(() => {
            return props.profile?.aggregation_criteria || 'balance'
        })

        // Sort entries according to the specified order
        const sortedEntries = computed(() => {
            return [...entries.value].sort((a, b) => {
                if (a.portfolio_group !== b.portfolio_group) 
                    return a.portfolio_group.localeCompare(b.portfolio_group)
                if (a.start_state !== b.start_state) 
                    return a.start_state.localeCompare(b.start_state)
                return a.end_state.localeCompare(b.end_state)
            })
        })

        const addNewRow = () => {
            entries.value.push({
                portfolio_group: props.portfolioGroups[0],
                start_state: Object.keys(props.states)[0],
                end_state: Object.keys(props.states)[0],
                start_balance: 0,
                start_count: 0,
                end_balance: 0,
                end_count: 0,
                transitional_probability: 0,
                is_default: false
            })
            hasChanges.value = true
        }

        const removeRow = (index) => {
            entries.value.splice(index, 1)
            hasChanges.value = true
        }

        const updateEntry = (index) => {
            const entry = entries.value[index]
            entry.is_default = isDefaultState(entry.end_state)
            entry.transitional_probability = calculateProbability(entry)
            hasChanges.value = true
        }

        const calculateProbability = (entry) => {
            if (aggregationCriteria.value === 'balance') {
                return entry.start_balance > 0 
                    ? (entry.end_balance / entry.start_balance) * 100 
                    : 0
            }
            return entry.start_count > 0 
                ? (entry.end_count / entry.start_count) * 100 
                : 0
        }

        const formatProbability = (value) => {
            return parseFloat(value).toFixed(2)
        }

        const isDefaultState = (state) => {
            return state === 'Stage 3' || state === 'Write-off'
        }

        const saveEntries = () => {
            if (props.matrix?.id) {
                useForm({
                    entries: entries.value.map(entry => ({
                        portfolio_group: entry.portfolio_group,
                        start_state: entry.start_state,
                        end_state: entry.end_state,
                        start_balance: parseFloat(entry.start_balance) || 0,
                        start_count: parseInt(entry.start_count) || 0,
                        end_balance: parseFloat(entry.end_balance) || 0,
                        end_count: parseInt(entry.end_count) || 0
                    }))
                }).put(route('transition-matrices.entries.update', props.matrix.id), {
                    preserveScroll: true,
                    onSuccess: () => {
                        hasChanges.value = false
                    },
                    onError: (errors) => {
                        console.error('Failed to save entries:', errors)
                    }
                })
            }
        }

        // Initialize entries if matrix has existing entries
        if (props.matrix?.entries) {
            entries.value = props.matrix.entries
        }

        return {
            entries,
            sortedEntries,
            hasChanges,
            aggregationCriteria,
            addNewRow,
            removeRow,
            updateEntry,
            calculateProbability,
            formatProbability,
            isDefaultState,
            saveEntries
        }
    }
}
</script>
