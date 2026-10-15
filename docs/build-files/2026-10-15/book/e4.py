p = r'C:\xampp\htdocs\MAICC-IFRS9\resources\js\Pages\ExpectedCreditLoss\Index.vue'
s = open(p, encoding='utf-8').read()
def rep(old, new):
    global s
    assert old in s, old
    s = s.replace(old, new, 1)

# KPI strip: six cards, short labels
i = s.index('            <!-- One line of figures: the filtered population in view -->')
j = s.index('            <!-- Tabs: loan results by basis')
s = s[:i] + '''            <!-- One line of figures: the filtered population in view, in MWK -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                <div class="maiic-kpi !py-3" style="--accent:#0e7490" :title="'MWK ' + formatMoney(totals.ead) + ', carrying amount plus undrawn commitments times utilisation, ' + formatNumber(totals.loans) + ' loans'">
                    <div class="maiic-kpi-label">EAD (MWK)</div>
                    <div class="text-xl font-bold tabular-nums text-gray-900">{{ compact(totals.ead) }}</div>
                </div>
                <div class="maiic-kpi !py-3" style="--accent:#64748b" :title="buildUp.pre_fli !== null ? 'MWK ' + formatMoney(buildUp.pre_fli) : (buildUp.reason || '')">
                    <div class="maiic-kpi-label">ECL before FLI</div>
                    <div class="text-xl font-bold tabular-nums text-gray-900">{{ buildUp.pre_fli !== null ? compact(buildUp.pre_fli) : '-' }}</div>
                </div>
                <div class="maiic-kpi !py-3" style="--accent:#d97706" :title="buildUp.fli_total !== null ? 'MWK ' + formatMoney(buildUp.fli_total) + (buildUp.overlays ? ', of which overlays ' + formatMoney(buildUp.overlays) : '') : (buildUp.reason || '')">
                    <div class="maiic-kpi-label">FLI effect</div>
                    <div class="text-xl font-bold tabular-nums text-gray-900">{{ buildUp.fli_total !== null ? signedCompact(buildUp.fli_total) : '-' }}</div>
                </div>
                <div class="maiic-kpi !py-3" style="--accent:#dc2626" :title="'MWK ' + formatMoney(totals.ecl)">
                    <div class="maiic-kpi-label">ECL undiscounted</div>
                    <div class="text-xl font-bold tabular-nums text-gray-900">{{ buildUp.calculated ? compact(totals.ecl) : '-' }}</div>
                </div>
                <div class="maiic-kpi !py-3" style="--accent:#b45309" :title="totals.ecl_discounted !== null ? 'MWK ' + formatMoney(totals.ecl_discounted) : 'The run in view was undiscounted'">
                    <div class="maiic-kpi-label">ECL discounted</div>
                    <div class="text-xl font-bold tabular-nums text-gray-900">{{ totals.ecl_discounted !== null ? compact(totals.ecl_discounted) : '-' }}</div>
                </div>
                <div class="maiic-kpi !py-3" style="--accent:#15803d">
                    <div class="maiic-kpi-label">Coverage (ECL / EAD)</div>
                    <div class="text-xl font-bold tabular-nums text-gray-900">{{ buildUp.calculated && totals.ead ? formatPercent(coverageRate) : '-' }}</div>
                </div>
            </div>

''' + s[j:]

rep('<table class="maiic-table text-[13px] [&_td]:!px-2.5 [&_th]:!px-2.5">', '<table class="maiic-table text-[13px] [&_td]:!px-2 [&_th]:!px-2">')
rep('''                                    <th>Contract</th>
                                    <th>Stage</th>
                                    <th class="num" title="Carrying amount plus undrawn commitment times utilisation">EAD (MWK)</th>''', '''                                    <th>Contract / stage</th>
                                    <th class="num" title="Carrying amount plus undrawn commitment times utilisation, MWK">EAD</th>''')
rep('''                                        <th class="num">ECL before FLI (MWK)</th>
                                        <th class="num">ECL (MWK)</th>''', '''                                        <th class="num" title="EAD x PD used, on the PD before FLI, x LGD, MWK">ECL before FLI</th>
                                        <th class="num" title="The booked ECL, MWK">ECL</th>''')
rep('''                                        <th class="num">Undiscounted ECL (MWK)</th>''', '''                                        <th class="num">Undiscounted ECL</th>''')
rep('''                                        <th class="num">Discounted ECL (MWK)</th>
                                        <th class="num">Discounting effect (MWK)</th>''', '''                                        <th class="num">Discounted ECL</th>
                                        <th class="num">Discounting effect</th>''')
rep('''                                    <td class="whitespace-nowrap">
                                        <div class="font-mono text-xs font-semibold text-gray-900">{{ loan.contract_id }}</div>
                                        <div class="max-w-[13rem] truncate text-xs text-gray-500" :title="loan.client?.name || loan.customer_name">{{ loan.client?.name || loan.customer_name || '-' }}</div>
                                    </td>
                                    <td><span class="maiic-badge" :class="stageBadge(stageOf(loan))">Stage {{ stageOf(loan) }}</span></td>''', '''                                    <td class="whitespace-nowrap">
                                        <div class="flex items-center gap-2"><span class="font-mono text-xs font-semibold text-gray-900">{{ loan.contract_id }}</span><span class="maiic-badge !px-2 !text-[10px]" :class="stageBadge(stageOf(loan))">Stage {{ stageOf(loan) }}</span></div>
                                        <div class="max-w-[12rem] truncate text-xs text-gray-500" :title="loan.client?.name || loan.customer_name">{{ loan.client?.name || loan.customer_name || '-' }}</div>
                                    </td>''')
rep('''                                    <td colspan="2">Total, {{ formatNumber(totals.loans) }} loans</td>''', '''                                    <td>Total, {{ formatNumber(totals.loans) }} loans</td>''')
rep('''                                    <td :colspan="11" class="maiic-empty">''', '''                                    <td :colspan="activeTab === 'discounted' ? 10 : 10" class="maiic-empty">''')
rep('''<span>{{ currentTab.description }}</span>''', '''<span>{{ currentTab.description }} Amounts in MWK.</span>''')
open(p, 'w', encoding='utf-8', newline='\n').write(s)
print('ok')
