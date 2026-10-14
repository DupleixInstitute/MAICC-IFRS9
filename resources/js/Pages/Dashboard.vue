<template>
    <app-layout title="Dashboard">
        <!-- ============================ HEADER ============================ -->
        <template #header>
            <h1 class="flex items-center gap-2 text-xl font-extrabold leading-tight text-maiic-900 dark:text-slate-100">
                IFRS 9 ECL Dashboard
                <HelpManual inline />
            </h1>
            <p class="mt-0.5 text-sm text-gray-500 dark:text-slate-400">
                <template v-if="selectedPeriod">
                    Showing <b class="text-gray-700 dark:text-slate-200">{{ periodLabel(selectedPeriod) }}</b>
                    <template v-if="selectedPortfolioName"> &middot; portfolio <b class="text-gray-700 dark:text-slate-200">{{ selectedPortfolioName }}</b></template>
                    <template v-if="comparePeriod"> compared with <b class="text-gray-700 dark:text-slate-200">{{ periodLabel(comparePeriod) }}</b></template>
                </template>
                <template v-else>Expected credit loss position of the MAIIC loan book</template>
            </p>
        </template>

        <!-- Global filter bar: everything on this page is scoped by these.
             All options come from the database (reporting_periods /
             loan_portfolios) - nothing hardcoded. -->
        <template v-if="periods.length" #actions>
            <div class="flex flex-wrap items-end gap-3 rounded-xl bg-maiic-600 px-4 py-2.5 shadow-md">
                <div>
                    <label class="mb-0.5 block text-[10px] font-bold uppercase tracking-widest text-white/80">Reporting period</label>
                    <select v-model="filterForm.period" @change="applyFilters"
                            class="cursor-pointer rounded-lg border-0 bg-white py-1.5 pl-3 pr-8 text-sm font-bold text-maiic-800 shadow focus:ring-2 focus:ring-white">
                        <option v-for="p in periods" :key="p" :value="p">{{ periodLabel(p) }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-0.5 block text-[10px] font-bold uppercase tracking-widest text-white/80">Portfolio</label>
                    <select v-model="filterForm.portfolio_id" @change="applyFilters"
                            class="cursor-pointer rounded-lg border-0 bg-white py-1.5 pl-3 pr-8 text-sm font-bold text-maiic-800 shadow focus:ring-2 focus:ring-white">
                        <option :value="null">All portfolios</option>
                        <option v-for="p in portfolios" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-0.5 block text-[10px] font-bold uppercase tracking-widest text-white/80">Compare to</label>
                    <select v-model="filterForm.compare" @change="applyFilters"
                            class="cursor-pointer rounded-lg border-0 bg-white py-1.5 pl-3 pr-8 text-sm font-bold text-maiic-800 shadow focus:ring-2 focus:ring-white">
                        <option :value="null">Previous period</option>
                        <option v-for="p in comparablePeriods" :key="p" :value="p">{{ periodLabel(p) }}</option>
                    </select>
                </div>
            </div>
            <!-- The same figures as this screen, for the chosen period,
                 portfolio and compare-to month, as a branded PDF. -->
            <a :href="reportPdfUrl" title="Download the dashboard for the chosen period, portfolio and compare-to month as a PDF"
               class="inline-flex items-center gap-1.5 self-center rounded-lg bg-maiicgold-400 px-3 py-1.5 text-xs font-bold text-maiic-900 shadow-sm ring-1 ring-maiicgold-500/40 transition hover:bg-maiicgold-500">
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3v4a1 1 0 001 1h4"/><path d="M17 21H7a2 2 0 01-2-2V5a2 2 0 012-2h7l5 5v11a2 2 0 01-2 2z"/><path d="M12 11v6m0 0l-2.5-2.5M12 17l2.5-2.5"/></svg>
                PDF
            </a>
        </template>

        <!-- the filter bar and the PDF button (#actions) sit here, under the
             header and above the KPI tiles they scope (AppLayout actions row) -->
        <div data-actions-anchor></div>

        <!-- ============================ NO ECL YET ============================ -->
        <div v-if="error" class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-xl border border-amber-300 bg-amber-50 px-5 py-4 dark:border-amber-800 dark:bg-amber-900/30">
            <div class="flex items-start gap-3">
                <span class="mt-0.5 flex h-9 w-9 flex-none items-center justify-center rounded-full bg-maiicgold-500 text-white">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16v-4M12 8h.01"/></svg>
                </span>
                <div>
                    <p class="font-bold text-maiic-900 dark:text-slate-100">No ECL results to show yet</p>
                    <p class="text-sm text-gray-700 dark:text-slate-300">{{ error }}</p>
                </div>
            </div>
            <Link :href="route('expected-credit-loss.create')" class="inline-flex items-center gap-2 rounded-lg bg-maiic-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-maiic-700">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg> Run ECL calculation
            </Link>
        </div>

        <template v-if="!error">
            <!-- ============================ KPI TILES ============================ -->
            <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
                <div v-for="k in kpis" :key="k.label" class="maiic-kpi min-w-0 px-4 py-3" :style="{ '--accent': k.accent }" :title="k.full || k.value">
                    <div class="flex items-center gap-2">
                        <span class="flex h-7 w-7 flex-none items-center justify-center rounded-lg"
                              :style="{ backgroundColor: k.accent + '1f', color: k.accent }">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                 stroke-linecap="round" stroke-linejoin="round"><path :d="k.icon"/></svg>
                        </span>
                        <p class="maiic-kpi-label !mb-0 leading-tight">{{ k.label }}</p>
                    </div>
                    <p class="mt-1.5 truncate text-xl font-extrabold leading-tight tabular-nums text-gray-900 dark:text-slate-100">{{ k.value }}</p>
                    <p v-if="k.full" class="mt-0.5 truncate text-[11px] tabular-nums text-gray-400">{{ k.full }}</p>
                    <p v-if="k.delta" class="mt-1.5">
                        <span :class="['inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-bold',
                                       k.deltaGood === null ? 'bg-gray-100 text-gray-600'
                                           : k.deltaGood ? 'bg-maiic-100 text-maiic-800' : 'bg-red-100 text-red-700']">
                            <span v-if="k.deltaUp !== null">{{ k.deltaUp ? '▲' : '▼' }}</span>{{ k.delta }}
                        </span>
                    </p>
                    <p v-else-if="k.sub" class="mt-1.5 truncate text-xs text-gray-400">{{ k.sub }}</p>
                </div>
            </div>

            <!-- ============================ STAGE CARDS ============================ -->
            <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
                <div v-for="(s, i) in stages" :key="i" :class="['overflow-hidden rounded-2xl border shadow-sm', s.border]">
                    <div :class="['h-1.5', s.bar]"></div>
                    <div :class="['p-5', s.wash]">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <span :class="['flex h-8 w-8 items-center justify-center rounded-full text-sm font-extrabold text-white', s.bar]">{{ i + 1 }}</span>
                                <h3 class="font-bold text-gray-900 dark:text-slate-100">Stage {{ i + 1 }}</h3>
                            </div>
                            <span :class="['rounded-full px-2.5 py-1 text-xs font-bold', s.badge]">{{ s.tag }}</span>
                        </div>
                        <p class="mt-4 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-slate-400">Exposure (EAD)</p>
                        <p :class="['text-xl font-extrabold tabular-nums', s.text]">{{ currencyCode }} {{ formatAmount(s.ead) }}</p>
                        <div class="mt-3 space-y-1 rounded-lg bg-white/75 px-3 py-2 dark:bg-slate-900/50">
                            <div class="flex justify-between text-sm"><span class="text-gray-500 dark:text-slate-400">ECL</span><span class="num font-semibold text-gray-800 dark:text-slate-200">{{ currencyCode }} {{ formatAmount(s.ecl) }}</span></div>
                            <div class="flex justify-between text-sm"><span class="text-gray-500 dark:text-slate-400">Coverage</span><span class="num font-semibold text-gray-800 dark:text-slate-200">{{ s.ead ? formatPct((s.ecl / s.ead) * 100) : '0.00%' }}</span></div>
                            <div class="flex justify-between text-sm"><span class="text-gray-500 dark:text-slate-400">PD applied</span><span class="num font-semibold text-gray-800 dark:text-slate-200">{{ formatPct(s.pd) }}</span></div>
                            <div class="flex justify-between text-sm"><span class="text-gray-500 dark:text-slate-400">LGD applied</span><span class="num font-semibold text-gray-800 dark:text-slate-200">{{ formatPct(s.lgd) }}</span></div>
                            <div class="flex justify-between text-sm"><span class="text-gray-500 dark:text-slate-400">Loans</span><span class="num font-semibold text-gray-800 dark:text-slate-200">{{ formatCount(s.loans) }}</span></div>
                            <div class="flex justify-between text-sm"><span class="text-gray-500 dark:text-slate-400">Share of book</span><span :class="['num font-bold', s.text]">{{ stageShare(i) }}</span></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================ CHARTS ============================ -->
            <div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-5">
                <div class="maiic-panel min-w-0 p-6 lg:col-span-2">
                    <div class="mb-4 flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-gray-900 dark:text-slate-100">Exposure by stage</h3>
                            <p class="text-xs text-gray-400">{{ periodLabel(selectedPeriod) }}<span v-if="selectedPortfolioName"> &middot; {{ selectedPortfolioName }}</span></p>
                        </div>
                        <div class="flex overflow-hidden rounded-lg border border-gray-200 text-xs dark:border-slate-600">
                            <button @click="pieView = 'chart'" :class="tgl(pieView === 'chart')" class="px-3 py-1">Chart</button>
                            <button @click="pieView = 'table'" :class="tgl(pieView === 'table')" class="px-3 py-1">Table</button>
                        </div>
                    </div>
                    <div v-show="pieView === 'chart'" class="relative h-72 w-full min-w-0"><canvas ref="pieChart"></canvas></div>
                    <table v-if="pieView === 'table'" class="maiic-table">
                        <thead><tr><th>Stage</th><th class="num">EAD ({{ currencyCode }})</th><th class="num">Share</th></tr></thead>
                        <tbody>
                            <tr v-for="(s, i) in stages" :key="i">
                                <td>Stage {{ i + 1 }}</td>
                                <td class="num">{{ formatAmount(s.ead) }}</td>
                                <td class="num">{{ stageShare(i) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="maiic-panel min-w-0 p-6 lg:col-span-3">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="font-bold text-gray-900 dark:text-slate-100">ECL and coverage trend</h3>
                            <p class="text-xs text-gray-400">{{ trendRangeLabel }}<span v-if="selectedPortfolioName"> &middot; {{ selectedPortfolioName }}</span></p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <select v-model="filterForm.trend_from" @change="applyFilters"
                                    :class="rangeSelect">
                                <option :value="null">Last 12 months</option>
                                <option value="all">From the start</option>
                                <option v-for="p in periods" :key="'f' + p" :value="p">From {{ periodLabel(p) }}</option>
                            </select>
                            <select v-model="filterForm.trend_to" @change="applyFilters"
                                    :class="rangeSelect">
                                <option :value="null">To {{ periodLabel(selectedPeriod) }}</option>
                                <option v-for="p in periods" :key="'t' + p" :value="p">To {{ periodLabel(p) }}</option>
                            </select>
                            <div class="flex overflow-hidden rounded-lg border border-gray-200 text-xs dark:border-slate-600">
                                <button @click="trendView = 'chart'" :class="tgl(trendView === 'chart')" class="px-3 py-1">Chart</button>
                                <button @click="trendView = 'table'" :class="tgl(trendView === 'table')" class="px-3 py-1">Table</button>
                            </div>
                        </div>
                    </div>
                    <div v-show="trendView === 'chart'" class="relative h-72 w-full min-w-0"><canvas ref="trendChart"></canvas></div>
                    <div v-if="trendView === 'table'" class="maiic-table-wrap">
                        <table class="maiic-table">
                            <thead><tr><th>Period</th><th class="num">EAD ({{ currencyCode }})</th><th class="num">ECL ({{ currencyCode }})</th><th class="num">Coverage</th></tr></thead>
                            <tbody>
                                <tr v-for="t in (eclTrends || [])" :key="t.period">
                                    <td>{{ periodLabel(t.period) }}</td>
                                    <td class="num">{{ formatAmount(t.total_ead) }}</td>
                                    <td class="num">{{ formatAmount(t.total_ecl) }}</td>
                                    <td class="num">{{ formatPct(t.ecl_percentage) }}</td>
                                </tr>
                                <tr v-if="!(eclTrends || []).length"><td colspan="4" class="maiic-empty">No calculated periods in this range.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ============================ ECL BUILD-UP AND WHERE IT SITS ============================ -->
            <div v-if="eclBuildUp" class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="maiic-panel lg:col-span-1">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-4 py-3 dark:border-slate-700">
                        <div>
                            <h3 class="font-bold text-gray-900 dark:text-slate-100">ECL build-up</h3>
                            <p class="text-xs text-gray-400">From the PD before FLI to the booked ECL, {{ periodLabel(selectedPeriod) }}<span v-if="selectedPortfolioName"> &middot; {{ selectedPortfolioName }}</span></p>
                        </div>
                        <span :class="['maiic-badge', eclBuildUp.ties_to_runs ? 'maiic-badge-green' : 'maiic-badge-gold']"
                              :title="'Saved ECL runs: ' + fullMoney(eclBuildUp.runs_total)">
                            {{ eclBuildUp.ties_to_runs ? 'Ties to the total ECL' : 'Differs from the saved runs' }}
                        </span>
                    </div>
                    <div class="space-y-2.5 p-4">
                        <div v-for="step in buildSteps" :key="step.label">
                            <div class="flex items-baseline justify-between gap-3 text-sm">
                                <span :class="step.total ? 'font-bold text-gray-900 dark:text-slate-100' : 'text-gray-600 dark:text-slate-300'">{{ step.label }}</span>
                                <span :class="['num', step.total ? 'font-bold text-gray-900 dark:text-slate-100' : 'font-semibold text-gray-800 dark:text-slate-200']">
                                    {{ step.sign }}{{ formatAmount(step.value) }}
                                    <span v-if="step.pct !== null" class="ml-1 text-xs font-normal text-gray-400" title="Against the ECL before FLI">({{ step.pct }})</span>
                                </span>
                            </div>
                            <div class="relative mt-1 h-2.5 rounded bg-gray-100 dark:bg-slate-700">
                                <div class="absolute inset-y-0 rounded" :class="step.bar" :style="{ left: step.left + '%', width: Math.max(step.width, step.value ? 0.6 : 0) + '%' }"></div>
                            </div>
                            <p v-if="step.note" class="mt-0.5 text-[11px] text-gray-400">{{ step.note }}</p>
                        </div>
                        <p v-if="!eclBuildUp.available" class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                            <svg class="mt-0.5 h-3.5 w-3.5 flex-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
                            <span>The split before and after FLI is not shown: {{ eclBuildUp.reason }}</span>
                        </p>
                        <p v-else-if="eclBuildUp.basis === 'pre_fli'" class="text-xs text-amber-700">{{ eclBuildUp.reason }}</p>
                        <p v-if="ranUnder" class="border-t border-gray-100 pt-2 text-[11px] text-gray-500 dark:border-slate-700">Ran under {{ ranUnder }}</p>
                    </div>
                </div>

                <div class="maiic-panel min-w-0 lg:col-span-2">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-4 py-3 dark:border-slate-700">
                        <div>
                            <h3 class="font-bold text-gray-900 dark:text-slate-100">Where the ECL sits</h3>
                            <p class="text-xs text-gray-400">{{ breakdownCaption }}</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <div class="flex overflow-hidden rounded-lg border border-gray-200 text-xs dark:border-slate-600">
                                <button v-for="o in breakdownViews" :key="o.key" @click="breakdownView = o.key"
                                        :class="tgl(breakdownView === o.key)" class="px-3 py-1">{{ o.label }}</button>
                            </div>
                            <div class="flex overflow-hidden rounded-lg border border-gray-200 text-xs dark:border-slate-600">
                                <button @click="breakdownMode = 'chart'" :class="tgl(breakdownMode === 'chart')" class="px-3 py-1">Chart</button>
                                <button @click="breakdownMode = 'table'" :class="tgl(breakdownMode === 'table')" class="px-3 py-1">Table</button>
                            </div>
                        </div>
                    </div>
                    <div v-show="breakdownMode === 'chart'" class="px-4 py-3">
                        <div class="relative w-full min-w-0" :style="{ height: breakdownChartHeight + 'px' }"><canvas ref="breakdownChart"></canvas></div>
                    </div>
                    <div v-if="breakdownMode === 'table'" class="maiic-table-wrap">
                        <table class="maiic-table text-[13px] [&_td]:!py-2 [&_td]:!px-2.5 [&_th]:!px-2.5">
                            <thead>
                                <tr>
                                    <th>{{ breakdownViews.find(o => o.key === breakdownView).label }}</th>
                                    <th class="num">Loans</th>
                                    <th class="num">EAD ({{ currencyCode }})</th>
                                    <th class="num">ECL ({{ currencyCode }})</th>
                                    <th class="num">Coverage</th>
                                    <th v-if="breakdownView === 'rbm'" class="num" title="The RBM directive's minimum provision for the class">RBM min.</th>
                                    <th class="num" title="Share of the total ECL">Share</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="r in breakdown.rows" :key="r.label">
                                    <td class="max-w-[16rem] truncate" :class="r.other ? 'italic text-gray-500' : ''" :title="r.label">{{ r.label }}</td>
                                    <td class="num">{{ formatCount(r.loans) }}</td>
                                    <td class="num">{{ formatAmount(r.ead) }}</td>
                                    <td class="num font-semibold">{{ formatAmount(r.ecl) }}</td>
                                    <td class="num">{{ r.coverage === null ? '-' : formatPct(r.coverage) }}</td>
                                    <td v-if="breakdownView === 'rbm'" class="num">{{ r.minimum === null || r.minimum === undefined ? '-' : formatPct(r.minimum * 100) }}</td>
                                    <td class="num">
                                        <span class="mr-1.5 inline-block h-2 w-10 rounded bg-gray-100 align-middle dark:bg-slate-700"><span class="block h-2 rounded bg-maiic-600" :style="{ width: (r.share || 0) + '%' }"></span></span>{{ r.share === null ? '-' : r.share.toFixed(1) + '%' }}
                                    </td>
                                </tr>
                                <tr class="total">
                                    <td>Total</td>
                                    <td class="num">{{ formatCount(breakdown.total.loans) }}</td>
                                    <td class="num">{{ formatAmount(breakdown.total.ead) }}</td>
                                    <td class="num">{{ formatAmount(breakdown.total.ecl) }}</td>
                                    <td class="num">{{ breakdown.total.ead ? formatPct(breakdown.total.ecl / breakdown.total.ead * 100) : '-' }}</td>
                                    <td v-if="breakdownView === 'rbm'"></td>
                                    <td class="num text-xs" :class="breakdownTies ? 'text-maiic-700' : 'text-amber-700'" :title="breakdownTies ? 'The total ties to the total ECL on the tiles' : 'The total differs from the total ECL on the tiles'">{{ breakdownTies ? '100%, ties' : 'Differs' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ============================ SUMMARY TABLE ============================ -->
            <div class="maiic-panel mb-6">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-4 py-3 dark:border-slate-700">
                    <h3 class="text-sm font-extrabold uppercase tracking-wider text-maiic-800 dark:text-maiic-200">Portfolio summary</h3>
                    <p class="text-xs text-gray-400">Values in {{ currencyCode }}<template v-if="comparePeriod"> &middot; compared with {{ periodLabel(comparePeriod) }}</template></p>
                </div>
                <div class="maiic-table-wrap">
                    <table class="maiic-table">
                        <thead>
                            <tr>
                                <th>Metric</th>
                                <th class="num">{{ periodLabel(selectedPeriod) }}</th>
                                <th v-if="comparePeriod" class="num">{{ periodLabel(comparePeriod) }}</th>
                                <th v-if="comparePeriod" class="num">Change</th>
                                <th v-if="comparePeriod" class="!text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(r, i) in summaryRows" :key="i">
                                <td :class="r.bold ? 'font-bold' : ''">{{ r.label }}</td>
                                <td class="num" :class="r.bold ? 'font-bold' : ''">{{ r.value }}</td>
                                <td v-if="comparePeriod" class="num text-gray-500">{{ r.compare ?? '-' }}</td>
                                <td v-if="comparePeriod" class="num font-semibold"
                                    :class="r.status === 'good' ? 'text-maiic-700' : r.status === 'bad' ? 'text-red-600' : r.status === 'watch' ? 'text-amber-600' : 'text-gray-400'">
                                    {{ r.change ?? '-' }}
                                </td>
                                <td v-if="comparePeriod" class="text-center">
                                    <span v-if="r.status && r.status !== 'neutral'"
                                          :class="['maiic-badge', r.status === 'good' ? 'maiic-badge-green' : r.status === 'watch' ? 'maiic-badge-gold' : 'maiic-badge-red']">
                                        <span v-if="r.up !== null" class="mr-1">{{ r.up ? '▲' : '▼' }}</span>
                                        {{ r.status === 'good' ? 'Favourable' : r.status === 'watch' ? 'Watch' : 'Adverse' }}
                                    </span>
                                    <span v-else-if="r.status === 'neutral'" class="maiic-badge maiic-badge-grey">Stable</span>
                                    <span v-else class="text-gray-300">-</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ============================ ECL BY PORTFOLIO OVER MONTHS ============================ -->
            <div class="maiic-panel mb-6 min-w-0 p-6">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="font-bold text-gray-900 dark:text-slate-100">{{ mixTitle }}</h3>
                        <p class="text-xs text-gray-400">{{ rangeLabel(eclMixTrend && eclMixTrend.periods) }}<span v-if="selectedPortfolioName"> &middot; {{ selectedPortfolioName }}</span> &middot; the six largest in the last month, the rest as one line</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <select v-model="filterForm.pf_from" @change="applyChartRange('eclMixTrend')" :class="rangeSelect" title="First period of this chart">
                            <option :value="null">Last 12 months</option>
                            <option value="all">From the start</option>
                            <option v-for="p in periods" :key="'pf' + p" :value="p">From {{ periodLabel(p) }}</option>
                        </select>
                        <select v-model="filterForm.pf_to" @change="applyChartRange('eclMixTrend')" :class="rangeSelect" title="Last period of this chart">
                            <option :value="null">To {{ periodLabel(selectedPeriod) }}</option>
                            <option v-for="p in periods" :key="'pt' + p" :value="p">To {{ periodLabel(p) }}</option>
                        </select>
                        <span v-if="mixHasData" :class="['maiic-badge', eclMixTrend.ties ? 'maiic-badge-green' : 'maiic-badge-gold']"
                              :title="eclMixTrend.ties ? 'Every month adds back to the total ECL of the trend' : mixDiffersTitle">
                            {{ eclMixTrend.ties ? 'Each month ties to the total ECL' : 'Differs from the total ECL in ' + eclMixTrend.differs.length + ' month' + (eclMixTrend.differs.length === 1 ? '' : 's') }}
                        </span>
                        <div class="flex overflow-hidden rounded-lg border border-gray-200 text-xs dark:border-slate-600">
                            <button @click="mixView = 'chart'" :class="tgl(mixView === 'chart')" class="px-3 py-1">Chart</button>
                            <button @click="mixView = 'table'" :class="tgl(mixView === 'table')" class="px-3 py-1">Table</button>
                        </div>
                    </div>
                </div>
                <template v-if="mixHasData">
                    <div v-show="mixView === 'chart'" class="relative h-80 w-full min-w-0"><canvas ref="mixChart"></canvas></div>
                    <div v-if="mixView === 'table'" class="maiic-table-wrap">
                        <table class="maiic-table text-[13px]">
                            <thead>
                                <tr>
                                    <th>Period</th>
                                    <th v-for="s in eclMixTrend.series" :key="s.label" class="num">{{ s.label }}</th>
                                    <th class="num">Total ({{ currencyCode }})</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(p, i) in eclMixTrend.periods" :key="p">
                                    <td>{{ periodLabel(p) }}</td>
                                    <td v-for="s in eclMixTrend.series" :key="s.label" class="num">{{ formatAmount(s.values[i]) }}</td>
                                    <td class="num font-semibold">{{ formatAmount(eclMixTrend.totals[i]) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>
                <p v-else class="maiic-empty">No calculated ECL in the selected range.</p>
            </div>

            <!-- ============================ SCENARIOS AND DEFAULTS ============================ -->
            <div class="mb-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
                <div class="maiic-panel min-w-0 p-6">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="font-bold text-gray-900 dark:text-slate-100">Base and stressed ECL</h3>
                            <p class="text-xs text-gray-400">{{ rangeLabel(scenarioEcl && scenarioEcl.range_periods) }}<span v-if="selectedPortfolioName"> &middot; {{ selectedPortfolioName }}</span> &middot; {{ stageOptions.find(o => o.value === scenarioStage).label.toLowerCase() }}</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <select v-model="filterForm.st_from" @change="applyChartRange('scenarioEcl')" :class="rangeSelect" title="First period of this chart">
                                <option :value="null">Last 12 months</option>
                                <option value="all">From the start</option>
                                <option v-for="p in periods" :key="'sf' + p" :value="p">From {{ periodLabel(p) }}</option>
                            </select>
                            <select v-model="filterForm.st_to" @change="applyChartRange('scenarioEcl')" :class="rangeSelect" title="Last period of this chart">
                                <option :value="null">To {{ periodLabel(selectedPeriod) }}</option>
                                <option v-for="p in periods" :key="'st' + p" :value="p">To {{ periodLabel(p) }}</option>
                            </select>
                            <select v-model="scenarioStage" :class="rangeSelect" title="The stage the bars show">
                                <option v-for="o in stageOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                            </select>
                            <div class="flex overflow-hidden rounded-lg border border-gray-200 text-xs dark:border-slate-600">
                                <button @click="scenarioView = 'chart'" :class="tgl(scenarioView === 'chart')" class="px-3 py-1">Chart</button>
                                <button @click="scenarioView = 'table'" :class="tgl(scenarioView === 'table')" class="px-3 py-1">Table</button>
                            </div>
                        </div>
                    </div>
                    <template v-if="scenarioHasData">
                        <div class="mb-2 flex flex-wrap items-center gap-2">
                            <span v-if="scenarioTies !== null" :class="['maiic-badge', scenarioTies.ok ? 'maiic-badge-green' : 'maiic-badge-gold']"
                                  :title="scenarioTies.ok ? 'In every period shown, the probability-weighted ECL equals the total ECL of that period' : 'The record of ' + scenarioTies.differs.map(periodLabel).join(', ') + ' was taken on an ECL that differs from the total ECL now held'">
                                {{ scenarioTies.ok ? 'Weighted ties to the total ECL' : 'Differs from the total ECL in ' + scenarioTies.differs.length + ' period' + (scenarioTies.differs.length === 1 ? '' : 's') }}
                            </span>
                            <span v-if="scenarioMissing" class="text-[11px] text-gray-500 dark:text-slate-400">No stress run for {{ scenarioMissing }}.</span>
                        </div>
                        <div v-show="scenarioView === 'chart'" class="relative h-72 w-full min-w-0"><canvas ref="scenarioChart"></canvas></div>
                        <div v-if="scenarioView === 'table'" class="maiic-table-wrap">
                            <table class="maiic-table text-[13px]">
                                <thead>
                                    <tr>
                                        <th>Period</th>
                                        <th v-for="name in scenarioNames" :key="name" class="num">{{ name }}<span v-if="scenarioWeights[name] != null" class="font-normal"> ({{ scenarioWeights[name].toFixed(0) }}%)</span></th>
                                        <th class="num" title="Loans in the record for the stage shown">Loans</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="p in scenarioEcl.periods" :key="p">
                                        <td :title="sourceLabel(scenarioEcl.by_period[p])">{{ periodLabel(p) }}</td>
                                        <td v-for="name in scenarioNames" :key="name" class="num">{{ scenarioCell(p, name) }}</td>
                                        <td class="num text-gray-500">{{ formatCount((scenarioEcl.by_period[p].loans || [])[scenarioStage]) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p class="mt-3 text-[11px] text-gray-500 dark:text-slate-400">{{ scenarioSourceLine }}</p>
                        <p v-for="line in scenarioSetLines" :key="line" class="mt-1 text-[11px] text-gray-500 dark:text-slate-400">{{ line }}</p>
                        <p v-for="n in (scenarioEcl.notes || [])" :key="n" class="mt-1 text-[11px] text-amber-700 dark:text-amber-300">{{ n }}</p>
                    </template>
                    <p v-else class="maiic-empty">No stress run for {{ scenarioMissing || periodLabel(selectedPeriod) }}{{ selectedPortfolioName ? ' for this portfolio' : '' }}. Close the forward-looking month or run the scenario set on the Stress Testing screen.</p>
                </div>

                <div class="maiic-panel min-w-0 p-6">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="font-bold text-gray-900 dark:text-slate-100">Observed default rate</h3>
                            <p class="text-xs text-gray-400">{{ rangeLabel((defaultRates && defaultRates.rows || []).map(r => r.period)) }}<span v-if="selectedPortfolioName"> &middot; {{ selectedPortfolioName }} against the whole book</span> &middot; share of the Stage 1 and 2 balance that defaulted or left the book unpaid</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <select v-model="filterForm.dr_from" @change="applyChartRange('defaultRates')" :class="rangeSelect" title="First period of this chart">
                                <option :value="null">Last 12 months</option>
                                <option value="all">From the start</option>
                                <option v-for="p in periods" :key="'df' + p" :value="p">From {{ periodLabel(p) }}</option>
                            </select>
                            <select v-model="filterForm.dr_to" @change="applyChartRange('defaultRates')" :class="rangeSelect" title="Last period of this chart">
                                <option :value="null">To {{ periodLabel(selectedPeriod) }}</option>
                                <option v-for="p in periods" :key="'dt' + p" :value="p">To {{ periodLabel(p) }}</option>
                            </select>
                            <div class="flex overflow-hidden rounded-lg border border-gray-200 text-xs dark:border-slate-600">
                                <button @click="defaultView = 'chart'" :class="tgl(defaultView === 'chart')" class="px-3 py-1">Chart</button>
                                <button @click="defaultView = 'table'" :class="tgl(defaultView === 'table')" class="px-3 py-1">Table</button>
                            </div>
                        </div>
                    </div>
                    <template v-if="defaultHasData">
                        <div v-show="defaultView === 'chart'" class="relative h-72 w-full min-w-0"><canvas ref="defaultChart"></canvas></div>
                        <div v-if="defaultView === 'table'" class="maiic-table-wrap">
                            <table class="maiic-table text-[13px]">
                                <thead>
                                    <tr>
                                        <th>Period</th>
                                        <th class="num" title="Defaults over performing loans at the start of the month">Book, month</th>
                                        <th class="num" title="Defaults over performing loans twelve months earlier">Book, 12 months</th>
                                        <th v-if="selectedPortfolioId" class="num">Portfolio, month</th>
                                        <th v-if="selectedPortfolioId" class="num">Portfolio, 12 months</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="r in defaultRates.rows" :key="r.period">
                                        <td>{{ periodLabel(r.period) }}</td>
                                        <td class="num" :title="rateTitle(r.monthly.book)">{{ rateCell(r.monthly.book) }}</td>
                                        <td class="num" :title="rateTitle(r.annual.book)">{{ rateCell(r.annual.book) }}</td>
                                        <td v-if="selectedPortfolioId" class="num" :title="rateTitle(r.monthly.portfolio)">{{ rateCell(r.monthly.portfolio) }}</td>
                                        <td v-if="selectedPortfolioId" class="num" :title="rateTitle(r.annual.portfolio)">{{ rateCell(r.annual.portfolio) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p class="mt-3 text-[11px] text-gray-500 dark:text-slate-400">Balance-weighted, from the PD engine's own observation of the loan books (one-month window, and the twelve-month window of the observed one-year PD). A gap means no performing loan at the start of the window.</p>
                        <p v-for="n in (defaultRates.notes || [])" :key="n" class="mt-1 text-[11px] text-amber-700 dark:text-amber-300">Not measured: {{ n }}</p>
                    </template>
                    <p v-else class="maiic-empty">No default rate can be observed in this range: the loan books hold no month-to-month transition.</p>
                </div>
            </div>
        </template>
    </app-layout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, onMounted, onBeforeUnmount, watch, computed } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { Chart, registerables } from 'chart.js'
import HelpManual from '../Components/HelpManual.vue'
import { useTheme } from '@/composables/useTheme'

Chart.register(...registerables)
const theme = useTheme()

// Respect the OS / browser 'reduce motion' setting (also used by the
// manual screenshot tool so charts are captured fully drawn).
function reducedMotion() {
    return typeof window !== 'undefined' && window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches
}

const props = defineProps({
    summary: Object,
    compareSummary: Object,
    periods: { type: Array, default: () => [] },
    portfolios: { type: Array, default: () => [] },
    selectedPeriod: String,
    selectedPortfolioId: Number,
    comparePeriod: String,
    trendFrom: String,
    trendTo: String,
    eclTrends: { type: Array, default: () => [] },
    eclMixTrend: Object,
    scenarioEcl: Object,
    defaultRates: Object,
    eclBuildUp: Object,
    eclBreakdown: Object,
    error: String,
})

const page = usePage()
// Organisation reporting currency (Settings > currency) - shared prop.
const currencyCode = computed(() => page.props.currency?.code || '')
const summary = computed(() => props.summary || {})

// MAIIC palette for charts: brand green, deep green, gold, red, orange.
const C = { green: '#16a34a', deep: '#14532d', gold: '#f59e0b', red: '#dc2626', orange: '#ea580c' }
// the chart's neutrals follow the theme: card surface, axis text, grid lines
const N = () => theme.isDark.value
    ? { surface: '#1e293b', text: '#94a3b8', legend: '#cbd5e1', grid: 'rgba(51, 65, 85, 0.8)', line: '#4ade80' }
    : { surface: '#ffffff', text: '#64748b', legend: '#475569', grid: 'rgba(226, 232, 240, 0.8)', line: C.deep }

const filterForm = ref({
    period: props.selectedPeriod,
    portfolio_id: props.selectedPortfolioId ?? null,
    compare: props.comparePeriod ?? null,
    trend_from: props.trendFrom ?? null,
    trend_to: props.trendTo ?? null,
    // each chart below the summary keeps its own range
    pf_from: props.eclMixTrend?.range?.from ?? null,
    pf_to: props.eclMixTrend?.range?.to ?? null,
    st_from: props.scenarioEcl?.range?.from ?? null,
    st_to: props.scenarioEcl?.range?.to ?? null,
    dr_from: props.defaultRates?.range?.from ?? null,
    dr_to: props.defaultRates?.range?.to ?? null,
})
const rangeSelect = 'rounded-md border border-maiic-200 bg-maiic-50 py-1 pl-2 pr-7 text-xs font-semibold text-maiic-800 focus:border-maiic-500 focus:ring-maiic-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200'

// Only months before the reporting month can be compared with it.
const comparablePeriods = computed(() => (props.periods || []).filter(p => p < filterForm.value.period))

// The PDF reads the period, portfolio and compare-to month the page shows.
const reportPdfUrl = computed(() => {
    const q = {}
    if (props.selectedPeriod) q.period = props.selectedPeriod
    if (props.selectedPortfolioId) q.portfolio_id = props.selectedPortfolioId
    if (props.comparePeriod) q.compare = props.comparePeriod
    if (props.trendFrom) q.trend_from = props.trendFrom
    if (props.trendTo) q.trend_to = props.trendTo
    return route('dashboard.ecl-report-pdf', q)
})

const selectedPortfolioName = computed(() => {
    const p = (props.portfolios || []).find(p => p.id === props.selectedPortfolioId)
    return p ? p.name : null
})

const trendRangeLabel = computed(() => {
    const t = props.eclTrends || []
    if (!t.length) return 'No calculated periods in the selected range'
    return `${periodLabel(t[0].period)} to ${periodLabel(t[t.length - 1].period)}`
})

function applyFilters() {
    // A new reporting month on or before the compare month: compare with the
    // closest earlier calculated month instead (periods are newest first).
    const f = filterForm.value
    if (f.compare && f.period && f.compare >= f.period) {
        f.compare = (props.periods || []).find(p => p < f.period) ?? null
    }
    const query = {}
    Object.entries(filterForm.value).forEach(([k, v]) => { if (v) query[k] = v })
    router.get(route('dashboard'), query, { preserveState: false, preserveScroll: true })
}

// One chart's own from / to: reload that chart's prop alone, keep the page
// where it is (the other charts and the scroll position do not move).
function applyChartRange(prop) {
    const query = {}
    Object.entries(filterForm.value).forEach(([k, v]) => { if (v) query[k] = v })
    router.get(route('dashboard'), query, { preserveState: true, preserveScroll: true, replace: true, only: [prop] })
}

// "Jan 2026 to Aug 2026" for a list of periods (oldest first)
function rangeLabel(list) {
    const l = (list || []).filter(Boolean)
    if (!l.length) return 'No calculated periods in the selected range'
    return l.length === 1 ? periodLabel(l[0]) : `${periodLabel(l[0])} to ${periodLabel(l[l.length - 1])}`
}
// consecutive runs of the range's periods: "Jan 2026 to Jul 2026, Sep 2026"
function runsLabel(all, subset) {
    const want = new Set(subset)
    const runs = []
    let cur = null
    for (const p of all) {
        if (want.has(p)) { cur ? cur.push(p) : runs.push(cur = [p]) } else { cur = null }
    }
    return runs.map(r => r.length === 1 ? periodLabel(r[0]) : periodLabel(r[0]) + ' to ' + periodLabel(r[r.length - 1])).join(', ')
}

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
function periodLabel(p) {
    if (!p) return ''
    const [y, m] = String(p).split('-')
    return m ? `${MONTHS[Number(m) - 1]} ${y}` : p
}
function formatAmount(amount) {
    const n = Number(amount || 0)
    const abs = Math.abs(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    return n < 0 ? '(' + abs + ')' : abs
}
function formatCount(n) {
    return Number(n || 0).toLocaleString()
}
function formatPct(v) {
    return Number(v || 0).toFixed(2) + '%'
}
// Compact currency so KPI tiles never overflow: 66.40B / 8.62M / 950.0K
function money(v) {
    const n = Number(v || 0)
    const a = Math.abs(n)
    const c = currencyCode.value ? currencyCode.value + ' ' : ''
    if (a >= 1e12) return c + (n / 1e12).toFixed(2) + 'T'
    if (a >= 1e9) return c + (n / 1e9).toFixed(2) + 'B'
    if (a >= 1e6) return c + (n / 1e6).toFixed(2) + 'M'
    if (a >= 1e3) return c + (n / 1e3).toFixed(1) + 'K'
    return c + n.toLocaleString()
}
function fullMoney(v) {
    return (currencyCode.value ? currencyCode.value + ' ' : '') + formatAmount(v)
}

function stageShare(i) {
    const e = summary.value.total_eads || [0, 0, 0]
    const tot = e[0] + e[1] + e[2]
    return tot ? ((e[i] / tot) * 100).toFixed(1) + '%' : '0%'
}

// a Chart/Table (or view) toggle button, readable in light and dark
function tgl(active) {
    return active ? 'bg-maiic-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'
}

// Signed change vs the compare-to period. Money compares as % change,
// ratios in percentage points. goodWhenUp says whether a rise is favourable.
function deltaInfo(key, kind, goodWhenUp) {
    const s = summary.value
    const c = props.compareSummary
    if (!c || !props.comparePeriod) return { delta: null, deltaUp: null, deltaGood: null }
    const now = Number(s[key] || 0)
    const then = Number(c[key] || 0)
    let text, d
    if (kind === 'money') {
        if (then === 0) return { delta: null, deltaUp: null, deltaGood: null }
        d = ((now - then) / Math.abs(then)) * 100
        text = `${d > 0 ? '+' : ''}${d.toFixed(1)}% vs ${periodLabel(props.comparePeriod)}`
    } else {
        d = now - then
        text = `${d > 0 ? '+' : ''}${d.toFixed(2)}pts vs ${periodLabel(props.comparePeriod)}`
    }
    if (Math.abs(d) < 0.005) return { delta: text, deltaUp: null, deltaGood: null }
    return { delta: text, deltaUp: d > 0, deltaGood: goodWhenUp ? d > 0 : d < 0 }
}

const kpis = computed(() => {
    const s = summary.value
    // Lucide-style single-path icons per metric.
    const I = {
        bank: 'M3 21h18M4 18h16M6 18V9m4 9V9m4 9V9m4 9V9M2 9l10-6 10 6H2Z',
        alert: 'M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0ZM12 9v4m0 4h.01',
        shield: 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Zm-3-10 2 2 4-4',
        pie: 'M21.2 15.9A10 10 0 1 1 8 2.8M22 12A10 10 0 0 0 12 2v10Z',
        trend: 'M3 3v18h18M7 14l4-4 3 3 5-6',
        scale: 'M12 3v18M8 21h8M7 7l-4 6a4 4 0 0 0 8 0L7 7Zm10 0-4 6a4 4 0 0 0 8 0l-4-6ZM4 7h16',
    }
    return [
        { label: 'Total exposure (EAD)', value: money(s.carrying_amount), full: fullMoney(s.carrying_amount), icon: I.bank, accent: C.green, ...deltaInfo('carrying_amount', 'money', true) },
        { label: s.ecl_basis === 'discounted' ? 'Total ECL (booked)' : (s.ecl_basis === 'mixed' ? 'Total ECL (part discounted)' : 'Total ECL (undiscounted)'), value: money(s.total_ecl), full: fullMoney(s.total_ecl) + (s.ecl_basis === 'discounted' ? ', discounted at the EIR' : (s.ecl_basis === 'mixed' ? ': some runs of the period were not discounted' : ': the period was run undiscounted')), icon: I.alert, accent: C.red, ...deltaInfo('total_ecl', 'money', false) },
        { label: 'ECL coverage', value: formatPct(s.ecl_percentage), full: null, icon: I.shield, accent: C.gold, ...deltaInfo('ecl_percentage', 'pts', false) },
        { label: 'Stage 3 exposure', value: money(s.stage_3_amount), full: fullMoney(s.stage_3_amount), icon: I.pie, accent: C.orange, sub: formatPct(s.stage_3_percentage) + ' of book', ...deltaInfo('stage_3_amount', 'money', false) },
        { label: 'Weighted PD', value: formatPct(s.weighted_pd), full: null, icon: I.trend, accent: C.deep, ...deltaInfo('weighted_pd', 'pts', false) },
        { label: 'Weighted LGD', value: formatPct(s.weighted_lgd), full: null, icon: I.scale, accent: C.deep, ...deltaInfo('weighted_lgd', 'pts', false) },
    ]
})

const stages = computed(() => {
    const s = summary.value
    const meta = [
        { tag: 'Performing', badge: 'bg-maiic-100 text-maiic-800', bar: 'bg-maiic-600', border: 'border-maiic-200 dark:border-slate-700', wash: 'bg-gradient-to-br from-maiic-50 to-white dark:from-slate-800 dark:to-slate-800', text: 'text-maiic-800 dark:text-maiic-300' },
        { tag: 'Under-performing', badge: 'bg-amber-100 text-amber-800', bar: 'bg-amber-500', border: 'border-amber-200 dark:border-slate-700', wash: 'bg-gradient-to-br from-amber-50 to-white dark:from-slate-800 dark:to-slate-800', text: 'text-amber-800 dark:text-amber-300' },
        { tag: 'Credit-impaired', badge: 'bg-red-100 text-red-800', bar: 'bg-red-600', border: 'border-red-200 dark:border-slate-700', wash: 'bg-gradient-to-br from-red-50 to-white dark:from-slate-800 dark:to-slate-800', text: 'text-red-700 dark:text-red-300' },
    ]
    return [0, 1, 2].map(i => ({
        ead: (s.total_eads || [])[i] || 0,
        ecl: (s.ecl_totals || [])[i] || 0,
        pd: (s.pd_percentages || [])[i] || 0,
        lgd: (s.lgd_percentages || [])[i] || 0,
        loans: (s.loans_by_stage || [])[i] || 0,
        ...meta[i],
    }))
})

// Current vs compare-to with metric-aware traffic lights: a rise in exposure
// is growth, a rise in ECL / PD / LGD / coverage is risk.
const summaryRows = computed(() => {
    const s = summary.value
    const c = props.compareSummary || null
    const row = (label, key, kind, goodWhenUp, bold = false) => {
        const now = Number(s[key] || 0)
        const then = c ? Number(c[key] || 0) : null
        let change = null, status = null
        if (c && then !== null) {
            if (kind === 'money' || kind === 'count') {
                change = then !== 0 ? ((now - then) / Math.abs(then)) * 100 : null
                if (change !== null) {
                    const good = goodWhenUp ? change > 0 : change < 0
                    status = Math.abs(change) < 1 ? 'neutral' : (good ? 'good' : (Math.abs(change) < 10 ? 'watch' : 'bad'))
                    change = `${change > 0 ? '+' : ''}${change.toFixed(1)}%`
                }
            } else {
                const d = now - then
                const good = goodWhenUp ? d > 0 : d < 0
                status = Math.abs(d) < 0.05 ? 'neutral' : (good ? 'good' : (Math.abs(d) < 1 ? 'watch' : 'bad'))
                change = `${d > 0 ? '+' : ''}${d.toFixed(2)}pts`
            }
        }
        const fmt = (v) => kind === 'money' ? formatAmount(v) : kind === 'count' ? formatCount(v) : formatPct(v)
        return { label, bold, value: fmt(now), compare: c ? fmt(then) : null, change, status, up: c && then !== null ? now > then : null }
    }
    return [
        row('Number of loans', 'total_loans', 'count', true),
        row('Total EAD', 'carrying_amount', 'money', true, true),
        row('Stage 3 share of book', 'stage_3_percentage', 'pts', false),
        row('Weighted PD', 'weighted_pd', 'pts', false),
        row('Weighted LGD', 'weighted_lgd', 'pts', false),
        row('ECL coverage', 'ecl_percentage', 'pts', false),
        row('Total ECL', 'total_ecl', 'money', false, true),
        row('Net carrying amount', 'net_carrying_amount', 'money', true),
    ]
})

// ECL build-up: before FLI, the forward-looking effect of the model, the
// overlays the route applied, the booked ECL. Bars are a waterfall on one
// scale (the largest running total).
const buildSteps = computed(() => {
    const b = props.eclBuildUp
    if (!b) return []
    const final = Number(b.final || 0)
    if (!b.available) {
        return [{ label: 'ECL booked', value: final, sign: '', pct: null, left: 0, width: 100, bar: 'bg-maiic-600', total: true, note: null }]
    }
    const pre = Number(b.pre_fli || 0), model = Number(b.fli_model || 0), ov = Number(b.overlays || 0)
    const max = Math.max(pre, pre + model, final, 1)
    const seg = (from, delta) => ({ left: (Math.min(from, from + delta) / max) * 100, width: (Math.abs(delta) / max) * 100 })
    const pct = (v) => pre ? ((v > 0 ? '+' : '') + (v / pre * 100).toFixed(2) + '%') : null
    const overlayNote = (b.overlay_lines || []).length
        ? (b.overlay_lines.length + ' approved overlay' + (b.overlay_lines.length === 1 ? '' : 's') + ' from the register')
        : 'No approved overlay applied to these loans'
    return [
        { label: 'ECL before FLI', value: pre, sign: '', pct: null, ...seg(0, pre), bar: 'bg-slate-400', total: false, note: 'EAD x PD before FLI over the horizon x LGD' },
        { label: 'Forward-looking effect', value: model, sign: model > 0 ? '+' : '', pct: pct(model), ...seg(pre, model), bar: model >= 0 ? 'bg-amber-500' : 'bg-maiic-400', total: false, note: 'The macro adjustment to the PD, weighted across the scenario set' },
        { label: 'Manual overlays', value: ov, sign: ov > 0 ? '+' : '', pct: ov ? pct(ov) : null, ...seg(pre + model, ov), bar: 'bg-maiicgold-500', total: false, note: overlayNote },
        { label: 'ECL booked', value: final, sign: '', pct: null, ...seg(0, final), bar: 'bg-maiic-600', total: true, note: null },
    ]
})
const ranUnder = computed(() => {
    const l = (props.eclBuildUp?.lineage || [])[0]
    if (!l || !l.route) return null
    const parts = ['the ' + l.route.toLowerCase() + ' route' + (l.method ? ' (' + l.method.toLowerCase() + ')' : '')]
    if (l.fit) parts.push('fit ' + l.fit.id + (l.fit.relationship ? ', ' + l.fit.relationship : '') + (l.fit.status ? ', ' + l.fit.status.toLowerCase() : ''))
    if (l.set) parts.push('scenario set ' + l.set.id + (l.set.name ? ', ' + l.set.name : '') + (l.set.status ? ', ' + l.set.status.toLowerCase() : ''))
    const more = (props.eclBuildUp.lineage || []).length - 1
    return parts.join('; ') + (more > 0 ? '; and ' + more + ' other combination' + (more === 1 ? '' : 's') : '') + '.'
})

const breakdownViews = [
    { key: 'product', label: 'Product group' },
    { key: 'sector', label: 'Sector' },
    { key: 'rbm', label: 'RBM class' },
]
const breakdownView = ref('product')
const breakdown = computed(() => (props.eclBreakdown || {})[breakdownView.value] || { rows: [], total: { loans: 0, ead: 0, ecl: 0 } })
const breakdownCaption = computed(() => breakdownView.value === 'rbm'
    ? 'By the Reserve Bank of Malawi classes (days past due by term)'
    : 'The six largest by ECL, the rest as one line')
// the breakdown and the tiles must agree: the same loans, the same total
const breakdownTies = computed(() => Math.abs(Number(breakdown.value.total.ecl || 0) - Number(summary.value.total_ecl || 0)) <= Math.max(1, 0.01 * Number(breakdown.value.total.loans || 0)))

const breakdownMode = ref('table')
const breakdownChartHeight = computed(() => Math.max(180, (breakdown.value.rows || []).length * 38 + 70))

// ---- the three charts in place of the operations row ----------------------
const mixView = ref('chart')
const scenarioView = ref('chart')
const defaultView = ref('chart')

const mixHasData = computed(() => !!(props.eclMixTrend && (props.eclMixTrend.periods || []).length && (props.eclMixTrend.series || []).length))
const mixTitle = computed(() => props.eclMixTrend?.dimension === 'product' ? 'ECL by product group over months' : 'ECL by portfolio over months')
const mixDiffersTitle = computed(() => (props.eclMixTrend?.differs || [])
    .map(d => periodLabel(d.period) + ': ' + formatAmount(d.series_total) + ' against ' + formatAmount(d.trend_total)).join('; '))

const scenarioStage = ref(3)
const stageOptions = [{ value: 3, label: 'All stages' }, { value: 0, label: 'Stage 1' }, { value: 1, label: 'Stage 2' }, { value: 2, label: 'Stage 3' }]
const scenarioHasData = computed(() => !!(props.scenarioEcl && (props.scenarioEcl.periods || []).length))
// mild to severe, then the weighted figure; a name outside the four keeps its place after them
const SCEN_RANK = { Upside: 0, Base: 1, Downside: 2, Severe: 3 }
const scenarioNames = computed(() => {
    const names = []
    Object.values(props.scenarioEcl?.by_period || {}).forEach(r => (r.series || []).forEach(x => { if (!names.includes(x.name)) names.push(x.name) }))
    const w = (n) => n === 'Probability-weighted' ? 9 : (SCEN_RANK[n] ?? 5)
    return names.sort((a, b) => w(a) - w(b))
})
const scenarioWeights = computed(() => {
    const out = {}
    Object.values(props.scenarioEcl?.by_period || {}).forEach(r => (r.series || []).forEach(x => { if (x.weight != null) out[x.name] = x.weight }))
    return out
})
function scenarioValue(p, name) {
    const x = (props.scenarioEcl?.by_period?.[p]?.series || []).find(s => s.name === name)
    return x ? x.values[scenarioStage.value] : null
}
function scenarioCell(p, name) {
    const v = scenarioValue(p, name)
    return v === null ? '-' : formatAmount(v)
}
const scenarioMissing = computed(() => {
    const s = props.scenarioEcl
    if (!s || !(s.missing || []).length) return null
    return runsLabel(s.range_periods || [], s.missing)
})
const scenarioTies = computed(() => {
    const rows = Object.entries(props.scenarioEcl?.by_period || {}).filter(([, r]) => r.ties !== null && r.ties !== undefined)
    if (!rows.length) return null
    const differs = rows.filter(([, r]) => !r.ties).map(([p]) => p)
    return { ok: differs.length === 0, differs }
})
function sourceLabel(r) {
    if (!r) return ''
    if (r.source === 'month_end') return 'Forward-looking month-end record ' + r.record.id + ' (' + (r.record.status === 'SIGNED_OFF' ? 'signed off' : 'recorded, not yet signed off') + ')'
    return 'Saved stress runs of the scenario set'
}
const scenarioSourceLine = computed(() => {
    const by = props.scenarioEcl?.by_period || {}
    const groups = {}
    Object.entries(by).forEach(([p, r]) => { const k = sourceLabel(r); (groups[k] = groups[k] || []).push(p) })
    return Object.entries(groups).map(([k, ps]) => k + ': ' + runsLabel(props.scenarioEcl.range_periods || [], ps)).join('; ') + '.'
})
const scenarioSetLines = computed(() => {
    const seen = {}
    Object.values(props.scenarioEcl?.by_period || {}).forEach(r => { if (r.set) seen[r.set.id] = r.set })
    return Object.values(seen).map(set => {
        const who = set.bootstrap
            ? 'approved under the system bootstrap label (automated data-readiness, not a MAIIC approval)'
            : set.status === 'APPROVED' || set.status === 'LOCKED'
                ? 'approved' + (set.approver ? ' by ' + set.approver : '')
                : String(set.status || '').toLowerCase()
        return 'Scenario set ' + set.id + ' v' + set.version + ', ' + set.name + ': ' + who + '.'
    })
})

const defaultHasData = computed(() => (props.defaultRates?.rows || []).some(r => ['monthly', 'annual'].some(k => r[k]?.book?.rate != null || r[k]?.portfolio?.rate != null)))
function rateCell(x) {
    if (!x || x.rate === null || x.rate === undefined) return '-'
    return x.rate.toFixed(2) + '% (' + formatCount(x.defaults) + ' of ' + formatCount(x.loans) + ')'
}
function rateTitle(x) {
    if (!x) return 'No performing loan at the start of the window'
    return x.window + ' (' + x.months + (x.months === 1 ? ' month' : ' months') + '): ' + formatCount(x.defaults) + ' of ' + formatCount(x.loans) + ' loans, '
        + formatAmount(x.default_balance) + ' of ' + formatAmount(x.balance)
}

// Categorical hues in a fixed order (validated for colour-blind separation in
// light and dark); "Other" is always the neutral grey.
const CAT = () => theme.isDark.value
    ? ['#3987e5', '#d95926', '#199e70', '#c98500', '#d55181', '#008300', '#9085e9', '#e66767']
    : ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#6250d6', '#e34948']
const OTHER = () => theme.isDark.value ? '#64748b' : '#94a3b8'
// Scenario colours follow the scenario, from mild to severe.
const SCEN = () => theme.isDark.value
    ? { Upside: '#199e70', Base: '#3987e5', Downside: '#c98500', Severe: '#d55181' }
    : { Upside: '#1baf7a', Base: '#2a78d6', Downside: '#eda100', Severe: '#e34948' }

const pieView = ref('chart')
const trendView = ref('chart')
const pieChart = ref(null)
const trendChart = ref(null)
const breakdownChart = ref(null)
const mixChart = ref(null)
const scenarioChart = ref(null)
const defaultChart = ref(null)
const instances = {}

const tooltip = { backgroundColor: '#0b2b1a', titleColor: '#fbbf24', bodyColor: '#ffffff', padding: 12, cornerRadius: 10 }
const animation = () => reducedMotion() ? false : { duration: 700 }
const legend = (n) => ({ position: 'bottom', labels: { usePointStyle: true, pointStyle: 'circle', padding: 14, color: n.legend, font: { size: 11 } } })
const moneyTick = (v) => money(v).replace(currencyCode.value + ' ', '')

function make(key, canvas, config) {
    if (instances[key]) { instances[key].destroy(); delete instances[key] }
    if (canvas) instances[key] = new Chart(canvas.getContext('2d'), config)
}

function renderPie(n) {
    const e = summary.value.total_eads || [0, 0, 0]
    const loans = summary.value.loans_by_stage || [0, 0, 0]
    make('pie', pieChart.value, {
        type: 'doughnut',
        data: {
            labels: ['Stage 1', 'Stage 2', 'Stage 3'],
            datasets: [{ data: e, backgroundColor: [C.green, C.gold, C.red], borderWidth: 3, borderColor: n.surface, hoverOffset: 10 }],
        },
        options: {
            maintainAspectRatio: false,
            animation: animation(),
            cutout: '62%',
            plugins: {
                legend: { position: 'bottom', labels: { usePointStyle: true, pointStyle: 'circle', padding: 16, color: n.legend, font: { size: 12 } } },
                tooltip: {
                    ...tooltip,
                    callbacks: {
                        label: (item) => {
                            const total = e[0] + e[1] + e[2]
                            const share = total ? ((item.parsed / total) * 100).toFixed(1) : 0
                            return ' ' + formatAmount(item.parsed) + ' (' + share + '%)'
                        },
                        afterLabel: (item) => ' ' + formatCount(loans[item.dataIndex] || 0) + ' loan' + (Number(loans[item.dataIndex]) === 1 ? '' : 's'),
                    },
                },
            },
        },
    })
}

function renderTrend(n) {
    const t = props.eclTrends || []
    const coverage = t.map(i => Number(i.ecl_percentage || 0))
    const peak = coverage.length ? Math.max(...coverage) : 0
    // Two bands: the stacked ECL bars use the lower ~60% of the chart and
    // the coverage line rides above them, so the line never runs through
    // the bars. Each keeps its own scale (left currency, right %). The
    // exposure is a light area on its own (hidden) scale behind both; its
    // value is in the tooltip and the table.
    const barPeak = t.length ? Math.max(...t.map(i => (i.ecl_by_stage || []).reduce((a, b) => a + Number(b || 0), 0))) : 0
    const eadPeak = t.length ? Math.max(...t.map(i => Number(i.total_ead || 0))) : 0
    const eadLine = theme.isDark.value ? '#64748b' : '#94a3b8'
    make('trend', trendChart.value, {
        data: {
            labels: t.map(i => periodLabel(i.period)),
            datasets: [
                { type: 'bar', order: 1, label: 'Stage 1 ECL', data: t.map(i => (i.ecl_by_stage || [])[0] || 0), backgroundColor: C.green, stack: 'ecl', yAxisID: 'y', borderRadius: 3, maxBarThickness: 56 },
                { type: 'bar', order: 1, label: 'Stage 2 ECL', data: t.map(i => (i.ecl_by_stage || [])[1] || 0), backgroundColor: C.gold, stack: 'ecl', yAxisID: 'y', borderRadius: 3, maxBarThickness: 56 },
                { type: 'bar', order: 1, label: 'Stage 3 ECL', data: t.map(i => (i.ecl_by_stage || [])[2] || 0), backgroundColor: C.red, stack: 'ecl', yAxisID: 'y', borderRadius: 3, maxBarThickness: 56 },
                {
                    type: 'line', label: 'Coverage %', data: coverage, yAxisID: 'y1', order: 0,
                    borderColor: n.line, backgroundColor: n.line, borderWidth: 3, tension: 0.35,
                    pointRadius: t.map(i => i.period === props.selectedPeriod ? 6 : 3.5),
                    pointBackgroundColor: t.map(i => i.period === props.selectedPeriod ? '#fbbf24' : n.surface),
                    pointBorderColor: n.line, pointBorderWidth: 2,
                },
                {
                    type: 'line', label: 'Exposure (EAD)', data: t.map(i => Number(i.total_ead || 0)), yAxisID: 'y2', order: 2,
                    borderColor: eadLine, backgroundColor: theme.isDark.value ? 'rgba(100, 116, 139, 0.16)' : 'rgba(148, 163, 184, 0.16)',
                    fill: 'origin', borderWidth: 1.5, borderDash: [4, 3], tension: 0.35, pointRadius: 0, pointHoverRadius: 4,
                },
            ],
        },
        options: {
            maintainAspectRatio: false,
            animation: animation(),
            layout: { padding: { right: 14 } },
            interaction: { mode: 'index', intersect: false },
            scales: {
                y: {
                    stacked: true, beginAtZero: true, suggestedMax: barPeak > 0 ? barPeak * 1.65 : undefined,
                    border: { display: false }, grid: { color: n.grid },
                    ticks: { color: n.text, font: { size: 11 }, callback: moneyTick },
                },
                y1: {
                    position: 'right', beginAtZero: true, suggestedMax: peak > 0 ? Math.ceil(peak * 1.1) : 10,
                    border: { display: false }, grid: { display: false },
                    ticks: { color: n.line, font: { size: 11 }, callback: (v) => v + '%' },
                },
                y2: { display: false, beginAtZero: true, suggestedMax: eadPeak > 0 ? eadPeak * 1.08 : undefined },
                x: { stacked: true, border: { display: false }, grid: { display: false }, ticks: { color: n.text, font: { size: 11 }, maxRotation: 40 } },
            },
            plugins: {
                legend: legend(n),
                tooltip: {
                    ...tooltip,
                    callbacks: {
                        label: (item) => item.dataset.yAxisID === 'y1'
                            ? ' Coverage: ' + Number(item.parsed.y).toFixed(2) + '%'
                            : ' ' + item.dataset.label + ': ' + formatAmount(item.parsed.y),
                    },
                },
            },
        },
    })
}

// "Where the ECL sits" as horizontal bars: ECL per row on the bottom axis,
// coverage (and the RBM minimum on the RBM view) as markers on the top axis.
function renderBreakdown(n) {
    const rows = breakdown.value.rows || []
    const datasets = [
        { type: 'bar', label: 'ECL', data: rows.map(r => Number(r.ecl || 0)), xAxisID: 'x', backgroundColor: rows.map(r => r.other ? OTHER() : C.green), borderRadius: 4, maxBarThickness: 22, order: 1 },
        { type: 'line', label: 'Coverage %', data: rows.map(r => r.coverage === null ? null : Number(r.coverage)), xAxisID: 'x1', showLine: false,
          pointStyle: 'rectRot', pointRadius: 6, pointHoverRadius: 8, pointBackgroundColor: n.line, pointBorderColor: n.surface, pointBorderWidth: 2, borderColor: n.line, backgroundColor: n.line, order: 0 },
    ]
    if (breakdownView.value === 'rbm') {
        datasets.push({ type: 'line', label: 'RBM minimum %', data: rows.map(r => r.minimum == null ? null : Number(r.minimum) * 100), xAxisID: 'x1', showLine: false,
            pointStyle: 'line', rotation: 90, pointRadius: 9, pointBorderWidth: 3, borderColor: C.gold, backgroundColor: C.gold, pointBorderColor: C.gold, order: 0 })
    }
    const covPeak = Math.max(0, ...rows.map(r => Number(r.coverage || 0)), ...(breakdownView.value === 'rbm' ? rows.map(r => Number(r.minimum || 0) * 100) : []))
    make('breakdown', breakdownChart.value, {
        data: { labels: rows.map(r => r.label.length > 34 ? r.label.slice(0, 33) + '...' : r.label), datasets },
        options: {
            indexAxis: 'y',
            maintainAspectRatio: false,
            animation: animation(),
            interaction: { mode: 'index', intersect: false, axis: 'y' },
            scales: {
                x: { beginAtZero: true, position: 'bottom', border: { display: false }, grid: { color: n.grid }, ticks: { color: n.text, font: { size: 11 }, callback: moneyTick } },
                x1: { beginAtZero: true, position: 'top', suggestedMax: covPeak > 0 ? Math.min(100, Math.ceil(covPeak * 1.1)) : 10, border: { display: false }, grid: { display: false },
                      ticks: { color: n.line, font: { size: 11 }, callback: (v) => v + '%' } },
                y: { border: { display: false }, grid: { display: false }, ticks: { color: n.text, font: { size: 11 } } },
            },
            plugins: {
                legend: legend(n),
                tooltip: {
                    ...tooltip,
                    callbacks: {
                        title: (items) => rows[items[0].dataIndex]?.label,
                        label: (item) => item.dataset.xAxisID === 'x1'
                            ? ' ' + item.dataset.label.replace(' %', '') + ': ' + Number(item.parsed.x).toFixed(2) + '%'
                            : ' ECL: ' + formatAmount(item.parsed.x) + ' (' + formatCount(rows[item.dataIndex]?.loans) + ' loans)',
                    },
                },
            },
        },
    })
}

function renderMix(n) {
    const m = props.eclMixTrend
    if (!mixHasData.value) return make('mix', null)
    const cat = CAT()
    let slot = 0
    make('mix', mixChart.value, {
        type: 'line',
        data: {
            labels: m.periods.map(periodLabel),
            datasets: m.series.map(s => {
                const colour = s.other ? OTHER() : cat[slot++ % cat.length]
                return { label: s.label, data: s.values, borderColor: colour, backgroundColor: colour, borderWidth: 2, tension: 0.3,
                    borderDash: s.other ? [5, 4] : [], pointRadius: 3, pointHoverRadius: 6, pointBackgroundColor: colour, pointBorderColor: n.surface, pointBorderWidth: 2 }
            }),
        },
        options: {
            maintainAspectRatio: false,
            animation: animation(),
            layout: { padding: { right: 14 } },
            interaction: { mode: 'index', intersect: false },
            scales: {
                y: { beginAtZero: true, border: { display: false }, grid: { color: n.grid }, ticks: { color: n.text, font: { size: 11 }, callback: moneyTick } },
                x: { offset: true, border: { display: false }, grid: { display: false }, ticks: { color: n.text, font: { size: 11 }, maxRotation: 40 } },
            },
            plugins: {
                legend: legend(n),
                tooltip: {
                    ...tooltip,
                    itemSort: (a, b) => b.parsed.y - a.parsed.y,
                    callbacks: {
                        label: (item) => ' ' + item.dataset.label + ': ' + formatAmount(item.parsed.y),
                        footer: (items) => items.length ? 'Total ECL: ' + formatAmount(m.totals[items[0].dataIndex]) : '',
                    },
                },
            },
        },
    })
}

function renderScenario(n) {
    const s = props.scenarioEcl
    if (!scenarioHasData.value) return make('scenario', null)
    const sc = SCEN()
    const cat = CAT()
    let spare = 6
    const st = scenarioStage.value
    const loansAt = (i) => (s.by_period[s.periods[i]]?.loans || [])[st]
    make('scenario', scenarioChart.value, {
        type: 'bar',
        data: {
            labels: s.periods.map(periodLabel),
            datasets: scenarioNames.value.map(name => {
                const weighted = name === 'Probability-weighted'
                const colour = weighted ? n.line : (sc[name] || cat[spare++ % cat.length])
                const w = scenarioWeights.value[name]
                return { label: weighted ? name : name + (w != null ? ' (' + w.toFixed(0) + '%)' : ''), data: s.periods.map(p => scenarioValue(p, name)),
                    backgroundColor: colour, borderColor: n.surface, borderWidth: { left: 1, right: 1, top: 0, bottom: 0 }, borderRadius: 4, borderSkipped: 'bottom', maxBarThickness: 30 }
            }),
        },
        options: {
            maintainAspectRatio: false,
            animation: animation(),
            layout: { padding: { right: 14 } },
            interaction: { mode: 'index', intersect: false },
            scales: {
                y: { beginAtZero: true, border: { display: false }, grid: { color: n.grid }, ticks: { color: n.text, font: { size: 11 }, callback: moneyTick } },
                x: { border: { display: false }, grid: { display: false }, ticks: { color: n.text, font: { size: 11 } } },
            },
            plugins: {
                legend: legend(n),
                tooltip: {
                    ...tooltip,
                    callbacks: {
                        label: (item) => item.parsed.y === null ? null : ' ' + item.dataset.label + ': ' + formatAmount(item.parsed.y),
                        footer: (items) => items.length && loansAt(items[0].dataIndex) != null ? formatCount(loansAt(items[0].dataIndex)) + ' loans' : '',
                    },
                },
            },
        },
    })
}

function renderDefaults(n) {
    const d = props.defaultRates
    if (!defaultHasData.value) return make('default', null)
    const rows = d.rows
    const cat = CAT()
    const pick = (k, w) => rows.map(r => r[w]?.[k]?.rate ?? null)
    const line = (label, data, colour, dashed) => ({ label, data, borderColor: colour, backgroundColor: colour, borderWidth: 2, tension: 0.25, spanGaps: false,
        borderDash: dashed ? [6, 4] : [], pointRadius: 3.5, pointHoverRadius: 6, pointBackgroundColor: dashed ? n.surface : colour, pointBorderColor: colour, pointBorderWidth: 2 })
    const datasets = []
    if (props.selectedPortfolioId) {
        datasets.push(line((selectedPortfolioName.value || 'Portfolio') + ', month', pick('portfolio', 'monthly'), cat[1], false))
        datasets.push(line((selectedPortfolioName.value || 'Portfolio') + ', 12 months', pick('portfolio', 'annual'), cat[1], true))
    }
    datasets.push(line('Whole book, month', pick('book', 'monthly'), cat[0], false))
    datasets.push(line('Whole book, 12 months', pick('book', 'annual'), cat[0], true))
    const detail = (w, k, i) => rows[i]?.[w]?.[k]
    make('default', defaultChart.value, {
        type: 'line',
        data: { labels: rows.map(r => periodLabel(r.period)), datasets },
        options: {
            maintainAspectRatio: false,
            animation: animation(),
            layout: { padding: { right: 14 } },
            interaction: { mode: 'index', intersect: false },
            scales: {
                y: { beginAtZero: true, border: { display: false }, grid: { color: n.grid }, ticks: { color: n.text, font: { size: 11 }, callback: (v) => v + '%' } },
                x: { offset: true, border: { display: false }, grid: { display: false }, ticks: { color: n.text, font: { size: 11 }, maxRotation: 40 } },
            },
            plugins: {
                legend: legend(n),
                tooltip: {
                    ...tooltip,
                    callbacks: {
                        label: (item) => {
                            const w = item.dataset.label.endsWith('12 months') ? 'annual' : 'monthly'
                            const k = item.dataset.label.startsWith('Whole book') ? 'book' : 'portfolio'
                            const x = detail(w, k, item.dataIndex)
                            return ' ' + item.dataset.label + ': ' + Number(item.parsed.y).toFixed(2) + '%' + (x ? ' (' + formatCount(x.defaults) + ' of ' + formatCount(x.loans) + ' loans)' : '')
                        },
                    },
                },
            },
        },
    })
}

function renderCharts() {
    Object.keys(instances).forEach(k => { instances[k].destroy(); delete instances[k] })
    if (props.error) return
    const n = N()
    renderPie(n)
    renderTrend(n)
    renderBreakdown(n)
    renderMix(n)
    renderScenario(n)
    renderDefaults(n)
}

onMounted(renderCharts)
watch(() => [props.summary, props.eclTrends], renderCharts)
watch(theme.isDark, renderCharts)
watch(breakdownView, () => renderBreakdown(N()))
watch(() => props.eclMixTrend, () => renderMix(N()), { flush: 'post' })
watch(() => [props.scenarioEcl, scenarioStage.value], () => renderScenario(N()), { flush: 'post' })
watch(() => props.defaultRates, () => renderDefaults(N()), { flush: 'post' })
onBeforeUnmount(() => {
    Object.keys(instances).forEach(k => { instances[k].destroy(); delete instances[k] })
})
</script>
