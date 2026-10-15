<?php
$path = 'C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/ecl_dec2025.xlsx';
$r = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path); $r->setReadDataOnly(true); $r->setLoadSheetsOnly(['PDs-S&P','ASS1-Internal Grades']);
$b = $r->load($path);
foreach (['ASS1-Internal Grades','PDs-S&P'] as $sn) { echo "== $sn\n";
 foreach ($b->getSheetByName($sn)->toArray(null, true, false, true) as $i => $row) { $v = array_filter($row, fn($x) => $x !== null && $x !== ''); if ($v) echo $i . ': ' . json_encode($v) . "\n"; } }
