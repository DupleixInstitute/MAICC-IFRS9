P = 'C:/xampp/htdocs/MAICC-IFRS9/resources/js/Pages/Eir/Reconciliation.vue'
s = open(P, encoding='utf-8').read()

def rep(a, b):
    global s
    assert a in s, a[:70]
    s = s.replace(a, b, 1)

rep("""                  <th class="th text-right">EIR accrued</th><th class="th text-right">Variance</th>
                  <th class="th text-right">Carrying amount</th><th class="th text-right">Rate effect</th>
                  <th class="th text-right">Impairment</th><th class="th">Status</th>""",
"""                  <th class="th text-right">EIR accrued</th><th class="th text-right">Variance</th>
                  <th class="th text-right" title="The variance split into the carrying amount, rate and impairment effects">Variance split</th><th class="th">Status</th>""")
rep("""                  <td class="td text-xs text-gray-600">
                    <div v-if="r.expected_opening_balance !== null">""", """                  <td class="td max-w-[13rem] text-xs text-gray-600">
                    <div v-if="r.expected_opening_balance !== null">""")
rep("""                    <div class="mt-1 max-w-sm text-xs text-gray-500">{{ r.cause_detail }}</div>""",
"""                    <div class="mt-1 max-w-[14rem] text-xs text-gray-500">{{ r.cause_detail }}</div>""")
rep("""                  <td class="td text-right tabular-nums text-gray-600">{{ r.carrying_amount_effect === null ? '-' : money(r.carrying_amount_effect) }}</td>
                  <td class="td text-right tabular-nums text-gray-600">{{ r.rate_effect === null ? '-' : money(r.rate_effect) }}</td>
                  <td class="td text-right tabular-nums text-gray-600">{{ r.impairment_effect === null ? '-' : money(r.impairment_effect) }}</td>""",
"""                  <td class="td whitespace-nowrap text-right text-xs tabular-nums text-gray-600">
                    <div>Carrying {{ r.carrying_amount_effect === null ? '-' : money(r.carrying_amount_effect) }}</div>
                    <div>Rate {{ r.rate_effect === null ? '-' : money(r.rate_effect) }}</div>
                    <div>Impairment {{ r.impairment_effect === null ? '-' : money(r.impairment_effect) }}</div>
                  </td>""")
rep("""                  <td colspan="12" class="p-10""", """                  <td colspan="10" class="p-10""")
rep(""".th{@apply whitespace-nowrap bg-maiic-700 px-4 py-3""", """.th{@apply bg-maiic-700 px-2.5 py-3""")
rep(""".td{@apply border-t border-gray-100 px-4 py-3""", """.td{@apply border-t border-gray-100 px-2.5 py-3""")
open(P, 'w', encoding='utf-8').write(s)
print('ok')
