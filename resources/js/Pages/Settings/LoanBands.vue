<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Loan Bands
            </h2>
        </template>

        <teleport to="head">
            <title>{{ pageTitle }}</title>
            <meta property="og:description" :content="pageDescription">
        </teleport>

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
            <div v-if="viewMode === 'table'">
                <div class="flex justify-end mb-4">
                    <button @click="switchToSetup" class="btn btn-primary flex items-center space-x-2">
                        <i class="fas fa-edit"></i>
                        <span>Setup New Bands</span>
                    </button>
                </div>
                <table class="min-w-full bg-white shadow-md rounded-lg overflow-hidden">
                    <thead class="bg-gray-50">
                        <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Band Name/Code</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Min Score
                                (%)
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Max Score
                                (%)
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <template v-if="loanApplicationBands.length">
                            <tr v-for="band in loanApplicationBands" :key="band.id">
                                <td class="px-6 py-4 whitespace-nowrap">{{ band.name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ band.min }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ band.max }}</td>
                            </tr>
                        </template>
                        <tr v-else>
                            <td colspan="3" class="px-6 py-4 whitespace-nowrap text-center">No loan bands available. Click setup bands to add some.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-else>
                <div class="flex justify-end mb-4">
                    <button @click="switchToTable" class="btn btn-primary flex items-center space-x-2">
                        <i class="fas fa-table"></i>
                        <span>View Bands</span>
                    </button>
                </div>
                <form @submit.prevent="saveBands" class="space-y-6">
                    <div v-for="(band, index) in bands" :key="index" class="mb-4 bg-white p-6 rounded-lg shadow-md">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Band {{ index + 1 }}
                        </label>
                        <div class="grid grid-cols-4 gap-4">
                            <input type="number" v-model="band.min" required placeholder="Min" class="form-input" @input="validateBands" />
                            <input type="number" v-model="band.max" required  placeholder="Max" class="form-input" @input="validateBands" />
                            <input type="text" v-model="band.name" required placeholder="Band Name" class="form-input" />
                            <button type="button" @click="removeBand(index)" class="text-red-600 font-bold">Remove</button>
                        </div>
                        <div v-if="errors[index]" class="text-red-600 mt-2">{{ errors[index] }}</div>
                    </div>
                    <div class="flex space-x-4">
                        <button type="button" @click="addBand" class="btn btn-primary flex items-center space-x-2">
                            <i class="fas fa-plus"></i>
                            <span>Add Band</span>
                        </button>
                        <button type="submit" class="btn btn-primary flex items-center space-x-2" :disabled="hasErrors">
                            <i class="fas fa-save"></i>
                            <span>Save Bands</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue';

export default {
    components: {
        AppLayout,
    },
    props: {
        flash: Object,
        loanApplicationBands: Array,
    },
    data() {
        return {
            bands: [
                { min: 0, max: 10, name: 'A' },
                { min: 11, max: 20, name: 'B' },
            ],
            errors: [],
            pageTitle: "Setup Loan Bands",
            pageDescription: "Manage Settings",
            viewMode: 'table', // Add view mode to switch between table and setup
        }
    },
    computed: {
        hasErrors() {
            return this.errors.some(error => error);
        }
    },
    methods: {
        switchToSetup() {
            this.viewMode = 'setup';
        },
        switchToTable() {
            this.viewMode = 'table';
        },
        addBand() {
            this.bands.push({ min: 0, max: 0, name: '' });
            this.errors.push('');
        },
        removeBand(index) {
            this.bands.splice(index, 1);
            this.errors.splice(index, 1);
        },
        validateBands() {
            this.errors = this.bands.map((band, index) => {
                if (band.min < 0 || band.max > 100) {
                    return "Values must be between 0 and 100";
                }
                if (band.min >= band.max) {
                    return "Min value must be less than Max value";
                }
                for (let i = 0; i < this.bands.length; i++) {
                    if (i !== index && this.bandsOverlap(band, this.bands[i])) {
                        return "Bands must not overlap";
                    }
                }
                return '';
            });
        },
        bandsOverlap(band1, band2) {
            return band1.min <= band2.max && band1.max >= band2.min;
        },
        saveBands() {
            this.validateBands();
            if (this.hasErrors) return;

            this.$inertia.post(this.route('settings.other.store-bands'), this.bands, {
                preserveState: false,
                onSuccess: () => {
                    alert('Bands saved successfully!');
                    this.switchToTable();
                },
                onError: (errors) => {
                    console.error(errors);
                }
            });
        }
    },
    watch: {
        bands: {
            handler: 'validateBands',
            deep: true
        }
    }
}
</script>

<style scoped>
@import url('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css');

.form-input {
    border: 1px solid #ddd;
    padding: 0.5rem;
    border-radius: 0.25rem;
    width: 100%;
}
.btn {
    background-color: #3490dc;
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 0.25rem;
    cursor: pointer;
    transition: background-color 0.3s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.btn-primary {
    background-color: #1d72b8;
}
.btn-primary:disabled {
    background-color: #6c757d;
    cursor: not-allowed;
}
</style>
