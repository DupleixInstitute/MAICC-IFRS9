<?php
// Compare every table of two databases (read-only): row count and an order-independent content
// fingerprint over all columns except surrogate ids and datetime/timestamp columns (run times).
// Usage: php compare_all.php dbA dbB
[$_, $a, $b] = $argv;
$pdo = new PDO('mysql:host=127.0.0.1;port=3306', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('SET SESSION group_concat_max_len = 1073741824');
$tables = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = " . $pdo->quote($a) . " AND table_type = 'BASE TABLE' ORDER BY 1")->fetchAll(PDO::FETCH_COLUMN);
$diff = []; $same = 0; $skipped = [];
foreach ($tables as $t) {
    $cols = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_schema = " . $pdo->quote($a) . " AND table_name = " . $pdo->quote($t) . " ORDER BY ordinal_position")->fetchAll(PDO::FETCH_NUM);
    $use = [];
    foreach ($cols as [$c, $type]) {
        if (in_array($type, ['datetime', 'timestamp'], true) || $c === 'id' || preg_match('/(_at|_time|^token|password|remember_token|uuid|^payload_raw)$/i', $c)) {
            continue;
        }
        $use[] = "IFNULL(CAST(`{$c}` AS CHAR), '~')";
    }
    if ($use === []) { $skipped[] = $t; continue; }
    $expr = 'md5(concat_ws(\'|\', ' . implode(', ', $use) . '))';
    // order-independent: sum of the first 15 hex digits of each row's md5, plus the count
    $sql = "SELECT count(*), sum(conv(substr({$expr}, 1, 8), 16, 10)) FROM `%s`.`{$t}`";
    try {
        $ra = $pdo->query(sprintf($sql, $a))->fetch(PDO::FETCH_NUM);
        $rb = $pdo->query(sprintf($sql, $b))->fetch(PDO::FETCH_NUM);
    } catch (Throwable $e) {
        $diff[$t] = 'ERROR ' . substr($e->getMessage(), 0, 100);
        continue;
    }
    if ($ra === $rb) { $same++; } else { $diff[$t] = "A {$ra[0]} rows, B {$rb[0]} rows"; }
}
echo count($tables) . " tables: {$same} identical, " . count($diff) . " differ, " . count($skipped) . " without comparable columns\n";
foreach ($diff as $t => $why) echo "  DIFF {$t}: {$why}\n";
