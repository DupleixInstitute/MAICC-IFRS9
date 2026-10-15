<?php
use App\Support\Imports\ImportSpecValidator;
$f = 'tests/fixtures/eir/plr_reference_rates_corrected.csv';
$read = (new App\Services\Imports\MappedFileReader())->read($f, 'reference_rates', null, []);
$c = (new ImportSpecValidator())->checkRows('eir-reference-rates', $read['rows'], array_values($read['report']['mapped_headers']), basename($f));
echo json_encode(['ok'=>$c['ok'],'rows'=>$c['rows'],'err'=>$c['error_count'],'warn'=>$c['warning_count'],'first'=>array_slice($c['issues'],0,3)], JSON_PRETTY_PRINT);
