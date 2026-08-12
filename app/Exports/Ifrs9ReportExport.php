<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Branded Excel workbook for any IFRS 9 hub report. Consumes the same
 * normalised payload the Vue page and the PDF template use
 * (title / subtitle / kpis / sections{heading, columns, align, rows}),
 * so every one of the hub reports exports without per-report code.
 *
 * Numbers arrive as display strings; numeric-looking cells are coerced
 * back to real numbers with accounting formats so Excel can sum them.
 */
class Ifrs9ReportExport implements FromArray, WithEvents, WithTitle, ShouldAutoSize
{
    private const GREEN_DARK = '14532D';
    private const GREEN = '15803D';
    private const GOLD = 'D97706';
    private const ZEBRA = 'F0FDF4';

    /** @var array<int, array> */
    private array $rows = [];

    /** style bookkeeping collected while laying out rows */
    private array $meta = [
        'company' => 1,
        'title' => 2,
        'kpiLabelRows' => [],
        'kpiValueRows' => [],
        'sectionHeadingRows' => [],
        'columnHeaderRows' => [],   // row => colCount
        'dataRanges' => [],         // [firstRow, lastRow, align[], colCount]
        'formats' => [],            // cell ref => number format
        'maxCols' => 1,
    ];

    public function __construct(private array $report)
    {
        $this->layout();
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function title(): string
    {
        return mb_substr(preg_replace('/[\\\\\/?*\[\]:]+/', ' ', $this->report['title'] ?? 'Report'), 0, 31);
    }

    private function layout(): void
    {
        $r = $this->report;

        $this->rows[] = [$r['company'] ?? 'MAIIC'];
        $this->rows[] = [$r['title'] ?? 'IFRS 9 Report'];
        $this->rows[] = [trim(($r['subtitle'] ?? '') . (!empty($r['period']) ? ' | Reporting period: ' . $r['period'] : ''))];
        $this->rows[] = ['Generated ' . ($r['generated_at'] ?? now()->format('d M Y H:i'))
            . (!empty($r['generated_by']) ? ' by ' . $r['generated_by'] : '')
            . ' | MAIIC IFRS 9 ECL & EIR System'];
        $this->rows[] = [''];

        if (!empty($r['kpis'])) {
            $labels = array_map(fn ($k) => $k['label'] ?? '', $r['kpis']);
            $this->rows[] = $labels;
            $this->meta['kpiLabelRows'][] = count($this->rows);
            $valueRow = [];
            foreach ($r['kpis'] as $i => $k) {
                $valueRow[] = $this->coerce($k['value'] ?? '', count($this->rows) + 1, $i + 1);
            }
            $this->rows[] = $valueRow;
            $this->meta['kpiValueRows'][] = count($this->rows);
            $this->rows[] = [''];
            $this->meta['maxCols'] = max($this->meta['maxCols'], count($labels));
        }

        foreach ($r['sections'] ?? [] as $sec) {
            $this->rows[] = [$sec['heading'] ?? ''];
            $this->meta['sectionHeadingRows'][] = count($this->rows);

            $cols = $sec['columns'] ?? [];
            if (empty($sec['rows'])) {
                $this->rows[] = ['No data available for this section.'];
                $this->rows[] = [''];
                continue;
            }

            $this->rows[] = $cols;
            $this->meta['columnHeaderRows'][count($this->rows)] = count($cols);
            $this->meta['maxCols'] = max($this->meta['maxCols'], count($cols));

            $first = count($this->rows) + 1;
            foreach ($sec['rows'] as $row) {
                $out = [];
                foreach (array_values($row) as $i => $cell) {
                    $out[] = $this->coerce($cell, count($this->rows) + 1, $i + 1);
                }
                $this->rows[] = $out;
            }
            $this->meta['dataRanges'][] = [$first, count($this->rows), $sec['align'] ?? [], count($cols)];
            $this->rows[] = [''];
        }
    }

    /**
     * Convert display strings back to typed values so Excel can work with
     * them: "1,234.56" -> 1234.56, "(1,234.56)" -> -1234.56, "8.25%" -> 8.25
     * with a % display format. Anything else stays text.
     */
    private function coerce($cell, int $rowIdx, int $colIdx)
    {
        $s = trim((string) $cell);
        $ref = Coordinate::stringFromColumnIndex($colIdx) . $rowIdx;

        if (preg_match('/^\((-?[\d,]+(?:\.\d+)?)\)$/', $s, $m)) {
            $this->meta['formats'][$ref] = '#,##0.00;(#,##0.00)';
            return -(float) str_replace(',', '', $m[1]);
        }
        if (preg_match('/^-?[\d,]+\.\d+$/', $s)) {
            $this->meta['formats'][$ref] = '#,##0.00;(#,##0.00)';
            return (float) str_replace(',', '', $s);
        }
        if (preg_match('/^-?[\d,]+$/', $s) && $s !== '' && !preg_match('/^0\d/', str_replace(',', '', $s))) {
            $this->meta['formats'][$ref] = '#,##0;(#,##0)';
            return (float) str_replace(',', '', $s);
        }
        if (preg_match('/^(-?[\d,]+(?:\.\d+)?)\s*%$/', $s, $m)) {
            $this->meta['formats'][$ref] = '#,##0.00"%"';
            return (float) str_replace(',', '', $m[1]);
        }

        return $s;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $last = Coordinate::stringFromColumnIndex(max(1, $this->meta['maxCols']));

                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(15)
                    ->getColor()->setRGB(self::GREEN_DARK);
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12)
                    ->getColor()->setRGB(self::GREEN);
                $sheet->getStyle('A3:A4')->getFont()->setSize(9)->getColor()->setRGB('6B7280');

                foreach ($this->meta['kpiLabelRows'] as $row) {
                    $range = "A{$row}:{$last}{$row}";
                    $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB(self::GREEN);
                    $sheet->getStyle($range)->getFont()->setBold(true)->setSize(9)
                        ->getColor()->setRGB('FFFFFF');
                }
                foreach ($this->meta['kpiValueRows'] as $row) {
                    $sheet->getStyle("A{$row}:{$last}{$row}")->getFont()->setBold(true)->setSize(12);
                }

                foreach ($this->meta['sectionHeadingRows'] as $row) {
                    $style = $sheet->getStyle("A{$row}:{$last}{$row}");
                    $style->getFont()->setBold(true)->setSize(11)->getColor()->setRGB(self::GREEN_DARK);
                    $style->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM)
                        ->getColor()->setRGB(self::GOLD);
                }

                foreach ($this->meta['columnHeaderRows'] as $row => $colCount) {
                    $end = Coordinate::stringFromColumnIndex(max(1, $colCount));
                    $range = "A{$row}:{$end}{$row}";
                    $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB(self::GREEN_DARK);
                    $sheet->getStyle($range)->getFont()->setBold(true)->setSize(9)
                        ->getColor()->setRGB('FFFFFF');
                }

                foreach ($this->meta['dataRanges'] as [$first, $lastRow, $align, $colCount]) {
                    for ($c = 1; $c <= max(1, $colCount); $c++) {
                        $col = Coordinate::stringFromColumnIndex($c);
                        $sheet->getStyle("{$col}{$first}:{$col}{$lastRow}")
                            ->getAlignment()->setHorizontal(
                                ($align[$c - 1] ?? 'l') === 'r'
                                    ? Alignment::HORIZONTAL_RIGHT
                                    : Alignment::HORIZONTAL_LEFT
                            );
                    }
                    for ($row = $first; $row <= $lastRow; $row++) {
                        if (($row - $first) % 2 === 1) {
                            $end = Coordinate::stringFromColumnIndex(max(1, $colCount));
                            $sheet->getStyle("A{$row}:{$end}{$row}")->getFill()
                                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::ZEBRA);
                        }
                    }
                }

                foreach ($this->meta['formats'] as $ref => $format) {
                    $sheet->getStyle($ref)->getNumberFormat()->setFormatCode($format);
                }

                $sheet->freezePane('A6');
            },
        ];
    }
}
