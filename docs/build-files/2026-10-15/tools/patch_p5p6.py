R = 'C:/xampp/htdocs/MAICC-IFRS9/app/'

def patch(p, pairs):
    s = open(R + p, encoding='utf-8').read()
    for a, b in pairs:
        assert a in s, (p, a[:80])
        s = s.replace(a, b, 1)
    open(R + p, 'w', encoding='utf-8').write(s)

patch('Services/Eir/EirRevenueService.php', [
("""                if ($previous) {
                    $opening = (float) $previous->closing_gross;
                    $openingBasis = $previous->opening_basis ?: 'SCHEDULE';
                } else {
                    [$opening, $openingBasis] = $this->initialOpening($contract, $period);
                }""",
 """                $arrears = 0.0;
                if ($previous) {
                    $opening = (float) $previous->closing_gross;
                    $openingBasis = $previous->opening_basis ?: 'SCHEDULE';
                } else {
                    [$opening, $openingBasis, $arrears] = $this->initialOpening($contract, $period);
                }"""),
("""                $monthlyRate = pow(1 + (float) $contract->eir_effective_annual, 1 / 12) - 1;""",
 """                $eirAnnual = $this->eirInForce($contract, $loan);
                $monthlyRate = pow(1 + $eirAnnual, 1 / 12) - 1;"""),
("""                    'opening_basis' => $openingBasis,
                    'interest_accrued' => round($interest, 2),""",
 """                    'opening_basis' => $openingBasis,
                    'eir_annual' => round($eirAnnual, 8),
                    'opening_arrears' => round($arrears, 2),
                    'interest_accrued' => round($interest, 2),"""),
# initial opening: three values
("""    /** @return array{0: float, 1: string} the opening and its basis, SCHEDULE or LEDGER */
    private function initialOpening(ContractEir $contract, string $period): array""",
 """    /**
     * The EIR a month accrues at (decision D38, IFRS 9 B5.4.5). A fixed-rate loan
     * keeps its locked rate. A floating-rate loan's EIR moves with the rate E-Banker
     * charged in the month (the loan book's rate, section 3.2 of the specification),
     * keeping the margin the fees add: the nominal EIR is the locked nominal EIR plus
     * the change from the contractual rate the locked schedule was built on to the
     * month's rate. That is also how a schedule generated at a later rate than the
     * loan was lent at is put right: Chipala Investment was lent at 30.0 percent and
     * its schedule built at 26.1.
     */
    private function eirInForce(ContractEir $contract, object $loan): float
    {
        $locked = (float) $contract->eir_effective_annual;
        $built = (float) ($contract->contractual_rate ?? 0);
        $charged = isset($loan->interest_rate) && is_numeric($loan->interest_rate) ? (float) $loan->interest_rate / 100 : null;
        if (strtoupper((string) $contract->rate_type) !== 'FLOATING' || $built <= 0 || $charged === null || $charged <= 0) {
            return $locked;
        }
        $nominal = 12 * (pow(1 + $locked, 1 / 12) - 1) + ($charged - $built);

        return pow(1 + max(0.0, $nominal) / 12, 12) - 1;
    }

    /** @return array{0: float, 1: string, 2: float} the opening, its basis (SCHEDULE or LEDGER) and the arrears in it */
    private function initialOpening(ContractEir $contract, string $period): array"""),
("""            return $drawn ? [0.0, 'LEDGER'] : [$opening, 'SCHEDULE'];
        }
        if (! $origination || $flows === []) return [$opening, 'SCHEDULE'];""",
 """            return $drawn ? [0.0, 'LEDGER', 0.0] : [$opening, 'SCHEDULE', 0.0];
        }
        if (! $origination || $flows === []) return [$opening, 'SCHEDULE', 0.0];"""),
("""        $pv = 0.0;
        foreach ($flows as $flow) {
            if (empty($flow['due_date']) || ! isset($flow['amount'])) continue;
            $due = CarbonImmutable::parse($flow['due_date']);
            if ($due->lessThanOrEqualTo($asOf)) continue;
            $years = $this->yearFraction($asOf, $due, (string) ($contract->source_day_count_basis ?? 'ACT/365'));
            $pv += (float) $flow['amount'] / pow(1 + (float) $contract->eir_effective_annual, $years);
        }
        if ($pv <= 0) throw new RuntimeException("No remaining contractual cash flows exist after {$period}.");

        return $this->prepaidOpening($contract, $period, $pv);""",
 """        $pv = $this->presentValue($contract, $flows, $asOf, (float) $contract->eir_effective_annual);
        if ($pv <= 0) throw new RuntimeException("No remaining contractual cash flows exist after {$period}.");
        // the balance the schedule expects to be owed: the same flows at the contractual rate
        $contractual = (float) ($contract->contractual_rate ?? 0);
        $scheduled = $contractual > 0 ? $this->presentValue($contract, $flows, $asOf, pow(1 + $contractual / 12, 12) - 1) : $pv;

        return $this->prepaidOpening($contract, $period, $pv, $scheduled);
    }

    /** The present value at $asOf of the flows due after it, at an effective annual rate. */
    private function presentValue(ContractEir $contract, array $flows, CarbonImmutable $asOf, float $annual): float
    {
        $pv = 0.0;
        foreach ($flows as $flow) {
            if (empty($flow['due_date']) || ! isset($flow['amount'])) continue;
            $due = CarbonImmutable::parse($flow['due_date']);
            if ($due->lessThanOrEqualTo($asOf)) continue;
            $years = $this->yearFraction($asOf, $due, (string) ($contract->source_day_count_basis ?? 'ACT/365'));
            $pv += (float) $flow['amount'] / pow(1 + $annual, $years);
        }

        return $pv;"""),
("""    private function prepaidOpening(ContractEir $contract, string $period, float $pv): array
    {""",
 """    /**
     * Arrears at the opening (decision D39, specification section 6.9). The present
     * value assumes every instalment up to the month before was paid as scheduled.
     * Where the ledger owes more than the balance the schedule expects at that date
     * (by more than one percent), the difference is booked as arrears and added to the
     * opening: overdue amounts are owed now, so they are not discounted. The addition
     * is written to the audit log with the three figures.
     */
    private function prepaidOpening(ContractEir $contract, string $period, float $pv, float $scheduled): array
    {"""),
("""        } catch (\\Throwable) {
            return [$pv, 'SCHEDULE'];
        }
        if ($ledger === []) {
            return [$pv, 'SCHEDULE'];
        }""",
 """        } catch (\\Throwable) {
            return [$pv, 'SCHEDULE', 0.0];
        }
        if ($ledger === []) {
            return [$pv, 'SCHEDULE', 0.0];
        }"""),
("""        if (! $laterAdvance && $gross >= 0.5 * $pv) {
            return [$pv, 'SCHEDULE'];
        }""",
 """        if (! $laterAdvance && $gross >= 0.5 * $pv) {
            $arrears = $gross - $scheduled;
            if ($arrears <= 0.01 * $scheduled) {
                return [$pv, 'SCHEDULE', 0.0];
            }
            $arrears = round($arrears, 2);
            \\App\\Services\\AuditLoggerService::log('EIR Opening Arrears', 'eir_amortisation', null, ['reporting_period' => $period,
                'new_values' => ['contract_id' => $contract->contract_id, 'schedule_present_value' => round($pv, 2), 'scheduled_balance' => round($scheduled, 2),
                    'ledger_balance' => round($gross, 2), 'arrears' => $arrears, 'opening' => round($pv + $arrears, 2),
                    'reason' => 'the ledger at ' . $priorEnd . ' owes more than the schedule expects: the difference is booked as arrears (specification section 6.9)']]);

            return [$pv + $arrears, 'SCHEDULE', $arrears];
        }"""),
("""        return [$opening, 'LEDGER'];
    }""", """        return [$opening, 'LEDGER', 0.0];
    }"""),
("""                'opening_basis' => $row->opening_basis,
                'interest_accrued' => $row->interest_accrued,""",
 """                'opening_basis' => $row->opening_basis,
                'eir_annual' => $row->eir_annual,
                'opening_arrears' => $row->opening_arrears,
                'interest_accrued' => $row->interest_accrued,"""),
])

for m in ['Models/EirAmortisation.php', 'Models/EirAmortisationHistory.php']:
    patch(m, [("""        'opening_basis',
        'interest_accrued',""", """        'opening_basis',
        'eir_annual',
        'opening_arrears',
        'interest_accrued',"""),
              ("""        'advances'               => 'float',""", """        'advances'               => 'float',
        'eir_annual'             => 'float',
        'opening_arrears'        => 'float',""")])
print('ok')
