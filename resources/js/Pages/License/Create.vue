<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                <inertia-link class="text-indigo-400 hover:text-indigo-600" :href="route('vitals.index')">Vital
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
                                           required/>
                                <jet-input-error :message="form.errors.name" class="mt-2"/>
                            </div>
                            <div>
                                <jet-label for="lower_value" value="Lower Value"/>
                                <jet-input id="lower_value" type="text" class="mt-1 block w-full"
                                           v-model="form.lower_value"/>
                                <jet-input-error :message="form.errors.lower_value" class="mt-2"/>
                            </div>
                            <div>
                                <jet-label for="upper_value" value="Upper Value"/>
                                <jet-input id="upper_value" type="text" class="mt-1 block w-full"
                                           v-model="form.upper_value"/>
                                <jet-input-error :message="form.errors.upper_value" class="mt-2"/>
                            </div>
                            <div>
                                <jet-label for="normal_value" value="Normal Value"/>
                                <jet-input id="normal_value" type="text" class="mt-1 block w-full"
                                           v-model="form.normal_value"/>
                                <jet-input-error :message="form.errors.normal_value" class="mt-2"/>
                            </div>
                            <div>
                                <jet-label for="is_integer">
                                    <div class="flex items-center">
                                        <jet-checkbox name="is_integer" id="is_integer"
                                                      v-model:checked="form.is_integer"/>
                                        <div class="ml-2">
                                            Integer
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
                name: null,
                vital_position: null,
                lower_value: null,
                upper_value: null,
                normal_value: null,
                description: null,
                is_integer: false,
            }),
            pageTitle: "Create Vital",
            pageDescription: "Create Vital",
        }

    },
    methods: {
        submit() {
            this.form.post(this.route('vitals.store'), {})

        },

    }
}
</script>
<style scoped>

</style>
