<template>
    <!-- A field's validation message, under the field: red, with a small icon. -->
    <div v-show="message">
        <p class="flex items-start gap-1.5 text-sm font-medium text-red-600" role="alert">
            <svg class="mt-0.5 h-4 w-4 flex-none" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a1 1 0 00-1 1v4a1 1 0 002 0V6a1 1 0 00-1-1zm0 9a1 1 0 100 2 1 1 0 000-2z" clip-rule="evenodd"/>
            </svg>
            <span>{{ Array.isArray(message) ? message[0] : message }}</span>
        </p>
    </div>
</template>

<script>
    export default {
        props: ['message']
    }
</script>
