<template>
    <!-- Financial period fields, shared by Add and Edit. -->
    <div class="grid grid-cols-1 gap-5 md:grid-cols-3">
        <div>
            <label for="name" class="maiic-flabel">Name (optional)</label>
            <input id="name" v-model="form.name" type="text" class="maiic-input" placeholder="e.g. FY 2026" autofocus>
            <p v-if="form.errors.name" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.name }}</p>
        </div>
        <div>
            <label for="start_date" class="maiic-flabel">Start date</label>
            <input id="start_date" v-model="form.start_date" type="date" class="maiic-input" required>
            <p v-if="form.errors.start_date" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.start_date }}</p>
        </div>
        <div>
            <label for="end_date" class="maiic-flabel">End date (optional)</label>
            <input id="end_date" v-model="form.end_date" type="date" class="maiic-input">
            <p v-if="form.errors.end_date" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.end_date }}</p>
        </div>
        <div class="md:col-span-3">
            <label for="description" class="maiic-flabel">Description (optional)</label>
            <textarea id="description" v-model="form.description" rows="3" class="maiic-input"></textarea>
            <p v-if="form.errors.description" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.description }}</p>
        </div>
    </div>
</template>

<script>
export default {
    props: { form: { type: Object, required: true } },
}
</script>
