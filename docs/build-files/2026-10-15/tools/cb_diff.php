<?php
echo DB::connection()->getDatabaseName(), PHP_EOL;
$q = DB::table('loan_books')->where('build_method','B')->whereNotNull('stored_carrying_amount');
echo json_encode($q->clone()->selectRaw("reporting_period, count(*) n, sum(abs(carrying_amount-stored_carrying_amount)>0.02) bad, round(sum(carrying_amount-stored_carrying_amount),2) diff")->groupBy('reporting_period')->orderBy('reporting_period')->get()), PHP_EOL;
echo json_encode($q->clone()->whereRaw('abs(carrying_amount-stored_carrying_amount)>0.02')->orderBy('reporting_period')->limit(6)->get(['account_number','reporting_period','carrying_amount','stored_carrying_amount'])), PHP_EOL;
