import sys
p = r'c:\xampp\htdocs\MAICC-IFRS9\app\Services\Fli\FliProjectionService.php'
s = open(p, encoding='utf-8').read()
old_start = s.index("            foreach ($segments as $pid => $seg) {\n                DB::table('ecl_projection_runs')->insert(")
old_end = s.index("        });\n        AuditLoggerService::log('FLI Scenarios Projected'")
s = s[:old_start] + """            DB::table('ecl_projection_runs')->insert(['run_id' => $runId, 'reporting_period' => $period, 'scope_type' => 'portfolio', 'scope_value' => implode(',', array_keys($segments)),
                'methodology_version' => self::SOURCE, 'status' => 'COMPLETED', 'contracts_processed' => array_sum(array_column($segments, 'loans')), 'contracts_unresolved' => 0,
                'input_snapshot' => json_encode(['segments' => array_values(array_map(fn ($seg) => $seg + ['summary' => $summary[$seg['portfolio_id']] ?? []], $segments)),
                    'model_version' => ['id' => $fit->model_version_id, 'version' => $fit->version, 'relationship' => "{$fit->statistic_code} -> {$fit->proxy_code} (lag {$fit->lag_months})", 'effective_from' => $fit->effective_from],
                    'set' => ['id' => (int) $set->id, 'name' => $set->name, 'version' => (int) $set->version], 'horizon' => $h, 'method' => $method, 'adjustments' => $byYear]),
                'created_by' => $userId, 'completed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
""" + s[old_end:]
a = s.index("    public function latest(string $period): ?array")
b = s.index("    /** The engine's annualised lifetime PD")
s = s[:a] + """    public function latest(string $period): ?array
    {
        $run = DB::table('ecl_projection_runs')->where('reporting_period', $period)->where('methodology_version', self::SOURCE)->where('status', 'COMPLETED')->orderByDesc('id')->first();
        if ($run === null) {
            return null;
        }
        $in = json_decode((string) $run->input_snapshot, true);
        $segments = [];
        foreach ($in['segments'] ?? [] as $seg) {
            $rows = DB::table('ecl_pd_term_structures')->where('reporting_period', $period)->where('source', self::SOURCE)->where('contract_id', 'portfolio:' . $seg['portfolio_id'])
                ->orderBy('period_index')->get(['scenario_code', 'period_index', 'projection_date', 'conditional_pd', 'survival_open', 'marginal_pd', 'cumulative_pd']);
            $segments[] = $seg + ['terms' => $rows->groupBy('scenario_code')->map(fn ($g) => $g->values())->all(),
                'engine_lifetime_pd' => $this->engineLifetime((float) $seg['pd_pre'], (int) $seg['years'])];
        }

        return ['run_id' => $run->run_id, 'created_at' => (string) $run->completed_at, 'model_version' => $in['model_version'] ?? null, 'set' => $in['set'] ?? null,
            'horizon' => $in['horizon'] ?? null, 'method' => $in['method'] ?? null, 'adjustments' => $in['adjustments'] ?? [], 'segments' => $segments];
    }

""" + s[b:]
open(p, 'w', encoding='utf-8').write(s)
print('ok')
