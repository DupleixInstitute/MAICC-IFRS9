<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                <inertia-link class="text-indigo-400 hover:text-indigo-600" :href="route('users.index')">Users
                </inertia-link>
                <span class="text-indigo-400 font-medium">/</span> Edit
            </h2>
        </template>

        <div class="py-12">
            <div class=" mx-auto">
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-4">
                    <form @submit.prevent="submit" enctype="multipart/form-data">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2 mt-2">
                            <div>
                                <jet-label for="name" value="Name"/>
                                <jet-input id="name" type="text" class=" block w-full"
                                           v-model="form.name"
                                           required
                                           autofocus autocomplete="name"/>
                                <jet-input-error :message="form.errors.name" class="mt-2"/>
                            </div>
                            <div>
                                <jet-label for="external_id" value="External ID"/>
                                <jet-input id="external_id" type="text" class="block w-full"
                                           v-model="form.external_id"/>
                                <jet-input-error :message="form.errors.external_id" class="mt-2"/>

                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2 mt-2">
                            <div>
                                <jet-label for="gender" value="Gender"/>
                                <select
                                    class="border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm w-full"
                                    name="gender" v-model="form.gender" id="gender">
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                </select>
                                <jet-input-error :message="form.errors.gender" class="mt-2"/>
                            </div>

                            <div>
                                <jet-label for="mobile" value="Mobile"/>
                                <jet-input id="mobile" type="text" class=" block w-full" v-model="form.mobile"/>
                                <jet-input-error :message="form.errors.mobile" class="mt-2"/>

                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2 mt-2">
                            <div>
                                <jet-label for="tel" value="Tel"/>
                                <jet-input id="tel" type="text" class="block w-full" v-model="form.tel"/>
                                <jet-input-error :message="form.errors.tel" class="mt-2"/>

                            </div>
                            <div>
                                <jet-label for="zip" value="Zip"/>
                                <jet-input id="zip" type="text" class="block w-full" v-model="form.zip"/>
                                <jet-input-error :message="form.errors.zip" class="mt-2"/>

                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2 mt-2">
                            <div>
                                <jet-label for="role" value="Roles"/>
                                <Multiselect
                                    v-model="form.roles"
                                    mode="tags"
                                    :options="$page.props.roles"
                                />
                                <jet-input-error :message="form.errors.roles" class="mt-2"/>
                            </div>
                            <div>
                                <jet-label for="email" value="Email"/>
                                <jet-input id="email" type="email" class=" block w-full" v-model="form.email"
                                           required/>
                                <jet-input-error :message="form.errors.email" class="mt-2"/>

                            </div>
                            <div>
                                <jet-label for="group_email" value="Group Email"/>
                                <jet-input id="group_email" type="email" class=" block w-full" v-model="form.group_email"
                                           />
                                <jet-input-error :message="form.errors.group_email" class="mt-2"/>

                            </div>
                            <div>
                                <jet-label for="can_reassign" value="Can Reassign Applications to any other user in any role?"/>
                                <select class="border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm w-full"
                                name="can_reassign" v-model="form.can_reassign" id="can_reassign">
                                <option value="0">No</option>
                                <option value="1">Yes</option>
                            </select>
                                <jet-input-error :message="form.errors.can_reassign" class="mt-2"/>

                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2 mt-2">
                            <div>
                                <jet-label for="password" value="Password"/>
                                <jet-input id="password" type="password" class=" block w-full"
                                           v-model="form.password"
                                           autocomplete="new-password"/>
                                <jet-input-error :message="form.errors.password" class="mt-2"/>

                            </div>

                            <div>
                                <jet-label for="password_confirmation" value="Confirm Password"/>
                                <jet-input id="password_confirmation" type="password" class=" block w-full"
                                           v-model="form.password_confirmation" autocomplete="new-password"/>
                                <jet-input-error :message="form.errors.password_confirmation" class="mt-2"/>

                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2 mt-2">
                            <div>
                                <jet-label for="photo" value="Photo"/>
                                <file-input v-model="form.photo" class="block w-full" type="file"/>
                                <jet-input-error :message="form.errors.photo" class="mt-2"/>
                            </div>
                            <div>
                                <jet-label for="branch_id" value="Branch"/>
                                <Multiselect
                                    :searchable="true"
                                    v-model="form.branch_id"
                                    mode="single"
                                    :options="$page.props.branches"
                                    id="branch_id"
                                />
                                <jet-input-error :message="form.errors.branches" class="mt-2"/>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2 mt-2">
                            <div>
                                <jet-label for="active">
                                    <div class="flex items-center">
                                        <jet-checkbox name="active" id="active"
                                                      v-model:checked="form.active"/>
                                        <div class="ml-2">
                                            Active
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
        roles: Object,
        profile: Object,
        branches: Object,
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
    mounted() {

    },
    data() {
        return {
            form: this.$inertia.form({
                branch_id: this.profile.branch_id,
                name: this.profile.name,
                gender: this.profile.gender,
                email: this.profile.email,
                group_email: this.profile.group_email,
                password: null,
                password_confirmation: null,
                mobile: this.profile.mobile,
                tel: this.profile.tel,
                zip: this.profile.zip,
                external_id: this.profile.external_id,
                address: this.profile.address,
                photo: this.profile.photo,
                active: this.profile.active,
                roles: this.profile.selected_roles,
                can_reassign : this.profile.can_reassign
            }),
            pageTitle: "Edit User",
            pageDescription: "Edit User",
        }

    },
    methods: {
        submit() {
            this.form.put(this.route('users.update', this.profile.id), {
                onFinish: () => this.form.reset('password', 'password_confirmation'),
            })

        },

    }
}
</script>
<style scoped>

</style>
