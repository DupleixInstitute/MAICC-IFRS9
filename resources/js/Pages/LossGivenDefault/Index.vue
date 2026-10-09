<template>
    <app-layout>
        <template #header>
            <div>
                <div class="mb-1 flex items-center gap-2 text-xs text-gray-500">
                    <span>IFRS 9 Model Setup</span><span>/</span><span>LGD Model</span><span>/</span><span class="font-medium text-maiic-700">Monthly LGD</span>
                </div>
                <h2 class="text-xl font-semibold text-gray-800">Monthly LGD</h2>
                <p class="mt-1 text-sm text-gray-600">Loss given default from cures and recoveries on Stage 3 loans, one run per window</p>
            </div>
        </template>
        <template #actions>
            <button type="button" class="secondary-btn" @click="openReportModal">Get report</button>
            <Link :href="route('loss-given-default.create')" class="primary-btn">Calculate LGD</Link>
        </template>

    <div class="w-full space-y-4">
      <div class="maiic-panel">
        <div class="border-b border-gray-200 px-5 py-4">
          <h3 class="font-semibold text-gray-900">LGD runs</h3>
          <p class="text-xs text-gray-500">{{ lossGivenDefaults.length }} run(s). A closed run is locked for use in the ECL; amounts in MWK.</p>
        </div>
        <div class="maiic-table-wrap overflow-x-auto">
          <table class="maiic-table">
            <thead>
              <tr>
                <th>Window</th>
                <th>Portfolio</th>
                <th class="num">LGD %</th>
                <th class="num">Cure rate %</th>
                <th class="num">Recovery rate %</th>
                <th class="num">Recovered</th>
                <th class="num">Stage 3 balance, start / end</th>
                <th>Source</th>
                <th>Status</th>
                <th class="text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="lgd in pagedRows" :key="lgd.id">
                <td class="whitespace-nowrap" :title="'Created ' + formatDate(lgd.created_at) + (lgd.created_by ? ' by ' + lgd.created_by : '')">{{ formatDate(lgd.start_period) }} to {{ formatDate(lgd.reporting_period) }}</td>
                <td>{{ lgd.portfolio_group?.name || '-' }}</td>
                <td class="num font-bold" :class="lgd.loss_given_default_percentage >= 0.5 ? 'text-amber-700' : 'text-maiic-700'">{{ pct(lgd.loss_given_default_percentage) }}</td>
                <td class="num">{{ pct(lgd.cure_rate) }}</td>
                <td class="num">{{ pct(lgd.recovery_rate) }}</td>
                <td class="num whitespace-nowrap">{{ formatCurrency(lgd.recovered_amount) }}</td>
                <td class="num whitespace-nowrap">{{ formatCurrency(lgd.start_total_stage3) }}<div class="text-xs text-gray-500">{{ formatCurrency(lgd.end_total_stage3) }}</div></td>
                <td class="whitespace-nowrap">{{ lgd.calculation_source === 'manual' ? 'Manual' : 'System' }}<div class="text-xs" :class="lgd.is_discounting ? 'text-maiic-700' : 'text-gray-500'">{{ lgd.is_discounting ? 'Discounted' : 'Not discounted' }}</div></td>
                <td><span class="maiic-badge" :class="lgd.is_active_or_closed === 'closed' ? 'maiic-badge-green' : 'maiic-badge-gold'">{{ lgd.is_active_or_closed === 'closed' ? 'Closed' : 'Active' }}</span></td>
                <td>
                  <div class="flex flex-nowrap justify-end gap-1.5">
                    <Link v-if="lgd.is_discounting" :href="route('loss-given-default.discounted-payments', lgd.id)" class="maiic-action maiic-action-view" title="View the discounted payments"><font-awesome-icon icon="eye" /></Link>
                    <button v-if="lgd.calculation_source === 'manual'" type="button" class="maiic-action maiic-action-edit" title="Edit this LGD" @click="editLGD(lgd.id)"><font-awesome-icon icon="pen" /></button>
                    <button type="button" class="maiic-action" :class="lgd.is_active_or_closed === 'closed' ? 'maiic-action-edit' : 'maiic-action-view'" :disabled="loading === lgd.id" :title="lgd.is_active_or_closed === 'closed' ? 'Unlock this LGD' : 'Lock this LGD'" @click="lockLGD(lgd.id)"><font-awesome-icon :icon="loading === lgd.id ? 'spinner' : (lgd.is_active_or_closed === 'closed' ? 'lock-open' : 'lock')" :spin="loading === lgd.id" /></button>
                    <button v-if="lgd.is_active_or_closed === 'closed'" type="button" class="maiic-action maiic-action-neutral" :disabled="loading === lgd.id" title="Update the loan book" @click="openUpdateModal(lgd)"><font-awesome-icon icon="book" /></button>
                    <button v-if="lgd.is_active_or_closed === 'active'" type="button" class="maiic-action maiic-action-delete" :disabled="loading === lgd.id" title="Delete this LGD run" @click="deleteLGD(lgd.id)"><font-awesome-icon icon="trash" /></button>
                  </div>
                </td>
              </tr>
              <tr v-if="!lossGivenDefaults.length">
                <td colspan="10" class="maiic-empty">No LGD runs yet. Use <strong>Calculate LGD</strong> at the top right to run the first one.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <RowPager v-model="page" :total="lossGivenDefaults.length" class="border-t border-gray-100" />
      </div>
    </div>

  <div v-if="showModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
    <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
        <h2 class="text-lg font-bold mb-4">Update Loan Book Period</h2>
        <p class="mb-4">Select the reporting period to update loan books for <strong>{{ selectedLGD?.portfolio_group?.name }}</strong>.</p>

        <label for="period" class="block mb-2 text-sm font-medium text-gray-700">Reporting Period</label>
        <input
            type="month"
            v-model="selectedPeriod"
            id="period"
            class="border-gray-300 rounded-md shadow-sm w-full mb-4"
        >

        <div class="flex justify-end space-x-2">
            <button @click="showModal = false" class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">Cancel</button>
            <button
                @click="submitUpdate"
                class="px-4 py-2 bg-maiic-600 text-white rounded hover:bg-maiic-700"
                :disabled="loading === selectedLGD?.id"
            >
                <span v-if="loading === selectedLGD?.id">Update..</span>
                <span v-else>Update</span>

            </button>
        </div>
    </div>
</div>

 <div
    v-if="showReportModal"
    class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50"
>
    <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
        <h2 class="text-lg font-bold mb-4">Download LGD Monthly Report</h2>

        <label class="block mb-2 text-sm font-medium text-gray-700">Start Period</label>
        <input
            type="month"
            v-model="reportStartPeriod"
            class="border-gray-300 rounded-md shadow-sm w-full mb-4"
        />

        <label class="block mb-2 text-sm font-medium text-gray-700">End Period</label>
        <input
            type="month"
            v-model="reportEndPeriod"
            class="border-gray-300 rounded-md shadow-sm w-full mb-4"
        />

        <div class="flex justify-end space-x-2">
            <button
                @click="showReportModal = false"
                class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400"
            >
                Cancel
            </button>

            <button
                @click="downloadReport"
                :disabled="reportLoading"
                class="px-4 py-2 bg-maiic-600 text-white rounded hover:bg-maiic-700"
            >
                <span v-if="reportLoading">Preparing...</span>
                <span v-else>Download</span>
            </button>
        </div>
    </div>
</div>

    <HelpManual />
    </app-layout>
</template>

<script>
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import { confirmDialog } from '@/Components/confirmDialog';
import { notice } from '@/Components/Maiic/notice';
import RowPager from '@/Components/Maiic/RowPager.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import HelpManual from '../../Components/HelpManual.vue';

export default {
    components: {
        AppLayout,
        HelpManual,
        Link,
        RowPager,
    },
    props: {
        lossGivenDefaults: {
            type: Array,
            required: true,
        },
    },
    setup(props) {
        const loading = ref(null);
        const page = ref(1);
        const pagedRows = computed(() => props.lossGivenDefaults.slice((page.value - 1) * 15, page.value * 15));
        const pct = (v) => (v === null || v === undefined ? '-' : (Number(v) * 100).toFixed(2));
        const showModal = ref(false);
        const selectedLGD = ref(null);
        const selectedPeriod = ref('');
        const showReportModal = ref(false);
        const reportPeriod = ref('');
        const reportLoading = ref(false);
        const reportStartPeriod = ref('');
        const reportEndPeriod = ref('');

        const round = (value, decimals = 2) => {
            if (value === null || value === undefined) return '-';
            return Number(Math.round(parseFloat(value + 'e' + decimals)) + 'e-' + decimals).toFixed(decimals);
        };

        // const formatCurrency = (value) => {
        //     if (value === null || value === undefined) return '-';
        //     return new Intl.NumberFormat('en-US', {
        //         style: 'currency',
        //         currency: 'USD',
        //     }).format(value);
        // };

        const formatCurrency = (value) => {
            if (!value) return '0.00';
            return new Intl.NumberFormat('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(value).replace(/,/g, ' ');
        };

        const formatDate = (dateStr) => {
            if (!dateStr) return '-';
            const date = new Date(dateStr);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: '2-digit',
            });
        };

        const lockLGD = async (id) => {
            if (await confirmDialog({ title: 'Change the lock on this LGD?', message: 'A closed (locked) LGD is the one the ECL uses; an active run can still change.', confirmLabel: 'Change lock' })) {
                loading.value = id;
                router.post(route('loss-given-default.lock', id), {}, {
                    preserveScroll: true,
                    onFinish: () => { loading.value = null; },
                    onSuccess: () => { router.reload({ only: ['lossGivenDefaults'] }); },
                    onError: () => { notice('Something went wrong', 'Please try again.', 'danger'); },
                });
            }
        };

        const openUpdateModal = (lgd) => {
            selectedLGD.value = lgd;
            selectedPeriod.value = '';
            showModal.value = true;
        };

        const submitUpdate = () => {
            if (!selectedPeriod.value) {
                notice('Choose a period', 'Pick the reporting month whose loan book should be updated.', 'warning');
                return;
            }

            loading.value = selectedLGD.value.id;

            router.post(route('loss-given-default.update-loan-book', selectedLGD.value.id), {
                reporting_period: selectedPeriod.value,
                lgd_id: selectedLGD.value.id,
            }, {
                preserveScroll: true,
                onFinish: () => {
                    loading.value = null;
                    showModal.value = false;
                },
                onSuccess: () => {
                    router.reload({ only: ['lossGivenDefaults'] });
                },
                onError: () => {
                    notice('Something went wrong', 'Please try again.', 'danger');
                },
            });
        };


        const editLGD = (id) => {
            router.get(`/loss-given-default/${id}/edit`);
        };

        const deleteLGD = async (id) => {
            if (await confirmDialog({ title: 'Delete this LGD run?', message: 'The run and its figures are removed. This cannot be undone.', confirmLabel: 'Delete', tone: 'danger' })) {
                router.delete(`/loss-given-default/delete/${id}`);
            }
        };

        const openReportModal = () => {
            reportPeriod.value = '';
            showReportModal.value = true;
        };

      const downloadReport = () => {
        if (!reportStartPeriod.value || !reportEndPeriod.value) {
            notice('Choose both periods', 'Pick a start and an end period for the report.', 'warning');
            return;
        }

        // Optional: check that start <= end
        if (reportStartPeriod.value > reportEndPeriod.value) {
            notice('Check the periods', 'The start period cannot be after the end period.', 'warning');
            return;
        }

        reportLoading.value = true;

        // Redirect to backend route with both periods
        const params = new URLSearchParams({
            start_period: reportStartPeriod.value,
            end_period: reportEndPeriod.value,
        });

        window.location.href = route('loss-given-default.report-by-period') + '?' + params.toString();

        setTimeout(() => {
            reportLoading.value = false;
            showReportModal.value = false;
        }, 1500);
    };

      const lgdColorClass = (lgd) => {
          if (lgd == null) return 'px-6 py-4 whitespace-nowrap font-bold text-center text-gray-600'; // null or missing LGD
          // Example threshold: LGD >= 0.5 (50%) is high
          return lgd >= 0.5 ? 'px-6 py-4 whitespace-nowrap font-bold text-center text-amber-600' : 'px-6 py-4 whitespace-nowrap font-bold text-center text-maiic-600';
      };


        return {
            page,
            pagedRows,
            pct,
            formatDate,
            loading,
            lockLGD,
            openUpdateModal,
            submitUpdate,
            editLGD,
            deleteLGD,
            showModal,
            selectedLGD,
            selectedPeriod,
            showReportModal,
            openReportModal,
            reportPeriod,
            reportLoading,
            reportStartPeriod,
            reportEndPeriod,
            downloadReport,
            round,
            formatCurrency,
            lgdColorClass,
        };
    }
};
</script>
