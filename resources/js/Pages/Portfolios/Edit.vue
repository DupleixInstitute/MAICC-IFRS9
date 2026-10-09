<template>
    <app-layout title="Edit Portfolio" description="Change the name, description or status of this portfolio">
        <template #actions>
            <Link :href="route('portfolios.index')" class="secondary-btn">Back to portfolios</Link>
        </template>
        <portfolio-form :form="form" @submit="update"/>
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
    props: {
        portfolio: Object,
    },
    setup(props) {
        const form = useForm({
            name: props.portfolio.name,
            description: props.portfolio.description,
            active: props.portfolio.active,
        })

        function update() {
            form.put(route('portfolios.update', props.portfolio.id))
        }

        return { form, update }
    },
}
</script>
