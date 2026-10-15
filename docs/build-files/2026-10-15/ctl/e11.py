from ed import edit
p='app/Services/Ebanker/ContractInputsBuildService.php'
edit(p, r"""        return ['rows' => count($rows), 'result' => $this->contracts->import($rows)];
    }

    /** @return array{rows:int,result:array} */
    public function referenceRates(?int $userId = null): array""", r"""        $result = $this->contracts->import($rows);
        $result['over_sanction'] = $this->flagOverSanction();

        return ['rows' => count($rows), 'result' => $result];
    }

    /**
     * Spec 7.3: an account whose drawn amount exceeds its sanction is flagged
     * for Credit. The excess is written to contract_eir.over_sanction_amount
     * (null when the drawn amount is within the sanction or either figure is
     * missing); nothing else changes, the EIR still runs on the money lent.
     *
     * @return array{flagged:int, contracts:list<string>}
     */
    public function flagOverSanction(): array
    {
        $schema = DB::getSchemaBuilder();
        if (! $schema->hasTable('contract_eir') || ! $schema->hasColumn('contract_eir', 'over_sanction_amount')) {
            return ['flagged' => 0, 'contracts' => []];
        }
        $flagged = [];
        DB::transaction(function () use (&$flagged) {
            DB::table('contract_eir')->whereNotNull('over_sanction_amount')->update(['over_sanction_amount' => null]);
            foreach (DB::table('contract_eir')->where('approved_amount', '>', 0)->where('drawn_amount', '>', 0)->whereColumn('drawn_amount', '>', 'approved_amount')->get(['id', 'contract_id', 'approved_amount', 'drawn_amount']) as $c) {
                $excess = round((float) $c->drawn_amount - (float) $c->approved_amount, 2);
                if ($excess >= 0.01) {
                    DB::table('contract_eir')->where('id', $c->id)->update(['over_sanction_amount' => $excess]);
                    $flagged[] = (string) $c->contract_id;
                }
            }
        });

        return ['flagged' => count($flagged), 'contracts' => $flagged];
    }

    /**
     * The scheme settings (spec v3 section 6.3) from E-Banker's scheme master:
     * SM_01 (pack 3, every MAIIC and Mega Farm scheme) first, and the data
     * dictionary's DD_10 where SM_01 lacks the scheme or the field. The
     * schedule generator reads the scheme where the contract states nothing;
     * with the table empty, a partly drawn facility had no interest basis
     * (INTEREST_BASIS_MISSING) even though the scheme states it.
     *
     *   interest_calc_base     LOANINT_SANCWISE_BALWISE (S sanction, B balance:
     *                          E-Banker's "Loan Interest Cal. Base On")
     *   installment_based_on   INST_BASED_ON (S sanction, D disbursement:
     *                          "Installment Based On")
     *   interest_policy        INTEREST_POLICY, verbatim
     *   floating_flag          FLOATING_FLAG, verbatim
     *   effective_from         APPLICABLE_FROM_DATE
     *
     * The EMI calculation type and the default moratorium type are not
     * loaded: the scheme master holds them as codes (EMI_BASED_ON 0 or 2,
     * INSTALLMENT_TYPE) whose reading the vendor has not confirmed, so the
     * scheme says nothing about them rather than a guess. A scheme row with
     * no readable effective date is not loaded and is named.
     *
     * @return array{rows:int, upserted:int, from_dd10:int, refused:array<string,string>}
     */
    public function schemes(): array
    {
        $sm = [];
        foreach ($this->zone->family(['SM_01']) as $r) {
            $id = trim((string) ($r['payload']['SCHEME_MST_ID'] ?? ''));
            if ($id !== '') {
                $sm[$id] = $r['payload'];
            }
        }
        $dd = [];
        foreach ($this->zone->family(['DD_10']) as $r) {
            $id = trim((string) ($r['payload']['SCHEME_MST_ID'] ?? ''));
            if ($id !== '') {
                $dd[$id] = $r['payload'];
            }
        }
        $fields = ['interest_calc_base' => 'LOANINT_SANCWISE_BALWISE', 'installment_based_on' => 'INST_BASED_ON', 'interest_policy' => 'INTEREST_POLICY',
            'floating_flag' => 'FLOATING_FLAG', 'effective_from' => 'APPLICABLE_FROM_DATE', 'scheme_name' => 'SCHEME_NAME'];
        $upserted = 0; $fromDd = 0; $refused = [];
        $codes = array_unique(array_merge(array_keys($sm), array_keys($dd)));
        sort($codes);
        foreach ($codes as $code) {
            $row = []; $sources = [];
            foreach ($fields as $column => $source) {
                $value = trim((string) ($sm[$code][$source] ?? ''));
                $from = 'SM_01';
                if ($value === '') {
                    $value = trim((string) ($dd[$code][$source] ?? ''));
                    $from = 'DD_10';
                }
                if ($value !== '') {
                    $row[$column] = $value;
                    $sources[$from] = true;
                }
            }
            $effective = isset($row['effective_from']) ? $this->date($row['effective_from']) : null;
            if ($effective === null) {
                $refused[(string) $code] = 'no readable APPLICABLE_FROM_DATE in SM_01 or DD_10';
                continue;
            }
            $values = [
                'interest_calc_base' => isset($row['interest_calc_base']) ? strtoupper(substr($row['interest_calc_base'], 0, 1)) : null,
                'installment_based_on' => isset($row['installment_based_on']) ? mb_substr($row['installment_based_on'], 0, 20) : null,
                'interest_policy' => isset($row['interest_policy']) ? strtoupper(substr($row['interest_policy'], 0, 1)) : null,
                'floating_flag' => isset($row['floating_flag']) ? strtoupper(substr($row['floating_flag'], 0, 1)) : null,
                'scheme_name' => isset($row['scheme_name']) ? mb_substr($row['scheme_name'], 0, 120) : null,
                'source' => implode('+', array_keys($sources)),
                'updated_at' => now(),
            ];
            if (! isset($sm[$code]) || isset($sources['DD_10'])) {
                $fromDd++;
            }
            $exists = DB::table('schemes')->where('scheme_code', (string) $code)->whereDate('effective_from', $effective)->exists();
            if ($exists) {
                DB::table('schemes')->where('scheme_code', (string) $code)->whereDate('effective_from', $effective)->update($values);
            } else {
                DB::table('schemes')->insert($values + ['scheme_code' => (string) $code, 'effective_from' => $effective, 'created_at' => now()]);
            }
            $upserted++;
        }

        return ['rows' => count($codes), 'upserted' => $upserted, 'from_dd10' => $fromDd, 'refused' => $refused];
    }

    /** @return array{rows:int,result:array} */
    public function referenceRates(?int $userId = null): array""")
