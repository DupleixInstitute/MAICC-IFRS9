<template>
    <app-layout title="Edit Sector Type" description="Change the code, name or description of this sector type">
        <template #actions>
            <Link :href="route('industry_types.index')" class="secondary-btn">Back to sector types</Link>
        </template>
        <div class="maiic-panel max-w-3xl">
            <form @submit.prevent="submit">
                <div class="space-y-5 p-6">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div>
                            <label class="maiic-flabel" for="code">Sector code</label>
                            <input id="code" v-model="form.code" type="text" class="maiic-input"/>
                            <p v-if="form.errors.code" class="mt-1 text-xs text-red-600">{{ form.errors.code }}</p>
                            <p v-else class="mt-1 text-xs text-gray-500">As it appears in the loan book's industry_code column.</p>
                        </div>
                        <div class="md:col-span-2">
                            <label class="maiic-flabel" for="name">Name</label>
                            <input id="name" v-model="form.name" type="text" class="maiic-input" required/>
                            <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                        </div>
                    </div>
                    <div>
                        <label class="maiic-flabel" for="description">Description</label>
                        <textarea id="description" v-model="form.description" rows="3" class="maiic-input"></textarea>
                        <p v-if="form.errors.description" class="mt-1 text-xs text-red-600">{{ form.errors.description }}</p>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-2 border-t border-gray-200 bg-gray-50 px-6 py-4">
                    <Link :href="route('industry_types.index')" class="secondary-btn">Cancel</Link>
                    <button type="submit" class="primary-btn" :disabled="form.processing">{{ form.processing ? 'Saving...' : 'Save sector type' }}</button>
                </div>
            </form>
        </div>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link } from '@inertiajs/vue3'

export default {
    components: { AppLayout, Link },
    props: {
        type: Object,
    },
    data() {
        return {
            form: this.$inertia.form({
                code: this.type.code,
                name: this.type.name,
                description: this.type.description,
            }),
        }
    },
    methods: {
        submit() {
            this.form.put(this.route('industry_types.update', this.type.id))
        },
    },
}
</script>
