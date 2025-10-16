<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                <inertia-link class="text-indigo-400 hover:text-indigo-600"
                              :href="route('communication.sms_gateways.index')">SMS Gateways
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
                                       required autofocus/>
                            <jet-input-error :message="form.errors.name" class="mt-2"/>
                        </div>
                        <div v-if="!smsGateway.is_system">
                            <div class="">
                                <jet-label for="to_name" value="Phone Number Field Name"/>
                                <jet-input id="to_name" type="text" class="mt-1 block w-full" required
                                           v-model="form.to_name"/>
                                <jet-input-error :message="form.errors.to_name" class="mt-2"/>
                            </div>
                            <div class="">
                                <jet-label for="msg_name" value="Message Field Name"/>
                                <jet-input id="msg_name" type="text" class="mt-1 block w-full" required
                                           v-model="form.msg_name"/>
                                <jet-input-error :message="form.errors.msg_name" class="mt-2"/>
                            </div>
                            <div class="">
                                <jet-label for="url"
                                           value="URL (Remaining part of the url excluding Phone Number Field Name & Message Field Name) "/>
                                <jet-input id="url" type="text" class="mt-1 block w-full" required v-model="form.url"/>
                                <jet-input-error :message="form.errors.url" class="mt-2"/>
                            </div>
                        </div>
                        <div>
                            <jet-label for="active">
                                <div class="flex items-center">
                                    <jet-checkbox name="active" id="active" v-model:checked="form.active"/>
                                    <div class="ml-2">
                                        Active
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
        smsGateway: Object
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
                name: this.smsGateway.name,
                url: this.smsGateway.url,
                to_name: this.smsGateway.to_name,
                msg_name: this.smsGateway.msg_name,
                description: this.smsGateway.description,
                active: this.smsGateway.active,
            }),
            pageTitle: "Edit SMS Gateways",
            pageDescription: "Edit SMS Gateways",
        }

    },
    methods: {
        submit() {
            this.form.put(this.route('communication.sms_gateways.update', this.smsGateway.id), {})

        },

    }
}
</script>
<style scoped>

</style>
