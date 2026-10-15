<?php
echo DB::connection()->getDatabaseName(), PHP_EOL;
(new Database\Seeders\EbankerQuerySeeder())->run();
$svc = app(\App\Services\Ebanker\PackLandingService::class);
$r = $svc->land(base_path('docs/bootstrap/ebanker-pack-2026-10-14'), 1, 'BOOTSTRAP', false);
echo json_encode(array_intersect_key((array) $r, array_flip(['status','load_id','files','gates','error','message'])), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES), PHP_EOL;
echo json_encode(DB::table('ebanker_raw_rows')->whereIn('query_id',['CV_10','CV_14'])->whereNull('superseded_at')->selectRaw('query_id, count(*) n, count(account) with_account, min(row_date) d0, max(row_date) d1')->groupBy('query_id')->get()), PHP_EOL;
