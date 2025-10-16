<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                <inertia-link class="text-indigo-400 hover:text-indigo-600"
                              :href="route('accounting.financial_periods.index')">Financial Periods
                </inertia-link>
                <span class="text-indigo-400 font-medium">/</span> Edit
            </h2>
        </template>
        <div class=" mx-auto">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-4">
                <form @submit.prevent="submit">
                    <div class="grid grid-cols-1 gap-2">
                        <div class="">
                            <jet-label for="name" value="Name"/>
                            <jet-input id="name" type="text" class="mt-1 block w-full" v-model="form.name"
                                       autofocus/>
                            <jet-input-error :message="form.errors.name" class="mt-2" required/>
                        </div>
                        <div>
                            <jet-label for="start_date" value="Start Date"/>
                            <flat-pickr
                                v-model="form.start_date"
                                class="form-control w-full"
                                placeholder="Select date"
                                name="start_date">
                            </flat-pickr>
                            <jet-input-error :message="form.errors.start_date" class="mt-2"/>

                        </div>
                        <div>
                            <jet-label for="start_date" value="End Date"/>
                            <flat-pickr
                                v-model="form.end_date"
                                class="form-control w-full"
                                placeholder="Select date"
                                name="end_date">
                            </flat-pickr>
                            <jet-input-error :message="form.errors.end_date" class="mt-2"/>

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
        financialPeriod: Object,
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
                name: this.financialPeriod.name,
                start_date: this.financialPeriod.start_date,
                end_date: this.financialPeriod.end_date,
                description: this.financialPeriod.description,
            }),
            pageTitle: "Edit Financial Period",
            pageDescription: "Edit Financial Period",
        }

    },
    methods: {
        submit() {
            this.form.put(this.route('accounting.financial_periods.update', this.financialPeriod.id), {})

        },

    }
}
</script>
<style scoped>

</style>
