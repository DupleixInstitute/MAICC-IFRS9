<template>
    <!-- Compact summary cards on ONE line at desktop width: as many columns as
         there are cards, small padding, sentence-case labels. -->
    <div class="grid gap-3" :class="gridClass">
        <div v-for="card in cards" :key="card.label" class="maiic-kpi !py-3" :style="card.accent ? { '--accent': card.accent } : null" :title="card.hint || null">
            <div class="maiic-kpi-label truncate">{{ card.label }}</div>
            <div class="text-xl font-bold leading-tight text-gray-900 tabular-nums truncate" :class="card.valueClass || ''">{{ display(card) }}</div>
            <div v-if="card.bar !== undefined && card.bar !== null" class="mt-1 h-1 w-full overflow-hidden rounded-full bg-gray-200">
                <div class="h-full rounded-full bg-maiic-600" :style="{ width: Math.max(0, Math.min(100, Number(card.bar))) + '%' }"></div>
            </div>
            <div v-if="card.sub" class="mt-0.5 text-[11px] text-gray-500 truncate">{{ card.sub }}</div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    // [{ label, value, sub?, hint?, accent?, valueClass?, raw?, bar? (0-100 progress) }]
    cards: { type: Array, required: true },
});

// Full class names so Tailwind builds them.
const COLS = {
    1: 'grid-cols-1',
    2: 'grid-cols-2',
    3: 'grid-cols-2 sm:grid-cols-3',
    4: 'grid-cols-2 lg:grid-cols-4',
    5: 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-5',
    6: 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-6',
    7: 'grid-cols-2 sm:grid-cols-4 lg:grid-cols-7',
    8: 'grid-cols-2 sm:grid-cols-4 lg:grid-cols-8',
};
const gridClass = computed(() => COLS[Math.min(8, Math.max(1, props.cards.length))]);

function display(card) {
    if (card.raw || typeof card.value !== 'number') return card.value ?? '-';
    return Number(card.value).toLocaleString();
}
</script>
