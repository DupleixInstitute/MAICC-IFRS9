P = 'C:/xampp/htdocs/MAICC-IFRS9/app/Services/Eir/EirRevenueService.php'
s = open(P, encoding='utf-8').read()

def rep(a, b):
    global s
    assert a in s, a[:70]
    s = s.replace(a, b, 1)

rep(r"""        if ($pv <= 0) throw new RuntimeException("No remaining contractual cash flows exist after {$period}.");
        return $pv;
    }""", r"""        if ($pv <= 0) throw new RuntimeException("No remaining contractual cash flows exist after {$period}.");

        return $this->prepaidOpening($contract, $period, $pv);
    }

    /**
     * The first month's opening for a loan the borrower has prepaid (system
     * audit of 13 October 2026). The opening is the present value of the
     * remaining scheduled cash flows at the original EIR, which assumes every
     * instalment is still to come. When the borrower has repaid ahead of the
     * schedule, the schedule overstates what is owed: Barnet Agriculture
     * Resource Centre repaid MWK 55.6 million in November 2025 and the ledger
     * shows 0.4 million left, while the schedule's present value at December
     * was 38.8 million and the roll-forward accrued EIR interest on it. When
     * the ledger balance at the month before is less than half that present
     * value, the opening is the ledger balance (never below nil), and the
     * substitution is written to the audit log with both figures.
     */
    private function prepaidOpening(ContractEir $contract, string $period, float $pv): float
    {
        $priorEnd = CarbonImmutable::createFromFormat('Y-m-d', $period . '-01')->subDay()->toDateString();
        $account = DB::table('loan_books')->where('contract_id', $contract->contract_id)->whereNotNull('external_identity_id')
            ->where('external_identity_id', '!=', 'TBA')->value('external_identity_id') ?? str_pad((string) $contract->contract_id, 15, '0', STR_PAD_LEFT);
        try {
            $ledger = app(\App\Services\Ebanker\LandingZoneReader::class)->ledgerByAccount($priorEnd)[$account] ?? [];
        } catch (\Throwable) {
            return $pv;
        }
        if ($ledger === []) {
            return $pv;
        }
        $gross = -array_sum(array_map(fn ($p) => (float) str_replace(',', '', (string) ($p['payload']['TRANSAMT'] ?? 0)), $ledger));
        if ($gross >= 0.5 * $pv) {
            return $pv;
        }
        $opening = max(0.0, round($gross, 2));
        \App\Services\AuditLoggerService::log('EIR Opening From Ledger', 'eir_amortisation', null, ['reporting_period' => $period,
            'new_values' => ['contract_id' => $contract->contract_id, 'schedule_present_value' => round($pv, 2), 'ledger_balance' => round($gross, 2), 'opening' => $opening,
                'reason' => 'prepaid ahead of the schedule: the ledger balance at ' . $priorEnd . ' is below half the present value of the remaining schedule']]);

        return $opening;
    }""")
open(P, 'w', encoding='utf-8').write(s)
print('ok')
