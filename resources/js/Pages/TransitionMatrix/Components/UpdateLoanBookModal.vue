<template>
    <jet-dialog-modal :show="true" @close="$emit('close')">
        <template #title>
            Update Loan Book
        </template>

        <template #content>
            <div class="mt-4">
                <jet-label for="reporting_period" value="Reporting Period" />
                <flat-pickr
                    v-model="form.reporting_period"
                    :config="dateConfig"
                    class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm"
                />
                <jet-input-error :message="form.errors.reporting_period" class="mt-2" />
            </div>
        </template>

        <template #footer>
            <jet-secondary-button @click="$emit('close')" :disabled="form.processing">
                Cancel
            </jet-secondary-button>

            <jet-button class="ml-3" @click="updateLoanBook" :disabled="form.processing">
                Update
            </jet-button>
        </template>
    </jet-dialog-modal>
</template>

<script>
import { ref } from 'vue'
import JetDialogModal from '@/Jetstream/DialogModal.vue'
import JetButton from '@/Jetstream/Button.vue'
import JetSecondaryButton from '@/Jetstream/SecondaryButton.vue'
import JetLabel from '@/Jetstream/Label.vue'
import JetInputError from '@/Jetstream/InputError.vue'
import flatPickr from 'vue-flatpickr-component'
import 'flatpickr/dist/flatpickr.css'
import { useForm } from '@inertiajs/vue3'

export default {
    components: {
        JetDialogModal,
        JetButton,
        JetSecondaryButton,
        JetLabel,
        JetInputError,
        flatPickr
    },

    props: {
        matrixId: {
            type: Number,
            required: true
        }
    },

    emits: ['close', 'updated'],

    setup(props, { emit }) {
        const form = useForm({
            reporting_period: ''
        })

        const dateConfig = {
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'F j, Y'
        }

        const updateLoanBook = () => {
            form.post(route('transition-matrices.update-loan-book', props.matrixId), {
                preserveScroll: true,
                onSuccess: () => {
                    emit('updated')
                }
            })
        }

        return {
            form,
            dateConfig,
            updateLoanBook
        }
    }
}
</script>
