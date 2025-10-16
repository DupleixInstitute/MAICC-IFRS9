<template>
    <div>
        <div class="flex justify-center">
            <h1 class="font-bold text-center text-3xl text-gray-900"> #{{ loan.id }}</h1>
        </div>
        <div class="w-full mt-5">
            <inertia-link
                class="w-full border-t border-gray-100 font-medium text-gray-600 py-2 px-4 w-full block hover:bg-gray-100 transition duration-150"
                :class="{'bg-gray-100': route().current('loans.show')}"
                :href="route('loans.show',loan.id)">
                <font-awesome-icon icon="user" class="w-4 h-4 mr-2"></font-awesome-icon>Loan Details
            </inertia-link>
            <inertia-link v-if="can('loans.guarantors.index')"
                          class="w-full border-t border-gray-100 font-medium text-gray-600 py-2 px-4 w-full block hover:bg-gray-100 transition duration-150"
                          :class="{'bg-gray-100': route().current('loans.guarantors.index')}"
                          :href="route('loans.guarantors.index',loan.id)">
                <font-awesome-icon icon="users" class="w-4 h-4 mr-2"></font-awesome-icon>Guarantors
            </inertia-link>
            <inertia-link v-if="can('loans.notes.index')"
                          class="w-full border-t border-gray-100 font-medium text-gray-600 py-2 px-4 w-full block hover:bg-gray-100 transition duration-150"
                          :class="{'bg-gray-100': route().current('loans.notes.index')}"
                          :href="route('loans.notes.index',loan.id)">
                <font-awesome-icon icon="bookmark" class="w-4 h-4 mr-2"></font-awesome-icon>
                Notes
            </inertia-link>
            <inertia-link v-if="can('loans.files.index')"
                          class="w-full border-t border-gray-100 font-medium text-gray-600 py-2 px-4 w-full block hover:bg-gray-100 transition duration-150"
                          :class="{'bg-gray-100': route().current('loans.files.*')}"
                          :href="route('loans.files.index',loan.id)">
                <font-awesome-icon icon="folder" class="w-4 h-4 mr-2"></font-awesome-icon>Files
            </inertia-link>
        </div>
        <div class="p-5 border-t flex justify-between">
            <inertia-link v-if="can('loans.update')" :href="route('loans.edit',loan.id)"
                          class="btn btn-primary py-1 px-2">Edit
            </inertia-link>
            <button type="button" v-if="can('loans.destroy')" @click="deleteAction"
                    class="btn btn-danger py-1 px-2 ml-auto">Delete
            </button>
            <button type="button" v-if="can('loans.approve')" @click="showChangeStatusModal=true"
                    class="btn btn-success py-1 px-2 ml-auto">
                Change Status
            </button>
        </div>
    </div>
    <jet-confirmation-modal :show="confirmingLoanDeletion" @close="confirmingLoanDeletion = false">
        <template #title>
            Delete Record
        </template>

        <template #content>
            Are you sure you want to delete your account? Once the record is deleted, all of its resources and
            data will be permanently deleted.
        </template>

        <template #footer>
            <jet-secondary-button @click.native="confirmingLoanDeletion = false">
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
            Change Status
        </template>
        <template #content>
            <div class="grid grid-cols-1 gap-2 mt-4">
                <div>
                    <jet-label for="status" value="Status"/>
                    <select
                        class="border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm w-full"
                        name="status" v-model="status" id="status"
                        required>
                        <option value="received">Received</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
            </div>
        </template>

        <template #footer>
            <jet-secondary-button @click.native="showChangeStatusModal = false">
                Cancel
            </jet-secondary-button>

            <jet-success-button class="ml-2" @click.native="changeStatus"
                                :class="{ 'opacity-25': processing }"
                                :disabled="processing">
                Save
            </jet-success-button>
        </template>
    </jet-dialog-modal>
</template>

<script>
import JetConfirmationModal from '@/Jetstream/ConfirmationModal.vue'
import JetDialogModal from '@/Jetstream/DialogModal.vue'
import JetDangerButton from '@/Jetstream/DangerButton.vue'
import JetSuccessButton from '@/Jetstream/SuccessButton.vue'
import JetSecondaryButton from '@/Jetstream/SecondaryButton.vue'
import Icon from "@/Jetstream/Icon.vue";
import JetLabel from "@/Jetstream/Label.vue";
import SelectInput from "@/Jetstream/SelectInput.vue";

export default {
    components: {
        Icon,
        JetLabel,
        SelectInput,
        JetConfirmationModal,
        JetDangerButton,
        JetDialogModal,
        JetSecondaryButton,
        JetSuccessButton,
    },
    props: {
        loan: Object,
    },
    data() {
        return {
            processing: false,
            confirmingLoanDeletion: false,
            showChangeStatusModal: false,
            status: this.loan.status
        }
    },
    methods: {
        deleteAction() {
            this.confirmingLoanDeletion = true
        },
        destroy() {

            this.$inertia.delete(this.route('loans.destroy', this.loan.id))
            this.confirmingLoanDeletion = false
        },
        changeStatus() {

            this.$inertia.post(this.route('loans.change_status', this.loan.id), {
                status: this.status
            }, {
                onSuccess: () => {
                    this.$inertia.reload();
                }
            })
            this.showChangeStatusModal = false
        },
    },
}
</script>

<style scoped>

</style>
