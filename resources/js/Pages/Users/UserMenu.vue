<template>
    <div>
        <div class="flex justify-center">
            <img v-if="profile.profile_photo_url" :src="profile.profile_photo_url" alt=""
                 class="rounded-full mx-auto absolute -top-20 w-32 h-32 shadow-2xl border-4 border-white">
        </div>
        <div class="mt-16">
            <h1 class="font-bold text-center text-3xl text-gray-900">{{ profile.name }}</h1>
            <p class="text-center text-sm text-gray-400 font-medium capitalize">
                {{ profile.current_role }}
            </p>
            <p class="text-center text-sm text-gray-400 font-medium capitalize">
                {{ profile.gender }}
            </p>
            <p class="text-center text-sm text-gray-400 font-medium">
                {{ profile.mobile }}
            </p>
            <p class="text-center text-sm text-gray-400 font-medium">
                {{ profile.email }}
            </p>

        </div>
        <div class="w-full mt-5">
            <inertia-link
                class="w-full border-t border-gray-100 font-medium text-gray-600 py-2 px-4 w-full block hover:bg-gray-100 transition duration-150"
                :class="{'bg-gray-100': route().current('users.show')}"
                :href="route('users.show',profile.id)">
                <font-awesome-icon icon="user" class="w-4 h-4 mr-2"></font-awesome-icon>
                Basic Profile
            </inertia-link>
            <inertia-link v-if="profile.current_role==='doctor'"
                          class="w-full border-t border-gray-100 font-medium text-gray-600 py-2 px-4 w-full block hover:bg-gray-100 transition duration-150"
                          :class="{'bg-gray-100': route().current('users.availability_schedule.index')}"
                          :href="route('users.availability_schedule.index',profile.id)">
                <font-awesome-icon icon="calendar" class="w-4 h-4 mr-2"></font-awesome-icon>
                Availability Schedule
            </inertia-link>
        </div>
        <div class="p-5 border-t flex">
            <inertia-link v-if="can('users.update')" :href="route('users.edit',profile.id)"
                          class="btn btn-primary py-1 px-2">Edit
            </inertia-link>
            <button type="button" v-if="can('users.destroy')" @click="deleteAction" class="btn btn-danger py-1 px-2 ml-auto">Delete
            </button>
        </div>
    </div>
    <jet-confirmation-modal :show="confirmingUserDeletion" @close="confirmingUserDeletion = false">
        <template #title>
            Delete Record
        </template>

        <template #content>
            Are you sure you want to delete your account? Once the record is deleted, all of its resources and
            data will be permanently deleted.
        </template>

        <template #footer>
            <jet-secondary-button @click.native="confirmingUserDeletion = false">
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
        profile: Object,
    },
    data() {
        return {
            processing: false,
            confirmingUserDeletion: false,

        }
    },
    methods: {
        deleteAction() {
            this.confirmingUserDeletion = true
        },
        destroy() {

            this.$inertia.delete(this.route('users.destroy', this.profile.id))
            this.confirmingUserDeletion = false
        },
    },
}
</script>

<style scoped>

</style>
