<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                <inertia-link class="text-indigo-400 hover:text-indigo-600" :href="route('loan_applications.index')">
                    Loan Applications
                </inertia-link>
                <span class="text-indigo-400 font-medium">/</span>
                #{{ application.id }}
                <span class="px-6 py-4 inline-block text-sm font-semibold text-green-800 bg-green-200 rounded-full">
                    {{ application.loan_application_band }}
                </span>
            </h2>
        </template>
        <div class=" mx-auto">
            <div class="md:flex md:items-start">
                <div class="relative mb-4  w-full md:w-3/12">
                    <div class="intro-y bg-white mt-5 lg:mt-0">
                        <div class="relative flex items-center p-5">
                            <div class="ml-4 mr-auto">
                                <div class="font-medium text-base">#{{ application.id }}</div>
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
                                        <jet-dropdown-link v-if="can('loans.applications.update')"
                                                           :href="route('loan_applications.edit',application.id)">
                                            <font-awesome-icon icon="edit"/>
                                            Edit
                                        </jet-dropdown-link>

                                        <a href="#"
                                           class="block px-4 py-2 text-sm leading-5 text-gray-700 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 transition duration-150 ease-in-out"
                                           @click="confirmingDeletion=true"
                                           v-if="can('loans.applications.destroy')">
                                            <font-awesome-icon icon="trash"
                                                               class="text-red-600 hover:text-red-900"/>
                                            Delete
                                        </a>
                                    </div>
                                </template>
                            </jet-dropdown>
                        </div>
                        <div class="p-5 border-t border-gray-200 dark:border-dark-5">
                            <div class="grid grid-cols-2 ">
                                <span class="font-medium">Client</span>
                                <span>
                                    <inertia-link
                                        class="px-6 py-4 flex items-center text-indigo-600 focus:text-indigo-500"
                                        :href="route('clients.show', application.client_id)"
                                        v-if="application.client">
                                {{ application.client.name }}
                            </inertia-link>
                                </span>
                            </div>
                            <div class="grid grid-cols-2 ">
                                <span class="font-medium">Product</span>
                                <span>
                                    <inertia-link
                                        class="px-6 py-4 flex items-center text-indigo-600  focus:text-indigo-500"
                                        :href="route('loan_products.show', application.loan_product_id)"
                                        v-if="application.product">
                                {{ application.product.name }}
                            </inertia-link>
                                </span>
                            </div>

                            <div class="grid grid-cols-2 mt-2 mb-2 ">
                                <span class="font-medium">Product Description</span>
                                <span>
                                    {{ application.product_description ? application.product_description : 'N/A' }}
                                </span>
                            </div>
                            <div class="grid grid-cols-2 ">
                                <span class="font-medium">Amount</span>
                                <span>{{ $filters.formatNumber(application.amount) }}</span>
                            </div>
                            <div class="grid grid-cols-2 ">
                                <span class="font-medium">Score</span>
                                <span> {{
                                        $filters.formatNumber(application.score)
                                    }} ({{ $filters.formatNumber(application.score_percentage) }}%)</span>
                            </div>
                            <div class="grid grid-cols-2 ">
                                <div class="font-medium">Status</div>
                                <div v-if="application.current_linked_stage">
                                      <span
                                          v-if="application.current_linked_stage.stage">{{
                                              application.current_linked_stage.stage.name
                                          }} -</span>

                                    <span v-if="application.current_linked_stage.status=='pending'"
                                          class="px-2 rounded-full bg-yellow-100 text-yellow-800">
                                        pending
                                    </span>
                                    <span v-if="application.current_linked_stage.status=='returned'"
                                          class="px-2 rounded-full bg-yellow-100 text-yellow-800">
                                        returned
                                    </span>
                                    <span v-if="application.current_linked_stage.status=='in_progress'"
                                          class="px-2 rounded-full bg-blue-100 text-blue-800">
                                        in progress
                                    </span>
                                    <span v-if="application.current_linked_stage.status=='approved'"
                                          class="px-2 rounded-full bg-green-100 text-green-800">
                                        approved
                                    </span>
                                    <span v-if="application.current_linked_stage.status=='done'"
                                          class="px-2 rounded-full bg-green-100 text-green-800">
                                        done
                                    </span>
                                    <span v-if="application.current_linked_stage.status=='sent_back'"
                                          class="px-2 rounded-full bg-yellow-100 text-yellow-800">
                                        sent back
                                    </span>
                                    <span v-if="application.current_linked_stage.status=='rejected'"
                                          class="px-2 rounded-full bg-red-100 text-red-800">
                                        rejected
                                    </span>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 ">
                                <span class="font-medium">Created By</span>
                                <span>

                                    <inertia-link
                                        class="px-6 py-4 flex items-center text-indigo-600 focus:text-indigo-500"
                                        :href="route('users.show', application.created_by.id)"
                                        v-if="application.client">
                                        {{ application.created_by.name }}
                            </inertia-link>
                                </span>

                            </div>
                        </div>
                    </div>
                    <div class="bg-white p-5 mt-5">
                        <div class="flex items-center">
                            <div class="font-medium text-lg">Loan Motivation</div>
                        </div>

                        <div class="mt-4">{{ application.description }}</div>
                    </div>

                    <inertia-link :href="route('loan_applications.show_comments', application.id)" class="block">
                        <div class="bg-white p-5 mt-5 rounded-lg shadow-md hover:shadow-lg transition-shadow duration-200">
                            <div class="flex items-center">
                                <i class="fas fa-comments text-blue-500 text-2xl mr-4"></i>
                                <div class="text-gray-800 font-semibold text-lg">
                                    Reviewer Comments
                                    <span class="badge">{{ reviewerCommentsCount }}</span>
                                </div>
                            </div>
                        </div>
                    </inertia-link>




                </div>
                <div class="w-full md:w-9/12 p-4 md:ml-4 bg-white">
                    <div v-if="can('loans.applications.view_approvals')">
                        <div class="flex justify-between mb-4">
                            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Approvals</h2>
                        </div>
                        <div class="mt-4 overflow-x-auto">
                            <div class="table-container">
                                <table class="w-full whitespace-no-wrap table-auto mt-4">
                                <thead>
                                <tr class="text-left font-bold">
                                    <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Name</th>
                                    <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Approver</th>
                                    <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Status</th>
                                    <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Received At</th>
                                    <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Started At</th>
                                    <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Finished At</th>
                                    <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Notes</th>
                                    <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Actions</th>

                                </tr>
                                </thead>
                                <tbody>
                                <tr  v-for="approval in filteredStages"
                                    class="hover:bg-gray-100 focus-within:bg-gray-100">


                                    <td class="border-t px-6 py-4">
                                        <span v-if="approval.stage">{{ approval.stage.name }}</span>
                                    </td>
                                    <td class="border-t px-6 py-4">
                                        <div v-if="application.current_linked_stage?.status=='returned' || application.current_linked_stage?.status=='sent_back' && application.was_resend &&  approval.approver">
                                            <span class="ml-2 text-green-600"  title="Application resend"><font-awesome-icon icon="check-circle"/></span>
                                        </div>
                                        <div v-else-if="application.current_linked_stage?.status=='returned'  || application.current_linked_stage?.status=='sent_back' &&  !application.was_resend &&  approval.approver">
                                                <span class="ml-2 text-red-600" title="Application not resend">
                                            <font-awesome-icon icon="times-circle"/>
                                        </span>
                                        </div>

                                        <div v-if="approval.approver">
                                            <inertia-link :href="route('users.show', approval.approver.id)"
                                                          tabindex="-1" class="text-green-600 hover:text-green-900" title="View">
                                                {{approval.approver.name}}
                                            </inertia-link>

                                        </div>
                                        <div v-else>
                                            <button v-if="can('loans.applications.assign_approver')"
                                                @click="assignApproverAction(approval.id, 'Assign')"
                                                type="button" class="btn btn-primary py-1 px-2">
                                                Assign
                                            </button>
                                            <span v-else>not assigned</span>
                                        </div>
                                    </td>
                                    <td class="border-t px-6 py-4 flex">
                                         <span v-if="approval.status==='pending'"
                                               class="px-2 rounded-full bg-yellow-100 text-yellow-800">
                                        pending
                                    </span>
                                        <span v-if="approval.status==='returned'"
                                              class="px-2 rounded-full bg-yellow-100 text-yellow-800">
                                        returned
                                    </span>
                                        <span v-if="approval.status==='in_progress'"
                                              class="px-2 rounded-full bg-blue-100 text-blue-800">
                                        in progress
                                    </span>
                                        <span v-if="approval.status==='approved'"
                                              class="px-2 rounded-full bg-green-100 text-green-800">
                                        approved
                                    </span>
                                     <span v-if="approval.status==='recommend'"
                                              class="px-2 rounded-full bg-green-100 text-green-800">
                                        Recommended
                                    </span>
                                        <span v-if="approval.status==='done'"
                                              class="px-2 rounded-full bg-green-100 text-green-800">
                                        done
                                    </span>
                                        <span v-if="approval.status==='sent_back'"
                                              class="px-2 rounded-full bg-yellow-100 text-yellow-800">
                                        sent back
                                    </span>
                                        <span v-if="approval.status==='rejected'"
                                              class="px-2 rounded-full bg-red-100 text-red-800">
                                        rejected
                                    </span>
                                        <button class="ml-2 bg-blue-600 text-white p-1 rounded"
                                                @click="changeStatusAction(approval.id, approval.description)"
                                                v-if="approval.approver_id===$attrs.auth.user.id && approval.is_current && !approval.completed">
                                            <font-awesome-icon icon="edit"/>
                                        </button>
                                    </td>
                                    <td class="border-t px-6 py-4">
                                        <span v-if="approval.stage_received_at">{{
                                                $filters.time(approval.stage_received_at)
                                            }}</span>
                                    </td>
                                    <td class="border-t px-6 py-4">
                                        <span v-if="approval.stage_started_at">{{
                                                $filters.time(approval.stage_started_at)
                                            }}</span>
                                    </td>
                                    <td class="border-t px-6 py-4">
                                        <span v-if="approval.stage_finished_at">{{
                                                $filters.time(approval.stage_finished_at)
                                            }}</span>

                                    </td>
                                    <expandable-notes
                                    :description="approval.description"
                                    :previous-description="approval.previous_description"
                                    />




                                    <td class="border-t px-6 py-4">
                                        <div v-if=" approval.approver && approval.stage_finished_at == null && canReassignViaRole  && approval.approver_id &&  approval.has_same_role_as_approver  || $attrs.auth.user.can_reassign == true && approval.approver_id && approval.stage_finished_at == null  && approval.approver ">
                                            <!-- && approval.was_sent_back == false -->
                                            <button v-if="can('loans.applications.assign_approver')"
                                            @click="assignApproverAction(approval.id, 'Reassign')"
                                            type="button" class="btn btn-primary  py-1 px-2">
                                            <font-awesome-icon icon="edit"/>
                                            Reassign
                                           </button>
                                        </div>
                                        <span   class="px-2 rounded-full bg-green-100 text-green-800" v-else-if="approval.was_sent_back== true">Reassigned</span>
                                        <span   class="px-2 rounded-full bg-red-100 text-red-800" v-else>No Actions</span>


                                         <!-- Check if the approval has an attachment -->
                                         <a v-if="approval.attachment_path" :href="getDownloadUrl(approval.attachment_path)" class="btn btn-link">
                                            <font-awesome-icon icon="download" :style="{ fontSize: '0.90em', marginTop: '10px' }" />
                                          </a>


                                    </td>
                                </tr>
                                </tbody>
                                </table>
                        </div>
                        </div>
                    </div>
                    <div class="flex justify-between mb-4">
                        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Scores</h2>
                    </div>
                    <div class="mb-4 relative" v-for="(group,parent_index) in application.product.scoring_attributes">
                        <div class="bg-gray-50 border p-4">
                            <h4 class="font-semibold text-xl text-gray-800 leading-tight">{{ group.name }}</h4>
                            <table class="w-full whitespace-no-wrap table-auto mt-4">
                                <thead>
                                <tr class="text-left font-bold">
                                    <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Name</th>
                                    <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Value</th>
                                    <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Max Score</th>
                                    <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Actual Score</th>
                                    <th class="px-6 pt-4 pb-4 font-medium text-gray-500">% Score</th>
                                    <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Accepted</th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr v-for="attribute in group.attributes"
                                    class="hover:bg-gray-100 focus-within:bg-gray-100">
                                    <td class="border-t px-6 py-4">
                                        {{ attribute.name }}
                                    </td>
                                    <td class="border-t px-6 py-4">
                                        <div v-if="attribute.attribute.field_type==='checkbox'">
                                            <span class="mr-2 bg-gray-100 p-1"
                                                  v-for="value in attribute.value">{{ value }}</span>
                                        </div>
                                        <div v-else>{{ attribute.value }}</div>

                                    </td>
                                    <td class="border-t px-6 py-4">
                                        {{ attribute.score }}
                                    </td>
                                    <td class="border-t px-6 py-4">
                                        {{ attribute.actual_score }}
                                    </td>
                                    <td class="border-t px-6 py-4">
                                        {{ $filters.formatNumber(attribute.percentage_score) }}%
                                    </td>
                                    <td class="border-t px-6 py-4">
                                        <span class="ml-2 text-green-600" v-if="attribute.accepted"><font-awesome-icon
                                            icon="check-circle"/></span>
                                        <span class="ml-2 text-red-600" v-else><font-awesome-icon icon="times-circle"/></span>
                                    </td>
                                </tr>
                                </tbody>
                                <tfoot>
                                <tr class="text-left font-bold">
                                    <th class="px-6 pt-4 pb-4 font-medium text-gray-500" colspan="2">Total</th>
                                    <th class="px-6 pt-4 pb-4 font-bold text-gray-800" colspan="">
                                        {{ group.max_total_score }}
                                    </th>
                                    <th class="px-6 pt-4 pb-4 font-bold text-gray-800" colspan="">
                                        {{ group.total_score }}
                                    </th>
                                    <th class="px-6 pt-4 pb-4 font-bold text-gray-800" colspan="">
                                        {{ group.total_percentage }}%
                                    </th>
                                    <th class="px-6 pt-4 pb-4 font-bold text-gray-800" colspan="">
                                    </th>
                                </tr>
                                </tfoot>
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
        <jet-dialog-modal :show="showChangeStatusModal" @close="showChangeStatusModal = false">
            <template #title>
                Action Loan Application
            </template>
            <template #content>
                <div class="grid grid-cols-1 gap-4 mt-4">
                    <div>
                        <jet-label for="status" value="Select Action"/>
                        <select
                            class="border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm w-full"
                            name="status" v-model="changeStatusForm.status" id="status"
                            required>
                            <option value="pending">Pending</option>
                            <option value="in_progress">In Progress</option>
                            <option v-if="recommenderAccessRight" value="recommend">Recommend</option>
                            <option v-if="approverAccessRight" value="approved">Approved</option>
                            <option value="sent_back">Send Back To Last Stage</option>
                            <option v-if="approverAccessRight" value="rejected">Rejected</option>
                        </select>
                        <jet-input-error :message="changeStatusForm.errors.status" class="mt-2"/>
                    </div>
                    <div>
                        <jet-label for="description" value="Comments "/>
                        <textarea-input id="description" class="mt-1 block w-full"
                                        v-model="changeStatusForm.description"/>
                        <jet-input-error :message="changeStatusForm.errors.description" class="mt-2"/>
                    </div>
                    <div>
                        <jet-label for="attachment" value="Attach File (optional)"/>
                        <input
                            type="file"
                            name="attachment"
                            id="attachment"
                            class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                            @change="handleFileUpload"/>
                        <jet-input-error :message="changeStatusForm.errors.attachment" class="mt-2"/>

                        <!-- Display attachment name and remove option if set -->
                        <div v-if="changeStatusForm.attachment">
                            <jet-secondary-button @click="removeAttachment" class="mt-2">
                                Remove Attachment
                            </jet-secondary-button>
                        </div>
                    </div>
                </div>
            </template>

            <template #footer>
                <jet-secondary-button @click.native="showChangeStatusModal = false">
                    Cancel
                </jet-secondary-button>

                <jet-success-button class="ml-2" @click.native="changeStatus"
                                    :class="{ 'opacity-25': changeStatusForm.processing }"
                                    :disabled="changeStatusForm.processing">
                    Save
                </jet-success-button>
            </template>
        </jet-dialog-modal>

        <jet-dialog-modal :show="showAssignApproverModal" @close="showAssignApproverModal = false">
            <template #title>
                {{assignOrReassignActionName}} Approver
            </template>
            <template #content>
                <div class="grid grid-cols-1 gap-2 mt-4">
                    <div>
                        <jet-label for="approver_id" value="Approver"/>
                        <select
                            class="border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm w-full"
                            name="approver_id" v-model="assignApproverForm.approver_id" id="approver_id"
                            required>
                            <option v-for="item in approvers" :value="item.id">{{ item.name }} (#{{ item.id }})</option>
                        </select>
                    </div>
                    <div v-if="assignOrReassignActionName =='Reassign'">
                        <jet-label for="description" value="Reason for reassigning"/>
                        <textarea-input id="additional_notes" class="mt-1 block w-full"
                                        v-model="assignApproverForm.additional_notes" />
                        <jet-input-error :message="assignApproverForm.errors.additional_notes" class="mt-2"/>

                    </div>
                </div>
            </template>

            <template #footer>
                <jet-secondary-button @click.native="showAssignApproverModal = false">
                    Cancel
                </jet-secondary-button>

                <jet-success-button class="ml-2" @click.native="assignApprover"
                                    :class="{ 'opacity-25': assignApproverForm.processing }"
                                    :disabled="assignApproverForm.processing">
                    Save
                </jet-success-button>
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
import JetSuccessButton from '@/Jetstream/SuccessButton.vue'
import JetConfirmationModal from '@/Jetstream/ConfirmationModal.vue'
import JetDangerButton from '@/Jetstream/DangerButton.vue'
import Button from "../../Jetstream/Button.vue";
import {FontAwesomeIcon} from "@fortawesome/vue-fontawesome";
import ExpandableNotes from './ExpandableNotes.vue'

export default {
    props: {
        application: Object,
        groups: Object,
        approverAccessRight: Boolean,
        recommenderAccessRight: Boolean,
        canReassignViaRole : Boolean,
        reviewerCommentsCount: Number,


    },
    components: {
        FontAwesomeIcon,
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
        ExpandableNotes

    },
    data() {
        return {
            showAddAttributeModal: false,
            confirmItemDeletion: false,
            editingItem: false,
            showItemModal: false,
            group_id: '',
            attribute_id: '',
            form: this.$inertia.form({
                status: this.application.status,
            }),
            assignApproverForm: this.$inertia.form({
                approver_id: '',
                stage_id: '',
                action: '',
                additional_notes : ''
            }),
            changeStatusForm: this.$inertia.form({
                status: '',
                stage_id: '',
                description: '',
                attachment: null,
            }),
            approvers: [],
            readyToSave: false,
            confirmingDeletion: false,
            showAssignApproverModal: false,
            showChangeStatusModal: false,
            selectedGroup: null,
            selectedAttribute: null,
            selectedRecord: null,
            processing: false,
            groupPercentagesTotal: 0,
            attributePercentagesTotal: 0,
            assignOrReassignActionName : null,
            errors: [],
            pageTitle: "Loan Application",
            pageDescription: "Loan Application",
        }

    },
    mounted() {


    },
    methods: {

        assignApproverAction(id, action) {

            this.approvers = [];
            Object.keys(this.application.linked_stages).forEach(key => {

                let item = this.application.linked_stages[key]
                if(item.id==id){
                    if (item.stage) {
                        axios.get(this.route('users.search') + "?role_id=" + item.stage.role_id).then(response => {
                            this.approvers = response.data
                            //exclude the current approver from the list and the current logged in user
                            this.approvers = this.approvers.filter(function (item) {
                                return item.id !== this.application.current_linked_stage.approver_id && item.id !== this.$attrs.auth.user.id
                            }.bind(this))
                            // console.log(this.approvers)

                        })
                    }
                }

            })
            this.showAssignApproverModal = true
            this.assignApproverForm.stage_id = id
            this.assignApproverForm.action = action
            this.assignApproverForm.additional_notes = ''



            this.assignOrReassignActionName = action
            // action: '',
            //     additional_notes : ''
        },
        assignApprover() {
            this.assignApproverForm.post(this.route('loan_applications.assign_approver', this.application.id), {
                preserveState: false,
            })
            this.showAssignApproverModal = false
        },
        changeStatusAction(id, description) {
            this.showChangeStatusModal = true
            this.changeStatusForm.stage_id = id
            this.changeStatusForm.description = description
        },
        changeStatus() {
            console.log(this.changeStatusForm)
            this.changeStatusForm.post(this.route('loan_applications.change_status', this.application.id), {
                preserveState: false,
                    headers: {
                    'Content-Type': 'multipart/form-data'
                  },
            })
        },
        deleteAction(id) {
            this.confirmingDeletion = true
            this.selectedRecord = id
        },
        destroy() {
            this.$inertia.delete(this.route('loan_applications.destroy', this.application.id), {
                preserveState: false,
                onSuccess: () => {
                    this.$inertia.visit(this.route('loan_applications.index'))
                }
            })
            this.confirmingDeletion = false
            window.location = route('loan_applications.index')
        },
        handleFileUpload(event) {
        const file = event.target.files[0]; // Get the first selected file
        if (file) {
            // Store the file in your form data or handle it as needed
            this.changeStatusForm.attachment = file;
        }
    },
    removeAttachment() {
        this.changeStatusForm.attachment = null; // Clear the attachment
        const fileInput = document.getElementById('attachment');
        fileInput.value = ''; // Reset the file input value
    },
    getDownloadUrl(attachment) {
        // Generate the correct URL to access the file
        return `/storage/app/public/${attachment}`;  // This will map to public/storage/loan_applications/
    }



    },
    computed: {
    filteredStages() {
        const stages = this.application.linked_stages;
        const approvedIndex = stages.findIndex(stage => stage.status === 'approved');
        const rejectedIndex = stages.findIndex(stage => stage.status === 'rejected');



        // Find the earliest occurrence of approved or rejected status
        let cutoffIndex;
        if (approvedIndex !== -1 && rejectedIndex !== -1) {
            cutoffIndex = Math.min(approvedIndex, rejectedIndex);
        } else if (approvedIndex !== -1) {
            cutoffIndex = approvedIndex;
        } else if (rejectedIndex !== -1) {
            cutoffIndex = rejectedIndex;
        } else {
            cutoffIndex = -1;
        }

        if (cutoffIndex === -1) {
            return stages;
        } else {
            return stages.slice(0, cutoffIndex + 1);
        }
    }
},

    watch: {}
}
</script>
<style scoped>
.notes-column {
    position: relative;
    cursor: pointer; /* Indicates the text is clickable */
}

.short-description {
    display: inline-block;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.full-description {
    display: none;
    position: absolute;
    background: #fff;
    border: 1px solid #ddd;
    padding: 5px;
    box-shadow: 0 0 10px rgba(0,0,0,0.1);
    z-index: 10;
    max-width: 200px; /* Adjust as needed */
    white-space: normal;
    word-wrap: break-word;
}

.notes-column:hover .full-description {
    display: block;
}
.hoverable {
    text-decoration: none;
    transition: color 0.3s ease, text-decoration 0.3s ease;
}

.hoverable:hover {
    color: #1E90FF; /* Blue color */
    text-decoration: underline;
    text-decoration-color: #1E90FF; /* Blue underline */
}
.hoverable {
    color: #1E90FF; /* Subtle blue color */
    text-decoration: underline;
    text-decoration-color: rgba(30, 144, 255, 0.4); /* Light blue underline */
    transition: color 0.3s ease, text-decoration-color 0.3s ease;
}

.hoverable:hover {
    color: #1E90FF; /* Stronger blue */
    text-decoration-color: #1E90FF; /* Darker blue underline */
}
.hoverable {
    color: #1E90FF; /* Subtle blue color */
    text-decoration: underline;
    text-decoration-color: rgba(30, 144, 255, 0.4); /* Light blue underline */
    transition: color 0.3s ease, text-decoration-color 0.3s ease;
}

.hoverable:hover {
    color: #1E90FF; /* Stronger blue */
    text-decoration-color: #1E90FF; /* Darker blue underline */
}

.previous-description {
    font-style: italic; /* Italicize to differentiate it */
    color: #6B7280; /* Gray color for previous description */
}
.badge {
    background-color: #ff0000;
    color: #fff;
    border-radius: 12px;
    padding: 2px 8px;
    font-size: 12px;
    font-weight: bold;
    position: absolute;
    /* Position the badge absolutely */
   

    /* Adjust position to look better */
}





</style>
