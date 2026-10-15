import os
os.chdir('C:/xampp/htdocs/MAICC-IFRS9')

def sub(path, pairs):
    s = open(path, encoding='utf-8').read()
    for a, b in pairs:
        assert a in s, (path, a[:80])
        s = s.replace(a, b, 1)
    open(path, 'w', encoding='utf-8', newline='').write(s)

sub('app/Http/Controllers/Reports/StressTestingController.php', [
(""" * multipliers and LGD add-ons (in percentage points). Unlike the FDH handler,
 * which scaled an aggregate base ECL by stage-average factors, this recomputes
 * ECL loan-by-loan (EAD x PD x LGD with 100% caps) for accuracy, then rolls up
 * by stage and by portfolio. Scenarios can be saved and reloaded.""",
 """ * multipliers and LGD add-ons (in percentage points). Unlike the FDH handler,
 * which scaled an aggregate base ECL by stage-average factors, this stresses
 * loan by loan (StressTestService): each loan's booked loss allowance (BookedEcl)
 * scaled by the stressed over the booked stage PD and LGD (100% caps), with the
 * undiscounted measure beside it, then rolled up by stage and by portfolio.
 * Scenarios can be saved and reloaded."""),
("""            'subtitle' => 'Base against stressed ECL, recomputed loan by loan (EAD x PD x LGD, capped at 100%). ' . $portfolio . '.',""",
 """            'subtitle' => 'Base against stressed loss allowance, loan by loan: the booked allowance scaled by the stressed PD and LGD (capped at 100%). ' . $portfolio . '.',"""),
("""            'sections' => [
                $inputs,""",
 """            'sections' => [
                $inputs,
                ['heading' => 'Basis', 'columns' => ['Measure', 'Loans', 'Base', 'Stressed'], 'align' => ['l', 'r', 'r', 'r'], 'rows' => [
                    ['Booked loss allowance (the figures of this report)', '', $money($r['total_base_ecl']), $money($r['total_stress_ecl'])],
                    ['ECL undiscounted, stressed the same way', '', $money($r['basis']['undiscounted']['base_ecl']), $money($r['basis']['undiscounted']['stress_ecl'])],
                    ['Loans without a discounted allowance (shown at their undiscounted ECL)', number_format($r['basis']['not_booked']['loans']), $money($r['basis']['not_booked']['base_ecl']), ''],
                    ['Loans with no ECL calculated: not measured (exposure)', number_format($r['basis']['not_measured']['loans']), $money($r['basis']['not_measured']['exposure']), ''],
                    ['Loans with a nil PD or LGD: kept at their allowance, not scaled', number_format($r['basis']['not_scaled']['loans']), $money($r['basis']['not_scaled']['base_ecl']), ''],
                ]],"""),
])

sub('resources/js/Pages/Reports/StressTesting.vue', [
("""                            <p class="maiic-kpi-label">Base ECL</p>""",
 """                            <p class="maiic-kpi-label">Base ECL (booked allowance)</p>"""),
("""                    <div class="maiic-panel">
                        <div class="px-6 py-4 border-l-4 border-maiic-600">
                            <h3 class="font-semibold text-gray-900">By IFRS 9 Stage</h3>""",
 """                    <!-- the basis: booked allowance, the undiscounted measure beside it, and what was left out -->
                    <div v-if="result.basis" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700">
                        <div>
                            <b class="text-gray-900">Basis:</b> the base is the booked loss allowance, and each scenario scales that same figure loan by loan.
                            On the undiscounted measure the base is {{ money(result.basis.undiscounted.base_ecl) }} and the stressed figure {{ money(result.basis.undiscounted.stress_ecl) }}.
                        </div>
                        <ul v-if="result.basis.not_booked.loans || result.basis.not_measured.loans || result.basis.not_scaled.loans" class="mt-1 list-disc pl-5 text-xs text-gray-600">
                            <li v-if="result.basis.not_booked.loans">{{ result.basis.not_booked.loans }} loan(s) have no discounted allowance and are shown at their undiscounted ECL ({{ money(result.basis.not_booked.base_ecl) }}).</li>
                            <li v-if="result.basis.not_measured.loans">{{ result.basis.not_measured.loans }} loan(s) have no ECL calculated and are not measured (exposure {{ money(result.basis.not_measured.exposure) }}).</li>
                            <li v-if="result.basis.not_scaled.loans">{{ result.basis.not_scaled.loans }} loan(s) carry a nil PD or LGD and are kept at their allowance ({{ money(result.basis.not_scaled.base_ecl) }}).</li>
                        </ul>
                    </div>

                    <div class="maiic-panel">
                        <div class="px-6 py-4 border-l-4 border-maiic-600">
                            <h3 class="font-semibold text-gray-900">By IFRS 9 Stage</h3>"""),
])
print('done')
