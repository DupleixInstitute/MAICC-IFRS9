<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Stageing Rules - Quantitative Thresholds
            </h2>
        </template>
        <div class="mx-auto max-w-2xl bg-white p-6 rounded shadow">
            <form @submit.prevent="save">
                <div class="mb-4">
                    <jet-label value="Institution Type"/>
                    <input v-model="form.institution_type" type="text" class="input" />
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <jet-label value="Stage 1 Threshold (days)"/>
                        <input v-model.number="form.stage_1_threshold" type="number" min="0" class="input" />
                    </div>
                    <div>
                        <jet-label value="Stage 3 Threshold (days)"/>
                        <input v-model.number="form.stage_3_threshold" type="number" min="0" class="input" />
                    </div>
                </div>
                <div class="mt-6">
                    <jet-button :disabled="processing" :class="{ 'opacity-25': processing }">Save</jet-button>
                </div>
            </form>
        </div>
        <teleport to="head">
            <title>Stageing Rules - Thresholds</title>
        </teleport>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import JetButton from '@/Jetstream/Button.vue'
import JetLabel from '@/Jetstream/Label.vue'

export default {
    props: {
        rule: Object,
    },
    components: { AppLayout, JetButton, JetLabel },
    data() {
        return {
            form: {
                institution_type: this.rule?.institution_type || 'default',
                stage_1_threshold: this.rule?.stage_1_threshold || 30,
                stage_3_threshold: this.rule?.stage_3_threshold || 90,
            },
            processing: false,
        }
    },
    methods: {
        save() {
            this.processing = true
            this.$inertia.post(this.route('stageing-rules.store'), this.form, { onFinish: () => this.processing = false })
        }
    }
}
</script>

<style scoped>
.input{ @apply mt-1 block w-full rounded border-gray-300; }
</style>
