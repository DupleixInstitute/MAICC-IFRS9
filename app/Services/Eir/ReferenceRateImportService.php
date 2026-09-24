<?php

namespace App\Services\Eir;

use App\Models\ReferenceRate;
use App\Services\AuditLoggerService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Imports the reference-rate series (File C, spec v3 section 5.3): the
 * Reserve Bank prime lending rate (PLR) as one row per dated rate.
 *
 * The series drives every floating-rate reset, so the importer is built
 * around refusals rather than repairs (decisions D19 and validation rule 6):
 *
 *  - a date that is not written year first (yyyy-mm-dd) rejects the whole
 *    file, naming the column and the first bad row. The delivered file had
 *    1,134 dates with day and month transposed by Excel; the importer never
 *    reads a slash date either way round, because 03/04/2020 is a valid date
 *    in both orders and only one of them is true;
 *  - effective dates must rise strictly within an index. The first break
 *    rejects the file, naming both rows, because a series out of order would
 *    put the wrong rate in force for every month between the two dates;
 *  - a date already stored with a different rate is never overwritten. The
 *    row is skipped and named; the stored series is the audited history.
 *
 * Rows that repeat the previous rate are loaded (E-Banker records a rate on
 * every review, whether or not it moved) but are counted and noted rather
 * than reported as changes: 48 delivered rows carry 26 genuine changes.
 */
class ReferenceRateImportService
{
    /** Persisted lineage: names the file an auditor will ask for. */
    public const SOURCE = 'MAIIC_FILE_C';

    /** Two rates closer than this are the same rate. Rates carry two decimals. */
    private const RATE_TOLERANCE = 0.000005;

    /**
     * @param  list<array<string,mixed>>  $rows  mapped File C rows, in file order
     * @param  string  $defaultIndex  the index for rows that carry none
     * @param  int|null  $importId  the imports row when run through the intake screen
     * @param  int|null  $userId  who loaded the file; recorded as created_by
     * @return array{
     *   index_code:string, source_rows:int, loaded_rows:int, unchanged:int,
     *   duplicate_source_rows:int, held:array<string,string>, skipped:array<string,string>,
     *   rate_changes:int, repeated_rate_rows:int, notes:list<string>,
     *   first_date:?string, last_date:?string, current_rate:?float, series:array
     * }
     *
     * @throws RuntimeException when the file is refused as a whole
     */
    public function import(array $rows, string $defaultIndex = ReferenceRate::DEFAULT_INDEX, ?int $importId = null, ?int $userId = null): array
    {
        $defaultIndex = $this->index($defaultIndex, 'the default index') ?? ReferenceRate::DEFAULT_INDEX;

        [$parsed, $duplicates, $rateChanges, $repeated, $notes] = $this->validate($rows, $defaultIndex);

        $loaded = 0;
        $unchanged = 0;
        $skipped = [];
        $now = now();

        DB::transaction(function () use ($parsed, &$loaded, &$unchanged, &$skipped, &$notes, $importId, $userId, $now) {
            $latestStored = [];

            foreach ($parsed as $row) {
                $index = $row['index_code'];
                $latestStored[$index] ??= DB::table('reference_rate_series')
                    ->where('index_code', $index)->max('effective_date');

                $existing = DB::table('reference_rate_series')
                    ->where('index_code', $index)
                    ->where('effective_date', $row['effective_date'])
                    ->first(['id', 'rate']);

                if ($existing !== null) {
                    if ($this->sameRate((float) $existing->rate, $row['rate'])) {
                        $unchanged++;
                    } else {
                        $skipped["{$index} @ {$row['effective_date']}"] = sprintf(
                            'stored rate %s differs from the file\'s %s (%s); the stored series is not overwritten. Correct it through review with a reason.',
                            number_format((float) $existing->rate, 2),
                            number_format($row['rate'], 2),
                            $row['label']
                        );
                    }
                    continue;
                }

                DB::table('reference_rate_series')->insert([
                    'index_code' => $index,
                    'effective_date' => $row['effective_date'],
                    'rate' => $row['rate'],
                    'source_row' => $row['source_row'],
                    'as_delivered' => $row['as_delivered'],
                    'interpretation' => $row['interpretation'],
                    'import_id' => $importId,
                    'created_by' => $userId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $loaded++;

                // A row dated before the latest stored one changes which rate
                // was in force for months that may already have been run.
                // Loaded, because the history may genuinely have had a gap,
                // but said out loud.
                $latest = $latestStored[$index];
                if ($latest !== null && $row['effective_date'] < substr((string) $latest, 0, 10)) {
                    $notes[] = sprintf(
                        '%s (%s) is dated before the latest stored %s rate of %s; months between the two now read a different rate in force',
                        $row['label'], $row['effective_date'], $index, substr((string) $latest, 0, 10)
                    );
                }
            }
        });

        $last = $parsed === [] ? null : $parsed[array_key_last($parsed)];
        $summary = [
            'index_code' => $defaultIndex,
            'source_rows' => count($rows),
            'loaded_rows' => $loaded,
            'unchanged' => $unchanged,
            'duplicate_source_rows' => $duplicates,
            'held' => [],
            'skipped' => $skipped,
            'rate_changes' => $rateChanges,
            'repeated_rate_rows' => $repeated,
            'notes' => $notes,
            'first_date' => $parsed[0]['effective_date'] ?? null,
            'last_date' => $last['effective_date'] ?? null,
            'current_rate' => $last['rate'] ?? null,
            'series' => $this->summary($defaultIndex),
        ];

        AuditLoggerService::log(
            action: 'EIR Reference Rate Import',
            entityType: 'ReferenceRateSeries',
            entityId: $importId,
            data: ['meta' => ['source' => self::SOURCE, 'result' => $summary]]
        );

        return $summary;
    }

    /**
     * The stored series in one glance: how many rows, how many of them are
     * genuine rate changes, the rate in force today and the span of dates.
     *
     * @return array{index_code:string, rows:int, changes:int, current_rate:?float, first_date:?string, last_date:?string}
     */
    public function summary(string $index = ReferenceRate::DEFAULT_INDEX): array
    {
        $index = strtoupper(trim($index));
        $rows = DB::table('reference_rate_series')
            ->where('index_code', $index)
            ->orderBy('effective_date')
            ->get(['effective_date', 'rate']);

        $changes = 0;
        $previous = null;
        foreach ($rows as $row) {
            if ($previous === null || ! $this->sameRate($previous, (float) $row->rate)) {
                $changes++;
            }
            $previous = (float) $row->rate;
        }

        return [
            'index_code' => $index,
            'rows' => $rows->count(),
            'changes' => $changes,
            'current_rate' => $previous,
            'first_date' => $rows->first() ? substr((string) $rows->first()->effective_date, 0, 10) : null,
            'last_date' => $rows->last() ? substr((string) $rows->last()->effective_date, 0, 10) : null,
        ];
    }

    /**
     * Read every row before anything is written: a file is either loaded or
     * refused, never half loaded.
     *
     * @return array{0:list<array<string,mixed>>, 1:int, 2:int, 3:int, 4:list<string>}
     *         [parsed rows, in-file duplicates, rate changes, repeated-rate rows, notes]
     */
    private function validate(array $rows, string $defaultIndex): array
    {
        $parsed = [];
        $duplicates = 0;
        $rateChanges = 0;
        $repeated = 0;
        $notes = [];
        $last = []; // per index: ['date' => ..., 'rate' => ..., 'label' => ...]

        foreach (array_values($rows) as $i => $row) {
            $label = 'row ' . ($i + 2);

            $date = $this->isoDate($row['effective_date'] ?? null, $label);
            $rate = $this->rate($row['rate'] ?? null, $label);
            $index = $this->index($row['index_code'] ?? null, $label) ?? $defaultIndex;

            if (isset($last[$index])) {
                $previous = $last[$index];
                if ($date < $previous['date']) {
                    throw new RuntimeException(sprintf(
                        'The file was not loaded: effective_date on %s (%s) is earlier than %s (%s). The %s series must be in strictly increasing date order; sort the file by date and check for a transposed day and month.',
                        $label, $date, $previous['label'], $previous['date'], $index
                    ));
                }
                if ($date === $previous['date']) {
                    if ($this->sameRate($rate, $previous['rate'])) {
                        $duplicates++;
                        continue;
                    }
                    throw new RuntimeException(sprintf(
                        'The file was not loaded: %s repeats the date %s of %s with a different rate (%s against %s). One date can carry only one %s rate.',
                        $label, $date, $previous['label'], number_format($rate, 2), number_format($previous['rate'], 2), $index
                    ));
                }
                if ($this->sameRate($rate, $previous['rate'])) {
                    $repeated++;
                    $notes[] = sprintf('%s (%s) repeats the previous %s rate of %s; recorded, but it is not a rate change',
                        $label, $date, $index, number_format($rate, 2));
                } else {
                    $rateChanges++;
                }
            } else {
                $rateChanges++; // the opening rate of a series counts as its first change
            }

            $last[$index] = ['date' => $date, 'rate' => $rate, 'label' => $label];
            $parsed[] = [
                'label' => $label,
                'index_code' => $index,
                'effective_date' => $date,
                'rate' => $rate,
                'source_row' => $this->text($row['source_row'] ?? null),
                'as_delivered' => $this->text($row['as_delivered'] ?? null),
                'interpretation' => $this->text($row['interpretation'] ?? null),
            ];
        }

        return [$parsed, $duplicates, $rateChanges, $repeated, $notes];
    }

    /**
     * Only yyyy-mm-dd, as text, is a date. An Excel serial, a slash date or a
     * month-first string is refused with the value shown, so the person
     * fixing the file can see what the cell held.
     */
    private function isoDate($value, string $label): string
    {
        $text = is_scalar($value) ? trim((string) $value) : '';

        if ($text === '') {
            throw new RuntimeException("The file was not loaded: effective_date on {$label} is blank. Every row needs a date written year first (yyyy-mm-dd).");
        }

        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $text, $m) || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            throw new RuntimeException(sprintf(
                'The file was not loaded: effective_date on %s is \'%s\', which is not a date written year first (yyyy-mm-dd). The importer never guesses day against month; save the file with ISO dates and try again.',
                $label, $text
            ));
        }

        return $text;
    }

    /**
     * A percentage such as 25.30. A value of 1 or below reads as a fraction
     * (0.253) and is refused rather than multiplied up: the loan book writes
     * rates as percentages and the spread arithmetic depends on both sides
     * using the same unit.
     */
    private function rate($value, string $label): float
    {
        $text = is_scalar($value) ? trim((string) $value) : '';
        $cleaned = str_replace([',', ' ', "\xC2\xA0", '%'], '', $text);

        if ($cleaned === '' || ! is_numeric($cleaned)) {
            throw new RuntimeException("The file was not loaded: rate on {$label} is '{$text}', which is not a number.");
        }

        $rate = (float) $cleaned;
        if ($rate <= 1 || $rate >= 100) {
            throw new RuntimeException(sprintf(
                'The file was not loaded: rate on %s is %s. Rates must be percentages between 1 and 100, such as 25.30, not fractions.',
                $label, $text
            ));
        }

        return round($rate, 5);
    }

    private function index($value, string $label): ?string
    {
        $text = strtoupper((string) $this->text($value));
        if ($text === '') {
            return null;
        }
        if (strlen($text) > 20 || ! preg_match('/^[A-Z0-9_\-]+$/', $text)) {
            throw new RuntimeException("The file was not loaded: the index on {$label} is '{$text}'; an index code is up to 20 letters or digits, such as PLR.");
        }

        return $text;
    }

    private function text($value): ?string
    {
        $text = is_scalar($value) ? trim((string) $value) : '';

        return $text === '' || $text === '-' ? null : mb_substr($text, 0, 255);
    }

    private function sameRate(float $a, float $b): bool
    {
        return abs($a - $b) < self::RATE_TOLERANCE;
    }
}
