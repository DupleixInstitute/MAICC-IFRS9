p = r'C:\xampp\htdocs\MAICC-IFRS9\resources\js\Pages\FLI\Overview.vue'
s = open(p, encoding='utf-8').read()
rep = [
("""              <tr class="font-semibold"><td>ECL booked</td><td class="num">{{ mwk(snapshot.booked_ecl) }}</td></tr>
              <tr><td>Weighted over the scenarios</td><td class="num">{{ mwk(snapshot.weighted_ecl) }}</td></tr>
              <tr><td>Difference</td><td class="num" :class="snapshot.reconciles ? 'text-maiic-700' : 'text-red-700'">{{ mwk(snapshot.difference) }} {{ snapshot.reconciles ? '(ties)' : '(does not tie)' }}</td></tr>""",
 """              <tr class="font-semibold"><td>ECL, engine measure (undiscounted)</td><td class="num">{{ mwk(snapshot.booked_ecl) }}</td></tr>
              <tr><td>Weighted over the scenarios</td><td class="num">{{ mwk(snapshot.weighted_ecl) }}</td></tr>
              <tr><td>Difference</td><td class="num" :class="snapshot.reconciles ? 'text-maiic-700 dark:text-maiic-300' : 'text-red-700 dark:text-red-300'">{{ mwk(snapshot.difference) }} {{ snapshot.reconciles ? '(ties)' : '(does not tie)' }}</td></tr>
              <template v-if="snapshot.booked_ecl_discounted !== null && snapshot.booked_ecl_discounted !== undefined">
                <tr><td colspan="2" class="!pt-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-slate-400">Discounted at the EIR (IFRS 9 5.5.17)</td></tr>
                <tr v-if="Number(snapshot.discount_unresolved_loans) > 0"><td>Less: loans not discounted ({{ snapshot.discount_unresolved_loans }})</td><td class="num">{{ mwk(-Number(snapshot.unresolved_ecl)) }}</td></tr>
                <tr><td>Projection effect (EAD run down the schedule)</td><td class="num">{{ mwk(snapshot.projection_effect) }}</td></tr>
                <tr><td>Discounting effect</td><td class="num">{{ mwk(snapshot.discounting_effect) }}</td></tr>
                <tr class="font-semibold"><td>ECL booked, discounted ({{ snapshot.discounted_loans }} loans)</td><td class="num">{{ mwk(snapshot.booked_ecl_discounted) }}</td></tr>
                <tr><td>Weighted over the scenarios, discounted</td><td class="num">{{ mwk(snapshot.weighted_ecl_discounted) }}</td></tr>
                <tr><td>Difference</td><td class="num" :class="snapshot.discounted_reconciles ? 'text-maiic-700 dark:text-maiic-300' : 'text-red-700 dark:text-red-300'">{{ mwk(snapshot.discounted_difference) }} {{ snapshot.discounted_reconciles ? '(ties)' : '(does not tie)' }}</td></tr>
              </template>
              <tr v-if="Number(snapshot.programme_loans) > 0"><td>Mega Farm programme, its own engine (D30), not in the figures above ({{ snapshot.programme_loans }} loans)</td><td class="num">{{ mwk(snapshot.programme_ecl) }}</td></tr>"""),
("""              <thead><tr><th>Scenario</th><th class="num">Weight</th><th class="num">ECL</th></tr></thead>
              <tbody><tr v-for="r in snapshot.per_scenario || []" :key="r.scenario"><td>{{ r.scenario }}</td><td class="num">{{ r.weight }}%</td><td class="num">{{ mwk(r.ecl) }}</td></tr></tbody>
            </table>""",
 """              <thead><tr><th>Scenario</th><th class="num">Weight</th><th class="num">ECL</th><th v-if="snapshot.booked_ecl_discounted !== null && snapshot.booked_ecl_discounted !== undefined" class="num">Discounted</th></tr></thead>
              <tbody><tr v-for="r in snapshot.per_scenario || []" :key="r.scenario"><td>{{ r.scenario }}</td><td class="num">{{ r.weight }}%</td><td class="num">{{ mwk(r.ecl) }}</td><td v-if="snapshot.booked_ecl_discounted !== null && snapshot.booked_ecl_discounted !== undefined" class="num">{{ mwk(r.ecl_discounted) }}</td></tr></tbody>
            </table>
            <div v-if="Number(snapshot.discount_unresolved_loans) > 0" class="mt-3 rounded border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800 dark:border-amber-700/50 dark:bg-amber-900/20 dark:text-amber-200">
              {{ snapshot.discount_unresolved_loans }} loan(s) have no discounted allowance, so the discounted figure covers part of the book; signing off needs a commentary saying why.
              <ul class="mt-1 list-disc pl-4"><li v-for="u in (snapshot.discount_detail?.unresolved || []).slice(0, 8)" :key="u.contract_id">{{ u.contract_id }}: {{ u.reason || u.status }}</li></ul>
            </div>"""),
("""<span v-if="snapshot.needs_commentary" class="text-red-700"> (required: the movement is above {{ tolerance !== null ? (tolerance * 100).toFixed(0) + '%' : 'the tolerance' }})</span>""",
 """<span v-if="snapshot.needs_commentary" class="text-red-700 dark:text-red-300"> (required: the movement is above {{ tolerance !== null ? (tolerance * 100).toFixed(0) + '%' : 'the tolerance' }})</span><span v-else-if="Number(snapshot.discount_unresolved_loans) > 0" class="text-red-700 dark:text-red-300"> (required: loans are not discounted)</span>"""),
(""":disabled="snapshot.created_by === userId || !snapshot.reconciles\"""", """:disabled="snapshot.created_by === userId || !snapshot.reconciles || snapshot.discounted_reconciles === false\""""),
("""        { label: 'ECL booked', value: s ? this.short(s.booked_ecl) : '-', raw: true, accent: '#d97706', sub: s ? 'FLI effect ' + this.short(Number(s.model_effect) + Number(s.overlay_effect)) : 'month not recorded' },""",
 """        { label: s && s.booked_ecl_discounted !== null && s.booked_ecl_discounted !== undefined ? 'ECL booked, discounted' : 'ECL booked', value: s ? this.short(s.booked_ecl_discounted ?? s.booked_ecl) : '-', raw: true, accent: '#d97706', sub: s ? 'FLI effect ' + this.short(Number(s.model_effect) + Number(s.overlay_effect)) + ' (undiscounted)' + (Number(s.discount_unresolved_loans) > 0 ? '; ' + s.discount_unresolved_loans + ' loans not discounted' : '') : 'month not recorded' },"""),
]
for a, b in rep:
    assert s.count(a) == 1, a[:80]
    s = s.replace(a, b)
open(p, 'w', encoding='utf-8', newline='').write(s)
print('ok')
