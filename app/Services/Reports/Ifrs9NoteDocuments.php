<?php

namespace App\Services\Reports;

use App\Exports\Ifrs9ReportExport;
use App\Services\Reports\EclMovementAttribution as Attribution;
use App\Support\ReportDownload;
use App\Support\SimpleDocx;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

/**
 * The IFRS 9 note as a view model in one unit (currency, thousands or
 * millions), and its PDF, Word and Excel downloads. The screen and every
 * download read build(), so they always show the same figures. A note that
 * does not tie is never issued.
 *
 * Amounts are integers in the unit's smallest step (cents for the full
 * currency, whole thousands, tenths of a million); 'decimals' says how to
 * print them.
 */
class Ifrs9NoteDocuments
{
    public const STAGE_HEADINGS = [
        1 => ['Stage 1', '12-month ECL'],
        2 => ['Stage 2', 'Lifetime ECL not credit-impaired'],
        3 => ['Stage 3', 'Lifetime ECL credit-impaired'],
    ];

    public static function units(string $currency): array
    {
        $c = trim($currency);

        return [
            'full' => ['label' => $c ?: 'currency units', 'heading' => $c ?: '', 'step' => 1, 'decimals' => 2],
            'k' => ['label' => trim($c . " '000"), 'heading' => trim($c . " '000"), 'step' => 100000, 'decimals' => 0],
            'm' => ['label' => trim($c . ' million'), 'heading' => trim($c . ' m'), 'step' => 10000000, 'decimals' => 1],
        ];
    }

    public static function unitKey(?string $key): string
    {
        return in_array($key, ['full', 'k', 'm'], true) ? $key : 'k';
    }

    public static function build(array $report, string $unitKey): array
    {
        $unitKey = self::unitKey($unitKey);
        $unit = self::units($report['currency'] ?? '')[$unitKey];
        $open = $report['opening_period'];
        $close = $report['closing_period'];

        $note = [
            'status' => $report['status'],
            'message' => $report['message'] ?? null,
            'scope' => $report['scope'],
            'currency' => $report['currency'] ?? '',
            'opening_period' => $open,
            'closing_period' => $close,
            'opening_label' => Ifrs9NoteService::period($open),
            'closing_label' => Ifrs9NoteService::period($close),
            'unit_key' => $unitKey,
            'unit' => $unit,
            'checks' => $report['checks'] ?? [],
            'built_at' => $report['built_at'] ?? now()->format('d M Y H:i'),
            'title' => 'Loans and advances to customers: expected credit loss',
        ];
        if (! in_array($report['status'], ['ok', 'failed'], true)) {
            return $note;
        }

        $note['tables'] = [
            'ecl' => self::table($report['ecl'], $unit, Ifrs9NoteService::lineLabels('ECL allowance', $open, $close),
                'Reconciliation of the loss allowance', 'Movement in the ECL allowance by stage between ' . $note['opening_label'] . ' and ' . $note['closing_label'] . ' (IFRS 7.35H).'),
            'gross' => self::table($report['gross'], $unit, Ifrs9NoteService::lineLabels('gross carrying amount', $open, $close),
                'Reconciliation of the gross carrying amount', 'Movement in the gross carrying amount by stage, explaining the changes in the loss allowance (IFRS 7.35I).'),
        ];
        $note['position'] = self::position($report, $note['tables']);
        // (e) The charge to profit or loss: the movement in the allowance
        // other than amounts written off (which use the allowance).
        $ecl = collect($note['tables']['ecl']['rows'])->keyBy('key');
        $note['charge'] = [];
        foreach ([1, 2, 3, 'total'] as $c) {
            $note['charge'][$c] = $ecl['closing']['values'][$c] - $ecl['opening']['values'][$c] - $ecl['written_off']['values'][$c];
        }
        $note['contracts'] = $report['ecl']['contracts'];
        $note['paragraphs'] = $report['methodology']['paragraphs'];
        $note['groups'] = $report['groups'];

        return $note;
    }

    private static function table(array $attr, array $unit, array $labels, string $title, string $caption): array
    {
        $rounded = $unit['step'] > 1 ? Attribution::rounded($attr['lines'], $unit['step']) : null;
        $lines = $rounded ? $rounded['lines'] : $attr['lines'];
        $rows = [];
        foreach ($labels as $key => $label) {
            $rows[] = ['key' => $key, 'label' => $label,
                'kind' => $key === 'opening' ? 'opening' : ($key === 'closing' ? 'closing' : 'movement'),
                'values' => [1 => $lines[$key][1], 2 => $lines[$key][2], 3 => $lines[$key][3], 'total' => $lines[$key]['total']]];
        }
        $recon = null;
        if ($rounded) {
            $recon = [
                ['label' => 'Closing per loan book (each figure rounded)', 'values' => $rounded['recon']['closing']['book']],
                ['label' => 'Closing per note (stages cast)', 'values' => $rounded['recon']['closing']['note']],
                ['label' => 'Rounding difference', 'values' => $rounded['recon']['closing']['difference'], 'rule' => true],
                ['label' => 'Opening per loan book (each figure rounded)', 'values' => $rounded['recon']['opening']['book']],
                ['label' => 'Opening per note (stages cast)', 'values' => $rounded['recon']['opening']['note']],
                ['label' => 'Rounding difference', 'values' => $rounded['recon']['opening']['difference'], 'rule' => true],
                ['label' => 'Rounding absorbed in net remeasurement', 'values' => $rounded['absorbed'], 'rule' => true],
            ];
        }

        return ['title' => $title, 'caption' => $caption, 'rows' => $rows, 'recon' => $recon];
    }

    /** Exposure, loss allowance and coverage by stage, closing with the opening as comparative (IFRS 7.35M). */
    private static function position(array $report, array $tables): array
    {
        $out = [];
        foreach (['closing' => $report['closing_period'], 'opening' => $report['opening_period']] as $side => $period) {
            $gross = self::row($tables['gross'], $side);
            $ecl = self::row($tables['ecl'], $side);
            $eg = $report['gross']['lines'][$side];
            $ee = $report['ecl']['lines'][$side];
            $net = $coverage = $share = [];
            foreach ([1, 2, 3, 'total'] as $c) {
                $net[$c] = $gross[$c] - $ecl[$c];
                $coverage[$c] = $eg[$c] != 0 ? $ee[$c] / $eg[$c] : null;
                $share[$c] = $eg['total'] != 0 ? $eg[$c] / $eg['total'] : null;
            }
            $out[$side] = ['period' => $period, 'label' => Ifrs9NoteService::period($period), 'gross' => $gross, 'ecl' => $ecl,
                'net' => $net, 'coverage' => $coverage, 'share' => $share, 'contracts' => $report['ecl']['contracts'][$side]];
        }

        return $out;
    }

    private static function row(array $table, string $key): array
    {
        foreach ($table['rows'] as $r) {
            if ($r['key'] === $key) {
                return $r['values'];
            }
        }

        return [1 => 0, 2 => 0, 3 => 0, 'total' => 0];
    }

    // -----------------------------------------------------------------
    // Formatting (statement style: negatives in brackets, nil as "-")
    // -----------------------------------------------------------------

    public static function amount(int $value, int $decimals): string
    {
        if ($value === 0) {
            return '-';
        }
        $text = number_format(abs($value) / (10 ** $decimals), $decimals);

        return $value < 0 ? '(' . $text . ')' : $text;
    }

    public static function percent(?float $fraction, int $decimals = 1): string
    {
        return $fraction === null ? '-' : number_format($fraction * 100, $decimals) . '%';
    }

    public static function count(int $n): string
    {
        return $n === 0 ? '-' : number_format($n);
    }

    public static function filename(array $note, string $ext): string
    {
        return 'MAIIC-IFRS9-note-' . $note['opening_period'] . '-to-' . $note['closing_period']
            . '-' . ['full' => 'units', 'k' => 'thousands', 'm' => 'millions'][$note['unit_key']] . '.' . $ext;
    }

    /** Refuse to issue a note that does not tie or has nothing to reconcile. */
    public static function assertUsable(array $note): void
    {
        if ($note['status'] !== 'ok') {
            abort(422, $note['message'] ?: 'The note is not available for these months.');
        }
    }

    // -----------------------------------------------------------------
    // PDF
    // -----------------------------------------------------------------

    public static function pdf(array $note)
    {
        $pdf = Pdf::loadView('reports.ifrs9_note_pdf', [
            'note' => $note,
            'company' => ReportDownload::company(),
            'logo' => ReportDownload::logoPath(),
            'preparedOn' => now()->format('d M Y H:i'),
            'preparedBy' => optional(auth()->user())->name,
        ])->setPaper('a4', 'portrait')->setOption('enable_font_subsetting', true);
        ReportDownload::stampPageNumbers($pdf);

        return $pdf->download(self::filename($note, 'pdf'));
    }

    // -----------------------------------------------------------------
    // Word
    // -----------------------------------------------------------------

    public static function docx(array $note)
    {
        $d = $note['unit']['decimals'];
        $fmt = fn ($v) => self::amount((int) $v, $d);
        $cols = [1, 2, 3, 'total'];
        $widths = [['width' => 4238, 'align' => 'left'], ['width' => 1350], ['width' => 1350], ['width' => 1350], ['width' => 1350]];
        $head = [
            'cells' => ['', 'Stage 1', 'Stage 2', 'Stage 3', 'Total'],
            'sub' => ['', '12-month ECL', 'Lifetime ECL not credit-impaired', 'Lifetime ECL credit-impaired', $note['unit']['heading']],
        ];

        $doc = (new SimpleDocx('IFRS 9 note'))
            ->header(ReportDownload::company(), 'IFRS 9 expected credit loss')
            ->footer('Prepared from the MAIIC IFRS 9 system on ' . now()->format('d F Y') . '. Amounts in ' . $note['unit']['label'] . '.');

        $doc->heading($note['title'], 1);
        $doc->paragraph($note['scope'] . '. Movement from ' . $note['opening_label'] . ' to ' . $note['closing_label'] . '. Amounts in ' . $note['unit']['label'] . '.', ['italic' => true, 'color' => '4B5563', 'after' => 160]);

        foreach (['ecl' => '(a)', 'gross' => '(b)'] as $key => $letter) {
            $t = $note['tables'][$key];
            $doc->heading($letter . ' ' . $t['title'], 2);
            $doc->paragraph($t['caption'], ['italic' => true, 'size' => 16, 'color' => '6B7280', 'after' => 80]);
            $rows = [];
            foreach ($t['rows'] as $row) {
                $rows[] = ['cells' => array_merge([$row['label']], array_map(fn ($c) => $fmt($row['values'][$c]), $cols)),
                    'bold' => $row['kind'] !== 'movement',
                    'top' => $row['kind'] === 'closing' ? 'single' : null,
                    'bottom' => $row['kind'] === 'closing' ? 'double' : null];
            }
            $doc->table($widths, $rows, $head);
        }

        $doc->heading('(c) Exposure, loss allowance and coverage by stage', 2);
        $doc->paragraph('Gross carrying amount, loss allowance and coverage at ' . $note['position']['closing']['label'] . ', with ' . $note['position']['opening']['label'] . ' for comparison (IFRS 7.35M).', ['italic' => true, 'size' => 16, 'color' => '6B7280', 'after' => 80]);
        $rows = [];
        foreach (['closing', 'opening'] as $side) {
            $p = $note['position'][$side];
            $rows[] = ['cells' => [$p['label']], 'bold' => true, 'span' => true];
            $rows[] = ['cells' => array_merge(['Gross carrying amount'], array_map(fn ($c) => $fmt($p['gross'][$c]), $cols))];
            $rows[] = ['cells' => array_merge(['Loss allowance'], array_map(fn ($c) => $fmt(-$p['ecl'][$c]), $cols))];
            $rows[] = ['cells' => array_merge(['Net carrying amount'], array_map(fn ($c) => $fmt($p['net'][$c]), $cols)), 'bold' => true, 'top' => 'single'];
            $rows[] = ['cells' => array_merge(['ECL coverage'], array_map(fn ($c) => self::percent($p['coverage'][$c]), $cols)), 'color' => '4B5563'];
            $rows[] = ['cells' => array_merge(['Number of contracts'], array_map(fn ($c) => self::count($p['contracts'][$c]), $cols)), 'color' => '4B5563'];
        }
        $doc->table($widths, $rows, ['cells' => ['', 'Stage 1', 'Stage 2', 'Stage 3', 'Total']]);

        $doc->heading('(d) Impairment charge to profit or loss', 2);
        $doc->table($widths, [['cells' => array_merge(['ECL charge / (release) for the period'], array_map(fn ($c) => $fmt($note['charge'][$c]), $cols)), 'bold' => true, 'top' => 'single', 'bottom' => 'double']], ['cells' => ['', 'Stage 1', 'Stage 2', 'Stage 3', 'Total']]);

        $doc->heading('(e) Basis of measurement', 2);
        foreach ($note['paragraphs'] as $para) {
            $doc->paragraph($para);
        }

        $doc->pageBreak();
        $doc->heading('Workings and checks (not part of the published note)', 2);
        foreach (['ecl' => 'loss allowance', 'gross' => 'gross carrying amount'] as $key => $what) {
            $recon = $note['tables'][$key]['recon'];
            if (! $recon) {
                continue;
            }
            $doc->paragraph('Rounding reconciliation: ' . $what . ' (' . $note['unit']['label'] . ')', ['bold' => true, 'after' => 40]);
            $rows = [];
            foreach ($recon as $r) {
                $rows[] = ['cells' => array_merge([$r['label']], array_map(fn ($c) => $fmt($r['values'][$c]), $cols)), 'top' => ! empty($r['rule']) ? 'single' : null];
            }
            $doc->table($widths, $rows, ['cells' => ['', 'Stage 1', 'Stage 2', 'Stage 3', 'Total']]);
        }
        $rows = [];
        foreach ($note['checks'] as $c) {
            $rows[] = ['cells' => [strtoupper($c['level']) . ': ' . $c['label'], $c['detail']]];
        }
        $doc->paragraph('Checks', ['bold' => true, 'after' => 40]);
        $doc->table([['width' => 3598, 'align' => 'left'], ['width' => 6040, 'align' => 'left']], $rows);

        $path = tempnam(sys_get_temp_dir(), 'ifrs9note');
        $doc->save($path);

        return response()->download($path, self::filename($note, 'docx'), ['Content-Type' => SimpleDocx::MIME])->deleteFileAfterSend(true);
    }

    // -----------------------------------------------------------------
    // Excel and CSV: the note and the workings in the hub's payload shape
    // -----------------------------------------------------------------

    public static function payload(array $note): array
    {
        $d = $note['unit']['decimals'];
        $fmt = fn ($v) => self::amount((int) $v, $d);
        $cols = [1, 2, 3, 'total'];
        $align = ['l', 'r', 'r', 'r', 'r'];
        $head = ['', 'Stage 1 (' . $note['unit']['heading'] . ')', 'Stage 2', 'Stage 3', 'Total'];

        $sections = [];
        foreach (['ecl' => '(a)', 'gross' => '(b)'] as $key => $letter) {
            $t = $note['tables'][$key];
            $sections[] = ['heading' => $letter . ' ' . $t['title'], 'note' => $t['caption'], 'columns' => $head, 'align' => $align,
                'rows' => array_map(fn ($r) => array_merge([$r['label']], array_map(fn ($c) => $fmt($r['values'][$c]), $cols)), $t['rows'])];
        }
        $rows = [];
        foreach (['closing', 'opening'] as $side) {
            $p = $note['position'][$side];
            $rows[] = array_merge([$p['label'] . ': gross carrying amount'], array_map(fn ($c) => $fmt($p['gross'][$c]), $cols));
            $rows[] = array_merge([$p['label'] . ': loss allowance'], array_map(fn ($c) => $fmt(-$p['ecl'][$c]), $cols));
            $rows[] = array_merge([$p['label'] . ': net carrying amount'], array_map(fn ($c) => $fmt($p['net'][$c]), $cols));
            $rows[] = array_merge([$p['label'] . ': ECL coverage'], array_map(fn ($c) => self::percent($p['coverage'][$c]), $cols));
            $rows[] = array_merge([$p['label'] . ': number of contracts'], array_map(fn ($c) => self::count($p['contracts'][$c]), $cols));
        }
        $sections[] = ['heading' => '(c) Exposure, loss allowance and coverage by stage', 'columns' => $head, 'align' => $align, 'rows' => $rows];
        $sections[] = ['heading' => '(d) Impairment charge to profit or loss', 'columns' => $head, 'align' => $align,
            'rows' => [array_merge(['ECL charge / (release) for the period'], array_map(fn ($c) => $fmt($note['charge'][$c]), $cols))]];
        $sections[] = ['heading' => '(e) Basis of measurement', 'columns' => ['Topic', 'Basis'], 'align' => ['l', 'l'],
            'rows' => array_map(fn ($k, $v) => [ucfirst(str_replace('_', ' ', $k)), $v], array_keys($note['paragraphs']), $note['paragraphs'])];
        foreach (['ecl' => 'loss allowance', 'gross' => 'gross carrying amount'] as $key => $what) {
            if ($recon = $note['tables'][$key]['recon']) {
                $sections[] = ['heading' => 'Workings: rounding reconciliation, ' . $what, 'columns' => $head, 'align' => $align,
                    'rows' => array_map(fn ($r) => array_merge([$r['label']], array_map(fn ($c) => $fmt($r['values'][$c]), $cols)), $recon)];
            }
        }
        $cents = fn ($v) => self::amount((int) $v, 2);
        $sections[] = ['heading' => 'Workings: contracts grouped by opening and closing stage (' . ($note['currency'] ?: 'full units') . ')',
            'columns' => ['Opening stage', 'Closing stage', 'Movement', 'Contracts', 'ECL opening', 'ECL closing', 'Gross opening', 'Gross closing'],
            'align' => ['l', 'l', 'l', 'r', 'r', 'r', 'r', 'r'],
            'rows' => array_map(fn ($g) => [$g['from'] ? 'Stage ' . $g['from'] : '-', $g['to'] ? 'Stage ' . $g['to'] : '-', $g['category'], (string) $g['contracts'],
                $cents($g['ecl_open']), $cents($g['ecl_close']), $cents($g['gross_open']), $cents($g['gross_close'])], $note['groups'])];
        $sections[] = ['heading' => 'Workings: checks', 'columns' => ['Result', 'Check', 'Detail'], 'align' => ['l', 'l', 'l'],
            'rows' => array_map(fn ($c) => [strtoupper($c['level']), $c['label'], $c['detail']], $note['checks'])];

        return [
            'title' => 'IFRS 9 note for the annual financial statements',
            'subtitle' => $note['scope'] . ', ' . $note['opening_label'] . ' to ' . $note['closing_label'] . '. Amounts in ' . $note['unit']['label'] . '.',
            'period' => $note['closing_period'],
            'kpis' => [],
            'sections' => $sections,
        ];
    }

    public static function excel(array $note)
    {
        return Excel::download(new Ifrs9ReportExport(ReportDownload::normalise(self::payload($note))), self::filename($note, 'xlsx'));
    }
}
