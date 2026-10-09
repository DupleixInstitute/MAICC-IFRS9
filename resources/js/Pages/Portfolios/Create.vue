<template>
    <app-layout title="New Portfolio" description="Add a portfolio that loan books can be imported into">
        <template #actions>
            <Link :href="route('portfolios.index')" class="secondary-btn">Back to portfolios</Link>
        </template>
        <portfolio-form :form="form" @submit="store"/>
    </app-layout>
</template>

<script>
import { Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import PortfolioForm from './Form.vue'

export default {
    components: {
        AppLayout,
        Link,
        PortfolioForm,
    },
    setup() {
        const form = useForm({
            name: '',
            description: '',
            active: true,
        })

        function store() {
            form.post(route('portfolios.store'))
        }

        return { form, store }
    },
}
</script>
