from ed import edit
p='app/Console/Commands/Bootstrap.php'
edit(p, r"""        try {
            $t = app(TakeonLandingService::class)->build($user);
            $this->note('5b take-on population',""", r"""        try {
            // the Build with approval (spec 6.9): proposed, then approved under the bootstrap label (not a MAIIC approval)
            $takeon = app(TakeonLandingService::class);
            $request = $takeon->proposeBuild($user, 'eir:bootstrap');
            $t = $takeon->approveBuild($request['request'], $user, LoanBookBuildService::BOOTSTRAP_LABEL);
            $this->note('5b take-on population', "build request {$request['request']} approved under the bootstrap label; " .""")
edit(p, r"""        $cm = $inputs->contractMaster();
        $this->note('5c contract master', "{$cm['rows']} rows: {$cm['result']['created']} created, {$cm['result']['updated']} updated, {$cm['result']['unchanged']} unchanged, " . count($cm['result']['held']) . ' held, ' . count($cm['result']['skipped']) . ' skipped');""",
r"""        $cm = $inputs->contractMaster();
        $this->note('5c contract master', "{$cm['rows']} rows: {$cm['result']['created']} created, {$cm['result']['updated']} updated, {$cm['result']['unchanged']} unchanged, " . count($cm['result']['held']) . ' held, ' . count($cm['result']['skipped']) . ' skipped'
            . '; drawn above the sanction, flagged for Credit (spec 7.3): ' . ($cm['result']['over_sanction']['flagged'] ?? 0));
        // the scheme settings, so a schedule reads the interest and instalment basis its scheme states (spec v3 6.3)
        $sc = $inputs->schemes();
        $this->note('5c scheme settings', "{$sc['rows']} schemes in SM_01 and DD_10: {$sc['upserted']} loaded ({$sc['from_dd10']} with DD_10), " . count($sc['refused']) . ' refused'
            . ($sc['refused'] !== [] ? ': ' . json_encode($sc['refused']) : ''));""")
edit(p, r"""                $workflow->approve($c, $user, LoanBookBuildService::BOOTSTRAP_LABEL);""", r"""                // labelled, never a MAIIC approval: the label is recorded on the contract (schedule_approver_label)
                $workflow->approve($c, $user, LoanBookBuildService::BOOTSTRAP_LABEL, LoanBookBuildService::BOOTSTRAP_LABEL);""")
