<?php
if (DB::connection()->getDatabaseName() !== 'maiic_ifrs9_oct') { echo "WRONG DB\n"; return; }
ini_set('memory_limit', '-1');
$g = app(App\Services\Eir\BaselineService::class)->goldenActuals();
$vals = [];
foreach ($g as $k => $v) { $vals[$k] = is_array($v) ? ($v['value'] ?? $v['actual'] ?? $v) : $v; }
file_put_contents(getenv('GOLDEN_OUT'), json_encode($vals, JSON_PRETTY_PRINT));
echo count($vals) . " values\n";
