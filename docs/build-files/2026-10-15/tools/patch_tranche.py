R = 'C:/xampp/htdocs/MAICC-IFRS9/app/'

def patch(p, pairs):
    s = open(R + p, encoding='utf-8').read()
    for a, b in pairs:
        assert a in s, (p, a[:70])
        s = s.replace(a, b, 1)
    open(R + p, 'w', encoding='utf-8').write(s)

patch('Services/Eir/EirRevenueService.php', [
# the run: basis carried from the first month, advances added on a ledger basis
("""                $opening = $previous ? (float) $previous->closing_gross : $this->initialOpening($contract, $period);""",
 """                // The first month opens on the schedule's present value or on the ledger's drawn balance;
                // every later month carries that basis forward with the closing balance.
                if ($previous) {
                    $opening = (float) $previous->closing_gross;
                    $openingBasis = $previous->opening_basis ?: 'SCHEDULE';
                } else {
                    [$opening, $openingBasis] = $this->initialOpening($contract, $period);
                }"""),
("""                $cash = $this->cashReceived($contractId, $period);
                // a write-off or a settlement in kind leaves the gross without being cash
                $closing = max(0.0, $opening + $interest + $unwind - $cash['amount'] - $cash['derecognised']);""",
 """                $cash = $this->cashReceived($contractId, $period);
                // A tranche drawn in the month raises the gross, and earns interest from the day it is
                // drawn, but only on a ledger basis: a schedule's present value already counts every
                // tranche, so adding the advance there would count it twice.
                $advances = 0.0;
                if ($openingBasis === 'LEDGER') {
                    $rate = $basis === 'NET' ? 0.0 : $monthlyRate; // Stage 3 on net: the advance is not accrued
                    foreach ($cash['advances'] as $adv) {
                        $advances += $adv['amount'];
                        $interest += $rate * $adv['amount'] * $adv['share_of_month'];
                    }
                }
                // a write-off or a settlement in kind leaves the gross without being cash
                $closing = max(0.0, $opening + $advances + $interest + $unwind - $cash['amount'] - $cash['derecognised']);"""),
("""                    'opening_gross' => round($opening, 2),
                    'interest_accrued' => round($interest, 2),""",
 """                    'opening_gross' => round($opening, 2),
                    'advances' => round($advances, 2),
                    'opening_basis' => $openingBasis,
                    'interest_accrued' => round($interest, 2),"""),
# initial opening returns the basis
("""    private function initialOpening(ContractEir $contract, string $period): float
    {""", """    /** @return array{0: float, 1: string} the opening and its basis, SCHEDULE or LEDGER */
    private function initialOpening(ContractEir $contract, string $period): array
    {"""),
("""        if (! $origination || $asOf->lessThan(CarbonImmutable::parse($origination)->endOfMonth()) || $flows === []) return $opening;""",
 """        if (! $origination || $asOf->lessThan(CarbonImmutable::parse($origination)->endOfMonth()) || $flows === []) return [$opening, 'SCHEDULE'];"""),
("""    private function prepaidOpening(ContractEir $contract, string $period, float $pv): float
    {""", """    private function prepaidOpening(ContractEir $contract, string $period, float $pv): array
    {"""),
("""        } catch (\\Throwable) {
            return $pv;
        }
        if ($ledger === []) {
            return $pv;
        }
        $gross = -array_sum(array_map(fn ($p) => (float) str_replace(',', '', (string) ($p['payload']['TRANSAMT'] ?? 0)), $ledger));
        if ($gross >= 0.5 * $pv) {
            return $pv;
        }
        // a facility still drawing down is not prepaid: its schedule carries the tranches to come,
        // and the roll-forward does not add a later advance, so the schedule's value stands
        $laterAdvance = DB::table('eir_actual_transactions')->where('contract_id', $contract->contract_id)->where('transaction_type', 'Disbursement')
            ->where('transaction_date', '>', $priorEnd)->exists();
        if ($laterAdvance) {
            return $pv;
        }
        $opening = max(0.0, round($gross, 2));
        \\App\\Services\\AuditLoggerService::log('EIR Opening From Ledger', 'eir_amortisation', null, ['reporting_period' => $period,
            'new_values' => ['contract_id' => $contract->contract_id, 'schedule_present_value' => round($pv, 2), 'ledger_balance' => round($gross, 2), 'opening' => $opening,
                'reason' => 'prepaid ahead of the schedule: the ledger balance at ' . $priorEnd . ' is below half the present value of the remaining schedule']]);

        return $opening;
    }""", """        } catch (\\Throwable) {
            return [$pv, 'SCHEDULE'];
        }
        if ($ledger === []) {
            return [$pv, 'SCHEDULE'];
        }
        $gross = -array_sum(array_map(fn ($p) => (float) str_replace(',', '', (string) ($p['payload']['TRANSAMT'] ?? 0)), $ledger));
        // A facility still drawing down: the schedule assumes every tranche was advanced at the
        // start, so its present value counts money not yet lent. The opening is the drawn balance
        // and each later tranche is added in the month it is drawn.
        $laterAdvance = DB::table('eir_actual_transactions')->where('contract_id', $contract->contract_id)->whereIn('transaction_type', self::ADVANCE_TYPES)
            ->where('transaction_date', '>', $priorEnd)->exists();
        if (! $laterAdvance && $gross >= 0.5 * $pv) {
            return [$pv, 'SCHEDULE'];
        }
        $opening = max(0.0, round($gross, 2));
        \\App\\Services\\AuditLoggerService::log('EIR Opening From Ledger', 'eir_amortisation', null, ['reporting_period' => $period,
            'new_values' => ['contract_id' => $contract->contract_id, 'schedule_present_value' => round($pv, 2), 'ledger_balance' => round($gross, 2), 'opening' => $opening,
                'reason' => $laterAdvance
                    ? 'still drawing down: the opening is the drawn balance at ' . $priorEnd . ' and each later tranche is added in the month it is drawn'
                    : 'prepaid ahead of the schedule: the ledger balance at ' . $priorEnd . ' is below half the present value of the remaining schedule']]);

        return [$opening, 'LEDGER'];
    }"""),
# cash: the advances of the month with their dates
("""            return ['amount' => $this->scheduledCash($contractId, $start, $end), 'source' => 'DERIVED', 'unclassified' => 0.0, 'derecognised' => 0.0];""",
 """            return ['amount' => $this->scheduledCash($contractId, $start, $end), 'source' => 'DERIVED', 'unclassified' => 0.0, 'derecognised' => 0.0, 'advances' => []];"""),
("""        return ['amount' => $collected, 'source' => 'IMPORTED', 'unclassified' => $unclassified, 'derecognised' => $derecognised];""",
 """        // each advance with the share of the month it is outstanding for (drawn on the 1st: all of it)
        $days = $start->daysInMonth;
        $advances = DB::table('eir_actual_transactions')->where('contract_id', $contractId)->whereIn('transaction_type', self::ADVANCE_TYPES)
            ->whereBetween('transaction_date', [$start->toDateString(), $end->toDateString()])->get(['transaction_date', 'total_amount'])
            ->map(fn ($a) => ['amount' => (float) $a->total_amount,
                'share_of_month' => ($days - (int) CarbonImmutable::parse($a->transaction_date)->day + 1) / $days])->all();

        return ['amount' => $collected, 'source' => 'IMPORTED', 'unclassified' => $unclassified, 'derecognised' => $derecognised, 'advances' => $advances];"""),
("""                'opening_gross' => $row->opening_gross,
                'interest_accrued' => $row->interest_accrued,""", """                'opening_gross' => $row->opening_gross,
                'advances' => $row->advances,
                'opening_basis' => $row->opening_basis,
                'interest_accrued' => $row->interest_accrued,"""),
])

for m in ['Models/EirAmortisation.php', 'Models/EirAmortisationHistory.php']:
    patch(m, [("""        'opening_gross',
        'interest_accrued',""", """        'opening_gross',
        'advances',
        'opening_basis',
        'interest_accrued',"""),
              ("""        'opening_gross'          => 'float',""", """        'opening_gross'          => 'float',
        'advances'               => 'float',""")])
print('ok')
