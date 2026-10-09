import io, os
os.chdir(r'C:\xampp\htdocs\MAICC-IFRS9')
p = 'app/Services/Lgd/LgdEngineService.php'
s = io.open(p, encoding='utf-8').read()

old = s[s.index("        $rows = DB::table('loan_books as s')"):s.index("        return DB::transaction(function () use (")]
new = """        $rows = DB::table('loan_books as s')->leftJoin('loan_books as e', fn ($j) => $j->on('s.contract_id', '=', 'e.contract_id')->on('s.loan_portfolio_id', '=', 'e.loan_portfolio_id')->where('e.reporting_period', '=', $period))
            ->where('s.reporting_period', $startPeriod)->where('s.calculated_ifrs9_stage', '3')->where('s.loan_portfolio_id', $portfolioId)
            ->get(['s.contract_id', DB::raw('s.carrying_amount as start_balance'), DB::raw('e.carrying_amount as end_balance'), DB::raw("COALESCE(e.calculated_ifrs9_stage, '3') as closing_stage")]);
        if ($rows->isEmpty() || (float) $rows->sum('start_balance') <= 0) {
            throw new RuntimeException("No Stage 3 cohort at {$startPeriod} for portfolio {$portfolioId}.");
        }

        /*
        | The workout (system audit of 9 October 2026, finding H7).
        |
        | Cure: a loan back in Stage 1 or 2 at the end of the window. A cured
        | loan is repaying under its contract, so it is out of the recovery
        | measure; cure and recovery are exclusive.
        |
        | Recovery, on the loans that did not cure: the cash received over the
        | window (the rise in the cumulative repayments the loan book carries,
        | month by month), never more than the starting balance; where the book
        | carries no repayments column the fall in the carrying amount stands
        | in. A loan absent from the end book is a write-off unless its last
        | book row shows it settled (zero balance, or a closed status), in
        | which case the balance it last carried counts as recovered. Before
        | this a loan absent from the end book was read as fully recovered.
        |
        | Discounting: each month's cash is discounted to the start of the
        | window at the loan's locked EIR (its contractual rate when no EIR is
        | locked), so the recovery rate is a present value as IFRS 9 B5.5.28
        | requires; is_discounting records that.
        */
        $hasRepayments = Schema::hasColumn('loan_books', 'repayments');
        $hasStatus = Schema::hasColumn('loan_books', 'contract_status');
        $hasRate = Schema::hasColumn('loan_books', 'interest_rate');
        $lockedRates = Schema::hasTable('contract_eir') ? DB::table('contract_eir')->whereNotNull('locked_at')->pluck('eir_effective_annual', 'contract_id') : collect();
        $settled = ['closed', 'paid', 'settled', 'paid_up', 'repaid', 'matured'];

        $startBalance = 0.0; $cured = 0.0; $nonCuredStart = 0.0; $recovered = 0.0; $recoveredPv = 0.0; $writtenOff = 0.0; $endBalance = 0.0; $full = 0.0; $partly = 0.0; $discounted = false;
        foreach ($rows as $r) {
            $sb = (float) $r->start_balance;
            $startBalance += $sb;
            $endBalance += (float) ($r->end_balance ?? 0);
            if ((string) $r->closing_stage === '1' || (string) $r->closing_stage === '2') {
                $cured += $sb;
                continue;
            }
            $nonCuredStart += $sb;
            $rate = isset($lockedRates[$r->contract_id]) ? (float) $lockedRates[$r->contract_id] : null;

            // the loan's rows through the window, oldest first
            $history = DB::table('loan_books')->where('contract_id', $r->contract_id)->where('loan_portfolio_id', $portfolioId)
                ->where('reporting_period', '>=', $startPeriod)->where('reporting_period', '<=', $period)->orderBy('reporting_period')->get();
            $first = $history->first(); $last = $history->last();
            if ($rate === null && $hasRate && $first && $first->interest_rate !== null) {
                $rate = (float) $first->interest_rate; $rate = $rate > 1 ? $rate / 100 : $rate;
            }
            $rate = $rate ?? 0.0;
            if ($rate > 0) { $discounted = true; }

            $cash = 0.0; $pv = 0.0;
            if ($hasRepayments && $first) {
                $prev = (float) $first->repayments;
                foreach ($history->slice(1) as $row) {
                    $m = max(0.0, (float) $row->repayments - $prev); $prev = (float) $row->repayments;
                    $months = CarbonImmutable::parse($startPeriod . '-01')->diffInMonths(CarbonImmutable::parse($row->reporting_period . '-01'));
                    $cash += $m; $pv += $m / (1 + $rate) ** ($months / 12);
                }
            } else {
                $eb = $r->end_balance === null ? (float) ($last->carrying_amount ?? 0) : (float) $r->end_balance;
                $cash = max(0.0, $sb - $eb); $pv = $cash / (1 + $rate) ** ($windowMonths / 24); // mid-window when the months are not known
            }
            // absent at the end: settled, or written off
            if ($r->end_balance === null) {
                $lastBalance = (float) ($last->carrying_amount ?? 0);
                $isSettled = $lastBalance <= 0.0 || ($hasStatus && in_array(strtolower((string) ($last->contract_status ?? '')), $settled, true));
                if ($isSettled && $lastBalance > 0) {
                    $months = CarbonImmutable::parse($startPeriod . '-01')->diffInMonths(CarbonImmutable::parse($last->reporting_period . '-01'));
                    $cash += $lastBalance; $pv += $lastBalance / (1 + $rate) ** ($months / 12);
                } elseif (! $isSettled) {
                    $writtenOff += max(0.0, $sb - $cash);
                }
            }
            $cash = min($cash, $sb); $pv = min($pv, $sb);
            $recovered += $cash; $recoveredPv += $pv;
            if ($cash >= $sb - 0.005) { $full += $sb; } elseif ($cash > 0) { $partly += $cash; }
        }
        $cureRate = $startBalance > 0 ? $cured / $startBalance : 0.0;
        $recoveryRate = $nonCuredStart > 0 ? $recoveredPv / $nonCuredStart : 0.0;
        $lgd = max(0.0, min(1.0, (1 - $cureRate) * (1 - $recoveryRate)));
        $disbursed = 0.0;

"""
s = s.replace(old, new, 1)
s = s.replace("        return DB::transaction(function () use ($period, $startPeriod, $portfolioId, $rows, $startBalance, $endBalance, $disbursed, $cured, $partly, $full, $recovered, $cureRate, $recoveryRate, $lgd, $userId, $label) {",
              "        return DB::transaction(function () use ($period, $startPeriod, $portfolioId, $rows, $startBalance, $endBalance, $disbursed, $cured, $partly, $full, $recovered, $recoveredPv, $writtenOff, $discounted, $cureRate, $recoveryRate, $lgd, $userId, $label) {")
s = s.replace("                'recovery_rate_average_monthly' => 0, 'total_disbursments' => round($disbursed, 2), 'last_reporting_period' => null, 'is_active_or_closed' => 'closed', 'calculation_source' => 'system',\n                'created_by' => $userId, 'updated_by' => $userId, 'is_discounting' => false,",
              "                'recovery_rate_average_monthly' => 0, 'total_disbursments' => round($disbursed, 2), 'last_reporting_period' => null, 'is_active_or_closed' => 'closed', 'calculation_source' => 'system',\n                'written_offs' => round($writtenOff, 2), 'total_payment' => round($recovered, 2), 'discounted_payment_partly' => round($recoveredPv, 2),\n                'created_by' => $userId, 'updated_by' => $userId, 'is_discounting' => $discounted, 'discount_rate_source' => $discounted ? 'Locked EIR, else the contractual rate' : null,")
s = s.replace("use Illuminate\\Support\\Facades\\DB;", "use Illuminate\\Support\\Facades\\DB;\nuse Illuminate\\Support\\Facades\\Schema;", 1)
assert "written_offs" in s and "Schema::hasColumn" in s
io.open(p, 'w', encoding='utf-8', newline='\n').write(s)

t = 'tests/Feature/Pd/PdAndLgdEngineTest.php'
s = io.open(t, encoding='utf-8').read()
old = """        // four loans: A stays in Stage 1; B goes 1 -> 3; C is Stage 3 and cures to 2; D is Stage 3 and is paid down by half
        $start = [['A', '1', 1000], ['B', '1', 1000], ['C', '3', 500], ['D', '3', 500]];
        $end = [['A', '1', 1000], ['B', '3', 1000], ['C', '2', 500], ['D', '3', 250]];
        foreach ([['2025-08', $start], ['2026-08', $end]] as [$p, $rows]) {"""
new = """        // five loans: A stays in Stage 1; B goes 1 -> 3; C is Stage 3 and cures to 2; D is Stage 3 and is paid down by half;
        // E is Stage 3 and is absent from the end book with a balance still owing (a write-off, audit H7)
        $start = [['A', '1', 1000], ['B', '1', 1000], ['C', '3', 500], ['D', '3', 500], ['E', '3', 500]];
        $end = [['A', '1', 1000], ['B', '3', 1000], ['C', '2', 500], ['D', '3', 250]];
        foreach ([['2025-08', $start], ['2026-08', $end]] as [$p, $rows]) {"""
assert old in s; s = s.replace(old, new, 1)
old2 = """        $r = (new LgdEngineService())->run('2026-08', 1, 12, null, 'test');
        $this->assertSame(2, $r['cohort']);
        $this->assertEquals(1000.0, $r['start_balance']);
        $this->assertEqualsWithDelta(0.5, $r['cure_rate'], 1e-6);       // C (500 of 1,000) cured to Stage 2
        $this->assertEqualsWithDelta(0.25, $r['recovery_rate'], 1e-6);  // D paid 250 of the 1,000
        $this->assertEqualsWithDelta(0.375, $r['lgd'], 1e-6);           // (1 - 0.5)(1 - 0.25)
        $this->assertSame(4, $r['updated']);
        $this->assertEqualsWithDelta(0.375, (float) DB::table('loan_books')->where('reporting_period', '2026-08')->where('contract_id', 'A')->value('collection_lgd'), 1e-6);"""
new2 = """        $r = (new LgdEngineService())->run('2026-08', 1, 12, null, 'test');
        $this->assertSame(3, $r['cohort']);
        $this->assertEquals(1500.0, $r['start_balance']);
        $this->assertEqualsWithDelta(1 / 3, $r['cure_rate'], 1e-6);     // C (500 of 1,500) cured to Stage 2
        // of the 1,000 that did not cure, D paid 250 and E (absent, still owing) recovered nothing: 25 percent.
        // Before the audit E counted as fully recovered and the cured loan sat in the recovery denominator.
        $this->assertEqualsWithDelta(0.25, $r['recovery_rate'], 1e-6);
        $this->assertEqualsWithDelta(0.5, $r['lgd'], 1e-6);             // (1 - 1/3)(1 - 0.25)
        $this->assertSame(4, $r['updated']);
        $this->assertEqualsWithDelta(500.0, (float) DB::table('loss_given_default')->where('id', $r['lgd_id'])->value('written_offs'), 1e-6);
        $this->assertEqualsWithDelta(0.5, (float) DB::table('loan_books')->where('reporting_period', '2026-08')->where('contract_id', 'A')->value('collection_lgd'), 1e-6);"""
assert old2 in s; s = s.replace(old2, new2, 1)
io.open(t, 'w', encoding='utf-8', newline='\n').write(s)
print('ok')
