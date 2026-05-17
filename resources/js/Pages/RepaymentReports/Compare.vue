<template>
    <app-layout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Compare Calculations #{{ calculation1.id }} vs #{{ calculation2.id }}
                </h2>

                <div class="flex space-x-2 mt-2">
                    <Link
                        :href="route('lgd-calculations.index')"
                        class="inline-flex items-center bg-gray-900 hover:bg-gray-700 text-white px-3 py-1 rounded-lg shadow-md transition duration-300"
                    >
                        <i class="fas fa-arrow-left mr-2"></i>
                        Back
                    </Link>

                    <a
                        :href="route('lgd-payment-report.download-comparison', { id1: calculation1.id, id2: calculation2.id })"
                        class="inline-flex items-center bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow-md transition duration-300"
                    >
                        <i class="fas fa-download mr-2"></i>
                        Download CSV
                    </a>
                </div>
            </div>
        </template>

        <!-- Calculation headers -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-6">
            <div v-for="(calc, idx) in [calculation1, calculation2]" :key="calc.id"
                 class="bg-white rounded-lg shadow p-4">
                <div class="flex justify-between items-center">
                    <div>
                        <div class="text-sm text-gray-500">Calculation {{ idx + 1 }}</div>
                        <div class="text-lg font-semibold">#{{ calc.id }}</div>
                    </div>
                    <span class="px-3 py-1 rounded-full text-sm font-semibold" :class="statusClass(calc.status)">
                        {{ calc.status }}
                    </span>
                </div>
                <dl class="mt-4 grid grid-cols-2 gap-2 text-sm">
                    <dt class="text-gray-500">Portfolio</dt>
                    <dd class="font-medium text-right">{{ calc.portfolio?.name || 'N/A' }}</dd>
                    <dt class="text-gray-500">Period</dt>
                    <dd class="font-medium text-right">{{ formatDate(calc.start_period) }} - {{ formatDate(calc.end_period) }}</dd>
                    <dt class="text-gray-500">Created</dt>
                    <dd class="font-medium text-right">{{ formatDateTime(calc.created_at) }}</dd>
                </dl>
            </div>
        </div>

        <!-- Comparison table -->
        <div class="bg-white shadow-md rounded-lg mt-6 overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-200">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Metric</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Calc #{{ calculation1.id }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Calc #{{ calculation2.id }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Difference</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Difference %</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr>
                        <td class="px-4 py-3 text-sm font-medium text-gray-700">Total Records</td>
                        <td class="px-4 py-3 text-sm text-right">{{ Number(stats1.total_records || 0).toLocaleString() }}</td>
                        <td class="px-4 py-3 text-sm text-right">{{ Number(stats2.total_records || 0).toLocaleString() }}</td>
                        <td class="px-4 py-3 text-sm text-right" :class="diffClass(differences.records_diff)">
                            {{ Number(differences.records_diff || 0).toLocaleString() }}
                        </td>
                        <td class="px-4 py-3 text-sm text-right" :class="diffClass(differences.records_diff_percent)">
                            {{ differences.records_diff_percent }}%
                        </td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 text-sm font-medium text-gray-700">Total Payments</td>
                        <td class="px-4 py-3 text-sm text-right">{{ formatCurrency(stats1.total_payments) }}</td>
                        <td class="px-4 py-3 text-sm text-right">{{ formatCurrency(stats2.total_payments) }}</td>
                        <td class="px-4 py-3 text-sm text-right" :class="diffClass(differences.payments_diff)">
                            {{ formatCurrency(differences.payments_diff) }}
                        </td>
                        <td class="px-4 py-3 text-sm text-right" :class="diffClass(differences.payments_diff_percent)">
                            {{ differences.payments_diff_percent }}%
                        </td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 text-sm font-medium text-gray-700">Cured Contracts</td>
                        <td class="px-4 py-3 text-sm text-right">{{ Number(stats1.cured_count || 0).toLocaleString() }}</td>
                        <td class="px-4 py-3 text-sm text-right">{{ Number(stats2.cured_count || 0).toLocaleString() }}</td>
                        <td class="px-4 py-3 text-sm text-right" :class="diffClass(differences.cured_diff)">
                            {{ Number(differences.cured_diff || 0).toLocaleString() }}
                        </td>
                        <td class="px-4 py-3 text-sm text-right text-gray-400">&mdash;</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 text-sm font-medium text-gray-700">Average Payment</td>
                        <td class="px-4 py-3 text-sm text-right">{{ formatCurrency(stats1.avg_payment) }}</td>
                        <td class="px-4 py-3 text-sm text-right">{{ formatCurrency(stats2.avg_payment) }}</td>
                        <td class="px-4 py-3 text-sm text-right" :class="diffClass((stats2.avg_payment || 0) - (stats1.avg_payment || 0))">
                            {{ formatCurrency((stats2.avg_payment || 0) - (stats1.avg_payment || 0)) }}
                        </td>
                        <td class="px-4 py-3 text-sm text-right text-gray-400">&mdash;</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </app-layout>
</template>

<script>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

export default {
    components: {
        AppLayout,
        Link
    },
    props: {
        calculation1: {
            type: Object,
            required: true
        },
        calculation2: {
            type: Object,
            required: true
        },
        stats1: {
            type: Object,
            default: () => ({})
        },
        stats2: {
            type: Object,
            default: () => ({})
        },
        differences: {
            type: Object,
            default: () => ({})
        }
    },
    setup() {
        const formatDate = (dateStr) => {
            if (!dateStr) return '';
            const date = new Date(dateStr);
            return date.toLocaleDateString('en-US', { year: 'numeric', month: '2-digit' });
        };

        const formatDateTime = (dateStr) => {
            if (!dateStr) return '';
            const date = new Date(dateStr);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            });
        };

        const formatCurrency = (value) => {
            if (!value && value !== 0) return 'E0.00';
            return new Intl.NumberFormat('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(value).replace(/,/g, ' ');
        };

        const statusClass = (status) => {
            const classes = {
                completed: 'bg-green-100 text-green-700',
                processing: 'bg-yellow-100 text-yellow-700',
                pending: 'bg-gray-100 text-gray-700',
                failed: 'bg-red-100 text-red-700'
            };
            return classes[status] || 'bg-gray-100 text-gray-700';
        };

        const diffClass = (value) => {
            const n = Number(value || 0);
            if (n > 0) return 'text-green-600 font-medium';
            if (n < 0) return 'text-red-600 font-medium';
            return 'text-gray-500';
        };

        return {
            formatDate,
            formatDateTime,
            formatCurrency,
            statusClass,
            diffClass,
            Number
        };
    }
};
</script>
