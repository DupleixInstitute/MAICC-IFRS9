<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * A consolidated hub report as one workbook: one branded sheet per part
 * (each an Ifrs9ReportExport of that part's own payload).
 */
class Ifrs9MultiSheetExport implements WithMultipleSheets
{
    /** @param array<int,array> $sheets normalised part payloads */
    public function __construct(private array $sheets)
    {
    }

    public function sheets(): array
    {
        return array_map(fn ($s) => new Ifrs9ReportExport($s), $this->sheets);
    }
}
