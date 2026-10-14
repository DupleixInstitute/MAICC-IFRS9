<?php

namespace App\Services\Reports;

use App\Services\Eir\WriteOffRegisterService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The amounts written off that the reports carry (IFRS 7.35H, 35I and 35L;
 * spec v4 section 3.8). One source, two kinds of record, built 14 October 2026:
 *
 *  - the ledger: every credit to a loan account that the WriteOffClassifier
 *    marks as a write-off (type 300, a batch that debits GL 1064, an ACCOUNT
 *    CLOSURE in a TRF batch on a status-H account), read through
 *    WriteOffRegisterService::ledgerWriteOffs, so the reports, the loan-book
 *    build and the EIR all apply the same rule;
 *  - the governed register (loan_write_offs): an APPROVED write-off that is
 *    not on the loan account (one person proposed, another approved).
 *
 * A register entry that repeats a ledger write-off of the same contract,
 * date and amount is counted once (the ledger's) and named. Nothing else is
 * read: the loan book's contract_status carries no write-off marker.
 */
final class WriteOffSource
{
    /**
     * Write-offs dated after $after and on or before $to (Y-m-d).
     *
     * @return list<array{contract_id:string,customer_name:?string,date:string,amount:float,source:string,reference:string}>
     */
    public static function between(string $after, string $to): array
    {
        $out = [];
        $seen = [];
        foreach ((new WriteOffRegisterService())->ledgerWriteOffs($to) as $w) {
            if ($w['date'] <= $after || $w['date'] > $to) {
                continue;
            }
            $out[] = ['contract_id' => $w['contract_id'], 'customer_name' => $w['customer_name'], 'date' => $w['date'], 'amount' => (float) $w['amount'],
                'source' => 'ledger', 'reference' => str_contains($w['rule'], 'batch') ? $w['rule'] : trim($w['rule'] . '; batch ' . $w['batch'])];
            $seen[$w['contract_id'] . '|' . $w['date'] . '|' . number_format((float) $w['amount'], 2, '.', '')] = true;
        }
        if (Schema::hasTable('loan_write_offs')) {
            $register = DB::table('loan_write_offs')->where('status', 'APPROVED')
                ->where('write_off_date', '>', $after)->where('write_off_date', '<=', $to)->orderBy('write_off_date')->get();
            foreach ($register as $r) {
                $key = $r->contract_id . '|' . substr((string) $r->write_off_date, 0, 10) . '|' . number_format((float) $r->amount, 2, '.', '');
                if (isset($seen[$key])) {
                    continue; // the same write-off is already in the ledger: counted once
                }
                $out[] = ['contract_id' => (string) $r->contract_id, 'customer_name' => $r->customer_name, 'date' => substr((string) $r->write_off_date, 0, 10),
                    'amount' => (float) $r->amount, 'source' => 'register', 'reference' => 'write-off register #' . $r->id . ($r->gl_reference ? '; ' . $r->gl_reference : '')];
            }
        }
        usort($out, fn ($a, $b) => [$a['date'], $a['contract_id']] <=> [$b['date'], $b['contract_id']]);

        return $out;
    }

    /** The write-offs between two month-ends, summed per contract (cents). */
    public static function byContract(string $openingPeriod, string $closingPeriod): array
    {
        $by = [];
        foreach (self::between(self::monthEnd($openingPeriod), self::monthEnd($closingPeriod)) as $w) {
            $by[$w['contract_id']] = ($by[$w['contract_id']] ?? 0) + (int) round($w['amount'] * 100);
        }

        return $by;
    }

    public static function monthEnd(string $period): string
    {
        return \Carbon\CarbonImmutable::parse(substr($period, 0, 7) . '-01')->endOfMonth()->toDateString();
    }

    /**
     * IFRS 7.35L: the contractual amount outstanding on assets written off in
     * the period that are still subject to enforcement activity. The system
     * holds no enforcement status for a written-off loan (neither E-Banker's
     * write-off tables, which are empty, nor the register carry one), so the
     * figure is not recorded and the reports say so rather than show nil.
     */
    public static function enforcementDisclosure(): string
    {
        return 'Not recorded: the system holds no enforcement status for written-off loans (E-Banker\'s write-off tables are empty and the write-off register has no enforcement field), so the contractual amount still subject to enforcement activity (IFRS 7.35L) cannot be stated from the system.';
    }
}
