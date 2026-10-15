P = 'C:/xampp/htdocs/MAICC-IFRS9/'

def patch(p, pairs):
    s = open(P + p, encoding='utf-8').read()
    for a, b in pairs:
        assert a in s, (p, a[:60])
        s = s.replace(a, b, 1)
    open(P + p, 'w', encoding='utf-8').write(s)

patch('app/Http/Controllers/ClientsController.php', [(r"""        return Inertia::render('Clients/Show', [
            'client' => $client
        ]);""", r"""        // the client's loans in the latest loan book that holds any of them
        $latest = \Illuminate\Support\Facades\DB::table('loan_books')->where('client_id', $client->id)->max('reporting_period');
        $loans = $latest ? \Illuminate\Support\Facades\DB::table('loan_books')->where('client_id', $client->id)->where('reporting_period', $latest)
            ->orderByDesc('carrying_amount')->get(['id', 'contract_id', 'product_group', 'carrying_amount', 'overdue_days', 'ecl_value',
                \Illuminate\Support\Facades\DB::raw('COALESCE(ifrs9stage_post_qualitative, calculated_ifrs9_stage, ifrs9stage_pre_qualitative) as stage')]) : collect();

        return Inertia::render('Clients/Show', [
            'client' => $client,
            'loans' => $loans,
            'loansPeriod' => $latest,
        ]);""")])
patch('resources/js/Pages/Clients/Show.vue', [
("""                </dl>
            </div>
        </div>
    </app-layout>""", """                </dl>
            </div>
        </div>

        <div class="maiic-panel mt-5">
            <div class="border-b border-gray-200 px-5 py-3"><span class="text-sm font-bold text-gray-900">Loans</span><span class="ml-2 text-xs text-gray-500">{{ loansPeriod ? 'in the loan book of ' + loansPeriod : 'none in the loan book' }}</span></div>
            <div class="maiic-table-wrap"><table class="maiic-table">
                <thead><tr><th>Contract</th><th>Product group</th><th class="num">Carrying amount</th><th class="num">Days overdue</th><th class="num">Stage</th><th class="num">ECL</th></tr></thead>
                <tbody>
                    <tr v-for="l in loans" :key="l.id"><td class="font-mono text-xs">{{ l.contract_id }}</td><td>{{ l.product_group || '-' }}</td><td class="num">{{ money(l.carrying_amount) }}</td><td class="num">{{ l.overdue_days ?? '-' }}</td><td class="num">{{ l.stage || '-' }}</td><td class="num">{{ money(l.ecl_value) }}</td></tr>
                    <tr v-if="!(loans || []).length"><td colspan="6" class="maiic-empty">This client has no loan in the loan book.</td></tr>
                </tbody>
            </table></div>
        </div>
    </app-layout>"""),
("""    props: {
        client: Object,
    },""", """    props: {
        client: Object,
        loans: { type: Array, default: () => [] },
        loansPeriod: { type: String, default: null },
    },"""),
("""    computed: {
        identity() {""", """    computed: {
        money() { return (v) => (v === null || v === undefined ? '-' : Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })) },
        identity() {"""),
])
print('ok')
