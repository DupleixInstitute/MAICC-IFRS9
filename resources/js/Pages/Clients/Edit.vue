<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                <inertia-link class="text-indigo-400 hover:text-indigo-600" :href="route('clients.index')">Clients
                </inertia-link>
                <span class="text-indigo-400 font-medium">/</span> Edit
            </h2>
        </template>
        <div class=" mx-auto">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-4">
                <form @submit.prevent="submit" enctype="multipart/form-data">
                    <div class="mb-2">
                        <jet-label for="external_id" value="CIF"/>
                        <jet-input id="external_id" type="text" class="block w-full"
                                   v-model="form.external_id"/>
                        <jet-input-error :message="form.errors.external_id" class="mt-2"/>
                    </div>
                    <div class="mb-2">
                        <jet-label for="name" value="Name"/>
                        <jet-input id="name" type="text" class="block w-full"
                                   v-model="form.name"
                                   required
                                   autofocus autocomplete="name"/>
                        <jet-input-error :message="form.errors.name" class="mt-2"/>
                    </div>
                    <div class="mb-2">
                        <jet-label for="mobile" value="Mobile"/>
                        <jet-input id="mobile" type="text" class="block w-full" v-model="form.mobile"/>
                        <jet-input-error :message="form.errors.mobile" class="mt-2"/>
                    </div>
                    <div class="mb-2">
                        <jet-label for="type" value="Type"/>
                        <select
                            class="border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm w-full"
                            name="type" v-model="form.type" id="type" required>
                            <option value="individual">Individual</option>
                            <option value="corporate">Corporate</option>
                        </select>
                        <jet-input-error :message="form.errors.type" class="mt-2"/>
                    </div>
                    <div class="mb-2">
                        <jet-label for="status" value="Status"/>
                        <select
                            class="border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm w-full"
                            name="status" v-model="form.status" id="status" required>
                            <option value="pending">Pending</option>
                            <option value="inactive">Inactive</option>
                            <option value="active">Active</option>
                            <option value="archived">Archived</option>
                        </select>
                        <jet-input-error :message="form.errors.status" class="mt-2"/>
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
import JetLabel from "@/Jetstream/Label.vue";

export default {
    props: {
        client: Object,
    },
    components: {
        AppLayout,
        JetButton,
        JetInput,
        JetLabel,
        JetInputError,
    },
    data() {
        return {
            form: this.$inertia.form({
                '_method': 'PUT',
                external_id: this.client.external_id,
                name: this.client.name,
                mobile: this.client.mobile,
                type: 'individual',
                status: 'active',
            }),
            pageTitle: "Edit Client",
            pageDescription: "Edit Client",
        }
    },
    methods: {
        submit() {
            this.form.post(this.route('clients.update', this.client.id))
        },
    }
}
</script>
<style scoped>

</style>
