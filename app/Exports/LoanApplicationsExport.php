<?php

namespace App\Exports;

use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\FromArray;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithEvents;

class LoanApplicationsExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles, WithEvents
{
    protected $data;
    protected $hiddenColumns;

    public function __construct(array $data, array $hiddenColumns)
    {
        $this->data = $data;
        $this->hiddenColumns = $hiddenColumns;

    }

    /**
     * @return array
     */
    public function array(): array
    {
        return $this->data;
    }

    // Define the headings
    public function headings(): array
    {
        $headers = [
            'ID',
            'Loan Date',
            'Client',
            'Product',
            'Amount',
            'Score',
            'Status',
            'Created At',

        ];

        if ($this->hiddenColumns['loan_description'] != null) {
            $headers[] = 'Description';
        }
        // dd($headers);
        if ($this->hiddenColumns['user_id'] != null) {


            $headers[] = 'Created By';
        }

        if ($this->hiddenColumns['cif'] != null) {
            $headers[] = 'CIF';
        }
       
        return $headers;
    }

    // Apply styles to the Excel file
    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:I1')->applyFromArray([
            'font' => [
                'bold' => true,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                ],
            ],
        ]);
    }

    // Lock the sheet
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->getProtection()->setSheet(true);
                $sheet->getProtection()->setPassword('1234');
            },
        ];
    }
}
