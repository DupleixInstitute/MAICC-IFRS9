<?php
$s = app(\App\Services\Pd\SegmentPdReport::class);
foreach (['portfolio','sector'] as $b) { $r = $s->bySegment('2026-08', $b); echo "== $b {$r['window']}\n";
 foreach ($r['rows'] as $x) printf("%-55s S%s cohort %3d def %3d obs %7s meets %-5s applied %7s from %-25s now %3d ead %8.1fm ecl %8.1fm\n", $x['label'], $x['stage'], $x['cohort'], $x['defaults'], $x['observed']===null?'-':round($x['observed']*100,2).'%', var_export($x['meets'],true), $x['applied']===null?'-':round($x['applied']*100,2).'%', implode(',',$x['sources']), $x['loans'], $x['ead']/1e6, $x['ecl']/1e6);
 echo json_encode($r['book'])."\n"; }
$r = $s->byRbmClass('2026-08'); echo "== rbm {$r['window']}\n";
foreach ($r['rows'] as $x) printf("%-16s start %3d s3 %3d perf %3d def %3d pdc %7s pdb %7s min %4s | now %3d ead %8.1fm ecl %8.1fm cov %7s pd %7s\n", $x['class'], $x['start_loans'], $x['start_stage3'], $x['performing'], $x['defaults'], $x['pd_count']===null?'-':round($x['pd_count']*100,2).'%', $x['pd_balance']===null?'-':round($x['pd_balance']*100,2).'%', $x['minimum']*100, $x['loans'], $x['ead']/1e6, $x['ecl']/1e6, $x['coverage']===null?'-':round($x['coverage']*100,2).'%', $x['applied_pd']===null?'-':round($x['applied_pd']*100,2).'%');
print_r($s->unverifiedCodes('2026-08', ['4290']));
