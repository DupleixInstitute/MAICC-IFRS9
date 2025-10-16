<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                <inertia-link class="text-indigo-400 hover:text-indigo-600" :href="route('tariffs.index')">Tariffs
                </inertia-link>
                <span class="text-indigo-400 font-medium">/</span> Create
            </h2>
        </template>
        <div class=" mx-auto">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-4">
                <form @submit.prevent="submit">
                    <div class="grid grid-cols-1 gap-2">
                        <div>
                            <jet-label for="code" value="Code"/>
                            <jet-input id="code" type="text" class="mt-1 block w-full"
                                       v-model="form.code"
                                       autofocus/>
                            <jet-input-error :message="form.errors.code" class="mt-2"/>
                        </div>
                        <div class="">
                            <jet-label for="name" value="Name"/>
                            <jet-input id="name" type="text" class="mt-1 block w-full" v-model="form.name"
                                       required/>
                            <jet-input-error :message="form.errors.name" class="mt-2"/>
                        </div>
                        <div class="">
                            <jet-label for="amount" value="Amount"/>
                            <jet-input id="amount" type="text" class="mt-1 block w-full" v-model="form.amount"
                                       required/>
                            <jet-input-error :message="form.errors.amount" class="mt-2"/>
                        </div>
                        <div class="">
                            <jet-label for="co_payer_percent" value="Copayer %"/>
                            <jet-input id="co_payer_percent" type="text" class="mt-1 block w-full"
                                       v-model="form.co_payer_percent"/>
                            <jet-input-error :message="form.errors.co_payer_percent" class="mt-2"/>
                        </div>
                        <div class="">
                            <jet-label for="maximum_quantity" value="Maximum Quantity"/>
                            <jet-input id="maximum_quantity" type="number" class="mt-1 block w-full"
                                       v-model="form.maximum_quantity"/>
                            <jet-input-error :message="form.errors.maximum_quantity" class="mt-2"/>
                        </div>
                        <div>
                            <jet-label for="is_claimable">
                                <div class="flex items-center">
                                    <jet-checkbox name="is_claimable" id="is_claimable"
                                                  v-model:checked="form.is_claimable"/>
                                    <div class="ml-2">
                                        Claimable
                                    </div>
                                </div>
                            </jet-label>
                        </div>
                        <div>
                            <jet-label for="description" value="Description"/>
                            <textarea-input id="description" class="mt-1 block w-full"
                                            v-model="form.description"/>
                            <jet-input-error :message="form.errors.description" class="mt-2"/>

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


export default {
    props: {
        countries: Object,
        tariff: Object,
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
        TextareaInput,

    },
    data() {
        return {
            form: this.$inertia.form({
                name: this.tariff.name,
                code: this.tariff.code,
                film_code: this.tariff.film_code,
                cash_amount: this.tariff.cash_amount,
                co_payer_amount: this.tariff.co_payer_amount,
                co_payer_percent: this.tariff.co_payer_percent,
                amount: this.tariff.amount,
                maximum_quantity: this.tariff.maximum_quantity,
                description: this.tariff.description,
                is_pharmacy: this.tariff.is_pharmacy,
                is_claimable: this.tariff.is_claimable,
                needs_approval: this.tariff.needs_approval,
            }),
            pageTitle: "Edit Tariff",
            pageDescription: "Edit Tariff",
        }

    },
    methods: {
        submit() {
            this.form.put(this.route('tariffs.update', this.tariff.id), {})

        },

    }
}
</script>
<style scoped>

</style>
