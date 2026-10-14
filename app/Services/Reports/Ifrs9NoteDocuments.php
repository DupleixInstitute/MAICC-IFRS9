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
        $note['basis'] = self::basis($report, $unit);
        $note['write_offs'] = self::writeOffs($report, $unit);
        $note['modifications'] = self::modifications($report, $unit);

        return $note;
    }

    /**
     * (h) Modified financial assets: IFRS 7.35J(a), 35J(b) and 20A, and the IFRS 9 B5.4.6
     * catch-up, from the confirmed and applied restructure register. No row when there is
     * nothing to disclose: the message says so, and how many candidates await confirmation.
     */
    private static function modifications(array $report, array $unit): ?array
    {
        $m = $report['modifications'] ?? null;
        if ($m === null) {
            return null;
        }
        $amt = fn ($v) => (int) round(Attribution::cents((float) $v) / $unit['step']);
        $rows = [];
        if ($m['installed'] && ! $m['none']) {
            $closing = Ifrs9NoteService::period($report['closing_period']);
            $rows = [
                ['key' => 'lifetime_cost', 'ref' => 'IFRS 7.35J(a)', 'label' => 'Modified in the period while the loss allowance was lifetime ECL: amortised cost before the modification',
                    'contracts' => $m['lifetime_ecl_modified']['count'], 'amount' => $amt($m['lifetime_ecl_modified']['amortised_cost_before'])],
                ['key' => 'lifetime_gain', 'ref' => 'IFRS 7.35J(a)', 'label' => 'Net modification gain / (loss) on those assets',
                    'contracts' => null, 'amount' => $amt($m['lifetime_ecl_modified']['net_gain_loss'])],
                ['key' => 'now_12_month', 'ref' => 'IFRS 7.35J(b)', 'label' => 'Modified since initial recognition while the allowance was lifetime ECL, allowance changed to 12-month ECL in the period: gross carrying amount at ' . $closing,
                    'contracts' => $m['lifetime_modified_now_12_month']['count'], 'amount' => $amt($m['lifetime_modified_now_12_month']['gross_carrying_amount'])],
                ['key' => 'all_gain', 'ref' => 'IFRS 9 5.4.3', 'label' => 'All modifications in the period that did not derecognise the asset: net modification gain / (loss)',
                    'contracts' => $m['all_modified']['count'], 'amount' => $amt($m['all_modified']['net_gain_loss'])],
                ['key' => 'derecognised_cost', 'ref' => 'IFRS 7.20A', 'label' => 'Derecognised on a substantial modification: carrying amount derecognised',
                    'contracts' => $m['derecognised']['count'], 'amount' => $amt($m['derecognised']['carrying_amount'])],
                ['key' => 'derecognised_gain', 'ref' => 'IFRS 7.20A', 'label' => 'Gain / (loss) on that derecognition',
                    'contracts' => null, 'amount' => $amt($m['derecognised']['gain_loss'])],
                ['key' => 'catch_up', 'ref' => 'IFRS 9 B5.4.6', 'label' => 'Revised estimates of the contractual cash flows: catch-up adjustment to the gross carrying amount',
                    'contracts' => $m['catch_up']['count'], 'amount' => $amt($m['catch_up']['adjustment'])],
            ];
        }

        return ['installed' => (bool) $m['installed'], 'none' => (bool) $m['none'], 'message' => (string) $m['message'], 'rows' => $rows,
            'candidates' => (int) ($m['candidates'] ?? 0), 'proposed' => (int) ($m['proposed'] ?? 0), 'pending' => (int) ($m['pending'] ?? 0), 'blocked' => (int) ($m['blocked'] ?? 0),
            'caption' => 'Loans whose contractual cash flows were modified or revised in the period, from the restructure register (classified by one person and approved by another) as the revenue run applied them (IFRS 7.35J, 20A; IFRS 9 5.4.3, B5.4.6). The reconciliations in (a) and (b) have no line of their own for these amounts.'];
    }

    /**
     * (f) The basis of the loss allowance by stage at each month: the reporting-date
     * ECL, the lifetime projection, the discounting at the EIR and the allowance,
     * with the part on no discounted figure shown on its own line. Each figure is
     * rounded on its own; the rounding is absorbed in the projection line so the
     * column casts to the allowance.
     */
    private static function basis(array $report, array $unit): array
    {
        $r = fn (float $v) => (int) round(Attribution::cents($v) / $unit['step']);
        $out = [];
        foreach (['closing' => $report['closing_period'], 'opening' => $report['opening_period']] as $side => $period) {
            $summary = $report['basis'][$period] ?? null;
            if (! $summary) {
                continue;
            }
            $lines = ['undiscounted' => [], 'projection' => [], 'discounting_effect' => [], 'shown' => [], 'not_booked' => []];
            $loans = [];
            foreach ([1, 2, 3] as $s) {
                $st = $summary['stages'][$s] ?? ['undiscounted' => 0, 'projection' => 0, 'discounting_effect' => 0, 'shown' => 0, 'not_booked' => 0, 'not_booked_loans' => 0];
                $lines['undiscounted'][$s] = $r($st['undiscounted']);
                $lines['discounting_effect'][$s] = $r($st['discounting_effect']);
                $lines['shown'][$s] = $r($st['shown']);
                $lines['projection'][$s] = $lines['shown'][$s] - $lines['undiscounted'][$s] - $lines['discounting_effect'][$s];
                $lines['not_booked'][$s] = $r($st['not_booked']);
                $loans[$s] = (int) $st['not_booked_loans'];
            }
            foreach ($lines as $k => $v) {
                $lines[$k]['total'] = array_sum($v);
            }
            $loans['total'] = array_sum($loans);
            $out[$side] = ['period' => $period, 'label' => Ifrs9NoteService::period($period), 'status' => $summary['status'], 'message' => $summary['message'],
                'rows' => [
                    ['key' => 'undiscounted', 'label' => 'ECL at the reporting date, undiscounted (EAD x PD x LGD)', 'values' => $lines['undiscounted']],
                    ['key' => 'projection', 'label' => 'Add: lifetime projection over the reporting-date measure', 'values' => $lines['projection']],
                    ['key' => 'discounting_effect', 'label' => 'Less: effect of discounting at the original EIR', 'values' => $lines['discounting_effect']],
                    ['key' => 'shown', 'label' => 'Loss allowance', 'values' => $lines['shown'], 'total' => true],
                    ['key' => 'not_booked', 'label' => 'Of which with no discounted figure, shown undiscounted (' . number_format($loans['total']) . ' loans)', 'values' => $lines['not_booked'], 'muted' => true],
                ]];
        }

        return $out;
    }

    /** (g) The amounts written off in the period, contract by contract, and the IFRS 7.35L statement. */
    private static function writeOffs(array $report, array $unit): array
    {
        $rows = array_map(fn ($w) => ['date' => $w['date'], 'contract_id' => $w['contract_id'], 'customer_name' => $w['customer_name'] ?: '-',
            'amount' => (int) round(Attribution::cents($w['amount']) / $unit['step']), 'source' => $w['source'] === 'ledger' ? 'Ledger (' . $w['reference'] . ')' : 'Register (' . $w['reference'] . ')'],
            $report['write_offs'] ?? []);

        return ['rows' => $rows, 'total' => array_sum(array_column($rows, 'amount')), 'enforcement' => $report['enforcement'] ?? null,
            'outside' => count($report['write_offs_outside'] ?? [])];
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

        if (! empty($note['basis'])) {
            $doc->heading('(f) The loss allowance: undiscounted measure to the discounted allowance', 2);
            $doc->paragraph('The allowance is the expected credit loss projected over the loan\'s life and discounted at its original effective interest rate (IFRS 9 5.5.17, B5.5.44). A loan with no discounted figure is shown at its undiscounted ECL and counted on the last line.', ['italic' => true, 'size' => 16, 'color' => '6B7280', 'after' => 80]);
            $rows = [];
            foreach (['closing', 'opening'] as $side) {
                if (empty($note['basis'][$side])) {
                    continue;
                }
                $rows[] = ['cells' => [$note['basis'][$side]['label']], 'bold' => true, 'span' => true];
                foreach ($note['basis'][$side]['rows'] as $r) {
                    $rows[] = ['cells' => array_merge([$r['label']], array_map(fn ($c) => $fmt($r['values'][$c]), $cols)), 'bold' => ! empty($r['total']),
                        'top' => ! empty($r['total']) ? 'single' : null, 'color' => ! empty($r['muted']) ? '4B5563' : null];
                }
            }
            $doc->table($widths, $rows, ['cells' => ['', 'Stage 1', 'Stage 2', 'Stage 3', 'Total']]);
        }
        if (! empty($note['write_offs'])) {
            $doc->heading('(g) Amounts written off', 2);
            if ($note['write_offs']['rows']) {
                $rows = array_map(fn ($w) => ['cells' => [$w['date'] . '  ' . $w['contract_id'] . '  ' . $w['customer_name'], $fmt($w['amount'])]], $note['write_offs']['rows']);
                $rows[] = ['cells' => ['Total written off', $fmt($note['write_offs']['total'])], 'bold' => true, 'top' => 'single', 'bottom' => 'double'];
                $doc->table([['width' => 7288, 'align' => 'left'], ['width' => 2350]], $rows, ['cells' => ['Date, contract and customer', $note['unit']['heading']]]);
            } else {
                $doc->paragraph('No loan was written off in the period.');
            }
            $doc->paragraph('Amounts written off still subject to enforcement activity (IFRS 7.35L): ' . $note['write_offs']['enforcement']);
        }
        if (! empty($note['modifications'])) {
            $mo = $note['modifications'];
            $doc->heading('(h) Modified financial assets', 2);
            $doc->paragraph($mo['caption'], ['italic' => true, 'size' => 16, 'color' => '6B7280', 'after' => 80]);
            if ($mo['rows']) {
                $rows = array_map(fn ($r) => ['cells' => [$r['ref'], $r['label'], $r['contracts'] === null ? '' : self::count($r['contracts']), $fmt($r['amount'])]], $mo['rows']);
                $doc->table([['width' => 1500, 'align' => 'left'], ['width' => 5338, 'align' => 'left'], ['width' => 1100], ['width' => 1700]], $rows,
                    ['cells' => ['Reference', '', 'Contracts', $note['unit']['heading']]]);
            }
            $doc->paragraph($mo['message']);
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
        if (! empty($note['basis'])) {
            $rows = [];
            foreach (['closing', 'opening'] as $side) {
                foreach ($note['basis'][$side]['rows'] ?? [] as $r) {
                    $rows[] = array_merge([$note['basis'][$side]['label'] . ': ' . $r['label']], array_map(fn ($c) => $fmt($r['values'][$c]), $cols));
                }
            }
            $sections[] = ['heading' => '(f) The loss allowance: undiscounted measure to the discounted allowance', 'note' => $note['basis']['closing']['message'] ?? null,
                'columns' => $head, 'align' => $align, 'rows' => $rows];
        }
        if (! empty($note['write_offs'])) {
            $sections[] = ['heading' => '(g) Amounts written off', 'note' => 'Still subject to enforcement activity (IFRS 7.35L): ' . $note['write_offs']['enforcement'],
                'columns' => ['Date', 'Contract', 'Customer', 'Amount (' . $note['unit']['heading'] . ')', 'Record'], 'align' => ['l', 'l', 'l', 'r', 'l'],
                'rows' => array_merge(array_map(fn ($w) => [$w['date'], $w['contract_id'], $w['customer_name'], $fmt($w['amount']), $w['source']], $note['write_offs']['rows']),
                    [['Total written off', '', '', $fmt($note['write_offs']['total']), '']])];
        }
        if (! empty($note['modifications'])) {
            $mo = $note['modifications'];
            $sections[] = ['heading' => '(h) Modified financial assets', 'note' => $mo['caption'] . ' ' . $mo['message'],
                'columns' => ['Reference', 'Line', 'Contracts', 'Amount (' . $note['unit']['heading'] . ')'], 'align' => ['l', 'l', 'r', 'r'],
                'rows' => $mo['rows']
                    ? array_map(fn ($r) => [$r['ref'], $r['label'], $r['contracts'] === null ? '' : self::count($r['contracts']), $fmt($r['amount'])], $mo['rows'])
                    : [['IFRS 7.35J, 20A', $mo['message'], '', '']]];
        }
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
