<template>
    <div class="max-w-3xl bg-white rounded shadow overflow-hidden">
        <form @submit.prevent="$emit('submit')">
            <div class="p-8 -mr-6 -mb-8 flex flex-wrap">
                <div class="pr-6 pb-8 w-full">
                    <label class="form-label" for="name">Name</label>
                    <input
                        id="name"
                        v-model="form.name"
                        type="text"
                        class="form-input mt-1 block w-full"
                        :class="{ 'error': form.errors.name }"
                    />
                    <div v-if="form.errors.name" class="form-error">{{ form.errors.name }}</div>
                </div>

                <div class="pr-6 pb-8 w-full">
                    <label class="form-label" for="description">Description</label>
                    <textarea
                        id="description"
                        v-model="form.description"
                        class="form-textarea mt-1 block w-full"
                        rows="3"
                        :class="{ 'error': form.errors.description }"
                    ></textarea>
                    <div v-if="form.errors.description" class="form-error">{{ form.errors.description }}</div>
                </div>

                <div class="pr-6 pb-8 w-full">
                    <label class="flex items-center">
                        <input
                            type="checkbox"
                            class="form-checkbox"
                            v-model="form.active"
                        />
                        <span class="ml-2">Active</span>
                    </label>
                </div>
            </div>
            <div class="px-8 py-4 bg-gray-50 border-t border-gray-100 flex justify-end items-center">
                <button
                    type="submit"
                    class="btn-indigo"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Saving...' : 'Save Portfolio' }}
                </button>
            </div>
        </form>
    </div>
</template>

<script>
export default {
    props: {
        form: Object,
    },
    emits: ['submit'],
}
</script>

<style scoped>
.form-label {
    @apply block text-gray-700 font-bold;
}
.form-input, .form-textarea {
    @apply mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50;
}
.form-checkbox {
    @apply rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50;
}
.form-error {
    @apply text-red-500 mt-1 text-sm;
}
.btn-indigo {
    @apply px-6 py-3 bg-indigo-600 text-white text-sm font-semibold rounded-md shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50;
}
.error {
    @apply border-red-500 focus:ring-red-200;
}
</style>
