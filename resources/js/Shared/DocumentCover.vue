<script setup>
import { ref } from 'vue'

// On-screen cover and document control for the four System Documentation
// pages (Ticket #011). Mirrors the PDF front matter: same data
// (App\Support\DocumentFrontMatter), same order: cover, document control,
// revision history, distribution, how to use, who reads what.
const props = defineProps({
    front: { type: Object, required: true },
})

const showControl = ref(false)

const controlRows = () => [
    ['Document', `${props.front.company} ${props.front.platform} ${props.front.title}`],
    ['Contract reference', `Implementation, Licence and Support Agreement (19 August 2026), ${props.front.deliverable}`],
    ['Version', props.front.version],
    ['Status', props.front.status],
    ['Prepared date', props.front.preparedDate],
    ['Owner', props.front.owner],
    ['Approved by', props.front.approvedBy],
    ['Classification', props.front.classification],
    ['Developed by', props.front.developedBy],
    ['Applies to', props.front.appliesTo],
    ['Review cycle', props.front.reviewCycle],
    ['Retention', props.front.retention],
]
</script>

<template>
    <div>
        <!-- Cover -->
        <section class="maiic-panel px-6 py-12 text-center sm:py-16">
            <img v-if="front.logoUrl" :src="front.logoUrl" alt="MAIIC" class="mx-auto mb-6 h-20 w-auto"/>
            <p class="text-[11px] font-semibold uppercase tracking-[0.3em] text-gray-500">{{ front.institution }}</p>
            <h1 class="mt-5 text-3xl font-extrabold text-maiic-900 sm:text-4xl">{{ front.platform }}</h1>
            <p class="mt-1 text-xl font-bold text-gray-900 sm:text-2xl">{{ front.title }}</p>
            <div class="mx-auto mt-5 flex h-1 w-32 overflow-hidden rounded-full">
                <span class="w-7/12 bg-maiic-600"></span>
                <span class="w-3/12 bg-maiicgold-500"></span>
                <span class="w-2/12 bg-red-600"></span>
            </div>
            <p class="mt-4 text-sm text-gray-500">{{ front.subtitle }}</p>

            <dl class="mx-auto mt-8 grid max-w-sm grid-cols-[auto,1fr] gap-x-6 gap-y-1.5 text-left text-sm">
                <dt class="text-gray-500">Version</dt><dd class="font-bold text-gray-900">{{ front.version }}</dd>
                <dt class="text-gray-500">Prepared</dt><dd class="font-bold text-gray-900">{{ front.preparedDate }}</dd>
                <dt class="text-gray-500">Owner</dt><dd class="font-bold text-gray-900">{{ front.owner }}</dd>
                <dt class="text-gray-500">Approved by</dt><dd class="font-bold text-gray-900">{{ front.approvedBy }}</dd>
                <dt class="text-gray-500">Classification</dt><dd class="font-bold text-amber-600">{{ front.classification }}</dd>
            </dl>

            <div class="mt-9">
                <img v-if="front.dupleixLogoUrl" :src="front.dupleixLogoUrl" alt="Dupleix Institute" class="mx-auto mb-2 h-16 w-auto"/>
                <p class="text-sm font-semibold text-gray-500">
                    System designed &amp; developed by <span class="text-base font-bold" style="color:#2B3990">Dupleix Institute</span>:
                    Risk <span class="font-bold" style="color:#F58220">|</span> Strategy <span class="font-bold" style="color:#F58220">|</span> Data Analytics
                </p>
            </div>
            <p class="mx-auto mt-6 max-w-2xl text-xs text-gray-400">
                This document is confidential and prepared for {{ front.company }} {{ front.audience }}. Do not distribute outside the approved distribution list.
            </p>

            <button @click="showControl = !showControl"
                    class="mt-8 inline-flex items-center gap-1.5 rounded-lg border border-maiic-300 bg-maiic-50 px-3 py-1.5 text-sm font-medium text-maiic-700 hover:bg-maiic-100">
                <svg class="h-4 w-4 transition" :class="showControl ? 'rotate-90' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                {{ showControl ? 'Hide document control' : 'Document control, how to use and who reads what' }}
            </button>
        </section>

        <!-- Document control, revision history, distribution, how to use, role map -->
        <section v-if="showControl" class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div class="maiic-panel p-5">
                <h2 class="maiic-section-title !mt-0">Document control</h2>
                <table class="w-full text-sm">
                    <tbody>
                        <tr v-for="r in controlRows()" :key="r[0]" class="border-t border-gray-100">
                            <td class="w-2/5 bg-maiic-50/60 px-3 py-1.5 font-semibold text-maiic-800">{{ r[0] }}</td>
                            <td class="px-3 py-1.5 text-gray-700">{{ r[1] }}</td>
                        </tr>
                    </tbody>
                </table>

                <h2 class="maiic-section-title">Revision history</h2>
                <div class="maiic-table-wrap">
                    <table class="maiic-table">
                        <thead><tr><th>Version</th><th>Date</th><th>Author</th><th>Summary of change</th></tr></thead>
                        <tbody>
                            <tr v-for="rev in front.revisions" :key="rev[0] + rev[1]">
                                <td>{{ rev[0] }}</td><td>{{ rev[1] }}</td><td>{{ rev[2] }}</td><td>{{ rev[3] }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <h2 class="maiic-section-title">Distribution list</h2>
                <div class="maiic-table-wrap">
                    <table class="maiic-table">
                        <thead><tr><th>Recipient</th><th>Role</th><th>Copy</th></tr></thead>
                        <tbody>
                            <tr v-for="d in front.distribution" :key="d[0]">
                                <td>{{ d[0] }}</td><td>{{ d[1] }}</td><td>{{ d[2] }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="maiic-panel p-5">
                <h2 class="maiic-section-title !mt-0">How to use this document</h2>
                <p class="text-sm text-gray-700">{{ front.howToUse[0] }}</p>
                <ul class="mt-3 list-disc space-y-1.5 pl-5 text-sm text-gray-700">
                    <li v-for="tip in front.howToUse[1]" :key="tip">{{ tip }}</li>
                </ul>
                <div class="mt-4 rounded-r-lg border-l-4 border-maiicgold-500 bg-maiic-50 px-4 py-3 text-sm text-gray-700">
                    {{ front.howToUse[2] }}
                </div>

                <h2 class="maiic-section-title">Who reads what</h2>
                <div class="maiic-table-wrap">
                    <table class="maiic-table">
                        <thead><tr><th class="w-2/5">If you are a...</th><th>Focus on</th></tr></thead>
                        <tbody>
                            <tr v-for="r in front.roles" :key="r[0]">
                                <td class="font-semibold text-maiic-800">{{ r[0] }}</td><td>{{ r[1] }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</template>
