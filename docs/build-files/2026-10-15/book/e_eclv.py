p = r'C:\xampp\htdocs\MAICC-IFRS9\resources\js\Pages\ExpectedCreditLoss\Index.vue'
s = open(p, encoding='utf-8').read()
def rep(old, new):
    global s
    assert old in s, old
    s = s.replace(old, new, 1)

rep('description="Loan-level expected credit loss (PD x LGD x exposure) for every period that has been calculated"',
    'description="How each loan\'s ECL was built: EAD x PD over the horizon x LGD, with the PD before and after the forward-looking adjustment"')

# KPI strip
i = s.index('            <!-- KPI row: the filtered population in view -->')
j = s.index('            <!-- Tabs: loan results by basis')
s = s[:i] + '''            <!-- One line of figures: the filtered population in view -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-7">
                <div class="maiic-kpi !py-3" style="--accent:#15803d">
                    <div class="maiic-kpi-label">Loans in view</div>
                    <div class="text-xl font-bold tabular-nums text-gray-900">{{ formatNumber(totals.loans) }}</div>
                </div>
                <div class="maiic-kpi !py-3" style="--accent:#0e7490" :title="formatMoney(totals.ead) + ' = carrying amount plus undrawn commitments times utilisation'">
                    <div class="maiic-kpi-label">EAD (MWK)</div>
                    <div class="text-xl font-bold tabular-nums text-gray-900">{{ compact(totals.ead) }}</div>
                </div>
                <div class="maiic-kpi !py-3" style="--accent:#64748b" :title="buildUp.pre_fli !== null ? formatMoney(buildUp.pre_fli) : (buildUp.reason || '')">
                    <div class="maiic-kpi-label">ECL before FLI (MWK)</div>
                    <div class="text-xl font-bold tabular-nums text-gray-900">{{ buildUp.pre_fli !== null ? compact(buildUp.pre_fli) : '-' }}</div>
                </div>
                <div class="maiic-kpi !py-3" style="--accent:#d97706" :title="buildUp.fli_total !== null ? formatMoney(buildUp.fli_total) : (buildUp.reason || '')">
                    <div class="maiic-kpi-label">Forward-looking effect</div>
                    <div class="text-xl font-bold tabular-nums text-gray-900">{{ buildUp.fli_total !== null ? signedCompact(buildUp.fli_total) : '-' }}</div>
                </div>
                <div class="maiic-kpi !py-3" style="--accent:#dc2626" :title="formatMoney(summary?.total_loss)">
                    <div class="maiic-kpi-label">Undiscounted ECL (MWK)</div>
                    <div class="text-xl font-bold tabular-nums text-gray-900">{{ summary?.calculated_loans ? compact(summary.total_loss) : '-' }}</div>
                </div>
                <div class="maiic-kpi !py-3" style="--accent:#b45309" :title="formatMoney(summary?.total_discounted_loss)">
                    <div class="maiic-kpi-label">Discounted ECL (MWK)</div>
                    <div class="text-xl font-bold tabular-nums text-gray-900">{{ summary?.discounted_loans ? compact(summary.total_discounted_loss) : '-' }}</div>
                </div>
                <div class="maiic-kpi !py-3" style="--accent:#15803d">
                    <div class="maiic-kpi-label">Coverage (ECL / EAD)</div>
                    <div class="text-xl font-bold tabular-nums text-gray-900">{{ summary?.calculated_loans && summary?.total_ead ? formatPercent(coverageRate) : '-' }}</div>
                </div>
            </div>

''' + s[j:]

rep('''const coverageRate = computed(() => {
    const exposure = Number(props.summary?.total_exposure || 0);''', '''const coverageRate = computed(() => {
    const exposure = Number(props.summary?.total_ead || 0);''')

# filters
rep('''                    <select v-model="filters.stage" aria-label="Stage" title="Stage" class="maiic-select w-32 py-1.5" @change="fetchData">
                        <option value="">All stages</option>
                        <option value="1">Stage 1</option>
                        <option value="2">Stage 2</option>
                        <option value="3">Stage 3</option>
                    </select>''', '''                    <select v-model="filters.stage" aria-label="Stage" title="Stage" class="maiic-select w-32 py-1.5" @change="fetchData">
                        <option value="">All stages</option>
                        <option value="1">Stage 1</option>
                        <option value="2">Stage 2</option>
                        <option value="3">Stage 3</option>
                    </select>
                    <select v-model="filters.product_group" aria-label="Product group" title="Product group" class="maiic-select w-48 py-1.5" @change="fetchData">
                        <option value="">All product groups</option>
                        <option v-for="g in productGroups" :key="g" :value="g">{{ g }}</option>
                    </select>
                    <select v-model="filters.sector" aria-label="Sector" title="Sector" class="maiic-select w-52 py-1.5" @change="fetchData">
                        <option value="">All sectors</option>
                        <option v-for="o in bookSectors" :key="o.value" :value="o.value">{{ o.label }}</option>
                    </select>''')
rep('''                    <template v-if="summary?.discount_unresolved_loans > 0">''', '''                    <template v-if="activeTab === 'undiscounted' && !buildUp.available && buildUp.reason">
                        <span class="text-gray-300">|</span>
                        <span class="font-semibold text-amber-700"><font-awesome-icon icon="exclamation-triangle"/> ECL before FLI not shown: {{ buildUp.reason }}</span>
                    </template>
                    <template v-if="buildUp.basis === 'pre_fli' && buildUp.reason">
                        <span class="text-gray-300">|</span>
                        <span class="font-semibold text-amber-700">{{ buildUp.reason }}</span>
                    </template>
                    <template v-if="summary?.discount_unresolved_loans > 0">''')

# table
i = s.index('                <!-- Loan results -->')
j = s.index('                <!-- Saved run by stage -->')
s = s[:i] + '''                <!-- Loan results -->
                <div v-if="activeTab !== 'run'">
                    <div class="maiic-table-wrap">
                        <table class="maiic-table text-[13px] [&_td]:!px-2.5 [&_th]:!px-2.5">
                            <thead>
                                <tr>
                                    <th>Contract</th>
                                    <th>Stage</th>
                                    <th class="num" title="Carrying amount plus undrawn commitment times utilisation">EAD (MWK)</th>
                                    <template v-if="activeTab === 'undiscounted'">
                                        <th class="num" title="12-month PD from the PD engine">PD before FLI</th>
                                        <th class="num" title="PD after FLI over PD before FLI, less one">FLI adj.</th>
                                        <th class="num" title="12-month PD after the forward-looking adjustment">PD after FLI</th>
                                        <th class="num" title="The PD over the measurement horizon: 12 months in Stage 1, the remaining life in Stage 2, 100% in Stage 3">PD used</th>
                                        <th class="num">LGD</th>
                                        <th class="num">ECL before FLI (MWK)</th>
                                        <th class="num">ECL (MWK)</th>
                                        <th class="num">Coverage</th>
                                    </template>
                                    <template v-else>
                                        <th class="num">PD after FLI</th>
                                        <th class="num">LGD</th>
                                        <th class="num">Undiscounted ECL (MWK)</th>
                                        <th class="num">EIR</th>
                                        <th class="num">Horizon</th>
                                        <th class="num">Discounted ECL (MWK)</th>
                                        <th class="num">Discounting effect (MWK)</th>
                                        <th>Discount status</th>
                                    </template>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="loan in loanBooks.data" :key="loan.id">
                                    <td class="whitespace-nowrap">
                                        <div class="font-mono text-xs font-semibold text-gray-900">{{ loan.contract_id }}</div>
                                        <div class="max-w-[13rem] truncate text-xs text-gray-500" :title="loan.client?.name || loan.customer_name">{{ loan.client?.name || loan.customer_name || '-' }}</div>
                                    </td>
                                    <td><span class="maiic-badge" :class="stageBadge(stageOf(loan))">Stage {{ stageOf(loan) }}</span></td>
                                    <td class="num">{{ formatMoney(loan.lineage.ead) }}</td>
                                    <template v-if="activeTab === 'undiscounted'">
                                        <td class="num">{{ formatRate(loan.pd_prefli) }}</td>
                                        <td class="num" :class="adjClass(loan)">{{ stageOf(loan) === 3 ? '-' : signedRate(loan.fli_adj) }}</td>
                                        <td class="num">{{ formatRate(loan.pd_post_fli) }}</td>
                                        <td class="num font-semibold" :title="horizonTitle(loan)">{{ formatRate(loan.lineage.horizon_pd_post) }}</td>
                                        <td class="num">{{ formatRate(loan.lgd_value) }}</td>
                                        <td class="num text-gray-600">{{ formatMoney(loan.lineage.ecl_pre, true) }}</td>
                                        <td class="num font-semibold" :class="loan.ecl_value !== null && !loan.lineage.ties ? 'text-amber-700' : ''"
                                            :title="loan.ecl_value !== null && !loan.lineage.ties ? 'EAD x PD used x LGD gives ' + formatMoney(loan.lineage.ecl_recalc) + ': run the ECL again' : ''">{{ formatMoney(loan.ecl_value, true) }}</td>
                                        <td class="num">{{ formatPercent(loanCoverage(loan)) }}</td>
                                    </template>
                                    <template v-else>
                                        <td class="num">{{ formatRate(loan.pd_post_fli) }}</td>
                                        <td class="num">{{ formatRate(loan.lgd_value) }}</td>
                                        <td class="num">{{ formatMoney(loan.ecl_value, true) }}</td>
                                        <td class="num" :title="eirBasis(loan)">{{ eirRate(loan) }}</td>
                                        <td class="num">{{ formatHorizon(loan.ecl_discount_horizon_years) }}</td>
                                        <td class="num font-semibold">{{ formatMoney(loan.ecl_value_discounted, true) }}</td>
                                        <td class="num">{{ formatMoney(loan.ecl_discounting_effect, true) }}</td>
                                        <td><span class="maiic-badge" :class="discountStatusClass(loan.ecl_discount_status)">{{ discountStatusLabel(loan.ecl_discount_status) }}</span></td>
                                    </template>
                                </tr>
                                <tr v-if="loanBooks.data && loanBooks.data.length" class="total">
                                    <td colspan="2">Total, {{ formatNumber(totals.loans) }} loans</td>
                                    <td class="num">{{ formatMoney(totals.ead) }}</td>
                                    <template v-if="activeTab === 'undiscounted'">
                                        <td colspan="5"></td>
                                        <td class="num">{{ formatMoney(totals.ecl_pre, true) }}</td>
                                        <td class="num">{{ formatMoney(totals.ecl) }}</td>
                                        <td class="num">{{ totals.ead ? formatPercent(totals.ecl / totals.ead * 100) : '-' }}</td>
                                    </template>
                                    <template v-else>
                                        <td colspan="2"></td>
                                        <td class="num">{{ formatMoney(totals.ecl) }}</td>
                                        <td colspan="2"></td>
                                        <td class="num">{{ formatMoney(totals.ecl_discounted, true) }}</td>
                                        <td class="num">{{ totals.ecl_discounted === null ? '-' : formatMoney(totals.ecl - totals.ecl_discounted) }}</td>
                                        <td></td>
                                    </template>
                                </tr>
                                <tr v-if="!loanBooks.data || loanBooks.data.length === 0">
                                    <td :colspan="11" class="maiic-empty">
                                        No ECL results for this period and these filters. Pick another month, clear the filters, or run the ECL calculation.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-if="loanBooks.links" class="border-t border-gray-100 px-4 pb-4">
                        <Pagination :links="loanBooks.links"/>
                    </div>
                </div>

''' + s[j:]

rep('''    runInfo: { type: Object, default: () => ({ period: null, rows: [] }) },
});''', '''    runInfo: { type: Object, default: () => ({ period: null, rows: [] }) },
    buildUp: { type: Object, default: () => ({ available: false, pre_fli: null, fli_total: null }) },
    totals: { type: Object, default: () => ({ loans: 0, ead: 0, ecl: 0, ecl_pre: null, ecl_discounted: null }) },
    productGroups: { type: Array, default: () => [] },
    bookSectors: { type: Array, default: () => [] },
});''')
rep('''      description: 'PD x LGD x exposure for each loan, no present-value adjustment.' },''',
    '''      description: 'EAD x PD used x LGD for each loan, no present-value adjustment. PD used is the 12-month PD after FLI carried over the horizon.' },''')
rep('''    stage: '',
    portfolio: '',
    search: '',
    ...props.filters,''', '''    stage: '',
    portfolio: '',
    search: '',
    product_group: '',
    sector: '',
    ...props.filters,''')
rep('''        portfolio: filters.value.portfolio,
    }, { preserveState: true, preserveScroll: true, replace: true });''', '''        portfolio: filters.value.portfolio,
        product_group: filters.value.product_group || undefined,
        sector: filters.value.sector || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true });''')
rep('''    filters.value = { year: defaultYear, month: defaultMonth, stage: '', portfolio: '', search: '' };''',
    '''    filters.value = { year: defaultYear, month: defaultMonth, stage: '', portfolio: '', search: '', product_group: '', sector: '' };''')

# export columns: only the ones the export accepts; no legacy PD
i = s.index('const columnOptions = [')
j = s.index('];', i) + 2
s = s[:i] + '''const columnOptions = [
    { value: 'contract_id', label: 'Contract ID' },
    { value: 'customer_name', label: 'Customer' },
    { value: 'reporting_period', label: 'Reporting period' },
    { value: 'ifrs9stage_post_qualitative', label: 'Stage' },
    { value: 'carrying_amount', label: 'Carrying amount' },
    { value: 'commitments', label: 'Undrawn commitment' },
    { value: 'pd_prefli', label: 'PD before FLI' },
    { value: 'fli_adj', label: 'FLI adjustment' },
    { value: 'pd_post_fli', label: 'PD after FLI' },
    { value: 'lgd_value', label: 'LGD' },
    { value: 'ecl_value', label: 'ECL' },
    { value: 'remaining_tenor', label: 'Remaining term (months)' },
    { value: 'industry_code', label: 'Sector code' },
    { value: 'create_date', label: 'Create date' },
    { value: 'due_date', label: 'Due date' },
];''' + s[j:]

rep('''const eadValue = (loan) => {
    const utilisation = loan.facility_utilisation_rate === null || loan.facility_utilisation_rate === undefined
        ? 1 : Number(loan.facility_utilisation_rate);
    return Number(loan.carrying_amount || 0) + (Number(loan.commitments || 0) * utilisation);
};
const loanCoverage = (loan) => {
    const ead = eadValue(loan);''', '''const loanCoverage = (loan) => {
    const ead = Number(loan.lineage?.ead || 0);''')
rep('''const formatHorizon = (value)''', '''const signedRate = (value) => (value === null || value === undefined || value === '') ? '-' : (Number(value) > 0 ? '+' : '') + (Number(value) * 100).toFixed(2) + '%';
const signedCompact = (value) => (Number(value) > 0 ? '+' : '') + compact(value);
const adjClass = (loan) => {
    const a = Number(loan.fli_adj || 0);
    if (stageOf(loan) === 3 || !a) return 'text-gray-500';
    return a > 0 ? 'text-red-600' : 'text-maiic-700';
};
const horizonTitle = (loan) => {
    const x = loan.lineage || {};
    if (x.horizon === 'defaulted') return 'Stage 3: defaulted, PD 100%';
    if (x.horizon === 'lifetime') return 'Stage 2: lifetime PD over ' + Math.round(x.horizon_months) + ' months';
    return 'Stage 1: PD over ' + Math.round(x.horizon_months || 12) + ' months';
};
const formatHorizon = (value)''')
open(p, 'w', encoding='utf-8', newline='\n').write(s)
print('ok')
