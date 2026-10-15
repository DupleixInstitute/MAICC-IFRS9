<?php
use App\Support\Imports\ImportSpecs;
use App\Support\Imports\ImportTemplateBuilder;
$b = new ImportTemplateBuilder();
foreach (ImportSpecs::keys() as $k) {
    $s = ImportSpecs::forScreen($k);
    $t = '-';
    if ($s['template'] !== 'none') { [$body, $name] = $b->template($k); $t = $name . ' ' . strlen($body) . 'b'; }
    $g = strlen($b->guide($k));
    echo str_pad($k, 28) . ' cols=' . count($s['columns']) . ' req=' . $s['required_count'] . ' files=' . count($s['files']) . " tpl={$t} guide={$g}b\n";
}
