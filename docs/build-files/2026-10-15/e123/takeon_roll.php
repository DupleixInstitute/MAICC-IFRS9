    /**
     * The amortised cost from origination to the take-on date: each whole
     * calendar month earns the monthly EIR on the opening balance and gives
     * up the cash the schedule says was due in the month; money advanced in
     * the month (a later tranche) is added and earns the EIR from its date;
     * the first, part, month from origination to its month-end earns the EIR
     * for its actual days over 365, the same daily basis the dated solve
     * discounts on.
     *
     * @param  list<array{due_date:string, amount:float}>  $flows  the solved vector: receipts positive, advances negative
     * @return list<array{period:string,opening:float,interest:float,cash:float,advanced:float,closing:float,days:?int}>
     */
    private function rollForward(float $net, float $eir, CarbonImmutable $origination, array $flows, CarbonImmutable $to): array
    {
        $to = $to->endOfDay();
        if ($origination->gt($to)) {
            return [];
        }
        $monthly = pow(1 + $eir, 1 / 12) - 1;
        $rows = [];
        $balance = $net;
        $prevEnd = $origination;
        $monthEnd = $origination->endOfMonth();
        $stub = ! $origination->isSameDay($monthEnd);   // originated on a month-end: the first full month starts tomorrow
        if (! $stub) {
            $monthEnd = $monthEnd->addDay()->endOfMonth();
        }
        while ($monthEnd->lte($to)) {
            $days = $prevEnd->diffInDays($monthEnd);
            $interest = $stub ? $balance * (pow(1 + $eir, $days / 365) - 1) : $balance * $monthly;
            $cash = 0.0;
            $advanced = 0.0;
            foreach ($flows as $f) {
                $date = (string) $f['due_date'];
                if ($date > $prevEnd->toDateString() && $date <= $monthEnd->toDateString()) {
                    if ((float) $f['amount'] >= 0) {
                        $cash += (float) $f['amount'];
                    } else {
                        $advance = -(float) $f['amount'];
                        $advanced += $advance;
                        $interest += $advance * (pow(1 + $eir, CarbonImmutable::parse($date)->diffInDays($monthEnd->startOfDay()) / 365) - 1);
                    }
                }
            }
            $closing = $balance + $advanced + $interest - $cash;
            $rows[] = ['period' => $monthEnd->format('Y-m'), 'opening' => round($balance, 4), 'interest' => round($interest, 4), 'cash' => round($cash, 4),
                'advanced' => round($advanced, 4), 'closing' => round($closing, 4), 'days' => $stub ? $days : null];
            $balance = $closing;
            $prevEnd = $monthEnd;
            $monthEnd = $monthEnd->addDay()->endOfMonth();
            $stub = false;
        }

        return $rows;
    }
