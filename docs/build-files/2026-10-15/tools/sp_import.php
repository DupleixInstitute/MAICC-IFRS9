<?php
$path = 'C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/ecl_dec2025.xlsx';
echo 'db=' . DB::connection()->getDatabaseName() . PHP_EOL;
$t = microtime(true);
$svc = app(\App\Services\Ifrs9\SpGradesImportService::class);
$r = $svc->read($path);
echo 'read ' . round(microtime(true) - $t, 1) . 's grades=' . count($r['grades']) . PHP_EOL;
