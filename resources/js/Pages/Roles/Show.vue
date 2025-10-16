<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                <inertia-link class="text-indigo-400 hover:text-indigo-600" :href="route('users.roles.index')">Roles
                </inertia-link>
                <span class="text-indigo-400 font-medium">/</span> Details
            </h2>
        </template>

        <div class="py-12">
            <div class=" mx-auto">
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-4">
                    <form @submit.prevent="submit" enctype="multipart/form-data">
                        <div class="grid grid-cols-2 space-x-4">
                            <div>
                                <jet-label for="name" value="Name"/>
                                <jet-input id="first_name" type="text" class="mt-1 block w-full"
                                           v-model="form.name"
                                           required readonly
                                           autofocus/>
                                <jet-input-error :message="form.errors.name" class="mt-2"/>
                            </div>
                            <div class="">
                                <jet-label for="display_name" value="Display Name"/>
                                <jet-input id="display_name" type="text" class="mt-1 block w-full"
                                           v-model="form.display_name" readonly
                                           required/>
                                <jet-input-error :message="form.errors.display_name" class="mt-2"/>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 space-x-4">
                            <div style="display: flex; align-items: center;">
                                <jet-label for="send_email_to_role_members" value="Send email to all in role level?"/>
                                <div>
                                    <select id="send_email_to_role_members" v-model="form.send_email_to_role_members" class="ml-4" disabled>
                                        <option value="1">Yes</option>
                                        <option value="0">No</option>
                                    </select>
                                    <jet-input-error :message="form.errors.send_email_to_role_members" class="mt-2"/>
                                </div>
                            </div>

                            <div class="">
                                <jet-label for="group_email" value="Group Email"/>
                                <jet-input id="group_email" type="text" class="mt-1 block w-full"
                                           v-model="form.group_email"
                                           readonly/>
                                <jet-input-error :message="form.errors.group_email" class="mt-2"/>
                            </div>
                            <div style="display: flex; align-items: center;">
                                <jet-label for="can_reassign" value="Reassign loan application to role members?"/>
                                <div>
                                    <select id="can_reassign" v-model="form.can_reassign" class="ml-4" disabled>
                                        <option value="1">Yes</option>
                                        <option value="0">No</option>
                                    </select>
                                    <jet-input-error :message="form.errors.reassign_to_role_members" class="mt-2"/>
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 mt-4">
                            <div v-for="(permissionCat,key) in permissions">
                                <h3 class="mt-4">{{ key }}</h3>
                                <div class="grid grid-cols-1 mt-2" v-for="permission in permissionCat">
                                    <jet-label :for="'permission_'+permission.id">
                                        <div class="flex items-center">
                                            <jet-checkbox :name="'permission_'+permission.id" :value="permission.name" :id="'permission_'+permission.id"  v-model:checked="form.permissions" readonly />
                                            <div class="ml-2">
                                                {{ permission.display_name }}
                                            </div>
                                        </div>
                                    </jet-label>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center justify-end mt-4">
                        </div>
                    </form>
                </div>
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
import Select from "@/Jetstream/Select.vue";
import FileInput from "@/Jetstream/FileInput.vue";


export default {
    props: {
        role: Object,
        permissions: Object,
    },
    components: {
        Select,
        AppLayout,
        JetButton,
        JetInput,
        JetCheckbox,
        JetLabel,
        JetInputError,
        FileInput,

    },
    data() {
        return {
            form: this.$inertia.form({
                name: this.role.name,
                display_name: this.role.display_name,
                permissions: this.role.permissions,
                send_email_to_role_members: this.role.send_email_to_role_members,
                group_email: this.role.group_email,
                can_reassign:this.role.can_reassign
            }),
            pageTitle: "Edit Role",
            pageDescription: "Edit Role",
        }

    },
    methods: {
        submit() {
           // this.form.put(this.route('users.roles.update',this.role.id), {})

        },

    },
    mounted()
    {
        console.log(this.role.can_reassign)

    }
}
</script>
<style scoped>

</style>
