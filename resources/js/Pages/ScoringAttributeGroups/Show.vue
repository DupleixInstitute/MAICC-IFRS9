<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                <inertia-link class="text-indigo-400 hover:text-indigo-600" :href="route('scoring_attributes.index')">
                    Transition Profile Column Groups
                </inertia-link>
                <span class="text-indigo-400 font-medium">/</span> {{ attribute.name }}
            </h2>
        </template>
        <div class=" mx-auto">
            <div class="md:flex md:items-start">
                <div class="relative mb-4  w-full md:w-3/12">
                    <div class="intro-y bg-white mt-5 lg:mt-0">
                        <div class="relative flex items-center p-5">
                            <div class="ml-4 mr-auto">
                                <div class="font-medium text-base">{{ attribute.name }}</div>
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
                                        <jet-dropdown-link v-if="can('loans.scoring_attributes.update')"
                                                           :href="route('scoring_attributes.edit',attribute.id)">
                                            <font-awesome-icon icon="edit"/>
                                            Edit
                                        </jet-dropdown-link>

                                        <a href="#"
                                           class="block px-4 py-2 text-sm leading-5 text-gray-700 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 transition duration-150 ease-in-out"
                                           @click="confirmingDeletion=true"
                                           v-if="can('loans.scoring_attributes.destroy')">
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
                                <span class="font-medium">Active</span>
                                <span v-if="attribute.active" class="text-green-400">Yes</span>
                                <span v-if="!attribute.active" class="text-green-400">No</span>
                            </div>

                        </div>
                    </div>
                    <div class="bg-white p-5 mt-5">
                        <div class="flex items-center">
                            <div class="font-medium text-lg">Description</div>
                        </div>

                        <div class="mt-4">{{ attribute.description }}</div>
                    </div>
                </div>
                <div class="w-full md:w-9/12  md:ml-4">
                    <div class="bg-white p-5">
                        <div class="flex justify-between mt-4">
                            <h3>Scoring Attributes</h3>
                            <button class="btn btn-blue" @click="showItemModal =true">
                                <span>Create </span>
                                <span class="hidden md:inline">Attribute</span>
                            </button>
                        </div>
                        <div class=" overflow-x-auto">
                            <table class="w-full whitespace-no-wrap mt-4">
                                <thead class="bg-gray-50">
                                <tr class="text-left font-bold">
                                    <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Name</th>
                                    <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Type</th>
                                    <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Active</th>
                                    <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Required</th>
                                    <th>Action</th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr v-for="item in attribute.scoring_attributes"
                                    :key="item.id"
                                    class="hover:bg-gray-100 focus-within:bg-gray-100">
                                    <td class="border-t">
                                      <span class="px-6 py-4 flex items-center">
                                          {{ item.name }}
                                      </span>
                                    </td>
                                    <td class="border-t">
                                        <span class="px-6 py-4 flex items-center">
                                            <span v-if="item.field_type=='number'">
                                                Number
                                            </span>
                                             <span v-if="item.field_type=='dropdown'">
                                                Dropdown
                                            </span>
                                            <span v-if="item.field_type==='formula'">
                                                Readonly(Formula)
                                            </span>
                                            <span v-if="item.field_type=='text'">
                                                Text
                                            </span>
                                            <span v-if="item.field_type=='textarea'">
                                                Textarea
                                            </span>
                                            <span v-if="item.field_type=='radio'">
                                                Radio
                                            </span>
                                            <span v-if="item.field_type=='dropdown_sql'">
                                                Dropdown SQL
                                            </span>
                                            <span v-if="item.field_type=='checkbox'">
                                                Checkbox
                                            </span>
                                            <span v-if="item.field_type=='information'">
                                                Information
                                            </span>
                                        </span>
                                    </td>
                                    <td class="border-t">
                                       <span class="px-6 py-4 flex items-center" v-if="item.required">
                                            Yes
                                        </span>
                                        <span class="px-6 py-4 flex items-center" v-else>
                                            No
                                        </span>
                                    </td>
                                    <td class="border-t">
                                       <span class="px-6 py-4 flex items-center" v-if="item.active">
                                            Yes
                                        </span>
                                        <span class="px-6 py-4 flex items-center" v-else>
                                            No
                                        </span>
                                    </td>
                                    <td class="border-t w-px pr-2">
                                        <div class=" flex items-center space-x-2">
                                            <button v-if="can('loans.scoring_attributes.update')"
                                                    @click="editItem( item.id)"
                                                    tabindex="-1" class="text-indigo-600 hover:text-indigo-900">
                                                Edit
                                            </button>
                                            <a href="#" v-if="can('loans.scoring_attributes.destroy')"
                                               @click="deleteItemAction(item.id)"
                                               class="text-red-600 hover:text-red-900">Delete</a>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="attribute.scoring_attributes.length === 0">
                                    <td class="border-t px-6 py-4 text-center" colspan="5">No records found.</td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <jet-dialog-modal :show="showItemModal" @close="showItemModal = false">
            <template #title>
                <span v-if="item.id">Edit Attribute</span>
                <span v-else>Add Attribute</span>
            </template>
            <template #content>
                <div class="grid grid-cols-1 gap-2 ">
                    <div>
                        <jet-label for="name" value="Name"/>
                        <jet-input type="text" id="name" class="block w-full"
                                   v-model="item.name" required/>
                        <jet-input-error :message="$page.props.errors.name"
                                         class="mt-2"/>
                    </div>
                    <div>
                        <jet-label for="field_type" value="Field Type"/>
                        <select
                            class="border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm w-full"
                            name="field_type" v-model="item.field_type" id="field_type" required>
                            <option value="number">Number</option>
                            <option value="formula">Formula</option>
                            <option value="dropdown">Dropdown</option>
                            <option value="text">Text</option>
                            <option value="textarea">Textarea</option>
                            <option value="radio">Radio</option>
                            <option value="dropdown_sql">Dropdown SQL</option>
                            <option value="checkbox">Checkbox</option>
                            <option value="information">Information</option>
                        </select>
                        <jet-input-error :message="$page.props.errors.field_type" class="mt-2"/>
                    </div>
                    <div v-if="item.field_type==='dropdown'|| item.field_type==='radio' || item.field_type==='checkbox'"
                         class="grid grid-cols-1 gap-2 bg-gray-200 p-4">
                        <jet-success-button @click.native="addOption(item.id)">
                            Add Option
                        </jet-success-button>
                        <div v-for="(option,index) in item.options" class="grid grid-cols-2 gap-2">
                            <div class="">
                                <jet-label :for="'edit_option_'+index" :value="'Option '+(index+1)"/>
                                <jet-input :id="'edit_option_'+index" type="text" class="mt-1 block w-full"
                                           v-model="item.options[index]"
                                           required/>
                                <jet-input-error :message="$page.props.errors.options" class="mt-2"/>
                            </div>
                            <div>
                                <font-awesome-icon icon="trash" v-on:click="item.options.splice(index,1)"
                                                   class="w-4 h-4 mr-2 mt-8 text-red-600"></font-awesome-icon>
                            </div>
                        </div>
                    </div>
                    <div class="">
                        <jet-label for="default_values" value="Default Value"/>
                        <jet-input id="default_values" type="text" class="mt-1 block w-full"
                                   v-model="item.default_values"
                                   required/>
                        <jet-input-error :message="$page.props.errors.default_values" class="mt-2"/>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                        <div>
                            <jet-label for="required">
                                <div class="flex items-center">
                                    <jet-checkbox name="required" id="required"
                                                  v-model:checked="item.required"/>
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
                                                  v-model:checked="item.active"/>
                                    <div class="ml-2">
                                        Active
                                    </div>
                                </div>
                            </jet-label>
                        </div>
                    </div>
                    <div>
                        <jet-label for="description" value="Description"/>
                        <textarea-input id="description" class="mt-1 block w-full"
                                        v-model="item.description"/>
                        <jet-input-error :message="$page.props.errors.description" class="mt-2"/>
                    </div>
                </div>
            </template>
            <template #footer>
                <jet-secondary-button @click.native="showItemModal = false">
                    Cancel
                </jet-secondary-button>
                <jet-success-button class="ml-2" @click.native="storeItem"
                                    :class="{ 'opacity-25': processing }"
                                    :disabled="processing">
                    Save
                </jet-success-button>
            </template>
        </jet-dialog-modal>

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
        <jet-confirmation-modal :show="confirmItemDeletion" @close="confirmItemDeletion = false">
            <template #title>
                Delete Record
            </template>

            <template #content>
                Are you sure you want to delete record?
            </template>

            <template #footer>
                <jet-secondary-button @click.native="confirmItemDeletion = false">
                    Nevermind
                </jet-secondary-button>

                <jet-danger-button class="ml-2" @click.native="destroyItem"
                                   :class="{ 'opacity-25': processing }"
                                   :disabled="processing">
                    Delete Record
                </jet-danger-button>
            </template>
        </jet-confirmation-modal>

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
import JetSuccessButton from '@/Jetstream/SuccessButton.vue'
import JetConfirmationModal from '@/Jetstream/ConfirmationModal.vue'
import JetDangerButton from '@/Jetstream/DangerButton.vue'
import Button from "../../Jetstream/Button.vue";

export default {
    props: {
        attribute: Object,
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
        JetSuccessButton,
        JetConfirmationModal,
        JetDangerButton,

    },
    data() {
        return {
            confirmClaim: false,
            confirmItemDeletion: false,
            editingItem: false,
            showItemModal: false,
            item: {
                id: '',
                scoring_attribute_group_id: this.attribute.id,
                name: '',
                field_type: '',
                description: '',
                condition: '',
                options: [],
                default_values: '',
                rules: '',
                class: '',
                required: true,
                active: true,
            },
            confirmingDeletion: false,
            selectedPaymentRecord: null,
            selectedRecord: null,
            processing: false,
            pageTitle: "Scoring Attribute Group",
            pageDescription: "Scoring Attribute Group",
        }

    },
    mounted() {

    },
    methods: {
        addOption(id = null) {
            this.item.options.push('')

        },
        deleteAction(id) {
            this.confirmingDeletion = true
            this.selectedRecord = id
        },
        destroy() {
            this.$inertia.delete(this.route('scoring_attributes.destroy', this.attribute.id), {
                preserveState: false,
                onSuccess: () => {
                    this.$inertia.visit(this.route('scoring_attributes.index'))
                }
            })
            this.confirmingDeletion = false
            window.location = route('scoring_attributes.index')
        },
        editItem(id) {
            this.editingItem = true
            this.attribute.scoring_attributes.forEach(item => {
                if (item.id == id) {
                    this.item = item
                    this.showItemModal = true
                }
            })
        },
        deleteItemAction(id) {
            this.confirmItemDeletion = true
            this.selectedRecord = id
        },
        destroyItem() {
            this.$inertia.delete(this.route('scoring_attributes.items.destroy', this.selectedRecord))
            this.confirmItemDeletion = false
        },
        storeItem() {
            this.processing = true
            this.$inertia.post(this.route('scoring_attributes.items.store', this.attribute.id), this.item, {
                onSuccess: () => {
                    this.$inertia.reload()
                    this.showItemModal = false
                    this.item = {
                        id: '',
                        scoring_attribute_group_id: this.attribute.id,
                        name: '',
                        field_type: '',
                        description: '',
                        condition: '',
                        options: [],
                        default_values: '',
                        rules: '',
                        class: '',
                        required: true,
                        active: true,
                    }
                }
            })
            this.processing = false
        },

    },
    watch: {
        'item.field_type': function (val) {
            if (val === 'dropdown' || val === 'radio' || val === 'checkbox') {
                if (!this.item.options.length) {
                    this.item.options = []
                }
            } else {
                this.item.options = ''
            }
        },
        showItemModal: function (val) {
            if (!val) {
                this.item = {
                    id: '',
                    scoring_attribute_group_id: this.attribute.id,
                    name: '',
                    field_type: '',
                    description: '',
                    condition: '',
                    options: [],
                    default_values: '',
                    rules: '',
                    class: '',
                    required: true,
                    active: true,
                }
            }
        }
    }
}
</script>
<style scoped>

</style>
