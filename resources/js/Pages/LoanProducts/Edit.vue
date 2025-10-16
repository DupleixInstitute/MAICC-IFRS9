<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                <inertia-link class="text-indigo-400 hover:text-indigo-600" :href="route('loan_products.index')">Transition Profiles
                </inertia-link>
                <span class="text-indigo-400 font-medium">/</span> Edit
            </h2>
        </template>
        <div class=" mx-auto">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-4">
                <form @submit.prevent="submit" enctype="multipart/form-data">
                    <div>
                        <jet-label for="name" value="Name"/>
                        <jet-input id="name" type="text" class="block w-full"
                                   v-model="form.name"
                                   required/>
                        <jet-input-error :message="form.errors.name" class="mt-2"/>
                    </div>
                    <div class="mt-4">
                        <jet-label for="name" value="Grand Score"/>
                        <jet-input id="name" type="text" class="block w-full"
                                   v-model="form.score"
                                   required/>
                        <jet-input-error :message="form.errors.score" class="mt-2"/>
                    </div>
                    <div class="mt-4">
                        <jet-label for="loan_product_category_id" value="Category"/>
                        <Multiselect
                            id="loan_product_category_id"
                            v-model="form.loan_product_category_id"
                            :options="categories"
                        />
                        <jet-input-error :message="form.errors.loan_product_category_id" class="mt-2"/>
                    </div>

                    <div class="mt-4">
                        <jet-label for="description" value="Description"/>
                        <textarea-input id="description" class="mt-1 block w-full"
                                        v-model="form.description"/>
                        <jet-input-error :message="form.errors.description" class="mt-2"/>

                    </div>

                    <div class="grid grid-cols-1  mt-4">
                        <jet-label for="active">
                            <div class="flex items-center">
                                <jet-checkbox name="active" id="active" v-model:checked="form.active"/>
                                <div class="ml-2">
                                    Active
                                </div>
                            </div>
                        </jet-label>
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
import SelectInput from "@/Jetstream/SelectInput.vue"



export default {
    props: {
        categories: Object,
        product: Object,
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
        SelectInput
    },
    data() {
        return {
            form: this.$inertia.form({
                '_method': 'PUT',
                loan_product_category_id: this.product.loan_product_category_id,
                name: this.product.name,
                score: this.product.score,
                description: this.product.description ,
                active: this.product.active,
            }),
             pageTitle: "Edit Product",
            pageDescription: "Edit Product",
        }

    },
    methods: {
        submit() {
            this.form.post(this.route('loan_products.update', this.product.id), {})
        },

    },
    computed: {

    }
}
</script>
<style scoped>

</style>
