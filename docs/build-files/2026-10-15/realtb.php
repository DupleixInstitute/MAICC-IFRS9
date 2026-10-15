<?php
use App\Support\Imports\ImportSpecValidator;
foreach (glob('docs/bootstrap/trial-balances/monthly/*.xls') as $f) {
    $c = (new ImportSpecValidator())->checkFile('trial-balance', $f, basename($f));
    echo ($c['ok'] ? 'OK  ' : 'FAIL ') . basename($f) . " rows={$c['rows']} err={$c['error_count']}" . ($c['ok'] ? '' : ' ' . $c['issues'][0]['message']) . "\n";
}
