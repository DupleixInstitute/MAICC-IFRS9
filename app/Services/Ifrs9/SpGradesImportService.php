<?php

namespace App\Services\Ifrs9;

use App\Models\InternalGradeProfile;
use App\Services\AuditLoggerService;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

/**
 * The S&P internal grading of MAIIC's ECL model, loaded as an internal grading
 * profile (13 October 2026). The December 2025 model (MAIIC & FInES ECL Model -
 * 14th December 2025_Final.xlsx) carries it on two sheets, and any later model
 * built from the same template does too:
 *
 *  - "ASS1-Internal Grades": the group's ten credit grades, each with its
 *    description, its mapping to an S&P rating, its broad grade and its PD range;
 *  - "PDs-S&P": S&P's average cumulative default rates for the region (the
 *    December 2025 model uses Table 25, Emerging Markets 1981-2024), by S&P
 *    rating and time horizon 1 to 10 years, each row tied to an internal grade
 *    by the grade's combined label.
 *
 * A grade with no row of cumulative PDs on the second sheet (in the December 2025
 * model grades 7, CCC+, and 9, CC) is reported and left out rather than given a
 * curve the model does not have. The profile is created as a draft: it changes no
 * PD until someone activates it on Internal Grades.
 */
class SpGradesImportService
{
    public const GRADES_SHEET = 'ASS1-Internal Grades';
    public const PDS_SHEET = 'PDs-S&P';

    /** @return array{source:array,grades:list<array>,skipped:list<string>,max_tenor:int} */
    public function read(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly([self::GRADES_SHEET, self::PDS_SHEET]);
        $book = $reader->load($path);
        $grades = $book->getSheetByName(self::GRADES_SHEET);
        $pds = $book->getSheetByName(self::PDS_SHEET);
        if (! $grades || ! $pds) {
            throw new RuntimeException('The workbook needs the sheets "' . self::GRADES_SHEET . '" and "' . self::PDS_SHEET . '", as the ECL model has them.');
        }

        // the grades: number, description, S&P mapping, broad grade, combined label, PD range
        $out = [];
        $broad = null;
        foreach ($grades->toArray(null, true, false, false) as $r) {
            if (! is_numeric($r[0] ?? null) || trim((string) ($r[4] ?? '')) === '') {
                continue;
            }
            $broad = trim((string) ($r[3] ?? '')) ?: $broad;
            $out[trim((string) $r[4])] = ['grade_code' => (string) (int) $r[0], 'description' => trim((string) $r[1]), 'sp_rating' => trim((string) $r[2]),
                'broad' => $broad, 'label' => trim((string) $r[4]),
                'lower' => is_numeric($r[5] ?? null) ? (float) $r[5] : null, 'upper' => is_numeric($r[6] ?? null) ? (float) $r[6] : null, 'pds' => []];
        }
        if ($out === []) {
            throw new RuntimeException('No grade was found on "' . self::GRADES_SHEET . '": each row needs the grade number in column A and its combined label in column E.');
        }

        // the cumulative PDs: the header row names the horizons; each row names its internal grade
        $rows = $pds->toArray(null, true, false, false);
        $years = null;
        $source = ['provider' => null, 'link' => null, 'table' => null, 'title' => null, 'region' => null, 'note' => null];
        foreach ($rows as $i => $r) {
            $c1 = trim((string) ($r[1] ?? ''));
            $c2 = trim((string) ($r[2] ?? ''));
            if ($c2 === 'Data Source Provider:' || (string) ($r[2] ?? '') === 'Data Source Provider:') {
                $source['provider'] = trim((string) ($r[5] ?? ''));
            } elseif ($c2 === 'Report link:') {
                $source['link'] = trim((string) ($r[5] ?? ''));
            } elseif (stripos($c1, 'Table') === 0 && $source['table'] === null) {
                $source['table'] = $c1;
            } elseif (stripos($c1, 'Cumulative Default') !== false) {
                $source['title'] = $c1;
            } elseif ($c1 === 'S&P Rating' && $c2 === 'Internal Grade') {
                $years = [];
                foreach ($r as $col => $v) {
                    if ($col >= 3 && is_numeric($v)) {
                        $years[$col] = (int) $v;
                    }
                }
                $source['region'] = trim((string) ($rows[$i - 1][1] ?? '')) ?: null;
            } elseif ($years !== null && $c2 !== '' && isset($out[$c2])) {
                foreach ($years as $col => $year) {
                    if (is_numeric($r[$col] ?? null)) {
                        $out[$c2]['pds'][$year] = round((float) $r[$col], 8);
                    }
                }
                $out[$c2]['sp_row'] = $c1 !== '' ? $c1 : null;
            } elseif (stripos($c1, 'Report Extract') !== false) {
                $source['note'] = $c1;
            }
        }
        if ($years === null) {
            throw new RuntimeException('"' . self::PDS_SHEET . '" has no header row with "S&P Rating" and "Internal Grade" and the horizons 1, 2, 3 ...');
        }

        $grades = [];
        $skipped = [];
        foreach ($out as $g) {
            if ($g['pds'] === []) {
                $skipped[] = "Grade {$g['grade_code']} ({$g['sp_rating']}) has no row of cumulative PDs on \"" . self::PDS_SHEET . '"; it is left out.';
                continue;
            }
            ksort($g['pds']);
            $grades[] = $g;
        }
        usort($grades, fn ($a, $b) => (int) $a['grade_code'] <=> (int) $b['grade_code']);

        return ['source' => $source, 'grades' => $grades, 'skipped' => $skipped, 'max_tenor' => $years === [] ? 0 : max($years)];
    }

    /** A draft profile from the workbook; the name must be new. */
    public function import(string $path, string $fileName, string $name, ?int $userId = null): InternalGradeProfile
    {
        $r = $this->read($path);
        if ($r['grades'] === []) {
            throw new RuntimeException('The workbook carries no grade with cumulative PDs.');
        }
        if (InternalGradeProfile::where('name', $name)->exists()) {
            throw new RuntimeException("A grading profile named \"{$name}\" exists already; choose another name.");
        }
        $s = $r['source'];
        $description = trim(implode(' ', array_filter([
            ($s['provider'] ?: 'S&P') . ' ' . ($s['title'] ?: 'average cumulative default rates') . ($s['region'] ? ', ' . $s['region'] : '') . ($s['table'] ? ' (' . $s['table'] . ')' : '') . '.',
            $s['note'] ? $s['note'] . '.' : null,
            'Loaded from ' . $fileName . ' (sheets ' . self::GRADES_SHEET . ' and ' . self::PDS_SHEET . ').',
            $s['link'] ? 'Source: ' . $s['link'] : null,
            $r['skipped'] !== [] ? 'Left out: ' . implode(' ', $r['skipped']) : null,
        ])));

        return DB::transaction(function () use ($r, $name, $description, $fileName, $userId) {
            $profile = InternalGradeProfile::create(['name' => $name, 'description' => $description, 'max_tenor_years' => max(1, $r['max_tenor']), 'is_active' => false]);
            foreach ($r['grades'] as $g) {
                $m = $profile->mappings()->create([
                    'grade_code' => $g['grade_code'], 'grade_name' => $g['label'], 'sp_rating' => $g['sp_rating'],
                    'rbm_class' => $g['broad'], 'lower_bound' => $g['lower'], 'upper_bound' => $g['upper'],
                ]);
                $m->tenorPds()->createMany(array_map(fn ($year, $pd) => ['tenor_years' => $year, 'pd_probability' => $pd], array_keys($g['pds']), $g['pds']));
            }
            AuditLoggerService::log('Internal Grades Imported (S&P)', 'internal_grade_profiles', $profile->id, ['new_values' => [
                'file' => $fileName, 'profile' => $name, 'grades' => count($r['grades']), 'skipped' => $r['skipped'], 'source' => $r['source'], 'user' => $userId]]);

            return $profile;
        });
    }
}
