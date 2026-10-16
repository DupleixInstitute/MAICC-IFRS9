<?php

namespace App\Services\Eir;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Reads Finance's answers out of a fee reconciliation workbook and maps each one to exactly one record
 * (16 October 2026; spec v4 section 7.8.9). It reads and writes nothing.
 *
 * A workbook produced by the download (FeeReconciliationWorkbook) is KEYED: its Read me carries the
 * fingerprint, which must name this database and a download recorded in the audit log, and the keys found in
 * the file must hash to the fingerprint's (a deleted row or a changed key refuses the whole file). Each answer
 * row is then mapped by its hidden key.
 *
 * A workbook built offline before the download existed (the version of 16 October 2026) has no keys: its rows
 * are mapped by the columns that name each record (NATURAL_KEYS): sheet 8 by the Line (the fee line id); sheet
 * 2 by posting date, batch, GL, amount and narration; sheets 3, 4b and 5 by loan account, fee type and amount
 * (and source); sheet 4 by loan account, fee type and the two sources; sheet 6 and sheet 1 by loan account;
 * sheet 7 by the fee line. A row that maps to no record, or to more than one, is refused and named; the
 * other rows still come through. An answer that is not one of its sheet's list is refused too.
 */
class FeeWorkbookReader
{
    public const KEYED = 'KEYED';
    public const NATURAL = 'NATURAL_KEYS';

    /** @var array<string, string> the source labels back to source_system */
    private array $sources;

    public function __construct()
    {
        $this->sources = array_flip(FeeReconciliationWorkbook::SOURCE);
    }

    /**
     * @return array{basis:string, fingerprint:?array, answers:list<array>, refused:list<array{sheet:string,row:int,reason:string}>}
     * @throws InvalidArgumentException when the file is not a fee reconciliation workbook, or its fingerprint fails
     */
    public function read(string $path): array
    {
        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $book = $reader->load($path);
        } catch (\Throwable $e) {
            throw new InvalidArgumentException('The file could not be read as an Excel workbook: ' . $e->getMessage());
        }
        $known = array_intersect(array_keys(FeeReconciliationWorkbook::SHEETS), $book->getSheetNames());
        if ($known === [] || $book->getSheetByName('Read me') === null) {
            throw new InvalidArgumentException('This is not a fee reconciliation workbook: it has no "Read me" sheet and none of the sheets "1. Loan status" to "8. Fee lines".');
        }
        $readme = $book->getSheetByName('Read me');
        $col = FeeReconciliationWorkbook::FINGERPRINT_COLUMN;
        $fingerprint = null;
        if (trim((string) $readme->getCell($col . '1')->getValue()) === FeeReconciliationWorkbook::FINGERPRINT_LABEL) {
            $fingerprint = json_decode((string) $readme->getCell($col . '2')->getValue(), true);
            if (! is_array($fingerprint) || ($fingerprint['generator'] ?? null) !== FeeReconciliationWorkbook::GENERATOR) {
                throw new InvalidArgumentException('The workbook\'s fingerprint (Read me, hidden column ' . $col . ') cannot be read: it was changed. Download a fresh workbook.');
            }
        }
        $basis = $fingerprint ? self::KEYED : self::NATURAL;
        if ($fingerprint) {
            $this->checkFingerprint($book, $fingerprint);
        }

        $answers = [];
        $refused = [];
        foreach ($this->confirmations($readme, $basis) as $c) {
            $answers[] = $c;
        }
        foreach (FeeReconciliationWorkbook::SHEETS as $title => $sheet) {
            $ws = $book->getSheetByName($title);
            if ($ws === null) {
                continue;
            }
            $headers = $this->headers($ws);
            if (! isset($headers[$sheet['answer']])) {
                $refused[] = ['sheet' => $title, 'row' => 1, 'reason' => "The answer column \"{$sheet['answer']}\" is missing from the sheet."];
                continue;
            }
            $last = $ws->getHighestDataRow();
            for ($r = 2; $r <= $last; $r++) {
                $row = $this->row($ws, $headers, $r);
                $answer = $this->text($row[$sheet['answer']] ?? null);
                $fields = [];
                foreach ($sheet['fields'] as $header => $field) {
                    $fields[$field] = $this->text($row[$header] ?? null);
                }
                if ($answer === null && array_filter($fields, fn ($v) => $v !== null) === []) {
                    continue; // nothing answered on this row
                }
                $label = $this->label($title, $row);
                if ($answer !== null && ! in_array($answer, $sheet['options'], true)) {
                    $refused[] = ['sheet' => $title, 'row' => $r, 'reason' => "Row {$r} ({$label}): \"{$answer}\" is not one of the answers in the list."];
                    continue;
                }
                if ($fields['correct_amount'] ?? null) {
                    $amount = str_replace([',', ' '], '', $fields['correct_amount']);
                    if (! is_numeric($amount)) {
                        $refused[] = ['sheet' => $title, 'row' => $r, 'reason' => "Row {$r} ({$label}): the correct amount \"{$fields['correct_amount']}\" is not a number."];
                        continue;
                    }
                    $fields['correct_amount'] = round((float) $amount, 2);
                }
                $found = $basis === self::KEYED
                    ? $this->byKey($sheet['type'], $this->text($row[FeeReconciliationWorkbook::KEY_HEADER] ?? null), $title)
                    : $this->byNaturalKey($sheet['type'], $row);
                if (count($found) !== 1) {
                    $refused[] = ['sheet' => $title, 'row' => $r, 'reason' => "Row {$r} ({$label}) " . ($found === [] ? 'matches no record in the system' : 'matches ' . count($found) . ' records (' . implode(', ', array_column($found, 'record_key')) . '), so it cannot be told which one it answers') . '.'];
                    continue;
                }
                $answers[] = $found[0] + ['sheet' => $title, 'row_number' => $r, 'answer' => $answer] + $fields
                    + ['context' => array_filter(array_diff_key($row, [FeeReconciliationWorkbook::KEY_HEADER => 1]), fn ($v) => $v !== null && $v !== '')];
            }
        }

        return ['basis' => $basis, 'fingerprint' => $fingerprint, 'answers' => $answers, 'refused' => $refused];
    }

    /** The fingerprint names this database and a recorded download, and the keys in the file hash to it. */
    private function checkFingerprint(\PhpOffice\PhpSpreadsheet\Spreadsheet $book, array $fingerprint): void
    {
        $here = DB::connection()->getDatabaseName();
        if (($fingerprint['database'] ?? null) !== $here) {
            throw new InvalidArgumentException('The workbook was generated from the database "' . ($fingerprint['database'] ?? '?') . "\", not from this one (\"{$here}\"): its keys name records of another database.");
        }
        $recorded = DB::table('audit_logs')->where('action', FeeReconciliationWorkbook::AUDIT_ACTION)->get(['new_values'])
            ->contains(fn ($e) => (json_decode((string) $e->new_values, true)['keys_hash'] ?? null) === ($fingerprint['keys_hash'] ?? '') && (json_decode((string) $e->new_values, true)['generated_at'] ?? null) === ($fingerprint['generated_at'] ?? ''));
        if (! $recorded) {
            throw new InvalidArgumentException('No download of this workbook (generated ' . ($fingerprint['generated_at'] ?? '?') . ') is recorded in the audit log, so it cannot be shown to come from this system.');
        }
        $keys = [];
        foreach (FeeReconciliationWorkbook::SHEETS as $title => $sheet) {
            $ws = $book->getSheetByName($title);
            if ($ws === null) {
                throw new InvalidArgumentException("The sheet \"{$title}\" was removed from the workbook; download a fresh workbook.");
            }
            $headers = $this->headers($ws);
            if (! isset($headers[FeeReconciliationWorkbook::KEY_HEADER])) {
                throw new InvalidArgumentException("The hidden key column of \"{$title}\" was removed; download a fresh workbook.");
            }
            $c = $headers[FeeReconciliationWorkbook::KEY_HEADER];
            for ($r = 2; $r <= $ws->getHighestDataRow(); $r++) {
                $k = $this->text($ws->getCell([$c, $r])->getValue());
                if ($k !== null) {
                    $keys[] = $k;
                }
            }
        }
        $readme = $book->getSheetByName('Read me');
        for ($r = 1; $r <= $readme->getHighestDataRow(); $r++) {
            $k = $this->text($readme->getCell(FeeReconciliationWorkbook::FINGERPRINT_COLUMN . $r)->getValue());
            if ($k !== null && str_starts_with($k, 'confirm:')) {
                $keys[] = $k;
            }
        }
        if (FeeReconciliationWorkbook::keysHash($keys) !== ($fingerprint['keys_hash'] ?? null)) {
            throw new InvalidArgumentException('The keys in the workbook do not match its fingerprint (' . count(array_unique($keys)) . ' keys found, ' . ($fingerprint['keys'] ?? '?') . ' written): a row was deleted or a hidden key was changed. Nothing was read; download a fresh workbook and answer on it.');
        }
    }

    /** The two confirmations on the Read me sheet: by key in column Z, or by the question's letter. */
    private function confirmations(Worksheet $readme, string $basis): array
    {
        $out = [];
        $last = $readme->getHighestDataRow('B');
        for ($r = 1; $r <= $last; $r++) {
            if ($basis === self::KEYED) {
                $key = $this->text($readme->getCell(FeeReconciliationWorkbook::FINGERPRINT_COLUMN . $r)->getValue());
                if ($key === null || ! str_starts_with($key, 'confirm:')) {
                    continue;
                }
                $answerRow = $r;
            } else {
                $q = (string) $readme->getCell('B' . $r)->getValue();
                if (preg_match('/^([AB])\. /', $q, $m) !== 1) {
                    continue;
                }
                $key = 'confirm:' . $m[1];
                $answerRow = $r + 1;
            }
            $answer = $this->text($readme->getCell('B' . $answerRow)->getValue());
            $comment = $this->text(preg_replace('/^\s*Comment:\s*/i', '', (string) $readme->getCell('B' . ($answerRow + 1))->getValue()));
            if ($answer === null && $comment === null) {
                continue;
            }
            $question = (string) $readme->getCell('B' . ($answerRow - 1))->getValue();
            if ($answer !== null && ! in_array($answer, FeeReconciliationWorkbook::CONFIRM_OPTIONS, true)) {
                continue; // only the two list answers are read; anything else stays in the file
            }
            $out[] = ['record_type' => 'confirmation', 'record_key' => $key, 'contract_id' => null, 'sheet' => 'Read me', 'row_number' => $answerRow, 'answer' => $answer,
                'comment' => $comment, 'context' => ['Question' => $question]];
        }

        return $out;
    }

    // ------------------------------------------------------------------ mapping

    /** The record a hidden key names, if it exists. @return list<array> */
    private function byKey(string $type, ?string $key, string $sheet): array
    {
        if ($key === null) {
            // a fee Finance says is missing, added at the bottom of "Fee lines" as the Read me invites
            return $type === 'fee' ? [['record_type' => 'missing_fee', 'record_key' => 'missing:' . $sheet, 'contract_id' => null]] : [];
        }
        [$kind, $id] = array_pad(explode(':', $key, 2), 2, null);
        if (str_starts_with($key, 'held#')) {
            [$kind, $id] = ['held#', substr($key, 5)];
        }
        $expected = ['loan' => ['loan', 'account'], 'held' => ['held', 'held#'], 'overlap' => ['overlap'], 'difference' => ['diff'], 'unposted' => ['unposted'],
            'classify' => ['classify'], 'takeon' => ['takeon'], 'move' => ['move'], 'fee' => ['fee']][$type] ?? [];
        if (! in_array($kind, $expected, true) || $id === null || $id === '') {
            return [];
        }

        return match ($kind) {
            'loan' => DB::table('contract_eir')->where('contract_id', $id)->exists() ? [$this->rec('loan', $key, $id)] : [],
            'account' => DB::table('contract_fees')->where('contract_id', $id)->exists() ? [$this->rec('account', $key, $id)] : [],
            'held' => $this->held(DB::table('fee_gl_unassigned')->where('external_transaction_id', $id)->get(), $key),
            'held#' => $this->held(DB::table('fee_gl_unassigned')->where('id', (int) $id)->get(), $key),
            'overlap', 'unposted', 'classify', 'fee' => ctype_digit($id) && ($f = DB::table('contract_fees')->where('id', (int) $id)->first(['id', 'contract_id', 'source_system']))
                && ($kind !== 'unposted' || $f->source_system === FeeSourcePrecedence::SOURCE_LOS) ? [$this->rec($type, $key, $f->contract_id)] : [],
            'diff' => $this->differences(DB::table('fee_source_differences')->where('record_fee_id', (int) explode(':', $id)[0])->where('other_fee_id', (int) (explode(':', $id)[1] ?? 0))->get()),
            'takeon' => Schema::hasTable('contract_takeon') && DB::table('contract_takeon')->where('contract_id', $id)->exists() ? [$this->rec('takeon', $key, $id)] : [],
            'move' => ctype_digit($id) && ($e = DB::table('eir_fee_classification_events')->where('id', (int) $id)->where('reason', 'like', 'Moved from loan%')->first(['id', 'contract_fee_id']))
                ? [$this->rec('move', $key, DB::table('contract_fees')->where('id', $e->contract_fee_id)->value('contract_id'))] : [],
            default => [],
        };
    }

    /** The records the visible columns of a keyless row name, within the sheet's population. @return list<array> */
    private function byNaturalKey(string $type, array $row): array
    {
        $loan = $this->text($row['Loan account'] ?? null);
        $feeType = $this->text($row['Fee type'] ?? null);
        $amount = is_numeric($row['Amount'] ?? null) ? round((float) $row['Amount'], 2) : null;
        $fees = fn () => DB::table('contract_fees')->where('contract_id', $loan)->where('fee_type', $feeType)->whereNull('superseded_by_source')
            ->get(['id', 'contract_id', 'amount', 'source_system', 'classification_status', 'source_overlap'])->filter(fn ($f) => $amount !== null && abs((float) $f->amount - $amount) < 0.005);
        switch ($type) {
            case 'loan':
                if ($loan === null) {
                    return [];
                }
                if (DB::table('contract_eir')->where('contract_id', $loan)->exists()) {
                    return [$this->rec('loan', 'loan:' . $loan, $loan)];
                }

                return DB::table('contract_fees')->where('contract_id', $loan)->exists() ? [$this->rec('account', 'account:' . $loan, $loan)] : [];
            case 'held':
                $date = $this->date($row['Posting date'] ?? null);
                $held = DB::table('fee_gl_unassigned')->where('batch', $this->text($row['Batch'] ?? null))->where('gl_code', $this->text($row['GL'] ?? null))
                    ->when($date, fn ($q) => $q->whereDate('transaction_date', $date))->get()
                    ->filter(fn ($h) => is_numeric($row['Amount'] ?? null) && abs((float) $h->amount - (float) $row['Amount']) < 0.005
                        && trim((string) $h->narration) === trim((string) ($row['Narration'] ?? '')));

                return $date === null ? [] : $this->held($held, null);
            case 'overlap':
                $source = $this->sources[$this->text($row['Source'] ?? null) ?? ''] ?? $this->text($row['Source'] ?? null);

                return $fees()->filter(fn ($f) => $f->source_overlap !== null && $f->source_system === $source)->map(fn ($f) => $this->rec('overlap', 'overlap:' . $f->id, $f->contract_id))->values()->all();
            case 'unposted':
                $never = UnpostedFeeBasis::neverPostedQuery($loan)->where('fee_type', $feeType)->get(['id', 'contract_id', 'amount']);

                return $never->filter(fn ($f) => $amount !== null && abs((float) $f->amount - $amount) < 0.005)->map(fn ($f) => $this->rec('unposted', 'unposted:' . $f->id, $f->contract_id))->values()->all();
            case 'classify':
                $source = $this->sources[$this->text($row['Source'] ?? null) ?? ''] ?? $this->text($row['Source'] ?? null);

                return $fees()->filter(fn ($f) => $f->classification_status === 'PENDING' && $f->source_system === $source)->map(fn ($f) => $this->rec('classify', 'classify:' . $f->id, $f->contract_id))->values()->all();
            case 'difference':
                return $this->differences(DB::table('fee_source_differences')->where('contract_id', $loan)->where('fee_type', $feeType)
                    ->where('source_of_record', $this->text($row['Source used'] ?? null))->where('other_source', $this->text($row['Other source'] ?? null))->get());
            case 'takeon':
                return $loan !== null && Schema::hasTable('contract_takeon') && DB::table('contract_takeon')->where('contract_id', $loan)->exists() ? [$this->rec('takeon', 'takeon:' . $loan, $loan)] : [];
            case 'move':
                $line = $row['Fee line'] ?? null;
                if (! is_numeric($line)) {
                    return [];
                }

                return DB::table('eir_fee_classification_events')->where('contract_fee_id', (int) $line)->where('reason', 'like', 'Moved from loan%')->orderBy('id')->get(['id'])
                    ->map(fn ($e) => $this->rec('move', 'move:' . $e->id, DB::table('contract_fees')->where('id', (int) $line)->value('contract_id')))->values()->all();
            case 'fee':
                $line = $row['Line'] ?? null;
                if ($line === null || $line === '') {
                    return [['record_type' => 'missing_fee', 'record_key' => 'missing:8. Fee lines', 'contract_id' => $loan]];
                }
                $f = is_numeric($line) ? DB::table('contract_fees')->where('id', (int) $line)->first(['id', 'contract_id']) : null;

                return $f ? [$this->rec('fee', 'fee:' . $f->id, $f->contract_id)] : [];
        }

        return [];
    }

    private function held($rows, ?string $key): array
    {
        return collect($rows)->map(fn ($h) => $this->rec('held', $key ?? FeeReconciliationWorkbook::heldKey(['external_id' => $h->external_transaction_id, 'id' => $h->id]), $h->candidate_contract_id ? ltrim((string) $h->candidate_contract_id, '0') : null))->values()->all();
    }

    private function differences($rows): array
    {
        return collect($rows)->map(fn ($d) => $this->rec('difference', 'diff:' . $d->record_fee_id . ':' . $d->other_fee_id, (string) $d->contract_id))->values()->all();
    }

    private function rec(string $type, string $key, mixed $contract): array
    {
        return ['record_type' => $type, 'record_key' => $key, 'contract_id' => $contract === null ? null : (string) $contract];
    }

    // ------------------------------------------------------------------ cells

    /** @return array<string,int> header text => column index */
    private function headers(Worksheet $ws): array
    {
        $out = [];
        $last = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($ws->getHighestDataColumn(1));
        for ($c = 1; $c <= $last; $c++) {
            $h = $this->text($ws->getCell([$c, 1])->getValue());
            if ($h !== null && ! isset($out[$h])) {
                $out[$h] = $c;
            }
        }

        return $out;
    }

    /** @return array<string,mixed> the row by header */
    private function row(Worksheet $ws, array $headers, int $r): array
    {
        $out = [];
        foreach ($headers as $h => $c) {
            $v = $ws->getCell([$c, $r])->getValue();
            $out[$h] = is_string($v) ? trim($v) : $v;
        }

        return $out;
    }

    /** A short name for a row in a refusal: the loan, the line or the posting. */
    private function label(string $sheet, array $row): string
    {
        foreach (['Line', 'Fee line', 'Loan account', 'Batch'] as $h) {
            if (($row[$h] ?? null) !== null && $row[$h] !== '') {
                return $h . ' ' . $row[$h] . (isset($row['Fee type']) ? ', ' . $row['Fee type'] : '') . (isset($row['Amount']) && is_numeric($row['Amount']) ? ', ' . number_format((float) $row['Amount'], 2) : '');
            }
        }

        return $sheet;
    }

    private function text(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $v = trim(is_float($v) && floor($v) === $v ? (string) (int) $v : (string) $v);

        return $v === '' ? null : $v;
    }

    /** A posting date as the sheet holds it: text (yyyy-mm-dd) or an Excel date number. */
    private function date(mixed $v): ?string
    {
        if (is_numeric($v)) {
            return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $v)->format('Y-m-d');
        }
        $v = $this->text($v);

        return $v !== null && preg_match('/^\d{4}-\d{2}-\d{2}/', $v) === 1 ? substr($v, 0, 10) : null;
    }
}
