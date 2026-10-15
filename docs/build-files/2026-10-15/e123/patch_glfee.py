p = 'app/Services/Ebanker/GlFeeBuildService.php'
s = open(p, encoding='utf-8').read()


def rep(a, b):
    global s
    assert a in s, a[:80]
    s = s.replace(a, b)


rep("""        $rows = []; $held = []; $assigned = ['lines' => 0, 'amount' => 0.0, 'by_rule' => []];
        foreach ($legs as $leg) {
            [$contract, $rule, $reason, $loans] = $this->assign($leg, $batches[$leg['batch']] ?? [], $names);
            if ($contract === null) {
                $held[] = $leg + ['reason' => $reason, 'loans' => $loans];
                continue;
            }""",
"""        $rows = []; $held = []; $assigned = ['lines' => 0, 'amount' => 0.0, 'by_rule' => []];
        // a held line a person has since tied to its loan is in contract_fees already and stays there
        $tiedByHand = DB::table('contract_fees')->where('source_system', self::SOURCE)->where('external_transaction_id', 'like', 'GL_04a:%')
            ->pluck('external_transaction_id')->flip()->all();
        $drawnOn = $this->drawdownsByDate();
        foreach ($legs as $leg) {
            [$contract, $rule, $reason, $loans] = $this->assign($leg, $batches[$leg['batch']] ?? [], $names);
            if ($contract === null) {
                if (isset($tiedByHand['GL_04a:' . $leg['voucher']])) {
                    continue;
                }
                $held[] = $leg + ['reason' => $reason, 'loans' => $loans] + $this->candidate($leg, $drawnOn[$leg['date']] ?? [], $names);
                continue;
            }""")

rep("""                    'reason' => mb_substr($h['reason'], 0, 500), 'loans_in_batch' => json_encode($h['loans']), 'created_at' => $now, 'updated_at' => $now,""",
"""                    'reason' => mb_substr($h['reason'], 0, 500), 'loans_in_batch' => json_encode($h['loans']),
                    'candidate_contract_id' => $h['candidate'], 'candidate_evidence' => $h['candidate_evidence'] === null ? null : mb_substr($h['candidate_evidence'], 0, 255),
                    'created_at' => $now, 'updated_at' => $now,""")

rep("""            'held' => ['lines' => count($held), 'amount' => round(array_sum(array_column($held, 'amount')), 2),""",
"""            'held' => ['lines' => count($held), 'amount' => round(array_sum(array_column($held, 'amount')), 2),
                'with_a_candidate' => count(array_filter($held, fn ($h) => $h['candidate'] !== null)),""")

rep("""    /** @return array{0:?string, 1:?string, 2:?string, 3:list<string>} the contract, the rule, the reason it is held, the loans in the batch */""",
"""    /**
     * Tie a held fee line to its loan: a person's act, with the reason, after
     * reading the batch and the narration (Fee Classification). The line moves
     * into contract_fees PENDING, so it still needs its classification and
     * review before it can affect an EIR; the precedence is applied again.
     */
    public function assignHeld(int $heldId, string $contractId, int $userId, string $reason): array
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 10) {
            throw new \\InvalidArgumentException('Say why the fee belongs to this loan (at least 10 characters).');
        }
        $held = DB::table('fee_gl_unassigned')->where('id', $heldId)->first();
        if ($held === null) {
            throw new \\InvalidArgumentException("No held fee line {$heldId}.");
        }
        $contractId = ltrim(trim($contractId), '0');
        if (! DB::table('contract_eir')->where('contract_id', $contractId)->exists() && ! DB::table('loan_books')->where('contract_id', $contractId)->exists()) {
            throw new \\InvalidArgumentException("Loan {$contractId} is not in the contract master or the loan book.");
        }
        $result = DB::transaction(function () use ($held, $contractId, $userId, $reason) {
            $r = $this->fees->import([[
                'contract_id' => $contractId, 'fee_type' => self::TYPE_BY_GL[$held->gl_code] ?? 'other', 'amount' => (float) $held->amount,
                'transaction_date' => $held->transaction_date, 'cashflow_direction' => 'RECEIVED', 'currency' => 'MWK',
                'description' => mb_substr(trim(($held->narration ?: 'Fee') . ' (GL ' . $held->gl_code . ', tied by hand: ' . $reason . ')'), 0, 199),
                'source_system' => self::SOURCE, 'source_reference' => mb_substr('batch ' . $held->batch, 0, 199),
                'external_transaction_id' => $held->external_transaction_id, 'gl_account_ref' => $held->gl_code,
            ]]);
            DB::table('fee_gl_unassigned')->where('id', $held->id)->delete();
            AuditLoggerService::log('GL Fee Tied To Loan', 'contract_fees', null, ['new_values' => ['contract_id' => $contractId, 'external_transaction_id' => $held->external_transaction_id,
                'amount' => (float) $held->amount, 'reason' => $reason, 'candidate_was' => $held->candidate_contract_id], 'meta' => ['user' => $userId]]);

            return $r;
        });

        return $result + ['precedence' => $this->precedence->apply($userId)];
    }

    /**
     * The loan a held line points to, for the person who ties it: a loan whose
     * account name (in full) the narration carries and which drew money on the
     * fee's date. Exactly one such loan is a candidate; it is shown, not used.
     *
     * @param  list<string>  $drawnThatDay  loan accounts with a type-301 posting on the fee's date
     * @return array{candidate:?string, candidate_evidence:?string}
     */
    private function candidate(array $leg, array $drawnThatDay, array $names): array
    {
        $narration = $this->normalise((string) $leg['narration']);
        $matches = array_values(array_filter(array_unique($drawnThatDay), fn ($a) => ($names[$a] ?? '') !== '' && str_contains($narration, $this->normalise($names[$a]))));
        if (count($matches) !== 1) {
            return ['candidate' => null, 'candidate_evidence' => null];
        }

        return ['candidate' => ltrim($matches[0], '0'), 'candidate_evidence' => sprintf('the narration carries the account name "%s" and the loan drew on %s', $names[$matches[0]], $leg['date'])];
    }

    /** @return array<string, list<string>> loan accounts with a type-301 posting, by date */
    private function drawdownsByDate(): array
    {
        $out = [];
        foreach ($this->zone->ledgerByAccount() as $account => $posts) {
            foreach ($posts as $p) {
                if ((string) ($p['payload']['TRANTYPE'] ?? '') === '301' && $p['row_date'] !== null) {
                    $out[substr((string) $p['row_date'], 0, 10)][] = (string) $account;
                }
            }
        }

        return $out;
    }

    /** @return array{0:?string, 1:?string, 2:?string, 3:list<string>} the contract, the rule, the reason it is held, the loans in the batch */""")

open(p, 'w', encoding='utf-8', newline='\n').write(s)
print('ok')
