<?php
echo DB::connection()->getDatabaseName(), ' ', json_encode(DB::table('loan_books')->where('build_method','B')->whereNotNull('stored_carrying_amount')->where('reporting_period','2025-01')->selectRaw("count(*) n, sum(abs(carrying_amount-stored_carrying_amount)>0.02) bad")->first()), PHP_EOL;
