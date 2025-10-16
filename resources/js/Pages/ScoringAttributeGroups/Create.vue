<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                <inertia-link class="text-indigo-400 hover:text-indigo-600" :href="route('scoring_attributes.index')">
                  Transition Profile Column
                </inertia-link>
                <span class="text-indigo-400 font-medium">/</span> Create
            </h2>
        </template>

        <div class=" mx-auto">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-4">
                <form @submit.prevent="submit" enctype="multipart/form-data">
                    <div class="grid grid-cols-1 gap-2">
                        <div class="mt-4">
                            <jet-label for="name" value="Transition Grade Column Name"/>
                            <jet-input id="name" type="text" class="mt-1 block w-full" v-model="form.name"
                                       placeholder="eg Stage, Arrear Days, Scores" required autofocus/>
                            <jet-input-error :message="form.errors.name" class="mt-2"/>
                        </div>
                        <div class="mt-4">
                            <jet-label for="description" value="Transition Grade Description"/>
                            <textarea-input id="description" class="mt-1 block w-full"
                                            v-model="form.description" placeholder="IFRS 9 stage assessment"/>
                            <jet-input-error :message="form.errors.description" class="mt-2"/>
                        </div>

                        <div class="mt-4">
                            <jet-label for="field_type" value="Field Type"/>
                            <select-input id="field_type" class="mt-1 block w-full"
                                        v-model="form.field_type">
                                <option value="number">Number</option>
                                <option value="range">Range</option>
                                <option value="text">Text</option>
                            </select-input>
                            <jet-input-error :message="form.errors.field_type" class="mt-2"/>
                        </div>

                        <div class="mt-4">
                            <jet-label for="db_table_name" value="Mapped Database Table Name"/>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <jet-input
                                    type="text"
                                    class="mt-1 block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm mb-2"
                                    v-model="tableSearch"
                                    placeholder="Search tables..."
                                />
                                <div v-if="tableSearch" 
                                     class="absolute inset-y-0 right-0 pr-3 flex items-center cursor-pointer"
                                     @click="tableSearch = ''">
                                    <svg class="h-5 w-5 text-gray-400 hover:text-gray-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                            </div>
                            <select-input 
                                id="db_table_name" 
                                class="mt-1 block w-full"
                                v-model="form.db_table_name"
                                @change="loadTableColumns">
                                <option value="">Select a table</option>
                                <option v-for="table in filteredTables" :key="table" :value="table">
                                    {{ table }}
                                </option>
                            </select-input>
                            <jet-input-error :message="form.errors.db_table_name" class="mt-2"/>
                        </div>

                        <div class="mt-4">
                            <jet-label for="db_table_column_name" value="Mapped Database Column Name"/>
                            <select-input 
                                id="db_table_column_name" 
                                class="mt-1 block w-full"
                                v-model="form.db_table_column_name"
                                :disabled="!form.db_table_name">
                                <option value="">Select a column</option>
                                <option v-for="column in tableColumns" :key="column" :value="column">
                                    {{ column }}
                                </option>
                            </select-input>
                            <jet-input-error :message="form.errors.db_table_column_name" class="mt-2"/>
                        </div>

                        <div class="grid grid-cols-1 mt-4">
                            <jet-label for="is_default">
                                <div class="flex items-center">
                                    <jet-checkbox name="is_default" id="is_default" v-model:checked="form.is_default"/>
                                    <div class="ml-2">
                                        Default profile column
                                    </div>
                                </div>
                            </jet-label>
                        </div>
                    </div>
                    <div class="flex items-center justify-end mt-4">

                        <jet-button class="ml-4" :class="{ 'opacity-25': form.processing }"
                                    :disabled="form.processing">
                            Save
                        </jet-button>
                    </div>
                </form>
            </div>
        </div>
        <teleport to="head">
            <title>{{ pageTitle }}</title>
            <meta property="og:description" :content="pageDescription">
        </teleport>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import JetButton from "@/Jetstream/Button.vue";
import JetInput from "@/Jetstream/Input.vue";
import JetInputError from "@/Jetstream/InputError.vue";
import JetCheckbox from "@/Jetstream/Checkbox.vue";
import JetLabel from "@/Jetstream/Label.vue";
import SelectInput from "@/Jetstream/SelectInput.vue";
import FileInput from "@/Jetstream/FileInput.vue";
import TextareaInput from "@/Jetstream/TextareaInput.vue";
import axios from 'axios';

export default {
    props: {
        tables: {
            type: Array,
            required: true
        }
    },
    components: {
        SelectInput,
        AppLayout,
        JetButton,
        JetInput,
        JetCheckbox,
        JetLabel,
        JetInputError,
        FileInput,
        TextareaInput,

    },
    data() {
        return {
            form: this.$inertia.form({
                name: null,
                description: '',
                field_type: 'number',
                db_table_name: '',
                db_table_column_name: '',
                is_default: true,
            }),
            tableSearch: '',
            tableColumns: [],
            pageTitle: "Create Transition Profile Column",
            pageDescription: "Create a new transition profile column for scoring attributes",
        }

    },
    computed: {
        filteredTables() {
            if (!this.tableSearch) return this.tables;
            const search = this.tableSearch.toLowerCase();
            return this.tables.filter(table => 
                table.toLowerCase().includes(search)
            );
        }
    },
    methods: {
        async loadTableColumns() {
            if (!this.form.db_table_name) {
                this.tableColumns = [];
                this.form.db_table_column_name = '';
                return;
            }

            try {
                const response = await axios.get(route('scoring_attributes.table-columns'), {
                    params: { table: this.form.db_table_name }
                });
                this.tableColumns = response.data;
                this.form.db_table_column_name = '';
            } catch (error) {
                console.error('Failed to load table columns:', error);
                this.tableColumns = [];
            }
        },
        submit() {
            this.form.post(this.route('scoring_attributes.store'), {
                onSuccess: () => {
                    // Reset form after successful submission
                    this.form.reset();
                    this.tableColumns = [];
                }
            });
        },
    }
}
</script>
<style scoped>

</style>
