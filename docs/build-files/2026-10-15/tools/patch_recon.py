R = 'C:/xampp/htdocs/MAICC-IFRS9/'

def patch(p, pairs):
    s = open(R + p, encoding='utf-8').read()
    for a, b in pairs:
        assert a in s, (p, a[:60])
        s = s.replace(a, b, 1)
    open(R + p, 'w', encoding='utf-8').write(s)

patch('app/Http/Controllers/ReportsController.php', [("""        $portfolioId = $request->portfolio_id;
        $startPeriod = $request->start_period;
        $endPeriod = $request->end_period;
        $report = null;
        $error = null;
""", """        $portfolioId = $request->portfolio_id;
        $startPeriod = $request->start_period;
        $endPeriod = $request->end_period;
        $report = null;
        $error = null;

        // opened without a choice: the largest portfolio of the latest month, against the month before
        if (! $request->hasAny(['portfolio_id', 'start_period', 'end_period'])) {
            $latest = DB::table('loan_books')->max('reporting_period');
            $portfolioId = $latest ? DB::table('loan_books')->where('reporting_period', $latest)->whereNotNull('loan_portfolio_id')
                ->groupBy('loan_portfolio_id')->orderByRaw('SUM(COALESCE(carrying_amount,0)) DESC')->value('loan_portfolio_id') : null;
            if ($portfolioId) {
                $held = array_column(LoanBookReconciliationService::getAvailablePeriods((int) $portfolioId), 'value');
                if (count($held) >= 2) {
                    [$endPeriod, $startPeriod] = [$held[0], $held[1]];
                }
            }
        }
""")])

patch('resources/js/Pages/Reports/LoanBookReconciliation.vue', [("""        <template #actions>
            <select v-model="form.portfolio_id" @change="onPortfolioChange" class="maiic-select w-40" aria-label="Portfolio">
                <option value="">Portfolio</option>
                <option v-for="p in portfolios" :key="p.value" :value="p.value">{{ p.label }}</option>
            </select>
            <select v-model="form.start_period" class="maiic-select w-36" aria-label="Start period" :disabled="!periods.length">
                <option value="">From</option>
                <option v-for="p in periods" :key="'s' + p.value" :value="p.value">From {{ p.label }}</option>
            </select>
            <select v-model="form.end_period" class="maiic-select w-36" aria-label="End period" :disabled="!periods.length">
                <option value="">To</option>
                <option v-for="p in periods" :key="'e' + p.value" :value="p.value">To {{ p.label }}</option>
            </select>
            <button type="button" @click="reload({ generate: 1 })" :disabled="!canRun"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-maiic-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-maiic-700 disabled:opacity-50">
                <ReportIcon name="play" class="h-4 w-4"/> Run
            </button>
            <DownloadButton format="CSV" :href="downloadUrl('csv')" :disabled="!report"/>
            <DownloadButton format="Excel" :href="downloadUrl('xlsx')" :disabled="!report"/>
            <DownloadButton format="PDF" label="Download PDF" :href="downloadUrl('pdf')" :disabled="!report"/>
        </template>

        <div class="w-full space-y-4">""", """        <template #actions>
            <DownloadButton format="CSV" :href="downloadUrl('csv')" :disabled="!report"/>
            <DownloadButton format="Excel" :href="downloadUrl('xlsx')" :disabled="!report"/>
            <DownloadButton format="PDF" label="Download PDF" :href="downloadUrl('pdf')" :disabled="!report"/>
        </template>

        <div class="w-full space-y-4">
            <div class="maiic-panel flex flex-wrap items-end gap-3 px-4 py-3">
                <label class="flex flex-col gap-1 text-xs font-semibold text-gray-600">Portfolio
                    <select v-model="form.portfolio_id" @change="onPortfolioChange" class="maiic-select w-56" aria-label="Portfolio">
                        <option value="">Choose a portfolio</option>
                        <option v-for="p in portfolios" :key="p.value" :value="p.value">{{ p.label }}</option>
                    </select>
                </label>
                <label class="flex flex-col gap-1 text-xs font-semibold text-gray-600">From
                    <select v-model="form.start_period" class="maiic-select w-36" aria-label="Start period" :disabled="!periods.length">
                        <option value="">Opening month</option>
                        <option v-for="p in periods" :key="'s' + p.value" :value="p.value">{{ p.label }}</option>
                    </select>
                </label>
                <label class="flex flex-col gap-1 text-xs font-semibold text-gray-600">To
                    <select v-model="form.end_period" class="maiic-select w-36" aria-label="End period" :disabled="!periods.length">
                        <option value="">Closing month</option>
                        <option v-for="p in periods" :key="'e' + p.value" :value="p.value">{{ p.label }}</option>
                    </select>
                </label>
                <button type="button" @click="reload({ generate: 1 })" :disabled="!canRun"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-maiic-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-maiic-700 disabled:opacity-50">
                    <ReportIcon name="play" class="h-4 w-4"/> Run
                </button>
            </div>
""")])
print('ok')
