<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                <inertia-link class="text-indigo-400 hover:text-indigo-600" :href="route('clients.index')">Clients
                </inertia-link>
                <span class="text-indigo-400 font-medium">/</span> Create
            </h2>
        </template>
        <div class="mx-auto">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-4">
                <form @submit.prevent="submit">
                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <jet-label for="external_id" value="Customer ID (CIF)" />
                            <jet-input
                                id="external_id"
                                type="text"
                                class="block w-full"
                                v-model="form.external_id"
                                required
                            />
                            <jet-input-error :message="form.errors.external_id" class="mt-2"/>
                        </div>

                        <div>
                            <jet-label for="name" value="Name"/>
                            <jet-input
                                id="name"
                                type="text"
                                class="block w-full"
                                v-model="form.name"
                                required
                            />
                            <jet-input-error :message="form.errors.name" class="mt-2"/>
                        </div>

                        <div>
                            <jet-label for="mobile" value="Phone Number"/>
                            <jet-input
                                id="mobile"
                                type="text"
                                class="block w-full"
                                v-model="form.mobile"
                                required
                                placeholder="07XXXXXXXX"
                            />
                            <p class="text-sm text-gray-500 mt-1">Format: 07XXXXXXXX (10 digits)</p>
                            <jet-input-error :message="form.errors.mobile" class="mt-2"/>
                        </div>
                    </div>

                    <div class="flex items-center justify-end mt-6">
                        <Link
                            :href="route('clients.index')"
                            class="inline-flex items-center px-4 py-2 bg-gray-300 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-400 active:bg-gray-500 focus:outline-none focus:border-gray-500 focus:ring focus:ring-gray-300 disabled:opacity-25 transition mr-2"
                        >
                            Cancel
                        </Link>
                        <jet-button
                            :class="{ 'opacity-25': form.processing }"
                            :disabled="form.processing"
                        >
                            Save
                        </jet-button>
                    </div>
                </form>
            </div>
        </div>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import JetButton from "@/Jetstream/Button.vue"
import JetInput from "@/Jetstream/Input.vue"
import JetInputError from "@/Jetstream/InputError.vue"
import JetLabel from "@/Jetstream/Label.vue"
import { Link } from '@inertiajs/vue3'

export default {
    components: {
        AppLayout,
        JetButton,
        JetInput,
        JetLabel,
        JetInputError,
        Link,
    },

    data() {
        return {
            form: this.$inertia.form({
                external_id: null,
                name: null,
                mobile: null,
            }),
        }
    },

    methods: {
        submit() {
            // Validate phone number format
            if (this.form.mobile && !this.form.mobile.match(/^07\d{8}$/)) {
                this.form.errors.mobile = 'Phone number must be 10 digits starting with 07'
                return
            }

            this.form.post(route('clients.store'))
        },
    },
}
</script>
