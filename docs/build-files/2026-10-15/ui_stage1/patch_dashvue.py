p='C:/xampp/htdocs/MAICC-IFRS9/resources/js/Pages/Dashboard.vue'
s=open(p,encoding='utf-8').read()
def rep(a,b):
    global s
    assert s.count(a)==1,a
    s=s.replace(a,b)
rep('''        <!-- ============================ NO ECL YET ============================ -->''','''        <!-- ============================ STATUS STRIP ============================
             Where the month stands, from the tables the engines write
             (DashboardController::statusStrip). Nothing here is assumed: a
             figure with nothing behind it reads "-" or "Not run". -->
        <section class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6" aria-label="Month-end status">
            <component :is="c.href ? Link : 'div'" v-for="c in stripCards" :key="c.key" :href="c.href || undefined"
                       class="maiic-kpi group block min-w-0 !px-3.5 !py-2.5 transition"
                       :class="c.href ? 'hover:border-maiic-300 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-maiic-400' : ''"
                       :style="{ '--accent': c.accent }" :title="c.hint || null">
                <div class="maiic-kpi-label flex items-center gap-1.5 !mb-0.5 truncate">
                    <font-awesome-icon :icon="c.icon" class="h-3 w-3 flex-none" :style="{ color: c.accent }" aria-hidden="true"/>
                    <span class="truncate">{{ c.label }}</span>
                </div>
                <div class="truncate text-base font-extrabold leading-tight tabular-nums" :class="c.tone">{{ c.value }}</div>
                <div class="mt-0.5 truncate text-[11px] leading-tight text-gray-500">{{ c.sub }}</div>
            </component>
            <div class="maiic-kpi min-w-0 !px-3.5 !py-2.5" :style="{ '--accent': '#d97706' }">
                <div class="maiic-kpi-label flex items-center gap-1.5 !mb-0.5 truncate">
                    <font-awesome-icon icon="clipboard-check" class="h-3 w-3 flex-none" style="color: #d97706" aria-hidden="true"/>
                    <span class="truncate">Awaiting approval</span>
                </div>
                <template v-if="approvalsTotal">
                    <Link v-if="(strip.approvals || [])[0]?.href" :href="strip.approvals[0].href"
                          class="block truncate text-base font-extrabold leading-tight tabular-nums text-amber-700 hover:underline dark:text-amber-300">{{ formatCount(approvalsTotal) }} item{{ approvalsTotal === 1 ? '' : 's' }}</Link>
                    <div v-else class="truncate text-base font-extrabold leading-tight tabular-nums text-amber-700 dark:text-amber-300">{{ formatCount(approvalsTotal) }} item{{ approvalsTotal === 1 ? '' : 's' }}</div>
                    <div class="mt-0.5 flex flex-wrap gap-x-2 text-[11px] leading-tight text-gray-500">
                        <component :is="a.href ? Link : 'span'" v-for="a in strip.approvals" :key="a.label" :href="a.href || undefined"
                                   class="whitespace-nowrap" :class="a.href ? 'hover:text-maiic-700 hover:underline' : ''">{{ a.count }} {{ a.label.toLowerCase() }}</component>
                    </div>
                </template>
                <template v-else>
                    <div class="text-base font-extrabold leading-tight text-gray-900">None</div>
                    <div class="mt-0.5 truncate text-[11px] leading-tight text-gray-500">Nothing waits for a second person</div>
                </template>
            </div>
        </section>
        <!-- the filter bar and the PDF button (#actions) sit here, under the strip -->
        <div data-actions-anchor></div>

        <!-- ============================ NO ECL YET ============================ -->''')
rep('''    eclBreakdown: Object,
    error: String,
})''','''    eclBreakdown: Object,
    error: String,
    statusStrip: Object,
})''')
rep('''const selectedPortfolioName = computed(() => {''','''// ---- status strip ---------------------------------------------------------
const strip = computed(() => props.statusStrip || { links: {}, approvals: [] })
const approvalsTotal = computed(() => (strip.value.approvals || []).reduce((t, a) => t + Number(a.count || 0), 0))
// run times as "13 Oct, 11:59" (UI spec, numbers and dates)
function runTime(iso) {
    if (!iso) return ''
    const d = new Date(iso)
    if (isNaN(d)) return ''
    return `${d.getDate()} ${MONTHS[d.getMonth()]}, ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`
}
const TONE = {
    ok: 'text-maiic-700 dark:text-maiic-300',
    warn: 'text-amber-700 dark:text-amber-300',
    none: 'text-gray-500',
    plain: 'text-gray-900',
}
const stripCards = computed(() => {
    const s = strip.value
    const links = s.links || {}
    const lb = s.loan_book
    const ecl = s.ecl
    const eir = s.eir
    const fli = s.fli
    const cards = []
    cards.push({
        key: 'period', label: 'Period in view', icon: 'calendar', accent: '#15803d', href: links.period,
        value: s.period ? periodLabel(s.period) : '-', tone: s.period ? TONE.plain : TONE.none,
        sub: !s.period ? 'No loan book loaded' : (s.period_source === 'chosen' ? 'Chosen on the filter bar' : 'Latest loan book loaded'),
    })
    cards.push({
        key: 'book', label: 'Loan book', icon: 'database', accent: '#0284c7', href: links.loan_book,
        value: lb ? `${formatCount(lb.loans)} loans` : 'Not loaded', tone: lb ? TONE.plain : TONE.none,
        sub: lb ? `Gross ${currencyCode.value ? currencyCode.value + ' ' : ''}${money(lb.gross)}` : (s.period ? 'Nothing loaded for the period' : '-'),
        hint: lb ? `Gross carrying amount ${formatAmount(lb.gross)}` : null,
    })
    const eclDone = !!ecl && ecl.loans > 0 && ecl.ecl_loans === ecl.loans
    const eclPart = !!ecl && ecl.ecl_loans > 0 && !eclDone
    cards.push({
        key: 'ecl', label: 'ECL run', icon: 'calculator', accent: '#dc2626', href: links.ecl,
        value: eclDone ? 'Run' : (eclPart ? 'Partly run' : 'Not run'), tone: eclDone ? TONE.ok : (eclPart ? TONE.warn : TONE.none),
        sub: ecl && ecl.loans > 0
            ? `${formatCount(ecl.ecl_loans)} of ${formatCount(ecl.loans)} loans` + (ecl.last_run_at ? `, last run ${runTime(ecl.last_run_at)}` : '')
            : '-',
        hint: ecl && ecl.last_run_at ? `Latest ECL calculation for the period: ${runTime(ecl.last_run_at)}` : null,
    })
    const eirRun = !!eir && eir.contracts_with_revenue > 0
    const eirFull = eirRun && eir.contracts_with_revenue >= eir.locked_contracts
    cards.push({
        key: 'eir', label: 'EIR revenue', icon: 'percent', accent: '#65a30d', href: links.eir,
        value: !eir ? '-' : (eirRun ? `${formatCount(eir.contracts_with_revenue)} of ${formatCount(eir.locked_contracts)}` : 'Not run'),
        tone: !eirRun ? TONE.none : (eirFull ? TONE.ok : TONE.warn),
        sub: !eir ? '-' : (eirRun ? 'Locked-EIR contracts with revenue' : `${formatCount(eir.locked_contracts)} contracts with a locked EIR`),
        hint: eir ? `Contracts with an EIR amortisation row for the period, against the contracts with a locked EIR` : null,
    })
    cards.push({
        key: 'fli', label: 'FLI month-end', icon: 'binoculars', accent: '#0369a1', href: links.fli,
        value: !s.period ? '-' : (!fli ? 'Not recorded' : (fli.status === 'SIGNED_OFF' ? 'Signed off' : 'Recorded')),
        tone: !fli ? TONE.none : (fli.status === 'SIGNED_OFF' ? TONE.ok : TONE.warn),
        sub: !fli ? (s.period ? 'No month record for the period' : '-')
            : (fli.status === 'SIGNED_OFF' ? `Signed off ${runTime(fli.at)}${fli.by ? ' by ' + fli.by : ''}` : `Recorded ${runTime(fli.at)}, awaiting sign-off`),
    })
    return cards
})

const selectedPortfolioName = computed(() => {''')
open(p,'w',encoding='utf-8',newline='').write(s)
print('ok')
