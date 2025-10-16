<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                <inertia-link class="text-indigo-400 hover:text-indigo-600" :href="route('loan_products.index')">Transition Profile
                </inertia-link>
                <span class="text-indigo-400 font-medium">/</span> {{ product.name }}
            </h2>
        </template>
        <div class=" mx-auto">
            <div class="md:flex md:items-start">
                <div class="relative mb-4  w-full md:w-3/12">
                    <div class="intro-y bg-white mt-5 lg:mt-0">
                        <div class="relative flex items-center p-5">
                            <div class="ml-4 mr-auto">
                                <div class="font-medium text-base">{{ product.name }}</div>
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
                                        <jet-dropdown-link v-if="can('loans.products.update')"
                                                           :href="route('loan_products.edit',product.id)">
                                            <font-awesome-icon icon="edit"/>
                                            Edit
                                        </jet-dropdown-link>

                                        <a href="#"
                                           class="block px-4 py-2 text-sm leading-5 text-gray-700 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 transition duration-150 ease-in-out"
                                           @click="confirmingDeletion=true"
                                           v-if="can('loans.products.destroy')">
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
                                <span v-if="product.active" class="text-green-400">Yes</span>
                                <span v-if="!product.active" class="text-green-400">No</span>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white p-5 mt-5">
                        <div class="flex items-center">
                            <div class="font-medium text-lg">Description</div>
                        </div>

                        <div class="mt-4">{{ product.description }}</div>
                    </div>
                </div>
                <div class="w-full md:w-9/12  md:ml-4">
                    <div class="flex justify-end mb-4">
                        <button class="btn btn-blue" @click="showAddGroupModal =true">
                            <span>Add Transition Column </span>
                        </button>
                    </div>
                    <div class="mb-4 relative" v-for="(attribute,parent_index) in form.attributes">
                        <div class="bg-gray-50 border p-4 flex justify-between">
                            <h4>{{ attribute.name }}</h4>
                            <div class="flex justify-end items-center gap-4">
                                <div>
                                    <span class="mr-2">Category Name</span>
                                    <jet-input type="text" class="w-16" v-model="attribute.weight" @blur="updateItems"/>
                                    <span class="ml-2"></span>
                                </div>
                                <button href="#" v-if="can('loans.products.destroy')"
                                        @click="deleteItem('group',parent_index)"
                                        class=" text-red-600 hover:text-red-900" title="Delete">
                                    <font-awesome-icon icon="trash"/>
                                </button>
                            </div>
                        </div>
                        <div class="bg-white border p-5">
                            <div class="flex justify-end mb-4">
                                <button class="btn btn-blue"
                                        @click="showAddAttributeModal =true;selectedGroup=attribute">
                                    <span>Configure Values </span>
                                </button>
                            </div>
                            <div class="bg-gray-50 p-4 mb-4 relative" v-for="(item,index) in attribute.attributes">
                                <div class="grid grid-cols-1 md:grid-cols-3">
                                    <div>{{ item.name }} <span v-if="item.id" title="Click to copy"
                                                               class="p-1 text-xs bg-blue-400 text-white cursor-pointer"
                                                               @click="copy('{{field_'+item.id+'}}')"> field_{{
                                            item.id
                                        }}</span>
                                    </div>
                                    <div>
                                        <span class="mr-2">Category Name</span>
                                        <jet-input type="text" class="w-16" v-model="item.weight" @blur="updateItems"/>
                                        <span class="ml-2"></span>
                                    </div>
                                </div>
                                <div
                                    v-if="item.attribute.field_type==='dropdown'||item.attribute.field_type==='radio'||item.attribute.field_type==='checkbox'">
                                    <!-- <h4>Option Weights</h4> -->
                                    <table class="w-full whitespace-no-wrap table-auto">
                                        <thead class="bg-gray-50">
                                        <tr class="text-left font-bold">
                                            <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Name</th>
                                            <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Category Name</th>
                                            <th class="px-6 pt-4 pb-4 font-medium text-gray-500">
                                                <button class="btn btn-green"
                                                        @click="addRangeOption(item)">
                                                    <span>Add Range </span>
                                                </button>
                                            </th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <tr v-for="(option,ky) in item.attribute.options"
                                            class="hover:bg-gray-100 focus-within:bg-gray-100">
                                            <td class="border-t px-6 py-4">
                                                <jet-input type="text" class="w-24" v-model="option.lower_value"
                                                           @blur="updateItems"/>
                                            </td>
                                            <td class="border-t px-6 py-4">
                                                <jet-input type="text" class="w-24" v-model="option.upper_value"
                                                           @blur="updateItems"/>
                                            </td>
                                            <td class="border-t px-6 py-4">
                                                <jet-input type="text" class="w-24" v-model="option.weight"
                                                           @blur="updateItems"/>
                                                <span class="ml-2"></span>
                                            </td>
                                            <td class="border-t px-6 py-4">
                                                <button href="#" v-if="can('loans.products.destroy')"
                                                        @click="deleteAttributeOption(item,ky)"
                                                        class="  text-red-600 hover:text-red-900"
                                                        title="Delete">
                                                    <font-awesome-icon icon="trash"/>
                                                </button>
                                            </td>
                                        </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div
                                    v-if="item.attribute.field_type==='number'||item.attribute.field_type==='text'||item.attribute.field_type==='formula'">
                                    <div class="mb-4">
                                        <jet-label for="option_type" value="Option Type"/>
                                        <select
                                            class="mt-1 border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm w-full"
                                            name="option_type" v-model="item.option_type" id="option_type"
                                            @change="updateItemOptionType(item)">
                                            <option value="range">Range</option>
                                            <option value="greater_than_or_less_than">Greater than/Less than</option>
                                        </select>
                                    </div>
                                    <div class="mb-4" v-if="item.option_type==='greater_than_or_less_than'">
                                        <div class="mb-4">
                                            <jet-label for="median_value" value="Greater than/Less than Value"/>
                                            <jet-input type="text" class="mt-1 w-full" v-model="item.median_value"/>
                                        </div>
                                        <div class="mb-4">
                                            <!-- <h4>Option Weights</h4> -->
                                            <table class="w-full whitespace-no-wrap table-auto">
                                                <thead class="bg-gray-50">
                                                <tr class="text-left font-bold">
                                                    <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Name</th>
                                                    <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Category Name</th>
                                                    <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Score</th>
                                                </tr>
                                                </thead>
                                                <tbody>
                                                <tr v-for="option in item.attribute.options"
                                                    class="hover:bg-gray-100 focus-within:bg-gray-100">
                                                    <td class="border-t px-6 py-4">
                                                        {{ option.name }} {{ item.median_value }}
                                                    </td>
                                                    <td class="border-t px-6 py-4">
                                                        <jet-input type="text" class="w-16" v-model="option.weight"
                                                                   @blur="updateItems"/>
                                                        <span class="ml-2"></span>
                                                    </td>
                                                    <td class="border-t px-6 py-4">
                                                        {{ option.score }}
                                                    </td>
                                                </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <div class="mb-4" v-if="item.option_type==='range'">
                                        <div class="mb-4">
                                            <!-- <h4>Option Weights</h4> -->
                                            <table class="w-full whitespace-no-wrap table-auto">
                                                <thead class="bg-gray-50">
                                                <tr class="text-left font-bold">
                                                    <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Lower Value
                                                    </th>
                                                    <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Upper Value
                                                    </th>
                                                    <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Category Name</th>
                                                    <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Score</th>
                                                    <th class="px-6 pt-4 pb-4 font-medium text-gray-500">
                                                        <button class="btn btn-green"
                                                                @click="addRangeOption(item)">
                                                            <span>Add Range </span>
                                                        </button>
                                                    </th>
                                                </tr>
                                                </thead>
                                                <tbody>
                                                <tr v-for="(option,ky) in item.attribute.options"
                                                    class="hover:bg-gray-100 focus-within:bg-gray-100">
                                                    <td class="border-t px-6 py-4">
                                                        <jet-input type="text" class="w-24" v-model="option.lower_value"
                                                                   @blur="updateItems"/>
                                                    </td>
                                                    <td class="border-t px-6 py-4">
                                                        <jet-input type="text" class="w-24" v-model="option.upper_value"
                                                                   @blur="updateItems"/>
                                                    </td>
                                                    <td class="border-t px-6 py-4">
                                                        <jet-input type="text" class="w-24" v-model="option.weight"
                                                                   @blur="updateItems"/>
                                                        <span class="ml-2"></span>
                                                    </td>
                                                    <td class="border-t px-6 py-4">
                                                        {{ option.score }}
                                                    </td>
                                                    <td class="border-t px-6 py-4">
                                                        <button href="#" v-if="can('loans.products.destroy')"
                                                                @click="deleteAttributeOption(item,ky)"
                                                                class="  text-red-600 hover:text-red-900"
                                                                title="Delete">
                                                            <font-awesome-icon icon="trash"/>
                                                        </button>
                                                    </td>
                                                </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-4" v-if="item.attribute.field_type!=='calculated'">
                                    <div class="grid grid-cols-1 gap-2 mt-2 mb-4 ">
                                        <div>
                                            <jet-label for="accept_value" value="Accepted Option(s)"/>
                                            <Multiselect class="bg-white"
                                                         mode="tags"
                                                         v-model="item.accept_value"
                                                         :options="item.attribute.options"
                                                         label="name"
                                                         valueProp="name"
                                            />
                                        </div>
                                    </div>
                                </div>
                                <button href="#" v-if="can('loans.products.destroy')"
                                        @click="deleteItem('attribute',parent_index,index)"
                                        class="absolute bottom-0 right-2  text-red-600 hover:text-red-900"
                                        title="Delete">
                                    <font-awesome-icon icon="trash"/>
                                </button>
                            </div>
                        </div>

                    </div>
                    <div v-if="errors.length > 0"
                         class="mb-8 flex items-center justify-between bg-red-500 rounded w-full">
                        <div class="flex items-center">
                            <svg class="ml-4 mr-2 flex-shrink-0 w-4 h-4 fill-white" xmlns="http://www.w3.org/2000/svg"
                                 viewBox="0 0 20 20">
                                <path
                                    d="M2.93 17.07A10 10 0 1 1 17.07 2.93 10 10 0 0 1 2.93 17.07zm1.41-1.41A8 8 0 1 0 15.66 4.34 8 8 0 0 0 4.34 15.66zm9.9-8.49L11.41 10l2.83 2.83-1.41 1.41L10 11.41l-2.83 2.83-1.41-1.41L8.59 10 5.76 7.17l1.41-1.41L10 8.59l2.83-2.83 1.41 1.41z"/>
                            </svg>
                            <div class="grid mb-2 py-4 text-white text-sm font-medium">
                                <div v-for="error in errors" class="grid text-white text-sm font-medium">
                                    <span>{{ error }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <jet-button class="w-full justify-center" :class="{ 'opacity-25': form.processing }"
                                    @click="submit"
                                    :disabled="form.processing||!readyToSave">
                            Save
                        </jet-button>
                    </div>
                </div>
            </div>

        </div>

        <jet-dialog-modal :show="showAddGroupModal" @close="showAddGroupModal = false">
            <template #title>
                <span>Add Transition Column</span>
            </template>
            <template #content>
                <div class="grid grid-cols-1 gap-2 ">
                    <div>
                        <jet-label for="group" value="Transition Column"/>
                        <select
                            class="border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm w-full"
                            name="group" v-model="group_id" id="group" required>
                            <option v-for="item in availableGroups" :value="item.id">
                                {{ item.name }} <span v-if="item.is_corporate"><small>(corporate)</small></span>
                            </option>
                        </select>
                    </div>
                </div>
            </template>
            <template #footer>
                <jet-secondary-button @click.native="showAddGroupModal = false">
                    Cancel
                </jet-secondary-button>
                <jet-success-button class="ml-2" @click.native="addGroup"
                                    :class="{ 'opacity-25': processing }"
                                    :disabled="processing">
                    Add
                </jet-success-button>
            </template>
        </jet-dialog-modal>
        <jet-dialog-modal :show="showAddAttributeModal" @close="showAddAttributeModal = false">
            <template #title>
                <span>Configure Values</span>
            </template>
            <template #content>
                <div class="grid grid-cols-1 gap-2 ">
                    <div>
                        <jet-label for="attribute_id" value="Attribute"/>
                        <select
                            class="border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm w-full"
                            name="attribute_id" v-model="attribute_id" id="attribute_id" required>
                            <option v-for="item in availableAttributes" :value="item.id">{{ item.name }}</option>
                        </select>
                    </div>
                </div>
            </template>
            <template #footer>
                <jet-secondary-button @click.native="showAddAttributeModal = false">
                    Cancel
                </jet-secondary-button>
                <jet-success-button class="ml-2" @click.native="addAttribute"
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
        product: Object,
        groups: Object,
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
            showAddGroupModal: false,
            showAddAttributeModal: false,
            confirmItemDeletion: false,
            editingItem: false,
            showItemModal: false,
            group_id: '',
            attribute_id: '',
            form: this.$inertia.form({
                attributes: JSON.parse(JSON.stringify(this.product.form_attributes)),
            }),
            readyToSave: false,
            confirmingDeletion: false,
            selectedGroup: null,
            selectedAttribute: null,
            selectedRecord: null,
            processing: false,
            errors: [],
            pageTitle: "Transition Profile",
            pageDescription: "Transition Profile",
        }

    },
    mounted() {
        this.updateItems()
    },
    methods: {
        addGroup() {
            if (this.group_id) {
                let groupExists = false
                let group = ''
                this.groups.forEach(item => {
                    if (item.id == this.group_id) {
                        group = item
                    }
                })
                this.form.attributes.forEach(item => {
                    if (item.scoring_attribute_group_id == this.group_id) {
                        groupExists = true
                    }
                })
                if (!groupExists) {
                    this.form.attributes.push({
                        id: '',
                        scoring_attribute_group_id: group.id,
                        name: group.name,
                        system_name: group.system_name,
                        is_ratio: group.is_ratio,
                        is_industry_analysis: group.is_industry_analysis,
                        is_shareholder_analysis: group.is_shareholder_analysis,
                        is_management_analysis: group.is_management_analysis,
                        scoring_attribute_id: '',
                        weight: '',
                        effective_weight: '',
                        reject_value: '',
                        accept_value: [],
                        accept_condition: '',
                        option_type: '',
                        median_value: '',
                        data: '',
                        active: true,
                        is_group: true,
                        order_position: this.form.attributes.length + 1,
                        attributes: [],
                    })
                }
            }
            this.showAddGroupModal = false
        },
        addAttribute() {
            if (this.attribute_id) {
                let attributeExists = false
                let attribute = ''
                this.groups.forEach(item => {
                    if (item.id == this.selectedGroup.scoring_attribute_group_id) {
                        item.scoring_attributes.forEach(attr => {
                            if (attr.id == this.attribute_id) {
                                attribute = attr
                            }
                        })
                    }
                })
                this.form.attributes.forEach(item => {
                    if (item.scoring_attribute_group_id == this.selectedGroup.scoring_attribute_group_id) {
                        Object.keys(item.attributes).forEach(key => {
                            let attr = item.attributes[key]
                            if (attr.scoring_attribute_id == this.attribute_id) {
                                attributeExists = true
                            }
                        })
                    }
                })
                if (!attributeExists) {
                    this.form.attributes.forEach(item => {
                        if (item.scoring_attribute_group_id == this.selectedGroup.scoring_attribute_group_id) {
                            item.attributes.push({
                                id: '',
                                scoring_attribute_group_id: item.scoring_attribute_group_id,
                                attribute: attribute,
                                name: attribute.name,
                                system_name: attribute.system_name,
                                scoring_attribute_id: attribute.id,
                                is_ratio: attribute.is_ratio,
                                is_industry_analysis: attribute.is_industry_analysis,
                                is_shareholder_analysis: attribute.is_shareholder_analysis,
                                is_management_analysis: attribute.is_management_analysis,
                                weight: '',
                                effective_weight: '',
                                reject_value: '',
                                accept_value: [],
                                accept_condition: '',
                                option_type: '',
                                median_value: '',
                                data: '',
                                active: true,
                                is_group: false,
                                order_position: item.attributes.length + 1,
                            })
                        }
                    })
                }
            }
            this.showAddAttributeModal = false
        },
        addRangeOption(item) {
            item.attribute.options.push({
                id: '',
                loan_product_scoring_attribute_id: item.id,
                loan_product_id: item.loan_product_id,
                scoring_attribute_id: item.scoring_attribute_id,
                weight: '',
                effective_weight: '',
                name: '',
                description: '',
                lower_value: '',
                upper_value: '',
                median_value: '',
                active: true,
            })
        },
        updateItems() {
            this.errors = []
            let formGood = true
            Object.keys(this.form.attributes).forEach(key => {
                let item = this.form.attributes[key]
                Object.keys(item.attributes).forEach(k => {
                    let attr = item.attributes[k]
                    if (attr.attribute.options && attr.attribute.options.length) {
                        attr.attribute.options.forEach(opt => {
                            if (attr.option_type === 'range') {
                                opt.name = opt.lower_value + ' to ' + opt.upper_value
                            }
                        })
                    }
                })
            })
            this.readyToSave = formGood
        },
        submit() {
            this.form.post(this.route('loan_products.sync_attributes', this.product.id), {
                preserveState: false,
                onSuccess: () => {
                    //this.$inertia.reload()
                },
            })
        },
        deleteItem(type, parentIndex, index = '') {
            this.$swal({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes'
            }).then((result) => {
                if (result.isConfirmed) {
                    if (type === 'group') {
                        this.form.attributes.splice(parentIndex, 1);
                    }
                    if (type === 'attribute') {
                        this.form.attributes[parentIndex].attributes.splice(index, 1);
                    }
                    this.updateItems()
                }
            })
        },
        deleteAttributeOption(item, index) {
            this.$swal({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes'
            }).then((result) => {
                if (result.isConfirmed) {
                    item.attribute.options.splice(index, 1)
                    this.updateItems()
                }
            })
        },
        deleteAction(id) {
            this.confirmingDeletion = true
            this.selectedRecord = id
        },
        destroy() {
            this.$inertia.delete(this.route('loan_products.destroy', this.product.id), {
                preserveState: false,
                onSuccess: () => {
                    this.$inertia.visit(this.route('loan_products.index'))
                }
            })
            this.confirmingDeletion = false
            window.location = route('loan_products.index')
        },
        copy(text) {
            navigator.clipboard.writeText(text);
        }
    },
    computed: {
        availableGroups() {
            let groups = []
            this.groups.forEach(item => {
                if (!item.used) {
                    groups.push({
                        id: item.id,
                        name: item.name,
                        is_corporate: item.is_corporate,
                    })
                }
            })
            return groups
        },
        availableAttributes() {
            let attributes = []
            if (this.selectedGroup) {
                this.groups.forEach(item => {
                    if (item.id == this.selectedGroup.scoring_attribute_group_id) {
                        attributes = item.scoring_attributes
                    }
                })
            }

            return attributes
        }
    },
    watch: {}
}
</script>
<style scoped>

</style>
