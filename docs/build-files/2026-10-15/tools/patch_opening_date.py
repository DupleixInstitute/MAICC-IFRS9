P = 'C:/xampp/htdocs/MAICC-IFRS9/app/Services/Eir/EirRevenueService.php'
s = open(P, encoding='utf-8').read()

a = """        $origination = $snapshot['metadata']['origination_date'] ?? $contract->origination_date?->toDateString();
        $flows = $snapshot['cash_flows'] ?? [];
        $asOf = CarbonImmutable::createFromFormat('Y-m-d', $period . '-01')->endOfMonth();
        if (! $origination || $asOf->lessThan(CarbonImmutable::parse($origination)->endOfMonth()) || $flows === []) return [$opening, 'SCHEDULE'];
"""
b = """        $origination = $snapshot['metadata']['origination_date'] ?? $contract->origination_date?->toDateString();
        $flows = $snapshot['cash_flows'] ?? [];
        // The opening of a month is the amortised cost at the end of the month before: the present
        // value at that date of the flows still to come. (Taking it at the end of the month itself,
        // as before 13 October 2026, counted that month's interest twice: once in the present value
        // and again in the month's accrual.)
        $start = CarbonImmutable::createFromFormat('Y-m-d', $period . '-01');
        $asOf = $start->subDay()->endOfDay();
        if ($origination && CarbonImmutable::parse($origination)->greaterThan($asOf)) {
            // Lent within the month: nothing was owed at its start. Where the ledger shows the
            // advances, the month opens at nil and each one is added from the day it was drawn;
            // without them the initial net investment stands in, as before.
            $drawn = DB::table('eir_actual_transactions')->where('contract_id', $contract->contract_id)->whereIn('transaction_type', self::ADVANCE_TYPES)
                ->whereBetween('transaction_date', [$start->toDateString(), $start->endOfMonth()->toDateString()])->exists();

            return $drawn ? [0.0, 'LEDGER'] : [$opening, 'SCHEDULE'];
        }
        if (! $origination || $flows === []) return [$opening, 'SCHEDULE'];
"""
assert a in s
s = s.replace(a, b, 1)
open(P, 'w', encoding='utf-8').write(s)
print('ok')
