import io, os
os.chdir(r'C:\xampp\htdocs\MAICC-IFRS9')

def sub(path, pairs):
    s = io.open(path, encoding='utf-8').read()
    for old, new in pairs:
        assert old in s, (path, old[:70])
        s = s.replace(old, new, 1)
    io.open(path, 'w', encoding='utf-8', newline='\n').write(s)

sub('app/Services/Ebanker/LoanBookBuildService.php', [
("""    public const TYPE_RECEIPT = ['305', '306', '343', '900', '901', '401', '300'];""",
"""    public const TYPE_RECEIPT = ['305', '306', '343', '900', '901'];
    /** 300: a write-off. It reduces the gross through the allowance; it is not cash received (spec 3.3; audit H1). */
    public const TYPE_WRITEOFF = ['300'];
    /** 401: the Nascomex loan settled by redemption of its preference shares, a settlement in kind, not cash (spec 3.3 and O13; audit H1). */
    public const TYPE_SETTLEMENT_IN_KIND = ['401'];"""),
("""            $sum = ['all' => 0.0, 'disb' => 0.0, 'int' => 0.0, 'rec' => 0.0, 'other' => 0.0, 'n' => 0, 'first' => null, 'last' => null, 'load' => null];""",
"""            $sum = ['all' => 0.0, 'disb' => 0.0, 'int' => 0.0, 'rec' => 0.0, 'woff' => 0.0, 'kind' => 0.0, 'other' => 0.0, 'n' => 0, 'first' => null, 'last' => null, 'load' => null];"""),
("""                } elseif (in_array($type, self::TYPE_RECEIPT, true)) {
                    $sum['rec'] += $amt;
                } else {
                    $sum['other'] += $amt;
                }
            }
            $carrying = round(-$sum['all'], 2);""",
"""                } elseif (in_array($type, self::TYPE_RECEIPT, true)) {
                    $sum['rec'] += $amt;
                } elseif (in_array($type, self::TYPE_WRITEOFF, true)) {
                    $sum['woff'] += $amt;     // in the balance, never in repayments
                } elseif (in_array($type, self::TYPE_SETTLEMENT_IN_KIND, true)) {
                    $sum['kind'] += $amt;     // in the balance, never in repayments
                } else {
                    $sum['other'] += $amt;
                }
            }
            $carrying = round(-$sum['all'], 2);"""),
("""                    'ledger' => ['disbursed' => $disbursed, 'interest' => $interest, 'receipts' => $receipts, 'other' => round($sum['other'], 2)],""",
"""                    'ledger' => ['disbursed' => $disbursed, 'interest' => $interest, 'receipts' => $receipts, 'written_off' => round($sum['woff'], 2), 'settled_in_kind' => round($sum['kind'], 2), 'other' => round($sum['other'], 2)],"""),
])

sub('app/Services/Ebanker/ContractInputsBuildService.php', [
("""                } elseif (in_array($type, LoanBookBuildService::TYPE_RECEIPT, true)) {
                    $kind = 'Principal+Interest'; $total = $amt;      // a credit: cash collected; a reversal arrives negative
                } else {
                    continue;
                }""",
"""                } elseif (in_array($type, LoanBookBuildService::TYPE_RECEIPT, true)) {
                    $kind = 'Principal+Interest'; $total = $amt;      // a credit: cash collected; a reversal arrives negative
                } elseif (in_array($type, LoanBookBuildService::TYPE_WRITEOFF, true)) {
                    $kind = 'Write-off'; $total = $amt;               // derecognition against the allowance, not cash (audit H1)
                } elseif (in_array($type, LoanBookBuildService::TYPE_SETTLEMENT_IN_KIND, true)) {
                    $kind = 'Settlement in kind'; $total = $amt;      // the Nascomex share redemption: settles the loan, not cash (audit H1)
                } else {
                    continue;
                }"""),
])

sub('app/Services/Eir/EirRevenueService.php', [
("""    private const COLLECTION_TYPES = ['Interest', 'Principal+Interest', 'Fee'];""",
"""    private const COLLECTION_TYPES = ['Principal+Interest', 'Fee']; // 'Interest' removed: an interest charge is not cash collected (spec 7.4, audit M15)

    /** Derecognition without cash: a write-off (against the allowance) or a settlement in kind. Reduces the gross; never cash received (audit H1). */
    private const DERECOGNITION_TYPES = ['Write-off', 'Settlement in kind'];"""),
("""        $collected = 0.0;
        $unclassified = 0.0;
        $rows = DB::table('eir_actual_transactions')->where('contract_id', $contractId)
            ->whereBetween('transaction_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('transaction_type, SUM(total_amount) as amount')->groupBy('transaction_type')->get();

        foreach ($rows as $row) {
            if (in_array($row->transaction_type, self::COLLECTION_TYPES, true)) {
                $collected += (float) $row->amount;
            } elseif (! in_array($row->transaction_type, self::ADVANCE_TYPES, true)) {
                $unclassified += (float) $row->amount;
            }
        }

        return ['amount' => $collected, 'source' => 'IMPORTED', 'unclassified' => $unclassified];""",
"""        $collected = 0.0;
        $unclassified = 0.0;
        $derecognised = 0.0;
        $rows = DB::table('eir_actual_transactions')->where('contract_id', $contractId)
            ->whereBetween('transaction_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('transaction_type, SUM(total_amount) as amount')->groupBy('transaction_type')->get();

        foreach ($rows as $row) {
            if (in_array($row->transaction_type, self::COLLECTION_TYPES, true)) {
                $collected += (float) $row->amount;
            } elseif (in_array($row->transaction_type, self::DERECOGNITION_TYPES, true)) {
                $derecognised += (float) $row->amount;
            } elseif (! in_array($row->transaction_type, self::ADVANCE_TYPES, true)) {
                $unclassified += (float) $row->amount;
            }
        }

        return ['amount' => $collected, 'source' => 'IMPORTED', 'unclassified' => $unclassified, 'derecognised' => $derecognised];"""),
("""            return ['amount' => $this->scheduledCash($contractId, $start, $end), 'source' => 'DERIVED', 'unclassified' => 0.0];""",
"""            return ['amount' => $this->scheduledCash($contractId, $start, $end), 'source' => 'DERIVED', 'unclassified' => 0.0, 'derecognised' => 0.0];"""),
("""                $cash = $this->cashReceived($contractId, $period);
                $closing = max(0.0, $opening + $interest + $unwind - $cash['amount']);
""",
"""                $cash = $this->cashReceived($contractId, $period);
                // a write-off or a settlement in kind leaves the gross without being cash
                $closing = max(0.0, $opening + $interest + $unwind - $cash['amount'] - $cash['derecognised']);
"""),
("""                    'modification_gain_loss' => 0,""",
"""                    'modification_gain_loss' => round(-$cash['derecognised'], 2), // derecognition without cash, disclosed on the row"""),
])
print('ok')
