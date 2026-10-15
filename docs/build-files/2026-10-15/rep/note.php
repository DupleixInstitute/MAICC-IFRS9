<?php
// usage: DB_DATABASE=maiic_ifrs9_reports php note.php opening closing
require 'C:/xampp/htdocs/MAICC-IFRS9/vendor/autoload.php';
$app = require 'C:/xampp/htdocs/MAICC-IFRS9/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
ini_set('memory_limit', '2048M');
$o = $argv[1] ?? '2025-12'; $c = $argv[2] ?? '2026-09';
$t = microtime(true);
$r = App\Services\Reports\Ifrs9NoteService::build($o, $c);
echo "status {$r['status']} in " . round(microtime(true) - $t, 1) . "s\n";
foreach (['ecl', 'gross'] as $k) {
    echo strtoupper($k) . "\n";
    foreach ($r[$k]['lines'] as $line => $v) {
        printf("  %-14s %18s %18s %18s %18s\n", $line, number_format($v[1] / 100, 2), number_format($v[2] / 100, 2), number_format($v[3] / 100, 2), number_format($v['total'] / 100, 2));
    }
}
foreach ($r['checks'] as $ch) {
    echo "[{$ch['level']}] {$ch['label']}: " . substr($ch['detail'], 0, 400) . "\n";
}
foreach ($r['basis'] as $p => $b) {
    echo "basis $p: {$b['status']} " . json_encode($b['reconciliation']) . "\n";
}
echo 'write-offs: ' . count($r['write_offs']) . ' ' . number_format(array_sum(array_column($r['write_offs'], 'amount')), 2) . "\n";
$n = App\Services\Reports\Ifrs9NoteDocuments::build($r, 'm');
echo json_encode($n['basis']['closing']['rows']) . "\n";
echo json_encode(array_slice($n['write_offs']['rows'], 0, 2)) . "\n";
echo $n['paragraphs']['discounting'] . "\n" . $n['paragraphs']['write_offs'] . "\n";
