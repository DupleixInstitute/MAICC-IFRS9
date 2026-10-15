R = 'C:/xampp/htdocs/MAICC-IFRS9/app/'

def patch(p, pairs):
    s = open(R + p, encoding='utf-8').read()
    for a, b in pairs:
        assert a in s, (p, a[:80])
        s = s.replace(a, b, 1)
    open(R + p, 'w', encoding='utf-8').write(s)   # one write per file

# 1. the loan book build, method B
patch('Services/Ebanker/LoanBookBuildService.php', [
("""        $statuses = $this->zone->statusHistoryByAccount();
        $accounts = array_unique(array_merge(array_keys($ledger), array_keys($runs)));""",
 """        $statuses = $this->zone->statusHistoryByAccount();
        // a write-off posted as a repayment (spec 3.8) is a write-off here too
        $writeOffs = new WriteOffClassifier($this->zone);
        $accounts = array_unique(array_merge(array_keys($ledger), array_keys($runs)));"""),
("""                if (in_array($type, self::TYPE_DISBURSEMENT, true)) {
                    $sum['disb'] += $amt;
                } elseif (in_array($type, self::TYPE_INTEREST, true)) {
                    $sum['int'] += $amt;
                } elseif (in_array($type, self::TYPE_RECEIPT, true)) {
                    $sum['rec'] += $amt;
                } elseif (in_array($type, self::TYPE_WRITEOFF, true)) {
                    $sum['woff'] += $amt;     // in the balance, never in repayments""",
 """                if ($writeOffs->isWriteOff($post['payload'], $post['row_date'], $account)) {
                    $sum['woff'] += $amt;     // in the balance, never in repayments (type 300, a 1064 batch, an H closure)
                } elseif (in_array($type, self::TYPE_DISBURSEMENT, true)) {
                    $sum['disb'] += $amt;
                } elseif (in_array($type, self::TYPE_INTEREST, true)) {
                    $sum['int'] += $amt;
                } elseif (in_array($type, self::TYPE_RECEIPT, true)) {
                    $sum['rec'] += $amt;"""),
])

# 2. the contract inputs (actual transactions)
s = open(R + 'Services/Ebanker/ContractInputsBuildService.php', encoding='utf-8').read()
a = """                if (in_array($type, LoanBookBuildService::TYPE_DISBURSEMENT, true)) {
                    $kind = 'Disbursement'; $total = -$amt;           // a debit to the loan: cash advanced
                } elseif (in_array($type, LoanBookBuildService::TYPE_RECEIPT, true)) {
                    $kind = 'Principal+Interest'; $total = $amt;      // a credit: cash collected; a reversal arrives negative
                } elseif (in_array($type, LoanBookBuildService::TYPE_WRITEOFF, true)) {
                    $kind = 'Write-off'; $total = $amt;               // derecognition against the allowance, not cash (audit H1)"""
b = """                if ($this->writeOffs()->isWriteOff($x, $p['row_date'], $account)) {
                    $kind = 'Write-off'; $total = $amt;               // derecognition, not cash (audit H1; spec 3.8: type 300, a 1064 batch, an H closure)
                } elseif (in_array($type, LoanBookBuildService::TYPE_DISBURSEMENT, true)) {
                    $kind = 'Disbursement'; $total = -$amt;           // a debit to the loan: cash advanced
                } elseif (in_array($type, LoanBookBuildService::TYPE_RECEIPT, true)) {
                    $kind = 'Principal+Interest'; $total = $amt;      // a credit: cash collected; a reversal arrives negative"""
assert a in s
s = s.replace(a, b, 1)
a = """    public function __construct("""
b = """    private ?WriteOffClassifier $writeOffClassifier = null;

    private function writeOffs(): WriteOffClassifier
    {
        return $this->writeOffClassifier ??= new WriteOffClassifier($this->zone);
    }

    public function __construct("""
assert a in s
s = s.replace(a, b, 1)
open(R + 'Services/Ebanker/ContractInputsBuildService.php', 'w', encoding='utf-8').write(s)

# 3. the EIR's ledger cash: classify, and correct a row loaded before the classifier existed
s = open(R + 'Services/Eir/EirLedgerCashService.php', encoding='utf-8').read()
a = """        $existing = array_flip(DB::table('eir_actual_transactions')->where('source_system', self::SOURCE)->pluck('external_transaction_id')->all());"""
b = """        // id => the type it was loaded as, so a write-off loaded as a repayment before the classifier existed is corrected
        $existing = DB::table('eir_actual_transactions')->where('source_system', self::SOURCE)->pluck('transaction_type', 'external_transaction_id')->all();
        $writeOffs = new \\App\\Services\\Ebanker\\WriteOffClassifier($this->zone);
        $reclassified = 0;"""
assert a in s
s = s.replace(a, b, 1)
a = """                $id = (string) ($p['CUMVOUCH_DET_ID'] ?? $r['source_key']);
                if (isset($existing[$id])) {
                    continue;
                }
                $amount = (float) str_replace(',', '', (string) ($p['TRANSAMT'] ?? 0));
                $kind = match (true) {
                    in_array($type, LoanBookBuildService::TYPE_RECEIPT, true) => 'Principal+Interest',"""
b = """                $id = (string) ($p['CUMVOUCH_DET_ID'] ?? $r['source_key']);
                $amount = (float) str_replace(',', '', (string) ($p['TRANSAMT'] ?? 0));
                $writeOffRule = $writeOffs->rule($p, $r['row_date'], $account);
                if (isset($existing[$id])) {
                    if ($writeOffRule !== null && $existing[$id] !== 'Write-off') {
                        DB::table('eir_actual_transactions')->where('source_system', self::SOURCE)->where('external_transaction_id', $id)
                            ->update(['transaction_type' => 'Write-off', 'total_amount' => round(abs($amount), 2), 'row_note' => 'Reclassified as a write-off: ' . $writeOffRule . ' (spec v4 3.8)', 'updated_at' => now()]);
                        $existing[$id] = 'Write-off';
                        $reclassified++;
                    }
                    continue;
                }
                $kind = match (true) {
                    $writeOffRule !== null => 'Write-off',
                    in_array($type, LoanBookBuildService::TYPE_RECEIPT, true) => 'Principal+Interest',"""
assert a in s
s = s.replace(a, b, 1)
a = """                $existing[$id] = true;"""
b = """                $existing[$id] = $kind;"""
assert a in s
s = s.replace(a, b, 1)
a = """        $result = ['postings' => $postings, 'loaded' => count($batch), 'accounts' => $accounts, 'by_type' => $byType, 'last_date' => $toDate];"""
b = """        $result = ['postings' => $postings, 'loaded' => count($batch), 'reclassified_as_write_off' => $reclassified, 'accounts' => $accounts, 'by_type' => $byType, 'last_date' => $toDate];"""
assert a in s
s = s.replace(a, b, 1)
open(R + 'Services/Eir/EirLedgerCashService.php', 'w', encoding='utf-8').write(s)
print('ok')
