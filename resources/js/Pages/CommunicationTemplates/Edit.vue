<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                <inertia-link class="text-indigo-400 hover:text-indigo-600"
                              :href="route('communication.templates.index')">
                    Communication Templates
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
                                       :readonly="template.is_system" required/>
                            <jet-input-error :message="form.errors.name" class="mt-2" required/>
                        </div>
                        <div>
                            <jet-label for="type" value="Type"/>
                            <select :disabled="template.is_system"
                                    class="border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm w-full disabled:bg-slate-50 disabled:text-slate-500 disabled:border-slate-200 disabled:shadow-none"
                                    name="gender" v-model="form.type" id="type" required>
                                <option value="email">Email</option>
                                <option value="sms">SMS</option>
                            </select>
                            <jet-input-error :message="form.errors.type" class="mt-2"/>
                        </div>
                        <div v-if="form.type==='email'">
                            <jet-label for="subject" value="Subject"/>
                            <jet-input id="subject" type="text" class="mt-1 block w-full" v-model="form.subject"
                                       required/>
                            <jet-input-error :message="form.errors.subject" class="mt-2" required/>
                        </div>

                        <div>
                            <jet-label for="active">
                                <div class="flex items-center">
                                    <jet-checkbox name="active" id="active"
                                                  v-model:checked="form.active" :disabled="template.is_system"/>
                                    <div class="ml-2">
                                        Active
                                    </div>
                                </div>
                            </jet-label>
                        </div>
                        <div v-if="form.type==='sms'">
                            <jet-label for="description" value="Description"/>
                            <textarea-input id="description" class="mt-1 block w-full"
                                            v-model="form.description"/>
                            <jet-input-error :message="form.errors.description" class="mt-2"/>

                        </div>
                        <div v-if="form.type==='email'">
                            <jet-label for="description" value="Description"/>
                            <editor
                                v-model="form.description"
                                api-key="no-api-key"
                                :init="editorConfig"
                            />
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
import Editor from "@tinymce/tinymce-vue";


export default {
    props: {
        template: Object
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
        Editor,
    },
    data() {
        return {
            form: this.$inertia.form({
                name: this.template.name,
                type: this.template.type,
                subject: this.template.subject,
                active: this.template.active,
                description: this.template.description,
            }),
            pageTitle: "Edit Template",
            pageDescription: "Edit Template",
        }

    },
    methods: {
        submit() {
            this.form.put(this.route('communication.templates.update', this.template.id), {})

        },

    },
    watch: {}
}
</script>
<style scoped>

</style>
