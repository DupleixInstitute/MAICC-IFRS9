<?php
$cols = Schema::getColumnListing('loan_books');
echo implode(',', array_filter($cols, fn($c) => preg_match('/acc|loan_no|stored|carry|gl|portfolio|source|method/i', $c))), PHP_EOL;
$acc = in_array('account_no', $cols) ? 'account_no' : (in_array('loan_account', $cols) ? 'loan_account' : 'loan_number');
$rows = DB::table('loan_books')->where('build_method','B')->where('reporting_period','2025-01')->whereRaw('abs(carrying_amount-stored_carrying_amount)>0.02')->limit(5)->get();
foreach ($rows as $r) { $a=(array)$r; echo json_encode(array_intersect_key($a, array_flip(array_filter($cols, fn($c) => preg_match('/acc|loan_no|loan_number|stored|carry|gl|outstanding|principal|method/i', $c))))), PHP_EOL; }
echo json_encode(DB::table('loan_books')->where('build_method','B')->where('reporting_period','2025-01')->selectRaw('round(sum(carrying_amount),2) c, round(sum(stored_carrying_amount),2) s')->first()), PHP_EOL;
