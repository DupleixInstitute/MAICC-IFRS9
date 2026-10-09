<template>
    <div class="maiic-panel max-w-3xl">
        <form @submit.prevent="$emit('submit')">
            <div class="space-y-5 p-6">
                <div>
                    <label class="maiic-flabel" for="name">Name</label>
                    <input id="name" v-model="form.name" type="text" class="maiic-input" :class="{ 'border-red-400': form.errors.name }" required/>
                    <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                </div>
                <div>
                    <label class="maiic-flabel" for="description">Description</label>
                    <textarea id="description" v-model="form.description" rows="3" class="maiic-input" :class="{ 'border-red-400': form.errors.description }"></textarea>
                    <p v-if="form.errors.description" class="mt-1 text-xs text-red-600">{{ form.errors.description }}</p>
                </div>
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" v-model="form.active" class="rounded border-gray-300 text-maiic-600 focus:ring-maiic-500"/>
                    Active
                </label>
            </div>
            <div class="flex items-center justify-end gap-2 border-t border-gray-200 bg-gray-50 px-6 py-4">
                <Link :href="route('portfolios.index')" class="secondary-btn">Cancel</Link>
                <button type="submit" class="primary-btn" :disabled="form.processing">{{ form.processing ? 'Saving...' : 'Save portfolio' }}</button>
            </div>
        </form>
    </div>
</template>

<script>
import { Link } from '@inertiajs/vue3'

export default {
    components: { Link },
    props: {
        form: Object,
    },
    emits: ['submit'],
}
</script>
