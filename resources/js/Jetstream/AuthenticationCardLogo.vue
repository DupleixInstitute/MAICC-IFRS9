<template>
    <inertia-link :href="'/'">
        <h1 class=" text-3xl font-bold tracking-tight text-gray-900" v-if="!$page.props.logoUrl">{{ $page.props.companyName }}</h1>
        <img alt="Logo" v-if="$page.props.logoUrl" :src="$page.props.logoUrl" class="w-auto" :alt="$page.props.companyName"/>
    </inertia-link>
</template>
