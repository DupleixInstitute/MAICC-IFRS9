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
 */
class EirLedgerCashService
{
    public const SOURCE = 'EBANKER_LEDGER';

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
        $result = ['postings' => $postings, 'loaded' => count($batch), 'reclassified_as_write_off' => $reclassified, 'accounts' => $accounts, 'by_type' => $byType, 'last_date' => $toDate];
        AuditLoggerService::log('EIR Ledger Cash Loaded', 'eir_actual_transactions', null, ['rows_affected' => count($batch), 'new_values' => $result, 'meta' => ['user' => $userId]]);

        return $result;
    }
}
