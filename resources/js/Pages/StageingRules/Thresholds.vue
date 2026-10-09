<template>
    <app-layout>
        <template #header>
            <div>
                <div class="mb-1 flex items-center gap-2 text-xs text-gray-500">
                    <span>IFRS 9 Model Setup</span><span>/</span><span>Staging &amp; SICR Rules</span><span>/</span><span class="font-medium text-maiic-700">Quantitative Thresholds</span>
                </div>
                <h2 class="text-xl font-semibold text-gray-800">Quantitative Thresholds</h2>
                <p class="mt-1 text-sm text-gray-600">Days-past-due limits for each stage, kept for reference</p>
            </div>
        </template>
        <template #actions>
            <button type="submit" form="thresholds-form" :disabled="processing || !isValid" class="primary-btn">
                {{ processing ? 'Saving...' : 'Save thresholds' }}
            </button>
        </template>

        <div class="w-full space-y-4">
            <!-- The engine does not read this table: say so in one line. -->
            <div class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm text-amber-900">
                <font-awesome-icon icon="info-circle" class="mt-0.5" />
                <span>
                    The ECL engine does not stage loans from these figures. It uses the staging thresholds and the approved settings in
                    <Link :href="route('settings.index', { tab: 'staging' })" class="font-semibold underline">Settings, Staging basis</Link>
                    and the <Link :href="route('eir-governance.index')" class="font-semibold underline">Governance Centre</Link>.
                </span>
            </div>

            <form id="thresholds-form" class="maiic-panel" @submit.prevent="save">
                <div class="border-b border-gray-200 px-5 py-4">
                    <h3 class="font-semibold text-gray-900">Threshold configuration</h3>
                    <p class="text-xs text-gray-500">Stage 2 is everything between the Stage 1 and Stage 3 limits.</p>
                </div>
                <div class="space-y-4 p-5">
                    <label class="block max-w-sm">
                        <span class="maiic-flabel">Institution type</span>
                        <input v-model="form.institution_type" type="text" class="maiic-input" placeholder="e.g. default, commercial, retail" />
                        <span class="mt-1 block text-xs text-gray-500">Name for this set of staging rules.</span>
                        <span v-if="errors.institution_type" class="mt-1 block text-xs text-red-600">{{ errors.institution_type }}</span>
                    </label>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div class="rounded-lg border border-maiic-200 bg-maiic-50 p-4">
                            <div class="flex items-center gap-2">
                                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-maiic-600 text-sm font-bold text-white">1</span>
                                <div>
                                    <div class="text-sm font-semibold text-maiic-900">Stage 1, up to</div>
                                    <div class="text-xs text-maiic-700">12-month ECL, performing loans</div>
                                </div>
                            </div>
                            <div class="relative mt-3">
                                <input v-model.number="form.stage_1_threshold" type="number" min="0" max="365" class="maiic-input pr-14 text-right" aria-label="Stage 1 threshold in days" />
                                <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-maiic-700">days</span>
                            </div>
                            <p class="mt-2 text-xs text-maiic-700">Loans up to {{ form.stage_1_threshold }} days past due</p>
                            <p v-if="errors.stage_1_threshold" class="mt-1 text-xs text-red-600">{{ errors.stage_1_threshold }}</p>
                        </div>

                        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                            <div class="flex items-center gap-2">
                                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-amber-500 text-sm font-bold text-white">2</span>
                                <div>
                                    <div class="text-sm font-semibold text-amber-900">Stage 2, calculated</div>
                                    <div class="text-xs text-amber-700">Lifetime ECL, significant increase in credit risk</div>
                                </div>
                            </div>
                            <p class="mt-4 text-sm text-amber-900">
                                Loans from {{ Number(form.stage_1_threshold || 0) + 1 }} to {{ Number(form.stage_3_threshold || 0) - 1 }} days past due
                            </p>
                        </div>

                        <div class="rounded-lg border border-red-200 bg-red-50 p-4">
                            <div class="flex items-center gap-2">
                                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-red-500 text-sm font-bold text-white">3</span>
                                <div>
                                    <div class="text-sm font-semibold text-red-900">Stage 3, from</div>
                                    <div class="text-xs text-red-700">Lifetime ECL, credit-impaired</div>
                                </div>
                            </div>
                            <div class="relative mt-3">
                                <input v-model.number="form.stage_3_threshold" type="number" min="0" max="365" class="maiic-input pr-14 text-right" aria-label="Stage 3 threshold in days" />
                                <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-red-700">days</span>
                            </div>
                            <p class="mt-2 text-xs text-red-700">Loans {{ form.stage_3_threshold }} or more days past due</p>
                            <p v-if="errors.stage_3_threshold" class="mt-1 text-xs text-red-600">{{ errors.stage_3_threshold }}</p>
                        </div>
                    </div>
                    <p v-if="!isValid" class="text-xs text-red-600">The Stage 1 limit must be lower than the Stage 3 limit, and both must be zero or more.</p>

                    <details class="text-xs text-gray-600">
                        <summary class="cursor-pointer select-none font-semibold text-maiic-700 hover:underline">How the three stages work</summary>
                        <ul class="mt-1 max-w-4xl list-disc space-y-0.5 pl-5">
                            <li><strong>Stage 1:</strong> 12-month expected credit losses for loans with no significant increase in credit risk since they were first recognised.</li>
                            <li><strong>Stage 2:</strong> lifetime expected credit losses for loans whose credit risk has increased significantly since they were first recognised.</li>
                            <li><strong>Stage 3:</strong> lifetime expected credit losses for credit-impaired loans.</li>
                        </ul>
                    </details>
                </div>
            </form>
        </div>

        <teleport to="head">
            <title>Quantitative Thresholds</title>
        </teleport>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link } from '@inertiajs/vue3'

export default {
    props: {
        rule: Object,
        errors: { type: Object, default: () => ({}) },
    },
    components: { AppLayout, Link },
    data() {
        return {
            form: {
                institution_type: this.rule?.institution_type || 'default',
                stage_1_threshold: this.rule?.stage_1_threshold ?? 30,
                stage_3_threshold: this.rule?.stage_3_threshold ?? 90,
            },
            processing: false,
        }
    },
    computed: {
        isValid() {
            return this.form.institution_type &&
                   this.form.stage_1_threshold >= 0 &&
                   this.form.stage_3_threshold >= 0 &&
                   this.form.stage_1_threshold < this.form.stage_3_threshold
        }
    },
    methods: {
        save() {
            if (!this.isValid) return
            this.processing = true
            // The server answers with a flash message, which the layout shows.
            this.$inertia.post(this.route('stageing-rules.store'), this.form, {
                preserveScroll: true,
                onFinish: () => this.processing = false,
            })
        }
    }
}
</script>
