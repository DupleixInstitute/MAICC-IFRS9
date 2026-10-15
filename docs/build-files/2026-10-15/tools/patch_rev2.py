P = 'C:/xampp/htdocs/MAICC-IFRS9/app/Services/Eir/EirRevenueService.php'
s = open(P, encoding='utf-8').read()

def rep(a, b):
    global s
    assert a in s, a[:70]
    s = s.replace(a, b, 1)

# the opening knows whether it came from the ledger; later advances raise the balance
rep("""                $opening = $previous ? (float) $previous->closing_gross : $this->initialOpening($contract, $period);""",
"""                $this->openedFromLedger = false;
                $opening = $previous ? (float) $previous->closing_gross : $this->initialOpening($contract, $period);
                // an advance in the month adds to the balance once the roll-forward runs on the
                // ledger's drawn balance: a month after the first, or a first month opened from the
                // ledger (a schedule's value already counts the advances it was built on)
                $addAdvances = $previous !== null || $this->openedFromLedger;""")
rep("""                $closing = max(0.0, $opening + $interest + $unwind - $cash['amount'] - $cash['derecognised']);""",
"""                $advanced = $addAdvances ? $cash['advanced'] : 0.0;
                $closing = max(0.0, $opening + $advanced + $interest + $unwind - $cash['amount'] - $cash['derecognised']);""")
rep("""        // a facility still drawing down is not prepaid: its schedule carries the tranches to come,
        // and the roll-forward does not add a later advance, so the schedule's value stands
        $laterAdvance = DB::table('eir_actual_transactions')->where('contract_id', $contract->contract_id)->where('transaction_type', 'Disbursement')
            ->where('transaction_date', '>', $priorEnd)->exists();
        if ($laterAdvance) {
            return $pv;
        }
        $opening = max(0.0, round($gross, 2));""", """        $opening = max(0.0, round($gross, 2));
        $this->openedFromLedger = true;""")
rep("""        if ($gross >= 0.5 * $pv) {
            return $pv;
        }""", """        // A facility still drawing down: the schedule carries the tranches to come, which
        // the roll-forward adds as they are advanced, so the opening is what is drawn today.
        // Otherwise the schedule stands unless the ledger shows it prepaid by more than half.
        $laterAdvance = DB::table('eir_actual_transactions')->where('contract_id', $contract->contract_id)->where('transaction_type', 'Disbursement')
            ->where('transaction_date', '>', $priorEnd)->exists();
        if (! $laterAdvance && $gross >= 0.5 * $pv) {
            return $pv;
        }""")
rep("""                'reason' => 'prepaid ahead of the schedule: the ledger balance at ' . $priorEnd . ' is below half the present value of the remaining schedule']]);""",
"""                'reason' => $laterAdvance ? 'still drawing down: the opening is the drawn balance at ' . $priorEnd . ', later advances are added as they are made'
                    : 'prepaid ahead of the schedule: the ledger balance at ' . $priorEnd . ' is below half the present value of the remaining schedule']]);""")
rep("""    private function prepaidOpening(ContractEir $contract, string $period, float $pv): float""",
"""    /** Set by prepaidOpening when the first month opens on the ledger's balance. */
    private bool $openedFromLedger = false;

    private function prepaidOpening(ContractEir $contract, string $period, float $pv): float""")
# cash: the advances of the month
rep("""            return ['amount' => $this->scheduledCash($contractId, $start, $end), 'source' => 'DERIVED', 'unclassified' => 0.0, 'derecognised' => 0.0];""",
"""            return ['amount' => $this->scheduledCash($contractId, $start, $end), 'source' => 'DERIVED', 'unclassified' => 0.0, 'derecognised' => 0.0, 'advanced' => 0.0];""")
rep("""        return ['amount' => $collected, 'source' => 'IMPORTED', 'unclassified' => $unclassified, 'derecognised' => $derecognised];""",
"""        $advanced = (float) DB::table('eir_actual_transactions')->where('contract_id', $contractId)->whereIn('transaction_type', self::ADVANCE_TYPES)
            ->whereBetween('transaction_date', [$start->toDateString(), $end->toDateString()])->sum('total_amount');

        return ['amount' => $collected, 'source' => 'IMPORTED', 'unclassified' => $unclassified, 'derecognised' => $derecognised, 'advanced' => $advanced];""")
open(P, 'w', encoding='utf-8').write(s)
print('ok')
