<?php
require 'C:/xampp/htdocs/MAICC-IFRS9/vendor/autoload.php';
$app = require 'C:/xampp/htdocs/MAICC-IFRS9/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
ini_set('memory_limit', '2048M');
$period = $argv[1] ?? '2026-09';
$t = microtime(true);
$r = app(App\Services\Rbm\RbmReturnService::class)->build($period, (bool) ($argv[2] ?? false));
echo 'built in ' . round(microtime(true) - $t, 1) . "s; loans {$r['loans']}\n";
echo json_encode($r['sections']['B_totals']) . "\n";
echo 'basis: ' . $r['sections']['B_allowance_basis']['status'] . ' ' . json_encode($r['sections']['B_allowance_basis']['reconciliation']) . "\n";
$l = $r['sections']['B_loss_write_offs'];
echo "loss: {$l['facilities']} facilities, {$l['balance']}; overdue {$l['overdue']} {$l['overdue_balance']}\n";
foreach ($l['rows'] as $row) {
    echo '  ' . implode(' | ', [$row['contract_id'], $row['customer_name'], $row['days_past_due'], ($row['loss_since_open'] ? '<=' : '') . $row['loss_since'], $row['months_classified_loss'], $row['write_off_due_by'], number_format($row['balance'], 2), number_format($row['ifrs9_allowance'], 2), $row['status']]) . "\n";
}
$c = $r['sections']['C_interest_in_suspense'];
unset($c['note']);
echo json_encode($c, JSON_PRETTY_PRINT) . "\n";
