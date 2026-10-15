P = 'C:/xampp/htdocs/MAICC-IFRS9/app/Http/Controllers/TransitionMatrixController.php'
s = open(P, encoding='utf-8').read()

def rep(a, b):
    global s
    assert a in s, a[:70]
    s = s.replace(a, b, 1)

rep("""            $matrices = TransitionMatrix::select([
                    'id',
                    'transition_profile_id',
                    'portfolio_group_id',""", """            $matrices = TransitionMatrix::select([
                    'id',
                    'transition_profile_id',
                    'portfolio_group_id',
                    'pd_calculation_id',
                    'pd_calculation_level',
                    'pd_calculation_code',""")
rep("""                if ($format === 'excel') {
                    // For Excel format, we'd need to use a library like Laravel Excel
                    // For now, return CSV with Excel MIME type
                    Log::info("Returning CSV download as Excel format");
                    return response()->download($csvPath, "{$filename}.csv", [
                        'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                    ])->deleteFileAfterSend(true);""", """                if ($format === 'excel') {
                    // a real workbook, converted from the CSV the report wrote
                    $reader = new \\PhpOffice\\PhpSpreadsheet\\Reader\\Csv();
                    $book = $reader->load($csvPath);
                    $sheet = $book->getActiveSheet();
                    $sheet->setTitle('PD report');
                    foreach (range('A', $sheet->getHighestColumn()) as $col) {
                        $sheet->getColumnDimension($col)->setAutoSize(true);
                    }
                    $xlsxPath = preg_replace('/\\.csv$/', '', $csvPath) . '.xlsx';
                    (new \\PhpOffice\\PhpSpreadsheet\\Writer\\Xlsx($book))->save($xlsxPath);
                    @unlink($csvPath);

                    return response()->download($xlsxPath, "{$filename}.xlsx", [
                        'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ])->deleteFileAfterSend(true);""")
open(P, 'w', encoding='utf-8').write(s)
print('ok')
