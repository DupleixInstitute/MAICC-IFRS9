<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$rows = json_decode(file_get_contents($argv[1]), true);
$r = app(App\Services\Eir\FeeWorkbookTieOut::class)->check($rows);
echo $r['status'], ' lines ', count($r['lines']), ' refusals ', count($r['refusals']), ' ties ', count($r['ties']), ' conflicts ', count($r['conflicts']), "\n";
foreach ($r['refusals'] as $x) echo 'REFUSED row ', $x['row'], ' ', $x['contract_id'], ' ', $x['amount'], ': ', mb_substr($x['reason'], 0, 400), "\n";
foreach ($r['conflicts'] as $x) echo 'CONFLICT ', json_encode($x), "\n";
foreach ($r['lines'] as $l) if (($l['difference'] ?? 0) != 0 || preg_grep('/no name|corrections|pays this fee/', $l['how'])) echo 'LINE ', $l['row'], ' ', $l['contract_id'], ' ', $l['gl'], ' ', $l['amount'], ' diff ', $l['difference'] ?? '', ' | ', implode(' ; ', $l['how']), "\n";
echo json_encode($r['coverage']), "\n";
