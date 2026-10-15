p = r'C:/xampp/htdocs/MAICC-IFRS9/app/Services/Lgd/LgdEngineService.php'
s = open(p, encoding='utf-8').read()
old_head_start = s.index("    /** @return array{lgd_id:int,window:string,cohort:int")
old_head_end = s.index("        /*\n        | The workout")
new_head = r"""    /** @return array{lgd_id:int,window:string,cohort:int,start_balance:float,cure_rate:float,recovery_rate:float,lgd:float,updated:int} */
    public function run(string $period, int $portfolioId, int $windowMonths = 12, ?int $userId = null, ?string $label = null): array
    {
        \App\Support\ReportingPeriodLock::assertOpen($period, 'the LGD engine');
        $m = $this->measure($period, [$portfolioId], $windowMonths);
        if ($m['cohort'] === 0 || $m['start_balance'] <= 0) {
            throw new RuntimeException("No Stage 3 cohort at {$m['start_period']} for portfolio {$portfolioId}.");
        }

        return DB::transaction(function () use ($m, $period, $portfolioId, $userId, $label) {
            $row = $this->persist($m, $period, 'portfolio', $portfolioId, null, $userId);
            $updated = DB::table('loan_books')->where('reporting_period', $period)->where('loan_portfolio_id', $portfolioId)->update(['lgd_value' => round($m['lgd'], 8), 'collection_lgd' => round($m['lgd'], 8)]);
            AuditLoggerService::log('LGD Engine Run', 'loss_given_default', $row->id, ['reporting_period' => $period, 'rows_affected' => $updated,
                'new_values' => ['window' => "{$m['start_period']} to {$period}", 'cohort' => $m['cohort'], 'cure_rate' => round($m['cure_rate'], 6), 'recovery_rate' => round($m['recovery_rate'], 6), 'lgd' => round($m['lgd'], 6)], 'meta' => ['user' => $userId, 'label' => $label]]);

            return ['lgd_id' => $row->id, 'window' => "{$m['start_period']} to {$period}", 'cohort' => $m['cohort'], 'start_balance' => round($m['start_balance'], 2), 'cure_rate' => round($m['cure_rate'], 6), 'recovery_rate' => round($m['recovery_rate'], 6), 'lgd' => round($m['lgd'], 6), 'updated' => $updated];
        });
    }

    /**
     * The cohort workout over one or more portfolios, written nowhere. One
     * portfolio is what run() measures; several are the pooled book of the
     * LGD by segment (LgdSegmentationService). $startPeriod fixes the window
     * start (the segment run uses one window for every segment); without it
     * the window starts twelve months back, or at the first staged month of
     * the portfolios when the book is younger.
     *
     * @param list<int> $portfolioIds
     * @return array{start_period:string,cohort:int,start_balance:float,end_balance:float,cured:float,partly:float,full:float,recovered:float,recovered_pv:float,written_off:float,discounted:bool,cure_rate:float,recovery_rate:float,lgd:float}
     */
    public function measure(string $period, array $portfolioIds, int $windowMonths = 12, ?string $startPeriod = null): array
    {
        if ($startPeriod === null) {
            $end = CarbonImmutable::parse($period . '-01');
            $start = $end->subMonths($windowMonths);
            $available = DB::table('loan_books')->whereNotNull('calculated_ifrs9_stage')->whereIn('loan_portfolio_id', $portfolioIds)->min('reporting_period');
            if ($available === null) {
                throw new RuntimeException('No staged loan book: run eir:stage first.');
            }
            if ($start->format('Y-m') < $available) {
                $start = CarbonImmutable::parse($available . '-01');
            }
            $startPeriod = $start->format('Y-m');
        }
        $rows = DB::table('loan_books as s')->leftJoin('loan_books as e', fn ($j) => $j->on('s.contract_id', '=', 'e.contract_id')->on('s.loan_portfolio_id', '=', 'e.loan_portfolio_id')->where('e.reporting_period', '=', $period))
            ->where('s.reporting_period', $startPeriod)->where('s.calculated_ifrs9_stage', '3')->whereIn('s.loan_portfolio_id', $portfolioIds)
            ->get(['s.contract_id', 's.loan_portfolio_id', DB::raw('s.carrying_amount as start_balance'), DB::raw('e.carrying_amount as end_balance'), DB::raw("COALESCE(e.calculated_ifrs9_stage, '3') as closing_stage")]);

"""
s = s[:old_head_start] + new_head + s[old_head_end:]
a = "            $history = DB::table('loan_books')->where('contract_id', $r->contract_id)->where('loan_portfolio_id', $portfolioId)"
assert a in s
s = s.replace(a, "            $history = DB::table('loan_books')->where('contract_id', $r->contract_id)->where('loan_portfolio_id', $r->loan_portfolio_id)")
tail_start = s.index("        $cureRate = $startBalance > 0 ? $cured / $startBalance : 0.0;")
tail_end = s.rindex("    }\n}")
new_tail = r"""        $cureRate = $startBalance > 0 ? $cured / $startBalance : 0.0;
        $recoveryRate = $nonCuredStart > 0 ? $recoveredPv / $nonCuredStart : 0.0;
        $lgd = max(0.0, min(1.0, (1 - $cureRate) * (1 - $recoveryRate)));

        return ['start_period' => $startPeriod, 'cohort' => $rows->count(), 'start_balance' => $startBalance, 'end_balance' => $endBalance, 'cured' => $cured, 'partly' => $partly, 'full' => $full,
            'recovered' => $recovered, 'recovered_pv' => $recoveredPv, 'written_off' => $writtenOff, 'discounted' => $discounted, 'cure_rate' => $cureRate, 'recovery_rate' => $recoveryRate, 'lgd' => $lgd];
    }

    /** One measured cohort as a system calculation in loss_given_default. */
    public function persist(array $m, string $period, string $level, ?int $portfolioId, ?string $code, ?int $userId): LossGivenDefault
    {
        return LossGivenDefault::create([
            'reporting_period' => $period . '-01', 'start_period' => $m['start_period'] . '-01', 'lgd_calculation_level' => $level, 'lgd_calculation_id' => $portfolioId, 'lgd_calculation_code' => $code,
            'start_total_stage3' => round($m['start_balance'], 2), 'end_total_stage3' => round($m['end_balance'], 2), 'loss_given_default_percentage' => round($m['lgd'], 6),
            'cured_amount' => round($m['cured'], 2), 'cure_rate' => round($m['cure_rate'], 6), 'cure_rate_average_monthly' => 0, 'cure_amount_stage1' => 0, 'cure_amount_stage2' => 0,
            'partially_recovered_amount' => round($m['partly'], 2), 'fully_recovered_amount' => round($m['full'], 2), 'recovered_amount' => round($m['recovered'], 2), 'recovery_rate' => round($m['recovery_rate'], 6),
            'recovery_rate_average_monthly' => 0, 'total_disbursments' => 0, 'last_reporting_period' => null, 'is_active_or_closed' => 'closed', 'calculation_source' => 'system',
            'written_offs' => round($m['written_off'], 2), 'total_payment' => round($m['recovered'], 2), 'discounted_payment_partly' => round($m['recovered_pv'], 2),
            'created_by' => $userId, 'updated_by' => $userId, 'is_discounting' => $m['discounted'], 'discount_rate_source' => $m['discounted'] ? 'loan_book' : null, // the column is an enum(manual, loan_book): the rate is the loan's locked EIR, else its contractual rate from the book
        ]);
"""
s = s[:tail_start] + new_tail + s[tail_end:]
open(p, 'w', encoding='utf-8', newline='').write(s)
print('ok')
