p = r'C:/xampp/htdocs/MAICC-IFRS9/app/Http/Controllers/FliAdjustmentsController.php'
s = open(p, encoding='utf-8').read()
def rep(a, b):
    global s
    assert a in s, a
    s = s.replace(a, b)
rep("use App\\Services\\Fli\\TransmissionMethodCatalogue;\n", "use App\\Services\\Fli\\TransmissionMethodCatalogue;\nuse App\\Support\\Fli\\FliPlainLanguage;\n")
rep("->orderByDesc('f.r_squared')->limit(60)", "->orderByDesc('f.r_squared')->limit(500)")
rep("""        $last = DB::table('audit_logs')""", """        // the codes in plain words for the screen; the approved fit pinned with who approved it and when
        $plain = new FliPlainLanguage();
        $fits = $fits->map(fn ($f) => (array) $f + ['driver_name' => $plain->driver($f->statistic_code), 'proxy_name' => $plain->proxy($f->proxy_code)['name'], 'declined_plain' => $plain->declined($f->declined_reason)])->values();
        $approved = $route->approvedFit($period);
        $approvedCard = null;
        if ($approved) {
            $who = DB::table('fli_fits as f')->leftJoin('users as a', 'a.id', '=', 'f.approved_by')->leftJoin('users as p', 'p.id', '=', 'f.proposed_by')->leftJoin('fli_relationships as r', 'r.id', '=', 'f.fli_relationship_id')
                ->where('f.id', $approved->id)->first(['a.name as approver', 'p.name as proposer', 'f.approver_label', 'f.approved_at', 'f.p_value', 'r.expected_sign']);
            $approvedCard = (array) $approved + ['driver_name' => $plain->driver($approved->statistic_code), 'proxy_name' => $plain->proxy($approved->proxy_code)['name'],
                'approver' => $who->approver ?? null, 'proposer' => $who->proposer ?? null, 'approver_label' => $who->approver_label ?? null, 'approved_at' => $who->approved_at ?? null, 'p_value' => $who->p_value ?? null, 'expected_sign' => $who->expected_sign ?? null];
        }
        $last = DB::table('audit_logs')""")
rep("'approved' => $route->approvedFit($period),", "'approved' => $approvedCard,")
open(p, 'w', encoding='utf-8', newline='').write(s)

p = r'C:/xampp/htdocs/MAICC-IFRS9/resources/js/Pages/FLI/Adjustments.vue'
s = open(p, encoding='utf-8').read()
start = s.index('    <div class="space-y-4">')
end = s.index('      <details class="maiic-panel group">')
body = open(r'C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/fli_adj_body.html', encoding='utf-8').read()
s = s[:start] + body + s[end:]
rep("""      </details>
    </div>
  </app-layout>""", """      </details>

      <details v-if="lastRun" class="maiic-panel group">
        <summary class="flex cursor-pointer select-none items-center justify-between px-5 py-4">
          <span><span class="font-semibold text-gray-900">Last run for {{ period }}</span><span class="ml-2 text-xs text-gray-500">{{ lastRun.loans }} loans, {{ lastRun.adjusted }} adjusted, {{ lastRun.held }} held</span></span>
          <span class="text-xs font-semibold text-maiic-700 group-open:hidden">Show</span><span class="hidden text-xs font-semibold text-maiic-700 group-open:inline">Hide</span>
        </summary>
        <div class="space-y-1 border-t border-gray-200 px-5 py-4 text-xs text-gray-600">
          <div>Route {{ lastRun.route }}, method {{ lastRun.method }}, weighting {{ lastRun.weighting }}<span v-if="lastRun.fit_relationship">, fit {{ lastRun.fit }} {{ lastRun.fit_relationship }}</span>.</div>
          <div v-if="lastRun.note" class="text-amber-700">{{ lastRun.note }}</div>
          <div>Adjustment per scenario: <span v-for="(a, n) in lastRun.scenarios" :key="n" class="mr-3">{{ n }} {{ a }}</span></div>
        </div>
      </details>
    </div>
  </app-layout>""")
rep("import { router } from '@inertiajs/vue3'\n", "import { router } from '@inertiajs/vue3'\nimport RowPager from '@/Components/Maiic/RowPager.vue'\n")
rep("components: { AppLayout },", "components: { AppLayout, RowPager },")
rep("data() { return { form: { period: this.period } } },", "data() { return { form: { period: this.period }, page: 1 } },\n  computed: {\n    pagedFits() { return this.fits.slice((this.page - 1) * 15, this.page * 15) },\n  },")
rep("    pct(v) {", "    n(v, d) { return v == null ? '' : Number(v).toFixed(d) },\n    signLabel(s) { return s === 'positive' ? 'moves with the driver' : (s === 'negative' ? 'moves against the driver' : '') },\n    pct(v) {")
open(p, 'w', encoding='utf-8', newline='').write(s)
print('ok')
