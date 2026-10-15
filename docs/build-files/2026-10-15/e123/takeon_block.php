
    /**
     * For one evidenced block: the version 1 schedule from the workbook, the
     * EIR solved from the date the workbook starts, the check that the solve
     * is a true reading of the schedule, and the amortised cost rolled forward
     * to the take-on date. Returns the contract_takeon columns to write, the
     * flags raised and whether the solve may be used.
     *
     * The cash-flow vector is read from the workbook's balance chain, in the
     * order the lines stand in the sheet (results review of 13 October 2026,
     * E2). Each line's principal and interest is what falls due on its end
     * date. A balance that rises between two lines, or within a line by more
     * than its capitalised interest, is money advanced on that date (a later
     * tranche, or arrears added to the balance when the loan was rescheduled);
     * a balance that falls by more than the line's principal is money received
     * then (the repayments Tamanda recorded in the schedule). Read this way the
     * vector holds exactly the money the workbook says moved, so with no fee it
     * solves to the rates the lines charge. The solve until 14 October 2026
     * took the header principal at the value date and the instalment column
     * only, so 21 of the 73 solves were implausible: Infracon's second tranche
     * of 265.7 million was never paid out (63.8 percent), Ruo Textiles opened
     * at the approved 70 million instead of the 9.7 million first drawn
     * (-46.5 percent), Steel Base's schedule starts a year after the Loan Book
     * value date (7.2 percent), Nsanama's interest-only months fell out of the
     * vector because their instalment column is nil (14.2 percent).
     *
     * @return array{solved:bool,columns:array,lines:int,flags:list<string>}
     */
    private function recompute(string $account, object $b, float $takeonBalance): array
    {
        $flags = [];
        $contractId = ltrim($account, '0');
        $valueDate = $b->value_date !== null ? CarbonImmutable::parse($b->value_date) : null;
        // the fees are a condition of the loan; the workbook's fee columns are the
        // only record of them (spec 6.9), and a known nil works as well as a figure
        $fees = (float) ($b->total_fees ?? 0);

        $all = DB::table('takeon_schedule_lines')->where('block_id', $b->id)->orderBy('sheet_row')->orderBy('serial')->get();
        $lines = $all->filter(fn ($l) => $l->period_end !== null)->values();
        if ($all->count() > $lines->count()) {
            $flags[] = ($all->count() - $lines->count()) . ' schedule line(s) without a due date left out of the recompute';
        }
        if ($lines->isEmpty()) {
            $flags[] = 'recompute not possible: the block has no dated schedule lines';

            return ['solved' => false, 'columns' => ['eir_check' => 'REFUSED', 'eir_check_reason' => 'the block has no dated schedule lines'], 'lines' => 0, 'flags' => $flags];
        }

        // the version 1 schedule as the workbook states it, one row per due date
        $byDate = [];
        foreach ($lines as $l) {
            $principalDue = (float) ($l->principal ?? 0);
            $interest = (float) ($l->interest ?? 0);
            $byDate[$l->period_end] ??= ['principal' => 0.0, 'interest' => 0.0, 'amount' => 0.0, 'rows' => []];
            $byDate[$l->period_end]['principal'] += $principalDue;
            $byDate[$l->period_end]['interest'] += $interest;
            $byDate[$l->period_end]['amount'] += $principalDue + $interest;
            $byDate[$l->period_end]['rows'][] = (int) $l->sheet_row;
        }
        ksort($byDate);
        $start = CarbonImmutable::parse($lines[0]->period_start ?? $lines[0]->period_end);
        $origination = $start;
        if ($valueDate !== null && ! $valueDate->isSameDay($start)) {
            $flags[] = sprintf('the schedule starts on %s and the Loan Book value date is %s: the solve runs from the schedule\'s start, the date it first charges interest', $start->toDateString(), $valueDate->toDateString());
        }
        $lines_ = $this->writeWorkbookSchedule($contractId, $b, $origination, $byDate, $flags);

        // the balance chain
        $opening = $lines[0]->opening_balance !== null ? (float) $lines[0]->opening_balance : (float) ($b->principal ?? 0);
        if ($opening <= 0) {
            $flags[] = 'recompute not possible: the schedule opens with no balance';

            return ['solved' => false, 'columns' => ['schedule_lines_written' => $lines_, 'eir_check' => 'REFUSED', 'eir_check_reason' => 'the schedule opens with no balance'], 'lines' => $lines_, 'flags' => $flags];
        }
        if ($b->principal !== null && abs((float) $b->principal - $opening) >= 1) {
            $flags[] = sprintf('the block\'s principal is %s and its schedule opens at %s: the schedule\'s balance is taken', number_format((float) $b->principal, 2), number_format($opening, 2));
        }
        $chain = $this->chain($lines, $start);
        $flags = array_merge($flags, $chain['notes']);

        $check = $this->check($chain, $opening, $start, $lines);
        $detail = ['chain' => ['start' => $start->toDateString(), 'opening' => round($opening, 4), 'flows' => $chain['flows'], 'advances' => $chain['advances'],
            'reductions' => $chain['reductions'], 'shared_dates' => $chain['shared_dates'], 'closing' => $chain['closing'], 'line_rates' => $check['rates']]];
        $base = ['schedule_lines_written' => $lines_, 'solve_start_date' => $start->toDateString(), 'eir_check' => $check['status'], 'eir_check_reason' => $check['reason'],
            'nofee_eir' => $check['nofee'] === null ? null : round($check['nofee'], 8), 'net_investment' => round($opening - $fees, 4)];
        if ($check['status'] !== 'PASS') {
            $flags[] = 'take-on EIR refused: ' . $check['reason'];

            return ['solved' => false, 'columns' => $base + ['recompute_detail' => json_encode($detail)], 'lines' => $lines_, 'flags' => $flags];
        }

        // the solve with the fees: the net investment is the opening balance less them
        $net = round($opening - $fees, 4);
        try {
            $dayCount = $this->governance->get('day_count', $origination);
            $ppy = in_array((int) $b->periods_per_year, [1, 2, 4, 6, 12], true) ? (int) $b->periods_per_year : 12;
            $solve = $this->eir->calculateDated($net, $chain['flows'], $ppy, $origination->toDateString(), $dayCount);
        } catch (GovernanceSettingMissingException $e) {
            $flags[] = 'recompute not possible: ' . $e->getMessage();

            return ['solved' => false, 'columns' => $base + ['eir_check' => 'REFUSED', 'eir_check_reason' => $e->getMessage()], 'lines' => $lines_, 'flags' => $flags];
        } catch (Throwable $e) {
            $flags[] = 'EIR not solved from origination: ' . $e->getMessage();

            return ['solved' => false, 'columns' => $base + ['eir_check' => 'REFUSED', 'eir_check_reason' => 'the EIR with the fees did not solve: ' . $e->getMessage()], 'lines' => $lines_, 'flags' => $flags];
        }

        $roll = $this->rollForward($net, (float) $solve['eir_effective_annual'], $origination, $chain['flows'], CarbonImmutable::parse(self::TAKEON_DATE));
        if ($roll === []) {
            $flags[] = 'origination is after the take-on date: nothing to roll forward';

            return ['solved' => false, 'columns' => $base + ['recomputed_eir' => $solve['eir_effective_annual'], 'eir_check' => 'REFUSED', 'eir_check_reason' => 'the schedule starts after the take-on date'], 'lines' => $lines_, 'flags' => $flags];
        }
        $closing = round(end($roll)['closing'], 4);
        $detail['solve'] = ['method' => $solve['method'], 'iterations' => $solve['solver_iterations'], 'residual' => $solve['solver_residual'], 'day_count' => $dayCount,
            'payments_per_year' => $ppy, 'net_investment' => $net, 'fees' => $fees, 'flows' => count($chain['flows']),
            'receipts' => round(array_sum(array_filter(array_column($chain['flows'], 'amount'), fn ($a) => $a > 0)), 4)];
        $detail['roll_forward'] = $roll;

        return ['solved' => true, 'lines' => $lines_, 'flags' => $flags, 'columns' => $base + [
            'recomputed_eir' => round((float) $solve['eir_effective_annual'], 8),
            'recomputed_eir_monthly' => round(pow(1 + (float) $solve['eir_effective_annual'], 1 / 12) - 1, 8),
            'recomputed_amortised_cost' => $closing,
            'recomputed_difference' => round($takeonBalance - $closing, 4),
            'recompute_detail' => json_encode($detail),
        ]];
    }

    /**
     * The workbook's balance chain as money in and out: each line's principal
     * and interest on its end date; a rise in the balance the line does not
     * explain, money advanced (between two lines on the later line's start,
     * within a line on its end); a fall it does not explain, money received.
     *
     * @return array{flows:list<array>, advances:list<array>, reductions:list<array>, notes:list<string>, shared_dates:int, closing:float}
     */
    private function chain($lines, CarbonImmutable $start): array
    {
        $flows = []; $advances = []; $reductions = []; $notes = []; $seen = []; $shared = 0; $before = 0;
        $move = function (string $date, float $amount, string $where, $line) use (&$flows, &$advances, &$reductions) {
            $amount = round($amount, 4);
            if ($amount > 0) {
                $advances[] = ['date' => $date, 'amount' => $amount, 'where' => $where, 'sheet_row' => (int) $line->sheet_row];
                $flows[] = ['due_date' => $date, 'amount' => -$amount, 'flow_type' => CalculateEirService::FLOW_DISBURSEMENT, 'note' => 'advanced ' . $where . ' (row ' . $line->sheet_row . ')'];
            } else {
                $reductions[] = ['date' => $date, 'amount' => -$amount, 'where' => $where, 'sheet_row' => (int) $line->sheet_row, 'repaid_column_k' => $line->amount_repaid];
                $flows[] = ['due_date' => $date, 'amount' => -$amount, 'note' => 'received ' . $where . ' (row ' . $line->sheet_row . ')'];
            }
        };
        $previous = null;
        foreach ($lines as $l) {
            $opening = $l->opening_balance !== null ? (float) $l->opening_balance : null;
            $closing = $l->closing_balance !== null ? (float) $l->closing_balance : null;
            $principal = (float) ($l->principal ?? 0);
            $interest = (float) ($l->interest ?? 0);
            if (isset($seen[$l->period_end])) {
                $shared++;
            }
            $seen[$l->period_end] = true;
            if ($previous !== null && $opening !== null && $previous->closing_balance !== null) {
                $jump = $opening - (float) $previous->closing_balance;
                if (abs($jump) >= 1) {
                    $move((string) ($l->period_start ?? $l->period_end), $jump, 'between two lines', $l);
                }
            }
            if ($opening !== null && $closing !== null) {
                $within = $closing - ($opening - $principal);
                if (abs($within) >= 1) {
                    $move((string) $l->period_end, $within, 'within a line', $l);
                }
            }
            $due = $principal + $interest;
            if (abs($due) >= 0.005) {
                if ($l->period_end <= $start->toDateString()) {
                    $before++;
                } else {
                    $flows[] = ['due_date' => (string) $l->period_end, 'amount' => round($due, 4)];
                }
            }
            $previous = $l;
        }
        if ($before > 0) {
            $notes[] = "{$before} schedule line(s) due on or before the start left out of the solve";
        }
        if ($advances !== []) {
            $notes[] = sprintf('%d rise(s) in the balance read as money advanced, %s in all', count($advances), number_format(array_sum(array_column($advances, 'amount')), 2));
        }
        if ($reductions !== []) {
            $notes[] = sprintf('%d fall(s) in the balance not in the instalments read as money received, %s in all', count($reductions), number_format(array_sum(array_column($reductions, 'amount')), 2));
        }
        if ($shared > 0) {
            $notes[] = "{$shared} schedule line(s) share a due date with another line (F16)";
        }
        usort($flows, fn ($a, $b) => [$a['due_date'], $a['amount'] < 0 ? 0 : 1] <=> [$b['due_date'], $b['amount'] < 0 ? 0 : 1]);

        return ['flows' => $flows, 'advances' => $advances, 'reductions' => $reductions, 'notes' => $notes, 'shared_dates' => $shared,
            'closing' => round((float) ($previous?->closing_balance ?? 0), 4)];
    }

    /**
     * The governed check of a take-on solve (takeon_eir_plausibility): the
     * schedule must retire the loan, and with no fee its own cash flows must
     * solve to a rate inside the lowest and highest rate its lines charge,
     * widened by the governed margin. A schedule that fails is not a reading
     * of the loan the EIR can rest on, and the reason says why.
     *
     * @return array{status:string, reason:?string, nofee:?float, rates:array}
     */
    private function check(array $chain, float $opening, CarbonImmutable $start, $lines): array
    {
        $rates = [];
        foreach ($lines as $l) {
            if ((float) ($l->opening_balance ?? 0) > 0 && (float) ($l->interest ?? 0) > 0 && (int) ($l->days ?? 0) > 0) {
                $rates[] = (float) $l->interest / (float) $l->opening_balance * 365 / (int) $l->days;
            }
        }
        $range = $rates === [] ? null : ['low' => round(min($rates), 6), 'high' => round(max($rates), 6)];
        $setting = $this->governance->get('takeon_eir_plausibility', $start);
        if (preg_match('/(\d+(?:\.\d+)?) point/', $setting, $m) !== 1) {
            throw new RuntimeException("takeon_eir_plausibility holds \"{$setting}\", which names no margin.");
        }
        $margin = (float) $m[1] / 100;
        if (abs($chain['closing']) >= 1) {
            return ['status' => 'REFUSED', 'reason' => sprintf('the schedule does not retire the loan: %s is still owed at its last line; it is an account history, not a contractual schedule', number_format($chain['closing'], 2)), 'nofee' => null, 'rates' => $range];
        }
        if ($range === null) {
            return ['status' => 'REFUSED', 'reason' => 'no line of the schedule charges interest, so there is no rate to hold the solve to', 'nofee' => null, 'rates' => null];
        }
        try {
            $dayCount = $this->governance->get('day_count', $start);
            $nofee = (float) $this->eir->calculateDated($opening, $chain['flows'], 12, $start->toDateString(), $dayCount)['eir_effective_annual'];
        } catch (Throwable $e) {
            return ['status' => 'REFUSED', 'reason' => 'the schedule with no fee does not solve: ' . $e->getMessage(), 'nofee' => null, 'rates' => $range];
        }
        $nominal = 12 * (pow(1 + $nofee, 1 / 12) - 1);
        if ($nominal < $range['low'] - $margin || $nominal > $range['high'] + $margin) {
            $why = [];
            if ($chain['shared_dates'] > 0) {
                $why[] = $chain['shared_dates'] . ' line(s) share a due date with another, so instalments are dated earlier than they fall';
            }
            if ($chain['reductions'] !== []) {
                $why[] = 'the balance falls by ' . number_format(array_sum(array_column($chain['reductions'], 'amount')), 2) . ' outside the instalments';
            }
            if ($chain['advances'] !== []) {
                $why[] = 'the balance rises by ' . number_format(array_sum(array_column($chain['advances'], 'amount')), 2) . ' outside the instalments';
            }

            return ['status' => 'REFUSED', 'nofee' => $nofee, 'rates' => $range, 'reason' => sprintf(
                'with no fee the schedule solves to %.2f percent while its lines charge %.2f to %.2f percent (%s): the dates or amounts do not describe the loan%s',
                $nominal * 100, $range['low'] * 100, $range['high'] * 100, $setting, $why === [] ? '' : '; ' . implode('; ', $why)
            )];
        }

        return ['status' => 'PASS', 'reason' => null, 'nofee' => $nofee, 'rates' => $range];
    }
