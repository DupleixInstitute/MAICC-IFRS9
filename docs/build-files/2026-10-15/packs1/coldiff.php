<?php
// Per-column fingerprints of tables in two databases; prints the columns that differ and samples.
// Usage: php coldiff.php dbA dbB table [table...]
[$_, $a, $b] = $argv;
$pdo = new PDO('mysql:host=127.0.0.1;port=3306', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
foreach (array_slice($argv, 3) as $t) {
    $cols = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_schema = " . $pdo->quote($a) . " AND table_name = " . $pdo->quote($t) . " ORDER BY ordinal_position")->fetchAll(PDO::FETCH_NUM);
    $bad = [];
    foreach ($cols as [$c, $type]) {
        if (in_array($type, ['datetime', 'timestamp'], true)) continue;
        $sql = "SELECT sum(conv(substr(md5(IFNULL(CAST(`{$c}` AS CHAR), '~')), 1, 8), 16, 10)) FROM `%s`.`{$t}`";
        $x = $pdo->query(sprintf($sql, $a))->fetchColumn();
        $y = $pdo->query(sprintf($sql, $b))->fetchColumn();
        if ($x !== $y) $bad[] = $c;
    }
    echo "{$t}: " . ($bad === [] ? 'no column differs' : implode(', ', $bad)) . "\n";
    foreach (array_slice($bad, 0, 4) as $c) {
        if ($c === 'id') continue;
        $va = $pdo->query("SELECT `{$c}` FROM `{$a}`.`{$t}` ORDER BY id LIMIT 3")->fetchAll(PDO::FETCH_COLUMN);
        $vb = $pdo->query("SELECT `{$c}` FROM `{$b}`.`{$t}` ORDER BY id LIMIT 3")->fetchAll(PDO::FETCH_COLUMN);
        echo "   {$c}: A " . substr(json_encode($va), 0, 160) . "\n   {$c}: B " . substr(json_encode($vb), 0, 160) . "\n";
    }
}
