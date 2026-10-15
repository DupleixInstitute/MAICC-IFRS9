from ed import edit
p='app/Services/Eir/EirLedgerCashService.php'
edit(p, r""" * Each posting is copied once (its voucher id); re-running adds only new ones.
 */""", r""" * Each posting is copied once (its voucher id); re-running adds only new ones.
 *
 * A reversal (306, 343, 901) is kept as its own row and paired to the posting
 * it reverses (spec 7.1; system audit M15): on the same account, the opposite
 * amount to the cent, the nearest posting dated on or before it that is not a
 * reversal and not already paired (on the same date, the lower voucher). The
 * pair is recorded on both rows (reverses_external_id, reversal_status) with
 * the days between them; no fixed window is imposed, so a long gap is shown,
 * not hidden. A reversal with no such posting is UNPAIRED and named. The
 * month's cash is unchanged: the reversal still nets it.
 */""")
edit(p, r"""    public const SOURCE = 'EBANKER_LEDGER';
""", r"""    public const SOURCE = 'EBANKER_LEDGER';

    /** The reversal transaction types (spec 3.3, 7.1). */
    public const TYPE_REVERSAL = ['306', '343', '901'];
""")
edit(p, r"""        $result = ['postings' => $postings, 'loaded' => count($batch), 'reclassified_as_write_off' => $reclassified, 'accounts' => $accounts, 'by_type' => $byType, 'last_date' => $toDate];""",
r"""        $result = ['postings' => $postings, 'loaded' => count($batch), 'reclassified_as_write_off' => $reclassified, 'accounts' => $accounts, 'by_type' => $byType, 'last_date' => $toDate,
            'reversals' => $this->pairReversals()];""")
edit(p, r"""        return $result;
    }
}""", r"""        return $result;
    }

    /**
     * Pair each unpaired reversal of the ledger cash with the posting it
     * reverses (see the class note).
     *
     * @return array{reversals:int, paired:int, unpaired:int, unpaired_ids:list<string>}
     */
    public function pairReversals(): array
    {
        $schema = DB::getSchemaBuilder();
        if (! $schema->hasColumn('eir_actual_transactions', 'reverses_external_id')) {
            return ['reversals' => 0, 'paired' => 0, 'unpaired' => 0, 'unpaired_ids' => []];
        }
        $rows = DB::table('eir_actual_transactions')->where('source_system', self::SOURCE)
            ->orderBy('contract_id')->orderBy('transaction_date')->get(['id', 'contract_id', 'transaction_date', 'total_amount', 'source_reference', 'external_transaction_id', 'reverses_external_id', 'reversal_status']);
        $byContract = $rows->groupBy('contract_id');
        $paired = 0; $unpaired = []; $reversals = 0;
        DB::transaction(function () use ($byContract, &$paired, &$unpaired, &$reversals) {
            foreach ($byContract as $contract => $list) {
                $used = [];
                foreach ($list as $r) {
                    if ($r->reversal_status === 'REVERSED') {
                        $used[$r->external_transaction_id] = true;
                    }
                }
                foreach ($list as $r) {
                    if (! in_array(self::tranType($r->source_reference), self::TYPE_REVERSAL, true)) {
                        continue;
                    }
                    $reversals++;
                    if ($r->reversal_status === 'PAIRED') {
                        $paired++;
                        continue;
                    }
                    $amount = round((float) $r->total_amount, 2);
                    $best = null;
                    foreach ($list as $o) {
                        if ($o->id === $r->id || isset($used[$o->external_transaction_id]) || in_array(self::tranType($o->source_reference), self::TYPE_REVERSAL, true)) {
                            continue;
                        }
                        if (abs(round((float) $o->total_amount, 2) + $amount) >= 0.005 || abs($amount) < 0.005) {
                            continue;
                        }
                        $before = (string) $o->transaction_date < (string) $r->transaction_date
                            || ((string) $o->transaction_date === (string) $r->transaction_date && (int) $o->external_transaction_id < (int) $r->external_transaction_id);
                        if (! $before) {
                            continue;
                        }
                        if ($best === null || [(string) $o->transaction_date, (int) $o->external_transaction_id] > [(string) $best->transaction_date, (int) $best->external_transaction_id]) {
                            $best = $o;
                        }
                    }
                    if ($best === null) {
                        DB::table('eir_actual_transactions')->where('id', $r->id)->update(['reversal_status' => 'UNPAIRED',
                            'row_note' => 'Reversal with no posting of the opposite amount on or before it on this account (spec 7.1): for review', 'updated_at' => now()]);
                        $unpaired[] = (string) $r->external_transaction_id;
                        continue;
                    }
                    $days = (int) round((strtotime((string) $r->transaction_date) - strtotime((string) $best->transaction_date)) / 86400);
                    $used[$best->external_transaction_id] = true;
                    DB::table('eir_actual_transactions')->where('id', $r->id)->update(['reverses_external_id' => $best->external_transaction_id, 'reversal_status' => 'PAIRED',
                        'row_note' => "Reverses voucher {$best->external_transaction_id} of {$best->transaction_date} ({$days} days earlier)", 'updated_at' => now()]);
                    DB::table('eir_actual_transactions')->where('id', $best->id)->update(['reversal_status' => 'REVERSED', 'updated_at' => now()]);
                    $paired++;
                }
            }
        });

        return ['reversals' => $reversals, 'paired' => $paired, 'unpaired' => count($unpaired), 'unpaired_ids' => array_slice($unpaired, 0, 20)];
    }

    /** The E-Banker transaction type, from the source reference the load writes ("TRANTYPE 306 ..."). */
    private static function tranType(?string $reference): ?string
    {
        return preg_match('/^TRANTYPE (\S+)/', (string) $reference, $m) === 1 ? $m[1] : null;
    }
}""")
