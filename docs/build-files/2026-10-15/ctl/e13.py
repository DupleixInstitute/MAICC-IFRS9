from ed import edit
p='app/Services/Ebanker/GlFeeBuildService.php'
edit(p, r"""    private function gate(array $legs): array
    {""", r"""    public function gate(array $legs): array
    {""")
edit(p, r"""        foreach ($this->zone->family(self::GL_FEE_LEGS) as $r) {
            $leg = $this->leg($r);
            if ($leg === null || ! in_array($leg['gl'], self::FEE_GLS, true) || $leg['batch_type'] === 'YRCLO' || in_array($leg['type'], ['800', '801'], true)) {
                continue;
            }
            $out[] = $leg;
        }""", r"""        foreach ($this->zone->family(self::GL_FEE_LEGS) as $r) {
            $leg = self::leg($r);
            if (! self::isGateLeg($leg)) {
                continue;
            }
            $out[] = $leg;
        }""")
edit(p, r"""    private function leg(array $r): ?array
    {""", r"""    /** A posted fee leg the gate counts: on a fee GL, not a year-end closing voucher (R04). */
    public static function isGateLeg(?array $leg): bool
    {
        return $leg !== null && in_array($leg['gl'], self::FEE_GLS, true) && $leg['batch_type'] !== 'YRCLO' && ! in_array($leg['type'], ['800', '801'], true);
    }

    /** One landed row as a leg ({source_key, row_date, account, payload}); null for a deleted, unpassed, undated or nil row. */
    public static function leg(array $r): ?array
    {""")

p='app/Services/Ebanker/PackGates.php'
edit(p, r""" *   month_end_rows     every in-scope account (in the master, on a loan GL,
 *                      with a non-zero ledger balance) has a row in the
 *                      stored loan book at each month-end the pack's runs
 *                      cover                                        (WARNING)
 *""", r""" *   month_end_rows     every in-scope account (in the master, on a loan GL,
 *                      with a non-zero ledger balance) has a row in the
 *                      stored loan book at each month-end the pack's runs
 *                      cover                                        (WARNING)
 *   fee_gl             the month's movement on each fee GL in GL_04a (the
 *                      pack's rows over the earlier landed ones) equals the
 *                      trial balance's movement on that GL (spec 6.4, 7.8.5;
 *                      closing vouchers left out, R04). A difference refuses
 *                      the pack; a month whose trial balance has not landed
 *                      yet is listed and checked again by the GL fee build
 *                      (eir:build-gl-fees, in the month-end)          (ERROR)
 *
 * The Mega Farm ledger (MF_03, on the programme GLs 1050301 to 1050307) is
 * gated like the MAIIC ledger: its postings must carry an account in the
 * master (MF_01) and, where the pack carries the programme's balance history
 * (MF_04) or loan book (MF_05), they tie to it (audit, 14 October 2026: the
 * gates skipped the programme GLs).
 *""")
edit(p, r"""        $master = $this->mergedMaster($parsed);
        $ledger = $this->mergedLedger($parsed, $dateFormat);

        return [
            'accounts_in_master' => $this->accountsInMaster($parsed, $master),
            'balance_history_ties' => $this->balanceHistoryTies($parsed, $ledger, $dateFormat),
            'month_end_rows' => $this->monthEndRows($parsed, $master, $ledger, $dateFormat),
        ];
    }""", r"""        $master = $this->mergedMaster($parsed);
        // the earlier landed Mega Farm ledger (about 300,000 rows) is read only when the
        // pack carries the programme's balance history or loan book, which tie to it
        $withMegaFarm = $this->carries($parsed, ['MF_04', 'MF_05']);
        $ledger = $this->mergedLedger($parsed, $dateFormat, $withMegaFarm);

        return [
            'accounts_in_master' => $this->accountsInMaster($parsed, $master),
            'balance_history_ties' => $this->balanceHistoryTies($parsed, $ledger, $dateFormat),
            'month_end_rows' => $this->monthEndRows($parsed, $master, $ledger, $dateFormat),
            'fee_gl' => $this->feeGl($parsed, $dateFormat),
        ];
    }

    /** Whether the pack carries a file of one of the queries. */
    private function carries(array $parsed, array $queryIds): bool
    {
        foreach ($parsed as $p) {
            if (in_array($p['query']->query_id, $queryIds, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The fee GL gate at the door (spec 6.4): each month the pack's GL_04a
     * touches, the fee GL movement (the pack's legs over the earlier landed
     * ones, by voucher) against the trial balance. FAIL on a difference of a
     * kwacha or more, as GlFeeBuildService::gate measures it.
     */
    private function feeGl(array $parsed, string $dateFormat): array
    {
        $packLegs = [];
        foreach ($parsed as $p) {
            if (! in_array($p['query']->query_id, GlFeeBuildService::GL_FEE_LEGS, true)) {
                continue;
            }
            foreach ($p['rows'] as $i => $r) {
                $date = $this->landing->parseDate(trim((string) ($r['TRANSACTION_DATE'] ?? '')), $dateFormat);
                $leg = GlFeeBuildService::leg(['source_key' => trim((string) ($r['CUMVOUCH_DET_ID'] ?? '')) ?: $p['file'] . '#' . ($i + 1), 'row_date' => $date,
                    'account' => trim((string) ($r['NEW_AC_NUMBER'] ?? '')) ?: null, 'payload' => $r]);
                if (GlFeeBuildService::isGateLeg($leg)) {
                    $packLegs[$leg['voucher']] = $leg;
                }
            }
        }
        if ($packLegs === []) {
            return ['level' => self::LEVEL_ERROR, 'result' => 'SKIPPED', 'checked' => 0, 'detail' => 'no fee GL postings (GL_04a) in the pack', 'failures' => []];
        }
        $months = [];
        foreach ($packLegs as $leg) {
            $months[substr($leg['date'], 0, 7)] = true;
        }
        $legs = [];
        foreach ($this->zone->family(GlFeeBuildService::GL_FEE_LEGS) as $r) {
            $leg = GlFeeBuildService::leg($r);
            if (GlFeeBuildService::isGateLeg($leg) && isset($months[substr($leg['date'], 0, 7)])) {
                $legs[$leg['voucher']] = $leg;
            }
        }
        $legs = array_values(array_replace($legs, $packLegs));
        $gate = app(GlFeeBuildService::class)->gate($legs);
        $failures = [];
        foreach ($gate['months'] as $m) {
            if ($m['status'] === 'DIFFERENCE' && count($failures) < self::LISTED) {
                $failures[] = sprintf('fee GL %s, %s: GL_04a %s against the trial balance %s, difference %s', $m['gl'], $m['month'], number_format($m['gl_04a'], 2), number_format((float) $m['trial_balance'], 2), number_format((float) $m['difference'], 2));
            }
        }
        $noTb = array_values(array_unique(array_map(fn ($m) => $m['month'], array_filter($gate['months'], fn ($m) => $m['status'] === 'NO_TB'))));
        sort($noTb);

        return ['level' => self::LEVEL_ERROR, 'result' => $gate['differ'] > 0 ? 'FAIL' : ($gate['agree'] > 0 ? 'PASS' : 'SKIPPED'), 'checked' => count($gate['months']),
            'agree' => $gate['agree'], 'differ' => $gate['differ'], 'no_tb' => $gate['no_tb'], 'months_without_tb' => $noTb,
            'detail' => "{$gate['agree']} fee GL month(s) agree with the trial balance, {$gate['differ']} differ" . ($noTb !== [] ? ', ' . count($noTb) . ' month(s) without a trial balance yet (' . implode(', ', array_slice($noTb, 0, 6)) . (count($noTb) > 6 ? ', ...' : '') . '): checked again by the GL fee build' : ''),
            'failures' => $failures];
    }""")
edit(p, r"""    private function mergedLedger(array $parsed, string $dateFormat): array
    {
        // keyed on the source key alone, as the reader's family is: the monthly
        // ledger query re-pulls keys the history query already landed
        $byKey = [];
        foreach ($this->zone->ledgerByAccount() as $account => $rows) {""", r"""    private function mergedLedger(array $parsed, string $dateFormat, bool $withMegaFarm = false): array
    {
        // keyed on the source key alone, as the reader's family is: the monthly
        // ledger query re-pulls keys the history query already landed
        $byKey = [];
        foreach ($this->zone->ledgerByAccount(null, null, $withMegaFarm) as $account => $rows) {""")
edit(p, r"""    /** A live posting to one of the loan GLs with an account on it. */
    private function isLoanPosting(array $r): bool
    {
        if (($r['DELETE_FLAG'] ?? 'N') === 'Y' || trim((string) ($r['NEW_AC_NUMBER'] ?? '')) === '') {
            return false;
        }
        $gl = trim((string) ($r['AC_GLCODE'] ?? ($r['CB_GLCODE'] ?? '')));

        return $gl === '' || isset(LandingZoneReader::LOAN_GLS[$gl]);
    }""", r"""    /** A live posting to one of the loan GLs, the Mega Farm programme's included, with an account on it. */
    private function isLoanPosting(array $r): bool
    {
        if (($r['DELETE_FLAG'] ?? 'N') === 'Y' || trim((string) ($r['NEW_AC_NUMBER'] ?? '')) === '') {
            return false;
        }
        $gl = trim((string) ($r['AC_GLCODE'] ?? ($r['CB_GLCODE'] ?? '')));

        return $gl === '' || isset(LandingZoneReader::LOAN_GLS[$gl]) || in_array($gl, \App\Services\MegaFarm\MegaFarmEclService::GLS, true);
    }""")
edit(p, r"""        $inScope = array_keys(array_filter($master, fn ($m) => isset(LandingZoneReader::LOAN_GLS[trim((string) ($m['GLCODE'] ?? ''))])));""",
r"""        // the programme's accounts are in scope where the pack carries its loan book (MF_05)
        $megaFarm = $this->carries($parsed, ['MF_05']);
        $inScope = array_keys(array_filter($master, fn ($m) => isset(LandingZoneReader::LOAN_GLS[trim((string) ($m['GLCODE'] ?? ''))])
            || ($megaFarm && in_array(trim((string) ($m['GLCODE'] ?? '')), \App\Services\MegaFarm\MegaFarmEclService::GLS, true))));""")
