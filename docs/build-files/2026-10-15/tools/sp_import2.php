<?php
$path = 'C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/ecl_dec2025.xlsx';
echo 'db=' . DB::connection()->getDatabaseName() . PHP_EOL;
$p = app(\App\Services\Ifrs9\SpGradesImportService::class)->import($path, 'MAIIC & FInES ECL Model - 14th December 2025_Final.xlsx', 'S&P Emerging Markets (ECL model, December 2025)', 1);
echo "profile {$p->id} grades=" . $p->mappings()->count() . ' pds=' . \DB::table('internal_grade_tenor_pds')->count() . PHP_EOL;
foreach ($p->mappings()->with('tenorPds')->get() as $m) echo $m->grade_code . ' ' . $m->sp_rating . ' ' . $m->rbm_class . ' y1=' . optional($m->tenorPds->firstWhere('tenor_years', 1))->pd_probability . ' y10=' . optional($m->tenorPds->firstWhere('tenor_years', 10))->pd_probability . PHP_EOL;
