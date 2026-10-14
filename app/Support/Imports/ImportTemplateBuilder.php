<?php

namespace App\Support\Imports;

use App\Services\Eir\EirSampleFileService;
use App\Services\Macro\ImfWeoParserService;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;

/**
 * Writes the two downloads of every import spec (13 October 2026):
 *
 *  - the sample file, in exactly the format the importer reads: the heading
 *    row and one or two illustrative rows (identifiers start with 8 and names
 *    with "Sample", so a sample loaded unedited is recognisable, never
 *    plausible data). A positional layout gets its layout with marked rows;
 *    a machine-generated pack gets a sample manifest, never a fake extract.
 *  - the column guide, an XLSX workbook: Instructions, Columns (or Files)
 *    with the type, required flag, format and allowed values of each, and
 *    the sample rows.
 *
 * The sample is a single-sheet CSV for every row import: an Excel workbook
 * with a second guide sheet would be read sheet by sheet by the importers.
 */
class ImportTemplateBuilder
{
    public static function templateFileName(array $spec): string
    {
        if (isset($spec['import_type'])) {
            return (new EirSampleFileService())->fileName($spec['import_type']);
        }

        return match ($spec['template']) {
            'tsv' => str_replace('-', '_', $spec['key']) . '_sample.tsv',
            'xlsx-layout' => str_replace('-', '_', $spec['key']) . '_layout_sample.xlsx',
            'manifest' => 'manifest_sample.json',
            'none' => '',
            default => str_replace('-', '_', $spec['key']) . '_sample.csv',
        };
    }

    public static function guideFileName(array $spec): string
    {
        return str_replace('-', '_', $spec['key']) . '_column_guide.xlsx';
    }

    /** @return array{0:string,1:string,2:string} body, file name, content type */
    public function template(string $key): array
    {
        $spec = ImportSpecs::get($key);
        $name = self::templateFileName($spec);

        if (isset($spec['import_type'])) {
            return [(new EirSampleFileService())->csv($spec['import_type']), $name, 'text/csv; charset=UTF-8'];
        }

        return match ($spec['template']) {
            'tsv' => [$this->imfWeoSample(), $name, 'text/tab-separated-values; charset=UTF-8'],
            'xlsx-layout' => [$this->trialBalanceLayout($spec), $name, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'manifest' => [$this->manifestSample(), $name, 'application/json'],
            'none' => abort(404, 'This import has no blank template: the file comes from the source system. Download the column guide instead.'),
            default => [$key === 'loan-book-ebanker' ? $this->ebankerReportLayout() : $this->csv($spec), $name, 'text/csv; charset=UTF-8'],
        };
    }

    /** The heading row and the sample rows, as a CSV the importer reads unchanged. */
    public function csv(array $spec): string
    {
        $headers = array_map(fn ($c) => $c['name'], $spec['columns']);
        $h = fopen('php://temp', 'r+');
        fputcsv($h, $headers);
        foreach ($spec['sample_rows'] as $row) {
            fputcsv($h, array_map(fn ($name) => self::safe($row[$name] ?? ''), $headers));
        }
        rewind($h);
        $out = (string) stream_get_contents($h);
        fclose($h);

        return $out;
    }

    /** A cell a spreadsheet will not run as a formula. */
    public static function safe($v)
    {
        if (is_string($v) && $v !== '' && (preg_match('/^[=@\t\r]/', $v) || (preg_match('/^[+\-]/', $v) && ! is_numeric($v)))) {
            return "'" . $v;
        }

        return $v;
    }

    /** The XLSX column guide: Instructions, Columns or Files, Sample. */
    public function guide(string $key): string
    {
        $spec = ImportSpecs::get($key);
        $book = new Spreadsheet();
        $book->getProperties()->setTitle($spec['title'] . ' - column guide')->setCreator('MAIIC IFRS 9');

        $ins = $book->getActiveSheet();
        $ins->setTitle('Instructions');
        $r = 1;
        $ins->setCellValue("A{$r}", $spec['title'] . ': how to import');
        $ins->getStyle("A{$r}")->getFont()->setBold(true)->setSize(14);
        $r += 2;
        $ins->setCellValue("A{$r}", $spec['summary']);
        $r += 2;
        $ins->setCellValue("A{$r}", 'File: ' . strtoupper($spec['format']) . ' (' . ($spec['accept'] ?? '') . ')' . (self::templateFileName($spec) !== '' ? '. Sample file: ' . self::templateFileName($spec) : ''));
        $r += 2;
        $ins->setCellValue("A{$r}", 'Steps');
        $ins->getStyle("A{$r}")->getFont()->setBold(true);
        foreach ($spec['steps'] as $i => $step) {
            $r++;
            $ins->setCellValue("A{$r}", ($i + 1) . '. ' . $step);
        }
        if ($spec['columns'] !== []) {
            $r += 2;
            $required = array_map(fn ($c) => $c['name'], array_filter($spec['columns'], fn ($c) => $c['required']));
            $ins->setCellValue("A{$r}", 'Required columns (' . count($required) . '): ' . implode(', ', $required));
            $r++;
            $ins->setCellValue("A{$r}", $spec['headers'] === 'positional'
                ? 'Columns are read by position: do not add, remove or move columns.'
                : 'Columns are read by their headings, in any order; other columns are ignored. A file with a missing required column, a value of the wrong type or a repeated key is refused as a whole, with every problem listed by row.');
        }
        if ($spec['notes'] !== []) {
            $r += 2;
            $ins->setCellValue("A{$r}", 'Notes');
            $ins->getStyle("A{$r}")->getFont()->setBold(true);
            foreach ($spec['notes'] as $note) {
                $r++;
                $ins->setCellValue("A{$r}", '- ' . $note);
            }
        }
        $ins->getColumnDimension('A')->setWidth(140);
        $ins->getStyle('A1:A' . $r)->getAlignment()->setWrapText(true);

        if ($spec['columns'] !== []) {
            $cols = $book->createSheet();
            $cols->setTitle('Columns');
            $head = $spec['headers'] === 'positional'
                ? ['Position', 'Column', 'Required', 'Type', 'Format and allowed values', 'Description', 'Example']
                : ['Column heading', 'Required', 'Type', 'Format and allowed values', 'Description', 'Example'];
            $cols->fromArray($head, null, 'A1');
            $i = 2;
            foreach ($spec['columns'] as $c) {
                $line = [$c['name'], $c['required'] ? 'Required' : 'Optional', ImportSpecs::typeLabel($c), ImportSpecs::formatText($c), $c['description'], $c['example'] === null ? '' : (string) $c['example']];
                if ($spec['headers'] === 'positional') {
                    array_unshift($line, (string) ($c['position'] ?? ''));
                }
                $cols->fromArray(array_map(fn ($v) => self::safe($v), $line), null, "A{$i}", true);
                $i++;
            }
            $this->styleTable($cols, count($head), $i - 1, [14, 30, 11, 18, 60, 60, 24]);
        }

        if ($spec['files'] !== []) {
            $files = $book->createSheet();
            $files->setTitle('Files');
            $files->fromArray(['File or sheet', 'Required', 'What it holds'], null, 'A1');
            $i = 2;
            foreach ($spec['files'] as $f) {
                $files->fromArray([$f['name'], ($f['required'] ?? false) ? 'Required' : 'When delivered', $f['description']], null, "A{$i}", true);
                $i++;
            }
            $this->styleTable($files, 3, $i - 1, [48, 16, 110]);
        }

        if ($spec['sample_rows'] !== [] && $spec['headers'] !== 'positional') {
            $sample = $book->createSheet();
            $sample->setTitle('Sample');
            $names = array_map(fn ($c) => $c['name'], $spec['columns']);
            $sample->fromArray($names, null, 'A1');
            $i = 2;
            foreach ($spec['sample_rows'] as $row) {
                $c = 1;
                foreach ($names as $n) {
                    $sample->setCellValueExplicit([$c, $i], (string) self::safe($row[$n] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $c++;
                }
                $i++;
            }
            $this->styleTable($sample, count($names), $i - 1, []);
        }

        $book->setActiveSheetIndex(0);

        return $this->xlsx($book);
    }

    private function styleTable($sheet, int $cols, int $lastRow, array $widths): void
    {
        $last = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cols);
        $sheet->getStyle("A1:{$last}1")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle("A1:{$last}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('15803D');
        $sheet->freezePane('A2');
        for ($i = 1; $i <= $cols; $i++) {
            $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
            if (isset($widths[$i - 1])) {
                $sheet->getColumnDimension($letter)->setWidth($widths[$i - 1]);
            } else {
                $sheet->getColumnDimension($letter)->setAutoSize(true);
            }
        }
        $sheet->getStyle("A1:{$last}{$lastRow}")->getAlignment()->setWrapText(true)->setVertical('top');
    }

    private function xlsx(Spreadsheet $book): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'tpl');
        (new Xlsx($book))->save($tmp);
        $body = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $body;
    }

    /**
     * The trial balance layout: B1 the period as an Excel date, row 2 the
     * header, two illustrative GL lines that balance, the Grand Total row; a
     * second sheet with the column guide (the importer reads the first sheet).
     */
    private function trialBalanceLayout(array $spec): string
    {
        $book = new Spreadsheet();
        $s = $book->getActiveSheet();
        $s->setTitle('Trial Balance');
        $s->setCellValue('A1', 'Period');
        $s->setCellValue('B1', ExcelDate::PHPToExcel(new \DateTime('2025-12-01')));
        $s->getStyle('B1')->getNumberFormat()->setFormatCode('yyyy-mm-dd');
        $s->fromArray([null, 'GL Title', 'Debit', 'Credit'], null, 'A2');
        $s->fromArray([null, '9000001...SAMPLE ONLY - asset line', '1,000,000.00', '0.00'], null, 'A3');
        $s->fromArray([null, '9000002...SAMPLE ONLY - liability line', '0.00', '1,000,000.00'], null, 'A4');
        $s->fromArray([null, 'Grand Total :', '1,000,000.00', '1,000,000.00'], null, 'A5');
        $s->getStyle('B2:D2')->getFont()->setBold(true);
        $s->getColumnDimension('B')->setWidth(48);
        $s->getColumnDimension('C')->setWidth(20);
        $s->getColumnDimension('D')->setWidth(20);

        $g = $book->createSheet();
        $g->setTitle('Columns');
        $g->fromArray(['Position', 'Column', 'Required', 'Type', 'Format', 'Description', 'Example'], null, 'A1');
        $i = 2;
        foreach ($spec['columns'] as $c) {
            $g->fromArray([(string) ($c['position'] ?? ''), $c['name'], $c['required'] ? 'Required' : 'Optional', ImportSpecs::typeLabel($c), ImportSpecs::formatText($c), $c['description'], (string) $c['example']], null, "A{$i}", true);
            $i++;
        }
        $this->styleTable($g, 7, $i - 1, [10, 20, 11, 14, 50, 70, 30]);
        $book->setActiveSheetIndex(0);

        return $this->xlsx($book);
    }

    /**
     * The E-Banker loan book report layout: one Loan Type row, the header row
     * and one illustrative row with every column in its place (30 columns,
     * serial number in column A).
     */
    private function ebankerReportLayout(): string
    {
        $header = ['Sr.', 'Customer ID', 'National ID', 'Loan account', 'Name', 'Value date', 'Maturity', 'Tenure', 'Moratorium', 'Interest', 'Approved', 'Disbursed', 'Not yet disbursed',
            'Principal', 'Collateral', 'Charge', 'Interest due', 'Repayment', 'Total due', 'Carrying', 'Principal arrears', 'Interest arrears', 'Total arrears', 'Arrears', '1 - 30 Days', '31 - 90 Days', '91 - 180 Days', 'Reserved', '181 - 270 Days', '271 - 360 Days'];
        $row = ['1', '80000001', 'SAMPLE', '800000000001', 'SAMPLE ONLY - not a loan', '31/07/2024', '30/11/2027', '40', '0', '28.5', '500,000,000.00', '500,000,000.00', '0.00',
            '412,500,000.00', '650,000,000.00', '0.00', '5,730,114.25', '87,500,000.00', '418,230,114.25', '418,230,114.25', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00'];
        $h = fopen('php://temp', 'r+');
        fputcsv($h, array_merge(['Loan Type : 01 - TERM LOAN'], array_fill(0, 29, '')));
        fputcsv($h, $header);
        fputcsv($h, $row);
        rewind($h);
        $out = (string) stream_get_contents($h);
        fclose($h);

        return $out;
    }

    /** A sample manifest.json naming every registered query, in the shape PackLandingService reads. */
    private function manifestSample(): string
    {
        $files = [];
        foreach (ImportSpecs::ebankerQueries() as $q) {
            $files[] = ['file' => $q->query_id . '_' . strtolower(preg_replace('/[^A-Za-z0-9]+/', '_', $q->title)) . '.csv', 'query_id' => $q->query_id,
                'query_version' => (string) $q->version, 'rows' => 0, 'sha256' => 'the SHA-256 of the file, 64 hex characters'];
        }
        if ($files === []) {
            $files[] = ['file' => 'P1_01_ledger_every_posting.csv', 'query_id' => 'P1_01', 'query_version' => '1', 'rows' => 0, 'sha256' => 'the SHA-256 of the file, 64 hex characters'];
        }

        return json_encode([
            'pack' => 'SAMPLE ONLY - name the pack, e.g. MAIIC E-Banker pack, received 9 October 2026',
            'assembled' => 'YYYY-MM-DD',
            'period' => 'YYYY-MM',
            'source' => 'E-Banker, who ran the queries and how',
            'date_format' => 'Y-m-d',
            'accepted_exceptions' => [],
            'files' => $files,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    }

    /**
     * The heading row ImfWeoParserService reads, then one row per series with
     * a WEO code for the parser's default country, year cells blank.
     */
    public function imfWeoSample(): string
    {
        $years = range(1980, (int) now()->format('Y') + 5);
        $lines = [implode("\t", array_merge(['ISO', 'WEO Subject Code', 'Units', 'Estimates Start After'], $years))];
        try {
            foreach (DB::table('macro_statistics')->orderBy('statistic_code')->get(['unit', 'external_codes']) as $s) {
                $code = (json_decode($s->external_codes ?? '', true) ?: [])['imf_weo'] ?? null;
                if ($code) {
                    $lines[] = implode("\t", array_merge([ImfWeoParserService::DEFAULT_COUNTRY, $code, (string) $s->unit, ''], array_fill(0, count($years), '')));
                }
            }
        } catch (Throwable) {
        }

        return implode("\r\n", $lines) . "\r\n";
    }
}
