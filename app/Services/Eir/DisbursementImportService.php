<?php

namespace App\Services\Eir;

use App\Models\ContractDisbursement;
use App\Services\AuditLoggerService;
use App\Support\ContractId;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Imports the per-drawdown extract (spec v3 section 7.6): one row for every
 * time money left MAIIC on a facility.
 *
 * The dates decide which month a tranche falls in, and a month with a tranche
 * in it cannot be reconciled to E-Banker's interest posting without them
 * (section 7.7), so the importer is built around refusals rather than repairs,
 * exactly as the reference-rate importer is (decision D19):
 *
 *  - a date that is not written year first (yyyy-mm-dd) rejects the whole file,
 *    naming the column and the first bad row. A slash date is never read either
 *    way round, because 03/04/2026 is a valid date in both orders and only one
 *    of them is true;
 *  - an amount that is not a positive number rejects the file. A drawdown is
 *    money out of MAIIC; a reversal is a correction to make at source, not a
 *    negative row to load;
 *  - a row with no facility identifier rejects the file, because a drawdown
 *    that belongs to no loan cannot be attributed to one later.
 *
 * A row already stored under the same source system and transaction id is left
 * alone and counted, so the same extract loaded twice adds nothing. Where the
 * file carries no transaction id of its own, the importer builds a fingerprint
 * from the facility, the date, the amount, the tranche and the reference. Two
 * drawdowns of the same amount on the same day with nothing to tell them apart
 * are therefore treated as one row loaded twice, which is said out loud in the
 * notes so that a genuine pair can be given references and loaded again.
 */
class DisbursementImportService
{
    /** Persisted lineage: names the file an auditor will ask for. */
    public const SOURCE = ContractDisbursement::SOURCE;

    /**
     * @param  list<array<string,mixed>>  $rows  mapped rows, in file order
     * @param  int|null  $importId  the imports row when run through the intake screen
     * @param  int|null  $userId  who loaded the file; recorded as created_by
     * @return array{
     *   source_rows:int, loaded_rows:int, duplicate_source_rows:int, already_stored:int,
     *   contracts:int, total_amount:float, first_date:?string, last_date:?string,
     *   notes:list<string>, skipped:array<string,string>, by_contract:array<string,array>
     * }
     *
     * @throws RuntimeException when the file is refused as a whole
     */
    public function import(array $rows, ?int $importId = null, ?int $userId = null): array
    {
        [$parsed, $duplicates, $notes] = $this->validate($rows);

        $loaded = 0;
        $alreadyStored = 0;
        $skipped = [];
        $now = now();

        DB::transaction(function () use ($parsed, $importId, $userId, $now, &$loaded, &$alreadyStored, &$skipped) {
            foreach ($parsed as $row) {
                $existing = DB::table('contract_disbursements')
                    ->where('source_system', self::SOURCE)
                    ->where('external_transaction_id', $row['external_transaction_id'])
                    ->first(['id', 'amount']);

                if ($existing !== null) {
                    if (abs((float) $existing->amount - $row['amount']) < 0.005) {
                        $alreadyStored++;
                    } else {
                        $skipped[$row['label']] = sprintf(
                            'drawdown %s on %s is already stored as %s and the file says %s; the stored row is not overwritten. Correct it at source and reload.',
                            $row['external_transaction_id'], $row['disbursement_date'],
                            number_format((float) $existing->amount, 2), number_format($row['amount'], 2)
                        );
                    }
                    continue;
                }

                DB::table('contract_disbursements')->insert([
                    'contract_id' => $row['contract_id'],
                    'sub_account_no' => $row['sub_account_no'],
                    'tranche_no' => $row['tranche_no'],
                    'disbursement_date' => $row['disbursement_date'],
                    'amount' => $row['amount'],
                    'reference' => $row['reference'],
                    'source_system' => self::SOURCE,
                    'source_reference' => $row['label'],
                    'external_transaction_id' => $row['external_transaction_id'],
                    'import_id' => $importId,
                    'created_by' => $userId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $loaded++;
            }
        });

        $byContract = [];
        foreach ($parsed as $row) {
            $id = $row['contract_id'];
            $byContract[$id] ??= ['tranches' => 0, 'amount' => 0.0];
            $byContract[$id]['tranches']++;
            $byContract[$id]['amount'] = round($byContract[$id]['amount'] + $row['amount'], 2);
        }
        $dates = array_column($parsed, 'disbursement_date');
        sort($dates);

        $summary = [
            'source_rows' => count($rows),
            'loaded_rows' => $loaded,
            'duplicate_source_rows' => $duplicates,
            'already_stored' => $alreadyStored,
            'contracts' => count($byContract),
            'total_amount' => round(array_sum(array_column($parsed, 'amount')), 2),
            'first_date' => $dates[0] ?? null,
            'last_date' => $dates === [] ? null : $dates[count($dates) - 1],
            'notes' => $notes,
            'skipped' => $skipped,
            'by_contract' => $byContract,
        ];

        AuditLoggerService::log(
            action: 'EIR Disbursement Import',
            entityType: 'ContractDisbursement',
            entityId: $importId,
            data: ['meta' => ['source' => self::SOURCE, 'user_id' => $userId, 'result' => $summary]]
        );

        return $summary;
    }

    /**
     * Read every row before anything is written: a file is either loaded or
     * refused, never half loaded.
     *
     * @return array{0:list<array<string,mixed>>, 1:int, 2:list<string>}
     */
    private function validate(array $rows): array
    {
        $parsed = [];
        $duplicates = 0;
        $notes = [];
        $seen = [];

        foreach (array_values($rows) as $i => $row) {
            $label = 'row ' . ($i + 2);

            $contractId = ContractId::normalise($row['contract_id'] ?? null);
            if ($contractId === null || $contractId === '') {
                throw new RuntimeException("The file was not loaded: the loan account number on {$label} is blank. A drawdown that belongs to no facility cannot be attributed to one later.");
            }

            $date = $this->isoDate($row['disbursement_date'] ?? null, $label);
            $amount = $this->amount($row['amount'] ?? null, $label);
            $tranche = $this->tranche($row['tranche_no'] ?? null, $label);
            $reference = $this->text($row['reference'] ?? null);
            $subAccount = $this->text($row['sub_account_no'] ?? null, 20);

            $fingerprint = $this->fingerprint($contractId, $date, $amount, $tranche, $reference);
            if (isset($seen[$fingerprint])) {
                $duplicates++;
                $notes[] = sprintf(
                    '%s repeats %s: %s drawn on %s for %s with the same tranche and reference. It is counted once. Give the two rows their own references if they are genuinely two drawdowns.',
                    $label, $seen[$fingerprint], $contractId, $date, number_format($amount, 2)
                );
                continue;
            }
            $seen[$fingerprint] = $label;

            $parsed[] = [
                'label' => $label,
                'contract_id' => $contractId,
                'sub_account_no' => $subAccount,
                'tranche_no' => $tranche,
                'disbursement_date' => $date,
                'amount' => $amount,
                'reference' => $reference,
                'external_transaction_id' => $fingerprint,
            ];
        }

        return [$parsed, $duplicates, $notes];
    }

    /**
     * Only yyyy-mm-dd, as text, is a date. An Excel serial, a slash date or a
     * month-first string is refused with the value shown, so the person fixing
     * the file can see what the cell held.
     */
    private function isoDate($value, string $label): string
    {
        $text = is_scalar($value) ? trim((string) $value) : '';

        if ($text === '') {
            throw new RuntimeException("The file was not loaded: disbursement_date on {$label} is blank. Every drawdown needs a date written year first (yyyy-mm-dd).");
        }

        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $text, $m) || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            throw new RuntimeException(sprintf(
                'The file was not loaded: disbursement_date on %s is \'%s\', which is not a date written year first (yyyy-mm-dd). The importer never guesses day against month; save the file with ISO dates and try again.',
                $label, $text
            ));
        }

        return $text;
    }

    private function amount($value, string $label): float
    {
        $text = is_scalar($value) ? trim((string) $value) : '';
        $cleaned = str_replace([',', ' ', "\xC2\xA0"], '', $text);
        if (preg_match('/^\((.*)\)$/', $cleaned, $m) === 1) {
            $cleaned = '-' . $m[1];
        }

        if ($cleaned === '' || ! is_numeric($cleaned)) {
            throw new RuntimeException("The file was not loaded: amount on {$label} is '{$text}', which is not a number.");
        }

        $amount = round((float) $cleaned, 2);
        if ($amount <= 0) {
            throw new RuntimeException(sprintf(
                'The file was not loaded: amount on %s is %s. A drawdown is money paid out, so every amount must be above zero; a reversal is a correction to make at source.',
                $label, $text
            ));
        }

        return $amount;
    }

    private function tranche($value, string $label): ?int
    {
        $text = is_scalar($value) ? trim((string) $value) : '';
        if ($text === '' || $text === '-') {
            return null;
        }
        if (! ctype_digit($text) || (int) $text < 1) {
            throw new RuntimeException("The file was not loaded: tranche_no on {$label} is '{$text}'. A tranche number counts from 1.");
        }

        return (int) $text;
    }

    private function text($value, int $limit = 255): ?string
    {
        $text = is_scalar($value) ? trim((string) $value) : '';

        return $text === '' || $text === '-' ? null : mb_substr($text, 0, $limit);
    }

    /**
     * The row's own identity, used as the transaction id when the file carries
     * none, so the same extract loaded twice adds nothing.
     */
    private function fingerprint(string $contractId, string $date, float $amount, ?int $tranche, ?string $reference): string
    {
        return implode('|', [
            $contractId,
            $date,
            number_format($amount, 2, '.', ''),
            $tranche ?? '-',
            mb_substr((string) $reference, 0, 40),
        ]);
    }
}
