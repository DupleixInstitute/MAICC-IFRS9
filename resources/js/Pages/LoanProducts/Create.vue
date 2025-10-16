<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                <inertia-link class="text-indigo-400 hover:text-indigo-600" :href="route('loan_products.index')">Transition Profiles
                </inertia-link>
                <span class="text-indigo-400 font-medium">/</span> Create
            </h2>
        </template>
        <div class="mx-auto">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-4">
                <form @submit.prevent="submit">
                    <div>
                        <jet-label for="name" value="Profile name"/>
                        <jet-input id="name" type="text" class="block w-full"
                                   v-model="form.name"
                                   required/>
                        <jet-input-error :message="form.errors.name" class="mt-2"/>
                    </div>

                    <div class="mt-4">
                        <jet-label value="Aggregation criteria"/>
                        <div class="flex items-center space-x-4">
                            <label class="inline-flex items-center">
                                <input type="radio" v-model="form.aggregation_criteria" value="count" class="form-radio">
                                <span class="ml-2">Count</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="radio" v-model="form.aggregation_criteria" value="balance" class="form-radio">
                                <span class="ml-2">Balance</span>
                            </label>
                        </div>
                        <jet-input-error :message="form.errors.aggregation_criteria" class="mt-2"/>
                    </div>

                    <div class="mt-4">
                        <jet-label for="end_transition_profile_id" value="Select different End Transition Column"/>
                        <select v-model="form.end_transition_profile_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="">None or Score</option>
                            <option v-for="profile in transitionProfiles" :key="profile.id" :value="profile.id">
                                {{ profile.name }}
                            </option>
                        </select>
                        <jet-input-error :message="form.errors.end_transition_profile_id" class="mt-2"/>
                    </div>

                    <div class="mt-4 space-y-4">
                        <div class="flex items-center">
                            <jet-checkbox name="is_default" id="is_default" v-model:checked="form.is_default"/>
                            <jet-label for="is_default" class="ml-2" value="Default profile"/>
                        </div>

                        <div class="flex items-center">
                            <jet-checkbox name="is_per_portfolio" id="is_per_portfolio" v-model:checked="form.is_per_portfolio"/>
                            <jet-label for="is_per_portfolio" class="ml-2" value="Calculated per portfolio groups"/>
                        </div>

                        <div class="flex items-center">
                            <jet-checkbox name="is_paid" id="is_paid" v-model:checked="form.is_paid"/>
                            <jet-label for="is_paid" class="ml-2" value="Include Paid Accounts"/>
                        </div>

                        <div class="flex items-center">
                            <jet-checkbox name="is_lgd" id="is_lgd" v-model:checked="form.is_lgd"/>
                            <jet-label for="is_lgd" class="ml-2" value="Adopt for lgd - recovery rates"/>
                        </div>
                    </div>

                    <div class="flex items-center justify-end mt-4">
                        <jet-button class="ml-4" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                            Create Profile
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
import JetButton from "@/Jetstream/Button.vue"
import JetInput from "@/Jetstream/Input.vue"
import JetInputError from "@/Jetstream/InputError.vue"
import JetCheckbox from "@/Jetstream/Checkbox.vue"
import JetLabel from "@/Jetstream/Label.vue"

export default {
    props: {
        transitionProfiles: Array
    },
    components: {
        AppLayout,
        JetButton,
        JetInput,
        JetCheckbox,
        JetLabel,
        JetInputError
    },
    data() {
        return {
            form: this.$inertia.form({
                name: '',
                aggregation_criteria: 'balance',
                is_default: false,
                end_transition_profile_id: '',
                is_per_portfolio: true,
                is_paid: true,
                is_lgd: true
            }),
            pageTitle: "Create Profile",
            pageDescription: "Create Profile",
        }
    },
    methods: {
        submit() {
            this.form.post(this.route('loan_products.store'))
        },
    },
    computed: {}
}
</script>
<style scoped>

</style>
