<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                <inertia-link class="text-indigo-400 hover:text-indigo-600" :href="route('currencies.index')">Currencies
                </inertia-link>
                <span class="text-indigo-400 font-medium">/</span> Create
            </h2>
        </template>

        <div class="py-12">
            <div class=" mx-auto">
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-4">
                    <form @submit.prevent="submit">
                        <div class="grid grid-cols-1 gap-2">
                            <div class="">
                                <jet-label for="name" value="Name"/>
                                <jet-input id="name" type="text" class="mt-1 block w-full" v-model="form.name"
                                           required autofocus/>
                                <jet-input-error :message="form.errors.name" class="mt-2"/>
                            </div>
                            <div>
                                <jet-label for="code" value="Code"/>
                                <jet-input id="code" type="text" class="mt-1 block w-full"
                                           v-model="form.code"/>
                                <jet-input-error :message="form.errors.code" class="mt-2"/>
                            </div>
                            <div class="">
                                <jet-label for="symbol" value="Symbol"/>
                                <jet-input id="symbol" type="text" class="mt-1 block w-full" v-model="form.symbol"/>
                                <jet-input-error :message="form.errors.symbol" class="mt-2"/>
                            </div>
                            <div class="">
                                <jet-label for="decimals" value="Decimals"/>
                                <jet-input id="decimals" type="text" class="mt-1 block w-full" v-model="form.decimals"/>
                                <jet-input-error :message="form.errors.decimals" class="mt-2"/>
                            </div>
                            <div class="">
                                <jet-label for="xrate" value="Exchange Rate"/>
                                <jet-input id="xrate" type="text" class="mt-1 block w-full" v-model="form.xrate"/>
                                <jet-input-error :message="form.errors.xrate" class="mt-2"/>
                            </div>
                            <div>
                                <jet-label for="active">
                                    <div class="flex items-center">
                                        <jet-checkbox name="active" id="active"  v-model:checked="form.active" />
                                        <div class="ml-2">
                                            Active
                                        </div>
                                    </div>
                                </jet-label>
                            </div>
                            <div>
                                <jet-label for="description" value="Description"/>
                                <textarea-input id="description" class="mt-1 block w-full"
                                                v-model="form.description" />
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
        currency: Object
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
                name: this.currency.name,
                code: this.currency.code,
                symbol: this.currency.symbol,
                decimals: this.currency.decimals,
                xrate: this.currency.xrate,
                international_code: this.currency.international_code,
                active: this.currency.active,
                is_default: this.currency.is_default,
            }),
            pageTitle: "Edit Currency",
            pageDescription: "Edit Currency",
        }

    },
    methods: {
        submit() {
            this.form.put(this.route('currencies.update',this.currency.id), {})

        },

    }
}
</script>
<style scoped>

</style>
