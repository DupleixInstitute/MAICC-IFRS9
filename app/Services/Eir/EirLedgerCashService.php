<?php

namespace App\Services\Eir;

use App\Services\AuditLoggerService;
use App\Services\Ebanker\LandingZoneReader;
use App\Services\Ebanker\LoanBookBuildService;
use Illuminate\Support\Facades\DB;

/**
 * The actual cash of every loan, from the E-Banker ledger already landed
 * (the P1 posting extract), for the EIR revenue engine.
 *
 * The engine takes a month's cash from eir_actual_transactions and falls back
 * to the contractual schedule only for a month no actual feed covers, labelled
 * DERIVED. MAIIC's transactions file (Extract B) was never delivered, so every
 * month fell back to the schedule: a loan repaid early kept "receiving" its
 * scheduled instalments and kept accruing EIR interest on a balance the
 * ledger shows repaid (system audit of 13 October 2026). The ledger holds the
 * same receipts, posting by posting; this service copies the cash movements
 * into the engine's table under their own source, so an imported Extract B
 * and the ledger never mix:
 *
 *   receipts (305, 306, 343, 900, 901)  Principal+Interest, the amount received
 *   write-off (300)                     Write-off
 *   settlement in kind (401)            Settlement in kind
 *   disbursement (301, 400)             Disbursement (cash advanced, never netted)
 *   interest charged (303, 120)         not cash: not copied
 *   any other type                      Other, which the engine reports as unclassified
 *
 * Each posting is copied once (its voucher id); re-running adds only new ones.
 *
 * A reversal (306, 343, 901) is kept as its own row and paired to the posting
 * it reverses (spec 7.1; system audit M15): on the same account, the opposite
 * amount to the cent, the nearest posting dated on or before it that is not a
 * reversal and not already paired (on the same date, the lower voucher). The
 * pair is recorded on both rows (reverses_external_id, reversal_status) with
 * the days between them; no fixed window is imposed, so a long gap is shown,
 * not hidden. A reversal with no such posting is UNPAIRED and named. The
 * month's cash is unchanged: the reversal still nets it.
 */
class EirLedgerCashService
{
    public const SOURCE = 'EBANKER_LEDGER';

    /** The reversal transaction types (spec 3.3, 7.1). */
    public const TYPE_REVERSAL = ['306', '343', '901'];

    public function __construct(private LandingZoneReader $zone)
    {
    }

    /** @return array{postings:int,loaded:int,accounts:int,by_type:array<string,int>,last_date:?string} */
    public function load(?string $toDate = null, ?int $userId = null): array
    {
        $toDate ??= $this->zone->lastLedgerDate();
        // id => the type it was loaded as, so a write-off loaded as a repayment before the classifier existed is corrected
        $existing = DB::table('eir_actual_transactions')->where('source_system', self::SOURCE)->pluck('transaction_type', 'external_transaction_id')->all();
        $writeOffs = new \App\Services\Ebanker\WriteOffClassifier($this->zone);
        $reclassified = 0;
        $customers = DB::table('loan_books')->whereNotNull('customer_id')->orderByDesc('reporting_period')->pluck('customer_id', 'contract_id')->all();
        $batch = [];
        $byType = [];
        $postings = 0;
        $accounts = 0;
        foreach ($this->zone->ledgerByAccount($toDate) as $account => $rows) {
            $accounts++;
            $contract = ltrim((string) $account, '0');
            foreach ($rows as $r) {
                $p = $r['payload'];
                $type = (string) ($p['TRANTYPE'] ?? '');
                if (in_array($type, LoanBookBuildService::TYPE_INTEREST, true)) {
                    continue;
                }
                $postings++;
                $id = (string) ($p['CUMVOUCH_DET_ID'] ?? $r['source_key']);
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
                    in_array($type, LoanBookBuildService::TYPE_RECEIPT, true) => 'Principal+Interest',
                    in_array($type, LoanBookBuildService::TYPE_WRITEOFF, true) => 'Write-off',
                    in_array($type, LoanBookBuildService::TYPE_SETTLEMENT_IN_KIND, true) => 'Settlement in kind',
                    in_array($type, LoanBookBuildService::TYPE_DISBURSEMENT, true) => 'Disbursement',
                    default => 'Other',
                };
                // a credit to the loan account is positive in the ledger: cash in, or the balance written down;
                // a receipt reversal (a debit on a receipt type) comes through negative and reduces the month's cash
                $total = in_array($kind, ['Write-off', 'Settlement in kind', 'Disbursement'], true) ? abs($amount) : $amount;
                $byType[$kind] = ($byType[$kind] ?? 0) + 1;
                $batch[] = ['contract_id' => $contract, 'customer_id' => $customers[$contract] ?? null, 'sub_account_no' => (string) ($p['SUB_ACCOUNT_NO'] ?? '') ?: null,
                    'transaction_date' => $r['row_date'], 'transaction_type' => $kind,
                    'principal_component' => 0, 'interest_component' => 0, 'fee_component' => 0, 'total_amount' => round($total, 2),
                    'balance_after_transaction' => null, 'source_system' => self::SOURCE, 'source_reference' => 'TRANTYPE ' . $type . ' ' . mb_substr((string) ($p['PARTICULARS'] ?? ''), 0, 120),
                    'external_transaction_id' => $id, 'row_note' => null, 'created_at' => now(), 'updated_at' => now()];
                $existing[$id] = $kind;
            }
        }
        foreach (array_chunk($batch, 500) as $chunk) {
            DB::table('eir_actual_transactions')->insert($chunk);
        }
        $result = ['postings' => $postings, 'loaded' => count($batch), 'reclassified_as_write_off' => $reclassified, 'accounts' => $accounts, 'by_type' => $byType, 'last_date' => $toDate,
            'reversals' => $this->pairReversals()];
        AuditLoggerService::log('EIR Ledger Cash Loaded', 'eir_actual_transactions', null, ['rows_affected' => count($batch), 'new_values' => $result, 'meta' => ['user' => $userId]]);

        return $result;
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
}
