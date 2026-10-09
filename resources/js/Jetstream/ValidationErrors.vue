<template>
    <!-- Form errors as one red box: icon, title, then each problem. -->
    <div v-if="hasErrors" class="flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 p-4" role="alert">
        <span class="flex h-9 w-9 flex-none items-center justify-center rounded-full bg-red-100 text-red-600">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M15 9l-6 6M9 9l6 6"/></svg>
        </span>
        <div class="min-w-0 flex-1 pt-0.5">
            <p class="text-sm font-bold text-red-900">
                {{ messages.length === 1 ? 'Please correct one thing' : `Please correct ${messages.length} things` }}
            </p>
            <ul class="mt-1.5 list-disc space-y-0.5 pl-5 text-sm text-red-800">
                <li v-for="(error, i) in messages" :key="i">{{ error }}</li>
            </ul>
        </div>
    </div>
</template>

<script>
    export default {
        computed: {
            errors() {
                return this.$page.props.errors || {}
            },
            messages() {
                const collect = (value) => (value && typeof value === 'object')
                    ? Object.values(value).flatMap(collect)
                    : [value]
                return collect(this.errors).filter(Boolean)
            },
            hasErrors() {
                return this.messages.length > 0;
            },
        }
    }
</script>
