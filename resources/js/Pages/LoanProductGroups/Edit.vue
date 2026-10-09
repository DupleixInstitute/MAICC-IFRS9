<template>
    <app-layout title="Edit Product Group" description="Change the name or description of this product group">
        <template #actions>
            <Link :href="route('groups.index')" class="secondary-btn">Back to product groups</Link>
        </template>
        <div class="maiic-panel max-w-3xl">
            <form @submit.prevent="submit">
                <div class="space-y-5 p-6">
                    <div>
                        <label class="maiic-flabel" for="name">Name</label>
                        <input id="name" v-model="form.name" type="text" class="maiic-input" required/>
                        <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label class="maiic-flabel" for="description">Description</label>
                        <textarea id="description" v-model="form.description" rows="3" class="maiic-input"></textarea>
                        <p v-if="form.errors.description" class="mt-1 text-xs text-red-600">{{ form.errors.description }}</p>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-2 border-t border-gray-200 bg-gray-50 px-6 py-4">
                    <Link :href="route('groups.index')" class="secondary-btn">Cancel</Link>
                    <button type="submit" class="primary-btn" :disabled="form.processing">{{ form.processing ? 'Saving...' : 'Save product group' }}</button>
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
        group: Object,
    },
    data() {
        return {
            form: this.$inertia.form({
                name: this.group.name,
                description: this.group.description,
            }),
        }
    },
    methods: {
        submit() {
            this.form.put(this.route('groups.update', this.group.id))
        },
    },
}
</script>
