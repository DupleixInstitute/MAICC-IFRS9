<?php

namespace App\Support;

use App\Exports\Ifrs9ReportExport;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * One way to download any report in the hub's normalised shape
 * ([company, title, subtitle, period, generated_at, generated_by, kpis,
 * sections{heading, columns, align, rows}]) as a branded PDF, a branded
 * Excel workbook or plain CSV data. Every report controller hands its
 * payload here, so all downloads carry the same heading, prepared-on date
 * and page numbers.
 */
class ReportDownload
{
    public static function company(): string
    {
        try {
            return optional(Setting::where('setting_key', 'company_name')->first())->setting_value ?: config('app.name');
        } catch (\Throwable) {
            return (string) config('app.name');
        }
    }

    /** The reporting currency code from Settings (e.g. MWK), or '' when none is set. */
    public static function currency(): string
    {
        try {
            $id = optional(Setting::where('setting_key', 'currency')->first())->setting_value;

            return (string) ($id ? (\App\Models\Currency::find($id)?->code ?? '') : '');
        } catch (\Throwable) {
            return '';
        }
    }

    /** Absolute path of the logo DomPDF can read: the uploaded company logo, else the MAIIC logo. */
    public static function logoPath(): ?string
    {
        try {
            $custom = optional(Setting::where('setting_key', 'company_logo')->first())->setting_value;
            if ($custom && is_file(storage_path('app/public/' . $custom))) {
                return storage_path('app/public/' . $custom);
            }
        } catch (\Throwable) {
            // fall through to the bundled logo
        }
        $logo = public_path('images/maiic-logo.png');

        return is_file($logo) ? $logo : null;
    }

    /** "2026-08" -> "August 2026"; anything else is returned unchanged. */
    public static function periodLabel(?string $period): string
    {
        if ($period && preg_match('/^(\d{4})-(\d{2})$/', $period, $m)) {
            return date('F Y', mktime(0, 0, 0, (int) $m[2], 1, (int) $m[1]));
        }

        return (string) $period;
    }

    /** Fills the common heading fields a payload may leave out. */
    public static function normalise(array $report): array
    {
        return array_merge([
            'company' => self::company(),
            'currency' => self::currency(),
            'title' => 'Report',
            'subtitle' => '',
            'period' => null,
            'generated_at' => now()->format('d M Y H:i'),
            'generated_by' => optional(auth()->user())->name,
            'kpis' => [],
            'sections' => [],
            'notes' => [],
        ], $report);
    }

    /** Dispatches on pdf / xlsx / csv. */
    public static function respond(array $report, string $filename, string $format, string $orientation = 'landscape')
    {
        return match ($format) {
            'pdf' => self::pdf($report, $filename, $orientation),
            'xlsx' => self::excel($report, $filename),
            default => self::csv($report, $filename),
        };
    }

    public static function pdf(array $report, string $filename, string $orientation = 'landscape')
    {
        $report = self::normalise($report);
        $pdf = Pdf::loadView('reports.ifrs9.report', [
            'report' => $report,
            'logo' => self::logoPath(),
            'periodLabel' => self::periodLabel($report['period'] ?? null),
        ])->setPaper('a4', $orientation)
            ->setOption('enable_font_subsetting', true);

        self::stampPageNumbers($pdf);

        return $pdf->download($filename . '.pdf');
    }

    public static function excel(array $report, string $filename)
    {
        $report = self::normalise($report);
        if (! empty($report['sheets'])) {
            // A consolidated report: one sheet per part, each headed like the report.
            $sheets = array_map(fn ($p) => self::normalise(array_merge($p, [
                'company' => $report['company'],
                'title' => $p['title'],
                'subtitle' => $report['title'] . ': ' . ($p['subtitle'] ?? ''),
                'generated_at' => $report['generated_at'],
                'generated_by' => $report['generated_by'],
            ])), $report['sheets']);

            return Excel::download(new \App\Exports\Ifrs9MultiSheetExport($sheets), $filename . '.xlsx');
        }

        return Excel::download(new Ifrs9ReportExport($report), $filename . '.xlsx');
    }

    /** Plain data: a heading block, then each section as a header row and its rows. */
    public static function csv(array $report, string $filename): StreamedResponse
    {
        $report = self::normalise($report);

        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [$report['company']]);
            fputcsv($out, [$report['title']]);
            if (! empty($report['period'])) {
                fputcsv($out, ['Reporting period', $report['period']]);
            }
            fputcsv($out, ['Prepared on', $report['generated_at']]);
            if (! empty($report['currency'])) {
                fputcsv($out, ['Amounts in', $report['currency']]);
            }
            foreach ($report['kpis'] as $k) {
                fputcsv($out, [$k['label'] ?? '', self::plain($k['value'] ?? '')]);
            }
            $part = null;
            foreach ($report['sections'] as $sec) {
                fputcsv($out, []);
                if (! empty($sec['part']) && $sec['part'] !== $part) {
                    $part = $sec['part'];
                    fputcsv($out, ['Part: ' . $part]);
                }
                fputcsv($out, [$sec['heading'] ?? '']);
                fputcsv($out, $sec['columns'] ?? []);
                foreach ($sec['rows'] ?? [] as $row) {
                    fputcsv($out, array_map([self::class, 'plain'], array_values($row)));
                }
            }
            fclose($out);
        }, $filename . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** "1,234.50" -> "1234.50" and "(1,234.50)" -> "-1234.50", so the CSV is data, not display text. */
    private static function plain($cell): string
    {
        $s = trim((string) $cell);
        if (preg_match('/^\((-?[\d,]+(?:\.\d+)?)\)$/', $s, $m)) {
            return '-' . str_replace(',', '', $m[1]);
        }
        if (preg_match('/^-?[\d,]+(?:\.\d+)?$/', $s) && str_contains($s, ',')) {
            return str_replace(',', '', $s);
        }

        return $s;
    }

    /**
     * DomPDF does not resolve counter(pages), so "Page N of M" is written on
     * every page after layout, right-aligned in the footer band of
     * reports/ifrs9/report.blade.php.
     */
    public static function stampPageNumbers($pdf): void
    {
        $pdf->render();
        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $metrics = $dompdf->getFontMetrics();
        $font = $metrics->getFont('DejaVu Sans', 'normal');
        $size = 6;
        $label = 'Page {PAGE_NUM} of {PAGE_COUNT}';
        $w = $metrics->getTextWidth('Page 00 of 00', $font, $size);
        $canvas->page_text($canvas->get_width() - 21 - $w, $canvas->get_height() - 30.5, $label, $font, $size, [0.42, 0.45, 0.5]);
    }
}
