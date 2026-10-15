<?php
use App\Support\Imports\ImportSpecs;
use App\Support\Imports\ImportTemplateBuilder;
use App\Support\Imports\ImportSpecValidator;
$b = new ImportTemplateBuilder();
foreach (ImportSpecs::keys() as $k) {
    $s = ImportSpecs::get($k);
    if (in_array($s['template'], ['none', 'manifest'])) continue;
    [$body, $name] = $b->template($k);
    $tmp = tempnam(sys_get_temp_dir(), 'rt'); file_put_contents($tmp, $body);
    if (isset($s['import_type'])) {
        $read = (new App\Services\Imports\MappedFileReader())->read($tmp, $s['import_type'], null, []);
        $c = (new ImportSpecValidator())->checkRows($k, $read['rows'], array_values($read['report']['mapped_headers']), $name);
    } else {
        $c = (new ImportSpecValidator())->checkFile($k, $tmp, $name);
    }
    echo str_pad($k, 28) . ($c['ok'] ? 'OK ' : 'FAIL ') . "rows={$c['rows']} err={$c['error_count']} warn={$c['warning_count']}\n";
    foreach (array_slice($c['issues'], 0, 4) as $i) echo "   - r{$i['row']} {$i['column']}: {$i['message']}\n";
}
