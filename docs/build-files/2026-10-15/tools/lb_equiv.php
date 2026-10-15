<?php
$svc = app(\App\Services\Ebanker\LoanBookBuildService::class);
$ref = new ReflectionClass($svc);
$rowsFor = $ref->getMethod('rowsFor'); $rowsFor->setAccessible(true);
$set = function ($name, $v) use ($ref, $svc) { $p = $ref->getProperty($name); $p->setAccessible(true); $p->setValue($svc, $v); };
$t = microtime(true);
$set('ledgerTo', null); $set('ledgerCache', null); $set('masterCache', []);
$alone = $rowsFor->invoke($svc, '2025-11', 'B');
$t1 = microtime(true) - $t; $t = microtime(true);
$set('ledgerTo', '2025-12-31'); $set('ledgerCache', null); $set('masterCache', []);
$sliced = $rowsFor->invoke($svc, '2025-11', 'B');
$t2 = microtime(true) - $t; $t = microtime(true);
$again = $rowsFor->invoke($svc, '2025-10', 'B');
$t3 = microtime(true) - $t;
$norm = fn ($rows) => md5(json_encode(array_map(fn ($r) => array_diff_key($r, ['created_at' => 1, 'updated_at' => 1]), $rows)));
echo 'rows ', count($alone), ' vs ', count($sliced), '; identical: ', $norm($alone) === $norm($sliced) ? 'yes' : 'NO', PHP_EOL;
printf("alone %.1fs, sliced first month (reads once) %.1fs, next month from cache %.1fs\n", $t1, $t2, $t3);
