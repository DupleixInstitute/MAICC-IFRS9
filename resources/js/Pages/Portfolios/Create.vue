<template>
    <app-layout title="Create Portfolio">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                <Link class="text-indigo-400 hover:text-indigo-600" :href="route('portfolios.index')">Portfolios</Link>
                <span class="text-indigo-400 font-medium"> / </span>
                Create
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="flex flex-col">
                    <div class="overflow-x-auto sm:-mx-6 lg:-mx-8">
                        <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                            <portfolio-form
                                :form="form"
                                @submit="store"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
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
