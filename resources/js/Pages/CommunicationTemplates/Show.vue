<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                <inertia-link class="text-indigo-400 hover:text-indigo-600"
                              :href="route('forms.index')">
                    Forms
                </inertia-link>
                <span class="text-indigo-400 font-medium">/</span> Form #{{ form.id }}
            </h2>
        </template>

        <div class="py-12">
            <div class=" mx-auto">
                <div class="grid grid-cols-12 gap-6">
                    <div class="col-span-12 lg:col-span-4 xxl:col-span-3 flex lg:block">
                        <div class="intro-y bg-white mt-5 lg:mt-0">
                            <div class="relative flex items-center p-5">
                                <div class="ml-4 mr-auto">
                                    <div class="font-medium text-base">{{ form.name }}</div>
                                </div>
                                <jet-dropdown align="right" width="60">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button type="button"
                                                    class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:bg-gray-50 hover:text-gray-700 focus:outline-none focus:bg-gray-50 active:bg-gray-50 transition ease-in-out duration-150">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                     viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                     stroke-width="1.5"
                                                     stroke-linecap="round" stroke-linejoin="round"
                                                     class="feather feather-more-horizontal w-5 h-5 text-gray-600 dark:text-gray-300">
                                            <circle cx="12" cy="12" r="1"></circle>
                                            <circle cx="19" cy="12" r="1"></circle>
                                            <circle cx="5" cy="12" r="1"></circle>
                                        </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <div class="w-60 shadow-xl bg-white rounded">
                                            <jet-dropdown-link
                                                :href="route('forms.edit',form.id)">
                                                <font-awesome-icon icon="edit"/>
                                                Edit
                                            </jet-dropdown-link>
                                            <a href="#"
                                               class="block px-4 py-2 text-sm leading-5 text-gray-700 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 transition duration-150 ease-in-out"
                                               @click="deleteAction(form.id)">
                                                <font-awesome-icon icon="trash"
                                                                   class="text-red-600 hover:text-red-900"/>
                                                Delete
                                            </a>
                                        </div>
                                    </template>
                                </jet-dropdown>
                            </div>
                            <div class="p-5 border-t border-gray-200 dark:border-dark-5">
                                <div class="flex justify-between">
                                    <span class="font-medium">Procedure</span>
                                    <span v-if="form.tariff">
                                        {{ form.tariff.name }}
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="font-medium">Type</span>
                                    <span>
                                        {{ form.type }}
                                    </span>
                                </div>
                            </div>

                        </div>
                        <div class="bg-white p-5 mt-5">
                            <div class="flex items-center">
                                <div class="font-medium text-lg">Description</div>
                            </div>

                            <div class="mt-4">{{ form.description }}</div>
                        </div>
                    </div>
                    <div class="col-span-12 lg:col-span-8 xxl:col-span-9">
                        <div class="bg-white p-5">
                            <div class="flex justify-between mt-4">
                                <h3>Fields</h3>
                                <button class="btn btn-blue" @click="showCreateFieldModal=true">
                                    <span>Create </span>
                                    <span class="hidden md:inline">Field</span>
                                </button>
                            </div>
                            <table class="w-full whitespace-no-wrap mt-4">
                                <thead class="bg-gray-50">
                                <tr class="text-left font-bold">
                                    <th class="px-6 pt-4 pb-4 font-medium text-gray-500">ID</th>
                                    <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Name</th>
                                    <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Type</th>
                                    <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Required</th>
                                    <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Active</th>
                                    <th></th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr v-for="field in form.fields" :key="field.id"
                                    class="hover:bg-gray-100 focus-within:bg-gray-100">
                                    <td class="border-t">
                                      <span class="px-6 py-4 flex items-center">
                                          {{ field.id }}
                                      </span>
                                    </td>
                                    <td class="border-t">
                                        <span class="px-6 py-4 flex items-center">
                                            {{ field.name }}
                                        </span>
                                    </td>
                                    <td class="border-t">
                                        <span class="px-6 py-4 flex items-center">
                                            {{ field.type }}
                                        </span>
                                    </td>
                                    <td class="border-t">
                                       <span class="px-6 py-4 flex items-center" v-if="field.required">
                                            Yes
                                        </span>
                                        <span class="px-6 py-4 flex items-center" v-else>
                                            No
                                        </span>
                                    </td>
                                    <td class="border-t">
                                       <span class="px-6 py-4 flex items-center" v-if="field.active">
                                            Yes
                                        </span>
                                        <span class="px-6 py-4 flex items-center" v-else>
                                            No
                                        </span>
                                    </td>
                                    <td class="border-t">
                                   <span class="px-6 py-4 flex items-center">
                                        {{ field.date }}
                                    </span>
                                    </td>
                                    <td class="border-t w-px pr-2">
                                        <div class=" flex items-center space-x-2">
                                            <a href="#" v-if="can('forms.update')"
                                               @click="editFieldAction(field.id)"
                                               tabindex="-1" class="text-indigo-600 hover:text-indigo-900">
                                                Edit
                                            </a>
                                            <a href="#" v-if="can('forms.update')"
                                               @click="deleteFieldAction(field.id)"
                                               class="text-red-600 hover:text-red-900">Delete</a>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="form.fields.length === 0">
                                    <td class="border-t px-6 py-4" colspan="8">No fields found.</td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <jet-confirmation-modal :show="confirmingDeletion" @close="confirmingDeletion = false">
            <template #title>
                Delete Record
            </template>

            <template #content>
                Are you sure you want to delete record?
            </template>

            <template #footer>
                <jet-secondary-button @click.native="confirmingDeletion = false">
                    Nevermind
                </jet-secondary-button>

                <jet-danger-button class="ml-2" @click.native="destroy" :class="{ 'opacity-25': processing }"
                                   :disabled="processing">
                    Delete Record
                </jet-danger-button>
            </template>
        </jet-confirmation-modal>
        <jet-confirmation-modal :show="confirmingFieldDeletion" @close="confirmingFieldDeletion = false">
            <template #title>
                Delete Record
            </template>

            <template #content>
                Are you sure you want to delete record?
            </template>

            <template #footer>
                <jet-secondary-button @click.native="confirmingFieldDeletion = false">
                    Nevermind
                </jet-secondary-button>

                <jet-danger-button class="ml-2" @click.native="destroyField" :class="{ 'opacity-25': processing }"
                                   :disabled="processing">
                    Delete Record
                </jet-danger-button>
            </template>
        </jet-confirmation-modal>
        <jet-dialog-modal :show="showCreateFieldModal" @close="showCreateFieldModal = false">
            <template #title>
                Create Field
            </template>
            <template #content>
                <div class="grid grid-cols-1 gap-2 mt-4">
                    <div class="">
                        <jet-label for="name" value="Name"/>
                        <jet-input id="name" type="text" class="mt-1 block w-full" v-model="field.name"
                                   required/>
                        <jet-input-error :message="field.errors.name" class="mt-2" required/>
                    </div>
                    <div>
                        <jet-label for="type" value="Type"/>
                        <select
                            class="border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm w-full"
                            name="type" v-model="field.type" id="type" required>
                            <option value="text">Text</option>
                            <option value="number">Number</option>
                            <option value="dropdown">Dropdown</option>
                            <option value="textarea">Textarea</option>
                            <option value="file">File</option>
                            <option value="checkbox">Checkbox</option>
                            <option value="checkboxes">Checkboxes</option>
                            <option value="radio">Radio</option>
                        </select>
                        <jet-input-error :message="field.errors.type" class="mt-2"/>
                    </div>
                    <div v-if="field.type==='dropdown'|| field.type==='radio' || field.type==='checkboxes'"
                         class="grid grid-cols-1 gap-2 bg-gray-200 p-4">
                        <jet-secondary-button @click.native="addOption(field.id)">
                            Add Option
                        </jet-secondary-button>
                        <div v-for="(item,index) in field.options" class="grid grid-cols-2 gap-2">
                            <div class="">
                                <jet-label :for="'edit_option_'+index" :value="'Option '+(index+1)"/>
                                <jet-input :id="'edit_option_'+index" type="text" class="mt-1 block w-full"
                                           v-model="field.options[index]"
                                           required/>
                                <jet-input-error :message="field.errors.options" class="mt-2"/>
                            </div>
                            <div>
                                <font-awesome-icon icon="trash" v-on:click="field.options.splice(index,1)"
                                                   class="w-4 h-4 mr-2 mt-8"></font-awesome-icon>
                            </div>
                        </div>
                    </div>
                    <div class="">
                        <jet-label for="field_position" value="Position"/>
                        <jet-input id="field_position" type="text" class="mt-1 block w-full"
                                   v-model="field.field_position"
                                   required/>
                        <jet-input-error :message="field.errors.field_position" class="mt-2"/>
                    </div>
                    <div class="">
                        <jet-label for="default_values" value="Default Value"/>
                        <jet-input id="default_values" type="text" class="mt-1 block w-full"
                                   v-model="field.default_values"
                                   required/>
                        <jet-input-error :message="field.errors.default_values" class="mt-2"/>
                    </div>
                    <div class="">
                        <jet-label for="unit" value="Unit"/>
                        <jet-input id="unit" type="text" class="mt-1 block w-full" v-model="field.unit"
                                   required/>
                        <jet-input-error :message="field.errors.unit" class="mt-2"/>
                    </div>
                    <div class="">
                        <jet-label for="normal_range" value="Normal Range"/>
                        <jet-input id="normal_range" type="text" class="mt-1 block w-full" v-model="field.normal_range"
                                   required/>
                        <jet-input-error :message="field.errors.normal_range" class="mt-2"/>
                    </div>
                    <div>
                        <jet-label for="required">
                            <div class="flex items-center">
                                <jet-checkbox name="required" id="required"
                                              v-model:checked="field.required"/>
                                <div class="ml-2">
                                    Required
                                </div>
                            </div>
                        </jet-label>
                    </div>
                    <div>
                        <jet-label for="active">
                            <div class="flex items-center">
                                <jet-checkbox name="active" id="active"
                                              v-model:checked="field.active"/>
                                <div class="ml-2">
                                    Active
                                </div>
                            </div>
                        </jet-label>
                    </div>
                    <div>
                        <jet-label for="description" value="Description"/>
                        <textarea-input id="description" class="mt-1 block w-full"
                                        v-model="field.description"/>
                        <jet-input-error :message="field.errors.description" class="mt-2"/>

                    </div>
                </div>
            </template>

            <template #footer>
                <jet-secondary-button @click.native="showCreateFieldModal = false">
                    Cancel
                </jet-secondary-button>

                <jet-secondary-button class="ml-2" @click.native="createField"
                                      :class="{ 'opacity-25': processing }"
                                      :disabled="processing">
                    Create
                </jet-secondary-button>
            </template>
        </jet-dialog-modal>
        <jet-dialog-modal :show="showEditFieldModal" @close="showEditFieldModal = false">
            <template #title>
                Edit Field
            </template>
            <template #content>
                <div class="grid grid-cols-1 gap-2 mt-4">
                    <div class="">
                        <jet-label for="edit_name" value="Name"/>
                        <jet-input id="edit_name" type="text" class="mt-1 block w-full" v-model="field.name"
                                   required/>
                        <jet-input-error :message="field.errors.name" class="mt-2" required/>
                    </div>
                    <div>
                        <jet-label for="edit_type" value="Type"/>
                        <select
                            class="border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm w-full"
                            name="type" v-model="field.type" id="edit_type" required>
                            <option value="text">Text</option>
                            <option value="number">Number</option>
                            <option value="dropdown">Dropdown</option>
                            <option value="textarea">Textarea</option>
                            <option value="file">File</option>
                            <option value="checkbox">Checkbox</option>
                            <option value="checkboxes">Checkboxes</option>
                            <option value="radio">Radio</option>
                        </select>
                        <jet-input-error :message="field.errors.type" class="mt-2"/>
                    </div>
                    <div v-if="field.type==='dropdown'|| field.type==='radio' || field.type==='checkboxes'"
                         class="grid grid-cols-1 gap-2 bg-gray-200 p-4">
                        <jet-secondary-button @click.native="addOption(field.id)">
                            Add Option
                        </jet-secondary-button>
                        <div v-for="(item,index) in field.options" class="grid grid-cols-2 gap-2">
                            <div class="">
                                <jet-label :for="'edit_option_'+index" :value="'Option '+(index+1)"/>
                                <jet-input :id="'edit_option_'+index" type="text" class="mt-1 block w-full"
                                           v-model="field.options[index]"
                                           required/>
                                <jet-input-error :message="field.errors.options" class="mt-2"/>
                            </div>
                            <div>
                                <font-awesome-icon icon="trash" v-on:click="field.options.splice(index,1)"
                                                   class="w-4 h-4 mr-2 mt-8"></font-awesome-icon>
                            </div>
                        </div>
                    </div>
                    <div class="">
                        <jet-label for="edit_field_position" value="Position"/>
                        <jet-input id="edit_field_position" type="text" class="mt-1 block w-full"
                                   v-model="field.field_position"
                                   required/>
                        <jet-input-error :message="field.errors.field_position" class="mt-2"/>
                    </div>
                    <div class="">
                        <jet-label for="edit_default_values" value="Default Value"/>
                        <jet-input id="edit_default_values" type="text" class="mt-1 block w-full"
                                   v-model="field.default_values"
                                   required/>
                        <jet-input-error :message="field.errors.default_values" class="mt-2"/>
                    </div>
                    <div class="">
                        <jet-label for="edit_unit" value="Unit"/>
                        <jet-input id="edit_unit" type="text" class="mt-1 block w-full" v-model="field.unit"
                                   required/>
                        <jet-input-error :message="field.errors.unit" class="mt-2"/>
                    </div>
                    <div class="">
                        <jet-label for="edit_normal_range" value="Normal Range"/>
                        <jet-input id="edit_normal_range" type="text" class="mt-1 block w-full"
                                   v-model="field.normal_range"
                                   required/>
                        <jet-input-error :message="field.errors.normal_range" class="mt-2"/>
                    </div>
                    <div>
                        <jet-label for="edit_required">
                            <div class="flex items-center">
                                <jet-checkbox name="required" id="edit_required"
                                              v-model:checked="field.required"/>
                                <div class="ml-2">
                                    Required
                                </div>
                            </div>
                        </jet-label>
                    </div>
                    <div>
                        <jet-label for="edit_active">
                            <div class="flex items-center">
                                <jet-checkbox name="active" id="edit_active"
                                              v-model:checked="field.active"/>
                                <div class="ml-2">
                                    Active
                                </div>
                            </div>
                        </jet-label>
                    </div>
                    <div>
                        <jet-label for="edit_description" value="Description"/>
                        <textarea-input id="edit_description" class="mt-1 block w-full"
                                        v-model="field.description"/>
                        <jet-input-error :message="field.errors.description" class="mt-2"/>

                    </div>
                </div>
            </template>

            <template #footer>
                <jet-secondary-button @click.native="showEditFieldModal = false">
                    Cancel
                </jet-secondary-button>

                <jet-secondary-button class="ml-2" @click.native="editField"
                                      :class="{ 'opacity-25': processing }"
                                      :disabled="processing">
                    Save
                </jet-secondary-button>
            </template>
        </jet-dialog-modal>
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
import Select from "@/Jetstream/Select.vue";
import FileInput from "@/Jetstream/FileInput.vue";
import TextareaInput from "@/Jetstream/TextareaInput.vue";
import JetDropdown from '@/Jetstream/Dropdown.vue'
import JetDropdownLink from '@/Jetstream/DropdownLink.vue'
import JetDialogModal from '@/Jetstream/DialogModal.vue'
import JetSecondaryButton from '@/Jetstream/SecondaryButton.vue'
import JetConfirmationModal from '@/Jetstream/ConfirmationModal.vue'
import JetDangerButton from '@/Jetstream/DangerButton.vue'
import print from 'print-js'
import Button from "@/Jetstream/Button.vue";

export default {
    props: {
        form: Object,
    },
    components: {
        Button,
        Select,
        AppLayout,
        JetButton,
        JetInput,
        JetCheckbox,
        JetLabel,
        JetInputError,
        FileInput,
        TextareaInput,
        JetDropdown,
        JetDropdownLink,
        JetDialogModal,
        JetSecondaryButton,
        JetConfirmationModal,
        JetDangerButton,

    },
    data() {
        return {
            field: this.$inertia.form({
                id: null,
                name: null,
                type: 'text',
                options: [],
                normal_range: null,
                unit: null,
                field_position: null,
                rules: [],
                classes: null,
                default_values: null,
                description: null,
                active: true,
                required: false,
            }),
            confirmingDeletion: false,
            confirmingFieldDeletion: false,
            showCreateFieldModal: false,
            showEditFieldModal: false,
            selectedRecord: null,
            selectedFieldRecord: null,
            processing: false,
            pageTitle: "Forms",
            pageDescription: "Forms",
        }

    },
    mounted() {

    },
    methods: {

        deleteAction(id) {
            this.confirmingDeletion = true
            this.selectedRecord = id
        },
        destroy() {
            this.$inertia.delete(this.route('forms.destroy', this.form.id))
            this.confirmingDeletion = false
            window.location = route('forms.index')
        },
        deleteFieldAction(id) {
            this.confirmingFieldDeletion = true
            this.selectedFieldRecord = id
        },
        destroyField() {
            this.$inertia.delete(this.route('forms.fields.destroy', this.selectedFieldRecord))
            this.confirmingFieldDeletion = false
            this.$inertia.reload();
        },
        addOption(id = null) {
            if (!id) {
                this.field.options.push('')
            } else {
                this.form.fields.forEach(item => {
                    if (item.id === id) {
                        item.options.push('')
                    }
                })
            }
        },
        createField() {
            this.field.post(this.route('forms.fields.store', this.form.id))
            this.showCreateFieldModal = false
            this.$inertia.reload();
        },
        editFieldAction(id) {
            this.showEditFieldModal = true
            this.selectedFieldRecord = id
            this.form.fields.forEach(item => {
                if (item.id === id) {
                    this.field.id = item.id;
                    this.field.name = item.name;
                    this.field.type = item.type;
                    this.field.options = item.options;
                    this.field.normal_range = item.normal_range;
                    this.field.unit = item.unit;
                    this.field.field_position = item.field_position;
                    this.field.classes = item.classes;
                    this.field.rules = item.rules;
                    this.field.default_values = item.default_values;
                    this.field.description = item.description;
                    this.field.active = item.active;
                    this.field.required = item.required;
                }
            })
        },
        editField() {
            this.field.put(this.route('forms.fields.update', this.selectedFieldRecord))
            this.showEditFieldModal = false
            this.$inertia.reload();
        },
    },
    watch: {
        showEditFieldModal: function (val) {
            if (!val) {
                this.field.name = null;
                this.field.type = 'text'
                this.field.options = [];
                this.field.normal_range = null;
                this.field.unit = null;
                this.field.field_position = null;
                this.field.classes = null;
                this.field.rules = [];
                this.field.default_values = null;
                this.field.description = null;
                this.field.active = true;
                this.field.required = false;
            }
        }
    }
}
</script>
<style scoped>

</style>
