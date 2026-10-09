<template>
    <app-layout title="Dashboard">
        <!-- ============================ HEADER ============================ -->
        <template #header>
            <h1 class="flex items-center gap-2 text-xl font-extrabold leading-tight text-maiic-900 dark:text-slate-100">
                IFRS 9 ECL Dashboard
                <HelpManual />
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
               class="inline-flex items-center gap-1.5 self-stretch rounded-xl border border-maiic-600 bg-white px-4 text-sm font-bold text-maiic-700 shadow-sm hover:bg-maiic-50">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m0 0l-4.5-4.5M12 15l4.5-4.5M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2"/></svg>
                Download dashboard (PDF)
            </a>
        </template>

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
                        <p class="maiic-kpi-label !mb-0 truncate">{{ k.label }}</p>
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

                <div class="maiic-panel lg:col-span-2">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-4 py-3 dark:border-slate-700">
                        <div>
                            <h3 class="font-bold text-gray-900 dark:text-slate-100">Where the ECL sits</h3>
                            <p class="text-xs text-gray-400">{{ breakdownCaption }}</p>
                        </div>
                        <div class="flex overflow-hidden rounded-lg border border-gray-200 text-xs dark:border-slate-600">
                            <button v-for="o in breakdownViews" :key="o.key" @click="breakdownView = o.key"
                                    :class="breakdownView === o.key ? 'bg-maiic-600 text-white' : 'bg-white text-gray-600'" class="px-3 py-1">{{ o.label }}</button>
                        </div>
                    </div>
                    <div class="maiic-table-wrap">
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

            <!-- ============================ CHARTS ============================ -->
            <div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-5">
                <div class="maiic-panel p-6 lg:col-span-2">
                    <div class="mb-4 flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-gray-900 dark:text-slate-100">Exposure by stage</h3>
                            <p class="text-xs text-gray-400">{{ periodLabel(selectedPeriod) }}<span v-if="selectedPortfolioName"> &middot; {{ selectedPortfolioName }}</span></p>
                        </div>
                        <div class="flex overflow-hidden rounded-lg border border-gray-200 text-xs dark:border-slate-600">
                            <button @click="pieView = 'chart'" :class="pieView === 'chart' ? 'bg-maiic-600 text-white' : 'bg-white text-gray-600'" class="px-3 py-1">Chart</button>
                            <button @click="pieView = 'table'" :class="pieView === 'table' ? 'bg-maiic-600 text-white' : 'bg-white text-gray-600'" class="px-3 py-1">Table</button>
                        </div>
                    </div>
                    <div v-show="pieView === 'chart'" class="relative h-72 w-full"><canvas ref="pieChart"></canvas></div>
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

                <div class="maiic-panel p-6 lg:col-span-3">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="font-bold text-gray-900 dark:text-slate-100">ECL and coverage trend</h3>
                            <p class="text-xs text-gray-400">{{ trendRangeLabel }}<span v-if="selectedPortfolioName"> &middot; {{ selectedPortfolioName }}</span></p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <select v-model="filterForm.trend_from" @change="applyFilters"
                                    class="rounded-md border border-maiic-200 bg-maiic-50 py-1 pl-2 pr-7 text-xs font-semibold text-maiic-800 focus:border-maiic-500 focus:ring-maiic-500">
                                <option :value="null">Last 12 months</option>
                                <option value="all">From the start</option>
                                <option v-for="p in periods" :key="'f' + p" :value="p">From {{ periodLabel(p) }}</option>
                            </select>
                            <select v-model="filterForm.trend_to" @change="applyFilters"
                                    class="rounded-md border border-maiic-200 bg-maiic-50 py-1 pl-2 pr-7 text-xs font-semibold text-maiic-800 focus:border-maiic-500 focus:ring-maiic-500">
                                <option :value="null">To {{ periodLabel(selectedPeriod) }}</option>
                                <option v-for="p in periods" :key="'t' + p" :value="p">To {{ periodLabel(p) }}</option>
                            </select>
                            <div class="flex overflow-hidden rounded-lg border border-gray-200 text-xs dark:border-slate-600">
                                <button @click="trendView = 'chart'" :class="trendView === 'chart' ? 'bg-maiic-600 text-white' : 'bg-white text-gray-600'" class="px-3 py-1">Chart</button>
                                <button @click="trendView = 'table'" :class="trendView === 'table' ? 'bg-maiic-600 text-white' : 'bg-white text-gray-600'" class="px-3 py-1">Table</button>
                            </div>
                        </div>
                    </div>
                    <div v-show="trendView === 'chart'" class="h-72"><canvas ref="trendChart"></canvas></div>
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
        </template>

        <!-- ============================ OPERATIONS ROW ============================ -->
        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <!-- month-end status -->
            <div class="maiic-panel">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-4 py-3 dark:border-slate-700">
                    <h3 class="text-sm font-extrabold uppercase tracking-wider text-maiic-800 dark:text-maiic-200">Month-end status</h3>
                    <span v-if="monthEnd" class="maiic-badge maiic-badge-green">{{ periodLabel(monthEnd.period) }}</span>
                </div>
                <ul v-if="monthEnd" class="divide-y divide-gray-100 dark:divide-slate-700">
                    <li v-for="step in monthEndSteps" :key="step.label" class="flex items-center justify-between gap-3 px-4 py-3">
                        <div class="flex items-center gap-3">
                            <span :class="['flex h-7 w-7 flex-none items-center justify-center rounded-full text-xs font-bold',
                                           step.done ? 'bg-maiic-600 text-white' : 'bg-gray-100 text-gray-400']">
                                <font-awesome-icon :icon="step.done ? 'check' : 'minus'"/>
                            </span>
                            <div>
                                <p class="text-sm font-semibold text-gray-800 dark:text-slate-200">{{ step.label }}</p>
                                <p class="text-xs text-gray-500 dark:text-slate-400">{{ step.detail }}</p>
                            </div>
                        </div>
                        <Link v-if="step.href" :href="step.href" class="text-xs font-bold text-maiic-600 hover:text-maiic-800">Open</Link>
                    </li>
                </ul>
                <p v-else class="maiic-empty">No loan book has been loaded yet.</p>
            </div>

            <!-- latest loan book -->
            <div class="maiic-panel">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-4 py-3 dark:border-slate-700">
                    <h3 class="text-sm font-extrabold uppercase tracking-wider text-maiic-800 dark:text-maiic-200">Latest loan book</h3>
                    <span v-if="loanBookSnapshot" class="maiic-badge maiic-badge-green">{{ periodLabel(loanBookSnapshot.period) }}</span>
                </div>
                <div v-if="loanBookSnapshot" class="p-4">
                    <div class="mb-3 flex items-baseline justify-between">
                        <span class="text-sm text-gray-500 dark:text-slate-400">Carrying amount</span>
                        <span class="text-lg font-extrabold tabular-nums text-maiic-900 dark:text-slate-100">{{ currencyCode }} {{ formatAmount(loanBookSnapshot.total_balance) }}</span>
                    </div>
                    <div class="mb-4 flex h-3 overflow-hidden rounded-full bg-gray-100 dark:bg-slate-700">
                        <div v-for="(b, i) in loanBookSnapshot.balance_by_stage" :key="i" :class="stageBar[i]"
                             :style="{ width: (loanBookSnapshot.total_balance ? (b / loanBookSnapshot.total_balance) * 100 : 0) + '%' }"></div>
                    </div>
                    <table class="maiic-table">
                        <thead><tr><th>Stage</th><th class="num">Loans</th><th class="num">Balance ({{ currencyCode }})</th></tr></thead>
                        <tbody>
                            <tr v-for="(b, i) in loanBookSnapshot.balance_by_stage" :key="i">
                                <td><span :class="['maiic-badge', stageBadge[i]]">Stage {{ i + 1 }}</span></td>
                                <td class="num">{{ formatCount(loanBookSnapshot.loans_by_stage[i]) }}</td>
                                <td class="num">{{ formatAmount(b) }}</td>
                            </tr>
                            <tr class="total">
                                <td>Total</td>
                                <td class="num">{{ formatCount(loanBookSnapshot.total_loans) }}</td>
                                <td class="num">{{ formatAmount(loanBookSnapshot.total_balance) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="maiic-empty">No loan book has been loaded yet.</p>
            </div>

            <!-- recent imports -->
            <div class="maiic-panel">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-4 py-3 dark:border-slate-700">
                    <h3 class="text-sm font-extrabold uppercase tracking-wider text-maiic-800 dark:text-maiic-200">Recent imports</h3>
                    <Link :href="route('imports.index')" class="text-xs font-bold text-maiic-600 hover:text-maiic-800">View all</Link>
                </div>
                <ul class="divide-y divide-gray-100 dark:divide-slate-700">
                    <li v-for="imp in (recentImports || [])" :key="imp.id" class="flex items-center justify-between gap-3 px-4 py-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-gray-800 dark:text-slate-200">{{ imp.name }}</p>
                            <p class="text-xs text-gray-500 dark:text-slate-400">{{ formatCount(imp.records) }} rows &middot; {{ formatDate(imp.completed_at || imp.created_at) }}</p>
                        </div>
                        <span :class="['maiic-badge', importBadge(imp.status)]">{{ imp.status }}</span>
                    </li>
                    <li v-if="!(recentImports || []).length" class="maiic-empty">No imports yet.</li>
                </ul>
            </div>
        </div>
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
    monthEnd: Object,
    loanBookSnapshot: Object,
    recentImports: { type: Array, default: () => [] },
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
})

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
function formatDate(d) {
    if (!d) return ''
    const dt = new Date(d)
    return isNaN(dt) ? d : dt.toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' })
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

const stageBar = ['bg-maiic-600', 'bg-amber-500', 'bg-red-600']
const stageBadge = ['maiic-badge-green', 'maiic-badge-gold', 'maiic-badge-red']
function importBadge(status) {
    return { completed: 'maiic-badge-green', processing: 'maiic-badge-gold', failed: 'maiic-badge-red' }[status] || 'maiic-badge-grey'
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
        { label: 'Total ECL', value: money(s.total_ecl), full: fullMoney(s.total_ecl), icon: I.alert, accent: C.red, ...deltaInfo('total_ecl', 'money', false) },
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

const monthEndSteps = computed(() => {
    const m = props.monthEnd
    if (!m) return []
    return [
        { label: 'Loan book loaded', done: m.loan_book_rows > 0, detail: m.loan_book_rows > 0 ? `${formatCount(m.loan_book_rows)} loans` : 'Not loaded', href: route('loan_applications.loan-book') },
        { label: 'PD applied', done: m.pd_applied, detail: m.pd_applied ? `Source: ${m.pd_source || 'system'}` : 'Cumulative PD not yet applied', href: route('transition-matrix-cummulative.index') },
        { label: 'LGD applied', done: m.lgd_applied, detail: m.lgd_applied ? `Source: ${m.lgd_source || 'system'}` : 'Cumulative LGD not yet applied', href: route('lgd-cummulative.index') },
        { label: 'ECL calculated', done: m.ecl_calculated, detail: m.ecl_calculated ? 'Results available' : 'Not yet calculated', href: route('expected-credit-loss.index') },
    ]
})

const pieView = ref('chart')
const trendView = ref('chart')
const pieChart = ref(null)
const trendChart = ref(null)
let pieInstance = null
let trendInstance = null

const tooltip = { backgroundColor: '#0b2b1a', titleColor: '#fbbf24', bodyColor: '#ffffff', padding: 12, cornerRadius: 10 }

function renderCharts() {
    const n = N()
    if (pieInstance) { pieInstance.destroy(); pieInstance = null }
    if (trendInstance) { trendInstance.destroy(); trendInstance = null }
    if (props.error) return

    if (pieChart.value) {
        const e = summary.value.total_eads || [0, 0, 0]
        pieInstance = new Chart(pieChart.value.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Stage 1', 'Stage 2', 'Stage 3'],
                datasets: [{ data: e, backgroundColor: [C.green, C.gold, C.red], borderWidth: 3, borderColor: n.surface, hoverOffset: 10 }],
            },
            options: {
                maintainAspectRatio: false,
                animation: reducedMotion() ? false : { duration: 700 },
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
                        },
                    },
                },
            },
        })
    }

    if (trendChart.value) {
        const t = props.eclTrends || []
        const coverage = t.map(i => Number(i.ecl_percentage || 0))
        const peak = coverage.length ? Math.max(...coverage) : 0
        // Two bands: the stacked ECL bars use the lower ~60% of the chart and
        // the coverage line rides above them, so the line never runs through
        // the bars. Each keeps its own scale (left currency, right %).
        const barPeak = t.length ? Math.max(...t.map(i => (i.ecl_by_stage || []).reduce((a, b) => a + Number(b || 0), 0))) : 0
        trendInstance = new Chart(trendChart.value.getContext('2d'), {
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
                ],
            },
            options: {
                maintainAspectRatio: false,
                animation: reducedMotion() ? false : { duration: 700 },
                interaction: { mode: 'index', intersect: false },
                scales: {
                    y: {
                        stacked: true, beginAtZero: true, suggestedMax: barPeak > 0 ? barPeak * 1.65 : undefined,
                        border: { display: false }, grid: { color: n.grid },
                        ticks: { color: n.text, font: { size: 11 }, callback: (v) => money(v).replace(currencyCode.value + ' ', '') },
                    },
                    y1: {
                        position: 'right', beginAtZero: true, suggestedMax: peak > 0 ? Math.ceil(peak * 1.1) : 10,
                        border: { display: false }, grid: { display: false },
                        ticks: { color: n.line, font: { size: 11 }, callback: (v) => v + '%' },
                    },
                    x: { stacked: true, border: { display: false }, grid: { display: false }, ticks: { color: n.text, font: { size: 11 }, maxRotation: 40 } },
                },
                plugins: {
                    legend: { position: 'bottom', labels: { usePointStyle: true, pointStyle: 'circle', padding: 14, color: n.legend, font: { size: 11 } } },
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
}

onMounted(renderCharts)
watch(() => [props.summary, props.eclTrends], renderCharts)
watch(theme.isDark, renderCharts)
onBeforeUnmount(() => {
    if (pieInstance) pieInstance.destroy()
    if (trendInstance) trendInstance.destroy()
})
</script>
