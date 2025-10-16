<template>
    <app-layout>
        <template #header>
            <div class="flex justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Dashboard

                    <HelpManual />
                </h2>
            </div>
        <form @change="handlePeriodChange">
          <select v-model="currentPeriod" class="border-gray-300 rounded">
            <option v-for="period in periods" :key="period" :value="period">
              {{ period }}
            </option>
          </select>
        </form>
        </template>

        <div class="p-6 bg-gray-100 min-h-screen">

            <!-- KPI Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-blue-500 text-white rounded-2xl shadow-xl p-6 flex flex-col items-center justify-center tooltip-wrapper" data-tooltip="Total portfolio balance including all stages.">
                    <h2 class="text-xl font-semibold">Total Loans<i class="fa fa-sort-amount-asc" aria-hidden="true"></i></h2>
                    <p class="text-2xl font-bold">MWK{{ formatAmount(summary.total_amount) }}</p>
                </div>

                <div class="bg-orange-500 text-white rounded-2xl shadow-xl p-6 flex flex-col items-center justify-center tooltip-wrapper" data-tooltip="Stage 3 exposures.">
                    <h2 class="text-xl font-semibold">Stage 3</h2>
                    <p class="text-2xl font-bold">MWK{{ formatAmount(summary.stage_3_amount) }}</p>
                    <p class="text-sm mt-2">({{ summary.stage_3_percentage }}%)</p>
                </div>

                <div class="bg-green-500 text-white rounded-2xl shadow-xl p-6 flex flex-col items-center justify-center tooltip-wrapper" data-tooltip=" Credit Adjusted Value .">
                    <h2 class="text-xl font-semibold">Net Carrying Amount</h2>
                    <p class="text-2xl font-bold">MWK{{ formatAmount(summary.paid_amount) }}</p>
                    <p class="text-sm mt-2">({{ summary.paid_percentage }}%)</p>
                </div>
            </div>

            <!-- Charts -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <div class="bg-white rounded-2xl shadow-lg p-4" width="100" height="100">
                    <h3 class="text-lg font-semibold mb-2">Portfolio Composition</h3>
                   <canvas ref="pieChart" width="70" height="70"></canvas>
                </div>


                <div class="bg-white rounded-2xl shadow-lg p-4 w-full">
                    <div>
                    <h3 class="text-lg font-semibold mb-2">ECL Trend Analysis</h3>
                    <canvas ref="trendChart" class="w-full"></canvas>
                </div>

                <div class="mt-8">
                    <h3 class="text-m font-semibold ">Summary Table</h3>
                    <table class="min-w-full text-left text-gray-700">
                        <thead>
                            <tr>
                                <th class="py-2">Metric</th>
                                <th class="py-2"> Currency (MWK)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="font-bold">
                                <td>Total EAD </td>
                                <td>{{ formatAmount(summary.total_amount) }}</td>
                            </tr>
                            <tr>
                                <td>Weighted PD</td>
                                <td>{{ formatAmount(summary.weighted_pd) }}</td>
                            </tr>
                            <tr>
                                <td>Weighted LGD</td>
                                <td>{{ formatAmount(summary.weighted_lgd) }}</td>
                            </tr>
                            <tr class="font-bold">
                                <td>Total ECL</td> 
                                <td>{{ formatAmount(summary.total_ecl) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
                    </div>
<!-- 
                <div class="bg-white rounded-2xl shadow-lg p-4">
                    <h3 class="text-lg font-semibold mb-2">Expected Credit Loss Comparison</h3>
                    <canvas ref="barChart"></canvas>
                    <p>Last ECL %: {{ summary.last_ecl_percentage }}</p>
                </div> -->
            </div>
             
    </app-layout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, onMounted, watch, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { Chart, registerables } from 'chart.js';
import HelpManual from '../Components/HelpManual.vue';
Chart.register(...registerables);

const props = defineProps({
    summary: Object,
    periods: Array,
    selectedPeriod: String,
    eclTrends: Array,
});

const summary = computed(() => props.summary);
const periods = ref(props.periods);
const currentPeriod = ref(props.selectedPeriod);

const pieChart = ref(null);
const barChart = ref(null);
const trendChart = ref(null);

function formatAmount(amount) {
    return Number(amount).toLocaleString();
}

function renderCharts() {
    if (pieChart.value) {
        new Chart(pieChart.value.getContext('2d'), {
            type: 'pie',
            data: {
                labels: ['Stage 1', 'Stage 2','Stage 3'],
                datasets: [{
                    data: [summary.value.total_eads[0], summary.value.total_eads[1],summary.value.total_eads[2]],
                    backgroundColor: ['#f97316', '#22c55e','#facc15'],
                }]
            },
            options: {
                plugins: { legend: { position: 'bottom' } },
                animation: { duration: 1000 }
            }
        });
    }

    // if (barChart.value) {
    //     new Chart(barChart.value.getContext('2d'), {
    //         type: 'bar',
    //         data: {
    //             labels: ['Last Period', 'Reporting Period'],
    //             datasets: [{
    //                 data: [summary.value.last_ecl_percentage ?? 0, summary.value.ecl_percentage ?? 0],
    //                 backgroundColor: ['#60a5fa', '#facc15'],
    //             }]
    //         },
    //         options: {
    //             scales: { y: { beginAtZero: true, max: 100 } },
    //             plugins: { legend: { display: false } },
    //             animation: { duration: 1000 }
    //         }
    //     });
    // }

  if (trendChart.value) {
    new Chart(trendChart.value.getContext('2d'), {
      type: 'line',
      data: {
        labels: props.eclTrends.map(item => item.period),
        datasets: [{
          label: 'ECL %',
          data: props.eclTrends.map(item => item.ecl_percentage),
          borderColor: '#3b82f6',
          backgroundColor: 'rgba(59, 130, 246, 0.2)',
          fill: true,
          tension: 0.3,
        }]
      },
    options: {
        maintainAspectRatio: true,
        aspectRatio: 2, // or 2.5 or 3 if you want it thinner
        scales: {
            y: {
            beginAtZero: true,
            max: 100,
            title: { display: true, text: 'ECL %' },
            },
            x: {
            title: { display: true, text: 'Reporting Period' },
            },
        },
        plugins: {
            legend: { display: true },
        },
},

    });
  }
}

function handlePeriodChange() {
    router.get(route('dashboard.index'), { period: currentPeriod.value }, { preserveState: false });
}

onMounted(() => {
    renderCharts();
});

watch(summary, () => {
    renderCharts();
});

        </script>


<style scoped>
.tooltip-wrapper {
    position: relative;
}

.tooltip-wrapper:after {
    content: attr(data-tooltip);
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(0, 0, 0, 0.75);
    color: white;
    padding: 0.5em 1em;
    border-radius: 0.25em;
    white-space: nowrap;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.3s ease;
}

.tooltip-wrapper:hover:after {
    opacity: 1;
}

.tooltip-wrapper:hover .shadow-xl {
    animation: bounce 0.5s;
}

.small-chart {
    width: 50px;
    height: 50px; 
}

@keyframes bounce {
    0%, 100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-5px);
    }
}
</style>
