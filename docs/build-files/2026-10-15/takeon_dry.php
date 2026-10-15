<?php
$s = app(App\Services\Ebanker\TakeonLandingService::class);
$r = $s->land('docs/bootstrap/takeon/Amortisation schedules as at 31 October 2024 - as received 11 Sep 2026.xlsx', 'docs/bootstrap/takeon/Take-on schedules with mapping - fees completed by MAIIC - received 13 Oct 2026.xlsx', null, true);
echo $r['status'], ' blocks=', $r['blocks'], ' lines=', $r['lines'], ' refused=', count(array_filter($r['gates']['blocks'], fn($g)=>$g['refusals'])), ' flagged=', count(array_filter($r['gates']['blocks'], fn($g)=>$g['flags'] ?? [])), "\n";
