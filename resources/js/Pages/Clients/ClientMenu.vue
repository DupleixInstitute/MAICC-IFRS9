<template>
    <div>
        <div class="flex justify-center">
            <img v-if="client.profile_photo_url" :src="client.profile_photo_url" alt=""
                 class="rounded-full mx-auto absolute -top-20 w-32 h-32 shadow-2xl border-4 border-white">
        </div>
        <div class="mt-16">
            <h1 class="font-bold text-center text-3xl text-gray-900">{{ client.name }}</h1>
            <p class="text-center text-sm text-gray-400 font-medium">
                {{ client.type }}
            </p>
            <p class="text-center text-sm text-gray-400 font-medium">
                {{ client.mobile }}
            </p>
            <p class="text-center text-sm text-gray-400 font-medium">
                {{ client.email }}
            </p>
        </div>
        <div class="w-full mt-5">
            <inertia-link
                class="w-full border-t border-gray-100 font-medium text-gray-600 py-2 px-4 w-full block hover:bg-gray-100 transition duration-150"
                :class="{'bg-gray-100': route().current('clients.show')}"
                :href="route('clients.show',client.id)">
                <font-awesome-icon icon="user" class="w-4 h-4 mr-2"></font-awesome-icon>
                Basic Profile
            </inertia-link>
            <inertia-link v-if="can('clients.notes')"
                          class="w-full border-t border-gray-100 font-medium text-gray-600 py-2 px-4 w-full block hover:bg-gray-100 transition duration-150"
                          :class="{'bg-gray-100': route().current('clients.notes.index')}"
                          :href="route('clients.notes.index',client.id)">
                <font-awesome-icon icon="bookmark" class="w-4 h-4 mr-2"></font-awesome-icon>
                Notes
            </inertia-link>
            <inertia-link v-if="can('clients.files.index')"
                          class="w-full border-t border-gray-100 font-medium text-gray-600 py-2 px-4 w-full block hover:bg-gray-100 transition duration-150"
                          :class="{'bg-gray-100': route().current('clients.files.*')}"
                          :href="route('clients.files.index',client.id)">
                <font-awesome-icon icon="folder" class="w-4 h-4 mr-2"></font-awesome-icon>
                Files
            </inertia-link>
        </div>
        <div class="p-5 border-t flex">
            <inertia-link v-if="can('clients.update')" :href="route('clients.edit',client.id)"
                          class="btn btn-primary py-1 px-2">Edit
            </inertia-link>
            <button type="button" v-if="can('clients.destroy')" @click="deleteAction"
                    class="btn btn-danger py-1 px-2 ml-auto">Delete
            </button>
        </div>
    </div>
    <jet-confirmation-modal :show="confirmingClientDeletion" @close="confirmingClientDeletion = false">
        <template #title>
            Delete Record
        </template>

        <template #content>
            Are you sure you want to delete your account? Once the record is deleted, all of its resources and
            data will be permanently deleted.
        </template>

        <template #footer>
            <jet-secondary-button @click.native="confirmingClientDeletion = false">
                Nevermind
            </jet-secondary-button>

            <jet-danger-button class="ml-2" @click.native="destroy" :class="{ 'opacity-25': processing }"
                               :disabled="processing">
                Delete Record
            </jet-danger-button>
        </template>
    </jet-confirmation-modal>
</template>

<script>
import JetConfirmationModal from '@/Jetstream/ConfirmationModal.vue'
import JetDangerButton from '@/Jetstream/DangerButton.vue'
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
        JetSecondaryButton,
    },
    props: {
        client: Object,
    },
    data() {
        return {
            processing: false,
            confirmingClientDeletion: false,

        }
    },
    methods: {
        deleteAction() {
            this.confirmingClientDeletion = true
        },
        destroy() {

            this.$inertia.delete(this.route('clients.destroy', this.client.id))
            this.confirmingClientDeletion = false
        },
    },
}
</script>

<style scoped>

</style>
