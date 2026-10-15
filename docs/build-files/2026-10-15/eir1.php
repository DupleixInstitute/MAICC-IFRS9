<?php
foreach (['104460000112','104430000068','104430000073','104430000074'] as $c) {
  $a = app(\App\Services\Eir\EirReadinessService::class)->assess($c);
  echo $c, ' ', $a['status'], ': ', implode(' | ', array_map(fn($i)=>$i['code'].': '.$i['message'], $a['issues'])), "\n";
}
echo json_encode(Schema::hasColumn('contract_eir','schedule_prepared_by')), "\n";
