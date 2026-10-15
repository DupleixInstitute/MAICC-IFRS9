        // The two bases only differ where the facility is partly drawn, so
        // that is the only case in which a missing flag stops the schedule.
        $partlyDrawn = round($sanctioned - $principal, 2) > 0.0;

        // ---- where the balance starts, and the tranches still to come --------
        // The moratorium runs from the first disbursement (E-Banker p.31), which
        // is `moratorium_from`, not from approval; the instalments accrue from
        // the end of the moratorium, or from the interest start date.
        $moratoriumFrom = isset($terms['moratorium_from']) && $terms['moratorium_from']
            ? $this->date($terms['moratorium_from'])
            : $startDate;
        $plainAccrualStart = isset($terms['interest_start_date']) && $terms['interest_start_date']
            ? $this->date($terms['interest_start_date'])
            : $startDate;
        $profileStart = $moratoriumMonths > 0 ? $moratoriumFrom : $plainAccrualStart;
        [$openingDrawn, $queue, $drawdowns] = $this->drawdownProfile($terms['drawdowns'] ?? null, $principal, $profileStart);
        // drawn in tranches after the schedule starts: partly drawn for a while
        $tranched = $queue !== [];

        [$emiCalcType, $emiSource] = $this->emiCalcType($terms, $scheme);
        [$interestBasis, $interestSource] = $this->interestBasis($terms, $scheme, $partlyDrawn || $tranched, $tranched);
        [$instalmentBasis, $instalmentSource] = ! $partlyDrawn && $tranched
            ? $this->trancheInstalmentBasis($terms, $scheme)
            : $this->instalmentBasis($terms, $scheme, $partlyDrawn);
        [$moratoriumType, $moratoriumSource] = $moratoriumMonths > 0
            ? $this->moratoriumType($terms, $scheme, $moratoriumMonths)
            : [null, 'NOT_APPLICABLE'];

        $interestBaseFor = fn (float $balance): float => $interestBasis === self::INTEREST_ON_SANCTION ? $sanctioned : $balance;

        // ---- the moratorium, measured from the first disbursement ----------
        $moratoriumEnd = null;
        $moratoriumRows = [];
        $servicedRows = [];
        $capitalisedInterest = 0.0;
        $balance = round($openingDrawn, 2);
        $skippedBeforeDrawdown = 0;

        if ($moratoriumMonths > 0) {
            $cursor = $moratoriumFrom;
            for ($m = 1; $m <= $moratoriumMonths; $m++) {
                // E-Banker posts interest monthly whatever the instalment
                // frequency, so the moratorium is walked month by month.
                $next = $moratoriumFrom->addMonthsNoOverflow($m);
                $days = $cursor->diffInDays($next);
                $monthOpening = $balance;
                [$interest, $balance] = $this->accrue($cursor, $next, $balance, $queue, $interestBaseFor, $annualRate, $dayCount, null);

                $capitalised = 0.0;
                $paid = 0.0;
                if ($moratoriumType === self::MORATORIUM_BOTH) {
                    // Decision D10: the balance rises each month by exactly
                    // the interest posted, so the posted figure compounds.
                    $capitalised = $interest;
                    $balance = round($balance + $interest, 2);
                    $capitalisedInterest = round($capitalisedInterest + $interest, 2);
                } elseif ($interest == 0.0 && $balance == 0.0) {
                    // nothing drawn yet: nothing is due on this date
                    $skippedBeforeDrawdown++;
                } else {
                    $paid = $interest;
                    $servicedRows[] = [
                        'due_date' => $next->toDateString(),
                        'days' => $days,
                        'opening_balance' => $monthOpening,
                        'principal_due' => 0.0,
                        'interest_due' => $interest,
                        'closing_balance' => $balance,
                        'phase' => 'MORATORIUM_INTEREST',
                    ];
                }

                $moratoriumRows[] = [
                    'month' => $m,
                    'due_date' => $next->toDateString(),
                    'days' => $days,
                    'opening_balance' => $monthOpening,
                    'interest' => $interest,
                    'interest_paid' => $paid,
                    'interest_capitalised' => $capitalised,
                    'closing_balance' => $balance,
                ];
                $cursor = $next;
            }
            $moratoriumEnd = $cursor;
        }

        $capitalisedPrincipal = round($balance, 2);

        // ---- the instalments ----------------------------------------------
        // An instalment sized on the sanctioned amount retires the sanctioned
        // amount: that is what "Installment Based On = sanction" means in
        // E-Banker, and a facility that is never drawn in full is rescheduled
        // at its last drawdown rather than left with a short final payment.
        // A facility drawn in tranches amortises what has been drawn by then,
        // and is sized again as each later tranche arrives.
        $amortisingOpening = $tranched
            ? round($balance, 2)
            : round(($instalmentBasis === self::INSTALMENT_ON_SANCTION ? $sanctioned : $principal) + $capitalisedInterest, 2);
        $balance = $amortisingOpening;

        $accrualStart = $moratoriumEnd ?? $plainAccrualStart;

        $intervalMonths = intdiv(12, $paymentsPerYear);
        $firstDue = isset($terms['first_due_date']) && $terms['first_due_date']
            ? $this->date($terms['first_due_date'])
            : $accrualStart->addMonthsNoOverflow($intervalMonths);
        if ($firstDue->lte($accrualStart)) {
            throw new InvalidArgumentException(sprintf(
                'The first instalment falls on %s, which is not after %s, the date interest starts running. A schedule cannot be generated from those dates.',
                $firstDue->toDateString(), $accrualStart->toDateString()
            ));
        }

        $instalment = $emiCalcType === self::EMI_EQUAL_PRINCIPAL
            ? round($amortisingOpening / $nPayments, 2)
            : $this->levelInstalment($amortisingOpening, $periodRate, $nPayments);

        $rows = $servicedRows;
        $previous = $accrualStart;
        for ($t = 1; $t <= $nPayments; $t++) {
            $due = $firstDue->addMonthsNoOverflow(($t - 1) * $intervalMonths);
            $days = $previous->diffInDays($due);
            // A whole payment period is charged at the period rate (for a
            // quarter, three months compounded under the E-Banker basis);
            // anything else is charged on its actual days under the day count,
            // and so is a period in which a tranche is drawn.
            $wholePeriod = $previous->addMonthsNoOverflow($intervalMonths)->isSameDay($due);

            $opening = $balance;
            [$interest, $balance, $drawnInPeriod] = $this->accrue($previous, $due, $balance, $queue, $interestBaseFor, $annualRate, $dayCount, $wholePeriod ? $periodRate : null);
            if ($drawnInPeriod) {
                // a tranche arrived: the instalment is sized again over the
                // instalments left, on the balance now drawn
                $left = $nPayments - $t + 1;
                $instalment = $emiCalcType === self::EMI_EQUAL_PRINCIPAL
                    ? round($balance / $left, 2)
                    : $this->levelInstalment($balance, $periodRate, $left);
            }
            if ($tranched && $opening == 0.0 && $balance == 0.0) {
                if ($t === $nPayments) {
                    throw new InvalidArgumentException('DRAWDOWN_AFTER_LAST_INSTALMENT: nothing is drawn before the last instalment falls due, so no schedule can be generated from these dates.');
                }
                $skippedBeforeDrawdown++;
                $previous = $due;
                continue;
            }

            if ($emiCalcType === self::EMI_EQUAL_PRINCIPAL) {
                $principalDue = $instalment;
            } else {
                $principalDue = round($instalment - $interest, 2);
                if ($principalDue < 0) {
                    throw new InvalidArgumentException(sprintf(
                        'Interest of %s is charged on the approved amount of %s while the instalment of %s is sized on the amount drawn of %s, so no principal would ever be repaid. Confirm Loan Interest Cal. Base On and Installment Based On for this facility with MAIIC.',
                        number_format($interest, 2), number_format($sanctioned, 2),
                        number_format($instalment, 2), number_format($principal, 2)
                    ));
                }
            }

            if ($t === $nPayments) {
                // Close out rounding drift: the final instalment retires the
                // exact remaining balance.
                $principalDue = round($balance, 2);
            }

            $balance = round($balance - $principalDue, 2);

            $rows[] = [
                'due_date' => $due->toDateString(),
                'days' => $days,
                'opening_balance' => $opening,
                'principal_due' => $principalDue,
                'interest_due' => $interest,
                'closing_balance' => $balance,
                'phase' => $drawnInPeriod ? 'INSTALMENT_TRANCHE_PERIOD' : ($wholePeriod ? 'INSTALMENT' : 'INSTALMENT_IRREGULAR_PERIOD'),
            ];
            $previous = $due;
        }
        if ($queue !== []) {
            throw new InvalidArgumentException(sprintf(
                'DRAWDOWN_AFTER_LAST_INSTALMENT: a tranche of %s drawn on %s falls after the last instalment (%s), so the schedule cannot retire it.',
                number_format($queue[0]['amount'], 2), $queue[0]['date']->toDateString(), $previous->toDateString()
            ));
        }
