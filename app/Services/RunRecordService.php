<?php

namespace App\Services;

use App\Services\Eir\GovernanceService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;

/**
 * Run records (Staging Module Specification v1, section 8.8, items 1 to 3;
 * acceptance 16 and 17): what each run of a month ran on and what it
 * produced, and which run a locked month reports.
 *
 * A run is started (RUNNING), its engines record their steps, and at the end
 * it is finished: the run number is stamped on the month's result rows, every
 * input is frozen in one snapshot with a SHA-256 fingerprint, and the month's
 * headline results are kept beside it. A month-end or bootstrap run that
 * completes supersedes the earlier runs of an unlocked month; they are kept.
 * Locking the month names its run of record.
 */
class RunRecordService
{
    public const MONTH_END = 'MONTH_END';

    public const BOOTSTRAP = 'BOOTSTRAP';

    public const SCREEN = 'SCREEN';

    public const RUNNING = 'RUNNING';

    public const COMPLETED = 'COMPLETED';

    public const FAILED = 'FAILED';

    public const SUPERSEDED = 'SUPERSEDED';

    /**
     * How each result table is stamped: 'month' every row of the month (the run recomputed the month);
     * 'written' the rows of the month the run created or changed; the period column and its form.
     */
    public const STAMP = [
        'loan_books' => ['month', 'reporting_period', 'ym'],
        'expected_credit_loss' => ['written', 'reporting_period', 'ym'],
        'eir_amortisation' => ['written', 'reporting_period', 'ym'],
        'segment_parameter_runs' => ['written', 'reporting_period', 'ym'],
        'segment_parameter_results' => ['written', 'reporting_period', 'ym'],
        'loss_given_default' => ['written', 'reporting_period', 'date'],
        'megafarm_ecl_runs' => ['written', 'reporting_period', 'ym'],
        'fli_run_snapshots' => ['written', 'reporting_period', 'ym'],
        'ecl_projection_runs' => ['written', 'reporting_period', 'ym'],
        'ecl_scenario_results' => ['written', 'reporting_period', 'ym'],
    ];

    public static function available(): bool
    {
        return Schema::hasTable('run_records');
    }

    public function start(string $period, string $kind, ?int $userId, ?string $label = null): int
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
            throw new LogicException("A run is of a month, YYYY-MM, not '{$period}'.");
        }

        return (int) DB::table('run_records')->insertGetId([
            'reporting_period' => $period, 'kind' => $kind, 'status' => self::RUNNING, 'label' => $label === null ? null : mb_substr($label, 0, 255),
            'started_by' => $userId, 'started_at' => now(), 'code_version' => self::codeVersion(), 'steps' => json_encode([]),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function stepStarted(int $runId, string $engine): int
    {
        $steps = json_decode((string) DB::table('run_records')->where('id', $runId)->value('steps'), true) ?: [];
        $steps[] = ['engine' => $engine, 'started_at' => now()->toDateTimeString(), 'finished_at' => null, 'ok' => null];
        DB::table('run_records')->where('id', $runId)->update(['steps' => json_encode($steps), 'updated_at' => now()]);

        return count($steps) - 1;
    }

    public function stepFinished(int $runId, int $step, bool $ok, ?string $error = null): void
    {
        $steps = json_decode((string) DB::table('run_records')->where('id', $runId)->value('steps'), true) ?: [];
        if (isset($steps[$step])) {
            $steps[$step]['finished_at'] = now()->toDateTimeString();
            $steps[$step]['ok'] = $ok;
            if ($error !== null) {
                $steps[$step]['error'] = mb_substr($error, 0, 500);
            }
        }
        DB::table('run_records')->where('id', $runId)->update(['steps' => json_encode($steps), 'updated_at' => now()]);
    }

    /** Finish a run: stamp, freeze, fingerprint; a completed month-end or bootstrap run supersedes the month's earlier runs. */
    public function finish(int $runId, bool $ok, ?string $error = null): void
    {
        $run = DB::table('run_records')->where('id', $runId)->first();
        if ($run === null || $run->status !== self::RUNNING) {
            return;
        }
        $this->stamp($run);
        $this->keepLoans($run);
        $snapshot = $this->snapshot($run->reporting_period, (string) $run->code_version);
        DB::table('run_records')->where('id', $runId)->update([
            'status' => $ok ? self::COMPLETED : self::FAILED, 'finished_at' => now(), 'error' => $error === null ? null : mb_substr($error, 0, 2000),
            'snapshot' => json_encode($snapshot), 'fingerprint' => self::fingerprint($snapshot), 'results' => json_encode($this->results($run->reporting_period)),
            'updated_at' => now(),
        ]);
        if ($ok && $run->kind !== self::SCREEN) {
            DB::table('run_records')->where('reporting_period', $run->reporting_period)->where('id', '<', $runId)->where('status', self::COMPLETED)->whereNull('of_record_at')
                ->update(['status' => self::SUPERSEDED, 'superseded_by_run' => $runId, 'updated_at' => now()]);
        }
        AuditLoggerService::log('Run Record ' . ($ok ? 'Completed' : 'Failed'), 'run_records', $runId, ['reporting_period' => $run->reporting_period,
            'new_values' => ['kind' => $run->kind, 'fingerprint' => self::fingerprint($snapshot), 'error' => $error], 'meta' => ['user' => $run->started_by]]);
    }

    /** The run number on the month's result rows (item 2). */
    private function stamp(object $run): void
    {
        foreach (self::STAMP as $table => [$how, $column, $form]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'run_record_id')) {
                continue;
            }
            $q = DB::table($table);
            $form === 'date' ? $q->where($column, 'like', $run->reporting_period . '%') : $q->where($column, $run->reporting_period);
            if ($how === 'written') {
                $q->where(fn ($w) => $w->where('created_at', '>=', $run->started_at)->orWhere('updated_at', '>=', $run->started_at));
            }
            $q->update(['run_record_id' => $run->id]);
        }
    }

    /** Each loan's figures as the run left them (item 6): what a re-performance is checked against, loan by loan. */
    private function keepLoans(object $run): void
    {
        if (! Schema::hasTable('run_record_loans') || ! Schema::hasTable('loan_books')) {
            return;
        }
        DB::table('run_record_loans')->where('run_record_id', $run->id)->delete();
        foreach (self::loanFigures(DB::connection(), $run->reporting_period) as $chunk) {
            DB::table('run_record_loans')->insert(array_map(fn ($l) => ['run_record_id' => $run->id] + $l, $chunk));
        }
    }

    /**
     * The month's figures per loan from a database connection (this one, or a re-performance workspace),
     * in chunks for insert: stage, PD, LGD, EAD, ECL and discounted ECL from the loan book; EIR interest and
     * closing gross carrying amount from the revenue roll-forward.
     *
     * @return list<list<array>>
     */
    public static function loanFigures(\Illuminate\Database\ConnectionInterface $db, string $period): array
    {
        $schema = $db->getSchemaBuilder();
        $has = fn (string $c) => $schema->hasColumn('loan_books', $c);
        $cols = array_filter(['contract_id' => 'contract_id', 'stage' => 'ifrs9_stage', 'pd' => 'pd_post_fli', 'lgd' => 'lgd_value', 'ead' => 'ead', 'ecl' => 'ecl_value', 'ecl_discounted' => 'ecl_value_discounted'], $has);
        $revenue = $schema->hasTable('eir_amortisation')
            ? $db->table('eir_amortisation')->where('reporting_period', $period)->get(['contract_id', 'interest_accrued', 'closing_gross'])->keyBy('contract_id') : collect();
        $rows = [];
        foreach ($db->table('loan_books')->where('reporting_period', $period)->orderBy('contract_id')->get(array_values($cols)) as $b) {
            $l = [];
            foreach ($cols as $name => $col) {
                $l[$name] = $b->{$col};
            }
            $l += ['stage' => null, 'pd' => null, 'lgd' => null, 'ead' => null, 'ecl' => null, 'ecl_discounted' => null];
            $l['contract_id'] = (string) $b->contract_id;
            $r = $revenue->get($b->contract_id);
            $l['eir_interest'] = $r?->interest_accrued;
            $l['closing_gross'] = $r?->closing_gross;
            $rows[$l['contract_id']] = $l; // one row per loan: a loan the book holds twice keeps its last
        }

        return array_chunk(array_values($rows), 500);
    }

    /** SHA-256 of the snapshot in a canonical form (keys sorted at every level). */
    public static function fingerprint(array $snapshot): string
    {
        return hash('sha256', json_encode(self::canonical($snapshot), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
    }

    private static function canonical(mixed $v): mixed
    {
        if (! is_array($v)) {
            return $v;
        }
        if (! array_is_list($v)) {
            ksort($v);
        }

        return array_map(fn ($x) => self::canonical($x), $v);
    }

    /** Whether a run's stored snapshot still matches its fingerprint (acceptance 16). */
    public function verifies(object $run): bool
    {
        $snapshot = json_decode((string) $run->snapshot, true);

        return is_array($snapshot) && $run->fingerprint !== null && hash_equals((string) $run->fingerprint, self::fingerprint($snapshot));
    }

    /** The repository commit the code runs from: APP_COMMIT when the server sets it, else read from .git; 'unknown' otherwise. */
    public static function codeVersion(): string
    {
        $env = trim((string) env('APP_COMMIT', ''));
        if ($env !== '') {
            return mb_substr($env, 0, 64);
        }
        try {
            $git = base_path('.git');
            if (is_file($git) && preg_match('/^gitdir:\s*(.+)$/m', (string) file_get_contents($git), $m)) {
                $git = trim($m[1]); // a worktree: its own HEAD, the refs in the common directory
            }
            if (! is_dir($git)) {
                return 'unknown';
            }
            $head = trim((string) @file_get_contents($git . '/HEAD'));
            if (preg_match('/^[0-9a-f]{40}$/', $head)) {
                return $head;
            }
            if (! preg_match('/^ref:\s*(.+)$/', $head, $m)) {
                return 'unknown';
            }
            $common = is_file($git . '/commondir') ? realpath($git . '/' . trim((string) file_get_contents($git . '/commondir'))) : $git;
            foreach (array_unique([$git, $common]) as $dir) {
                $ref = trim((string) @file_get_contents($dir . '/' . $m[1]));
                if (preg_match('/^[0-9a-f]{40}$/', $ref)) {
                    return $ref;
                }
                if (is_file($dir . '/packed-refs') && preg_match('/^([0-9a-f]{40}) ' . preg_quote($m[1], '/') . '$/m', (string) file_get_contents($dir . '/packed-refs'), $p)) {
                    return $p[1];
                }
            }
        } catch (\Throwable) {
        }

        return 'unknown';
    }

    /**
     * Every input of the month, as it stands at the end of the run (item 1): the Governance Centre
     * settings in force at the month-end with their proposer and approver, the staging threshold rows,
     * the fee rules in use with their approvals, the scenario set, the overlays, the forward-looking
     * model version and the month's forward-looking record, the query register, the data loads, the
     * loan book build, the locked EIRs and the code version.
     */
    public function snapshot(string $period, string $codeVersion): array
    {
        $end = CarbonImmutable::parse($period . '-01')->endOfMonth();
        $has = fn (string $t) => Schema::hasTable($t);
        $gov = app(GovernanceService::class);
        $settings = [];
        foreach (GovernanceService::keys() as $key) {
            $s = $gov->inForce($key, $end);
            $settings[$key] = $s === null ? null : ['value' => $s->value, 'effective_from' => $s->effective_from?->toDateString(), 'change' => (int) $s->id,
                'proposed_by' => $s->set_by, 'approved_by' => $s->approved_by, 'approved_at' => $s->approved_at?->toDateTimeString()];
        }
        $rows = fn ($q, array $cols) => $q->get($cols)->map(fn ($r) => (array) $r)->all();

        return [
            'month' => $period,
            'code_version' => $codeVersion,
            'governance_settings' => $settings,
            'staging_thresholds' => $has('staging_thresholds') ? $rows(DB::table('staging_thresholds')->whereDate('effective_from', '<=', $end->toDateString())
                ->where(fn ($q) => Schema::hasColumn('staging_thresholds', 'status') ? $q->where('status', 'APPROVED')->orWhereNull('status') : $q)->orderBy('facility_class')->orderBy('effective_from')->orderBy('id'),
                array_values(array_filter(['id', 'facility_class', 'min_tenor_months', 'stage2_dpd', 'stage3_dpd', 'effective_from', 'approved_by', 'approved_at'], fn ($c) => Schema::hasColumn('staging_thresholds', $c)))) : [],
            'fee_rules' => $has('eir_accounting_rules') ? $rows(DB::table('eir_accounting_rules')->where('active', true)->whereNotNull('approved_at')
                ->when(Schema::hasColumn('eir_accounting_rules', 'status'), fn ($q) => $q->where('status', 'APPROVED'))->orderBy('priority')->orderBy('id'),
                array_values(array_filter(['id', 'name', 'fee_type', 'proposed_integral', 'priority', 'approved_by', 'approved_at', 'approval_to_confirm'], fn ($c) => Schema::hasColumn('eir_accounting_rules', $c)))) : [],
            'scenario_set' => $has('governed_scenario_sets') ? (array) (DB::table('governed_scenario_sets')->where('reporting_period', $period)->whereIn('status', ['APPROVED', 'LOCKED'])
                ->orderByDesc('version')->first(['id', 'name', 'version', 'status', 'approved_by', 'approver_label', 'approved_at']) ?? []) : [],
            'overlays' => $has('fli_overlays') ? $rows(DB::table('fli_overlays')->where('status', 'APPROVED')->where('reporting_period', '<=', $period)
                ->where(fn ($q) => $q->whereNull('expiry_period')->orWhere('expiry_period', '>=', $period))->orderBy('id'), ['id', 'reporting_period', 'expiry_period', 'approved_by', 'approver_label']) : [],
            'fli_model_versions' => $has('fli_model_versions') ? $rows(DB::table('fli_model_versions')->where('status', 'PUBLISHED')->where('effective_from', '<=', $period)
                ->orderBy('model_id')->orderByDesc('effective_from')->orderByDesc('id'), ['id', 'model_id', 'version', 'effective_from', 'published_by', 'approver_label']) : [],
            'fli_record' => $has('fli_run_snapshots') ? (function () use ($period) {
                $f = DB::table('fli_run_snapshots')->where('reporting_period', $period)->whereIn('status', ['RECORDED', 'SIGNED_OFF'])->orderByDesc('id')->first(['id', 'status', 'inputs']);

                return $f === null ? null : ['id' => (int) $f->id, 'status' => $f->status, 'inputs' => json_decode((string) $f->inputs, true)];
            })() : null,
            'query_register' => $has('ebanker_queries') ? $rows(DB::table('ebanker_queries')->orderBy('query_id'), ['query_id', 'version', 'sha256']) : [],
            'data_loads' => $has('ebanker_loads') ? $rows(DB::table('ebanker_loads')->where('status', 'LANDED')->orderBy('id'), ['id', 'pack_name', 'pack_hash', 'period', 'watermarks']) : [],
            'loan_book' => $has('loan_books') ? [
                'loads' => DB::table('loan_books')->where('reporting_period', $period)->select('build_load_id', 'build_method')->distinct()->orderBy('build_load_id')->get()->map(fn ($r) => (array) $r)->all(),
                'build' => $has('loan_book_builds') ? (array) (DB::table('loan_book_builds')->where('period_from', '<=', $period)->where('period_to', '>=', $period)->whereIn('status', ['BUILT', 'APPROVED'])
                    ->orderByDesc('id')->first(['id', 'method', 'status', 'approved_by', 'approver_label', 'built_at']) ?? []) : [],
            ] : [],
            'locked_eirs' => $has('contract_eir') ? (function () {
                $locked = DB::table('contract_eir')->whereNotNull('locked_at')->orderBy('contract_id')->get(['contract_id', 'eir_effective_annual'])
                    ->map(fn ($c) => $c->contract_id . ':' . number_format((float) $c->eir_effective_annual, 12, '.', ''))->all();

                return ['count' => count($locked), 'sha256' => hash('sha256', implode("\n", $locked))];
            })() : [],
        ];
    }

    /** The month's headline results as the run left them (for "What changed" and re-performance). */
    public function results(string $period): array
    {
        if (! Schema::hasTable('loan_books')) {
            return [];
        }
        $q = DB::table('loan_books')->where('reporting_period', $period);
        $out = ['loans' => (clone $q)->count()];
        if (Schema::hasColumn('loan_books', 'ifrs9_stage')) {
            $out['by_stage'] = (clone $q)->selectRaw('ifrs9_stage as s, count(*) as n')->groupBy('ifrs9_stage')->pluck('n', 's')->map(fn ($n) => (int) $n)->all();
        }
        foreach (['ecl' => 'ecl_value', 'ecl_discounted' => 'ecl_value_discounted'] as $name => $col) {
            if (Schema::hasColumn('loan_books', $col)) {
                $out[$name] = round((float) (clone $q)->sum($col), 2);
            }
        }
        if (Schema::hasTable('eir_amortisation') && Schema::hasColumn('eir_amortisation', 'interest_accrued')) {
            $out['eir_interest'] = round((float) DB::table('eir_amortisation')->where('reporting_period', $period)->sum('interest_accrued'), 2);
        }

        return $out;
    }

    /**
     * The run a lock names (item 3): the latest completed month-end or bootstrap run of the month.
     * Refused when there is none, or when an engine run on a screen has changed the month since.
     */
    public function runOfRecord(string $period): object
    {
        if (! self::available()) {
            throw new LogicException('This database has no run records yet (run the migrations).');
        }
        $run = DB::table('run_records')->where('reporting_period', $period)->whereIn('kind', [self::MONTH_END, self::BOOTSTRAP])
            ->where('status', self::COMPLETED)->orderByDesc('id')->first();
        if ($run === null) {
            throw new LogicException("No completed month-end run of {$period} to lock: run the month-end job for the month first, so the lock names the run whose figures it reports.");
        }
        $later = DB::table('run_records')->where('reporting_period', $period)->where('kind', self::SCREEN)->where('id', '>', $run->id)
            ->whereIn('status', [self::COMPLETED, self::FAILED])->orderBy('id')->get(['id', 'label']);
        if ($later->isNotEmpty()) {
            throw new LogicException("The month-end run {$run->id} of {$period} is not what the month now holds: " . $later->map(fn ($l) => "run {$l->id} ({$l->label})")->implode(', ')
                . ' changed it from a screen afterwards. Run the month-end job again, then lock.');
        }

        return $run;
    }

    /**
     * The run that stands for a month in the reports: its run of record while locked; else the latest
     * completed month-end or bootstrap run; else the latest completed run of any kind; null if none.
     */
    public function monthRun(string $period): ?object
    {
        if (! self::available()) {
            return null;
        }
        $q = fn () => DB::table('run_records')->where('reporting_period', $period);

        return $q()->whereNotNull('of_record_at')->whereNull('of_record_until')->orderByDesc('id')->first()
            ?? $q()->whereIn('kind', [self::MONTH_END, self::BOOTSTRAP])->where('status', self::COMPLETED)->orderByDesc('id')->first()
            ?? $q()->where('status', self::COMPLETED)->orderByDesc('id')->first();
    }

    /**
     * A snapshot as sections of named items, each with its value in words (item 5): what "Settings in
     * force" shows for a month, and what "What changed" compares between two.
     *
     * @return array<string, array<string, string>>
     */
    public static function flatten(array $s): array
    {
        $out = ['Code version' => ['Repository commit' => (string) ($s['code_version'] ?? 'unknown')]];
        foreach ((array) ($s['governance_settings'] ?? []) as $key => $v) {
            $out['Governance Centre settings'][$key] = $v === null ? 'not set' : $v['value'] . ' (from ' . $v['effective_from'] . ', change ' . $v['change'] . ')';
        }
        foreach ((array) ($s['staging_thresholds'] ?? []) as $r) {
            $out['Staging thresholds'][$r['facility_class'] . ' from ' . substr((string) $r['effective_from'], 0, 10)] = 'Stage 2 at ' . ($r['stage2_dpd'] ?? '?') . ' days, Stage 3 at ' . ($r['stage3_dpd'] ?? '?') . ' days';
        }
        foreach ((array) ($s['fee_rules'] ?? []) as $r) {
            $out['Fee rules in use'][(string) $r['name']] = ((bool) ($r['proposed_integral'] ?? false) ? 'integral' : 'not integral') . ', priority ' . ($r['priority'] ?? '?')
                . ', approved ' . ($r['approved_at'] ?? '?') . (! empty($r['approval_to_confirm']) ? ' (to be confirmed)' : '');
        }
        $set = (array) ($s['scenario_set'] ?? []);
        $out['Scenario set']['Set in force'] = $set === [] ? 'none' : ($set['name'] ?? 'set') . ' version ' . ($set['version'] ?? '?') . ' (' . ($set['status'] ?? '?') . ', set ' . ($set['id'] ?? '?') . ')';
        foreach ((array) ($s['overlays'] ?? []) as $o) {
            $out['Forward-looking overlays']['Overlay ' . $o['id']] = $o['reporting_period'] . ' to ' . ($o['expiry_period'] ?? 'open');
        }
        foreach ((array) ($s['fli_model_versions'] ?? []) as $m) {
            $out['Forward-looking model'][$m['model_id'] . ' version ' . $m['version']] = 'from ' . $m['effective_from'] . ' (version ' . $m['id'] . ')';
        }
        $fli = $s['fli_record'] ?? null;
        $out['Forward-looking month record']['Record'] = $fli === null ? 'none' : 'record ' . $fli['id'] . ' (' . $fli['status'] . ')';
        foreach ((array) ($s['query_register'] ?? []) as $q) {
            $out['Query register'][(string) $q['query_id']] = 'version ' . $q['version'] . ', ' . substr((string) ($q['sha256'] ?? ''), 0, 12);
        }
        foreach ((array) ($s['data_loads'] ?? []) as $l) {
            $out['Data loads']['Load ' . $l['id'] . ' ' . $l['pack_name']] = substr((string) $l['pack_hash'], 0, 12) . ($l['watermarks'] ? ', watermarks ' . substr(hash('sha256', (string) $l['watermarks']), 0, 12) : '');
        }
        $book = (array) ($s['loan_book'] ?? []);
        $build = (array) ($book['build'] ?? []);
        $out['Loan book']['Build'] = $build === [] ? 'none' : 'build ' . ($build['id'] ?? '?') . ' by ' . ($build['method'] ?? '?') . ' (' . ($build['status'] ?? '?') . (($build['approver_label'] ?? null) ? ', ' . $build['approver_label'] : '') . ')';
        $out['Loan book']['Loads the month was built from'] = implode(', ', array_map(fn ($l) => ($l['build_load_id'] ?? 'none') . '/' . ($l['build_method'] ?? '?'), (array) ($book['loads'] ?? []))) ?: 'none';
        $eirs = (array) ($s['locked_eirs'] ?? []);
        $out['Locked EIRs']['Locked EIRs'] = ($eirs['count'] ?? 0) . ' (rates fingerprint ' . substr((string) ($eirs['sha256'] ?? ''), 0, 12) . ')';

        return $out;
    }

    /**
     * Every item that differs between two snapshots (item 5): section, item, the first month's value and the second's.
     *
     * @return list<array{0:string,1:string,2:string,3:string}>
     */
    public static function differences(array $a, array $b): array
    {
        $fa = self::flatten($a);
        $fb = self::flatten($b);
        $out = [];
        foreach (array_unique(array_merge(array_keys($fa), array_keys($fb))) as $section) {
            $ia = $fa[$section] ?? [];
            $ib = $fb[$section] ?? [];
            foreach (array_unique(array_merge(array_keys($ia), array_keys($ib))) as $item) {
                $va = $ia[$item] ?? '(none)';
                $vb = $ib[$item] ?? '(none)';
                if ($va !== $vb) {
                    $out[] = [$section, (string) $item, $va, $vb];
                }
            }
        }

        return $out;
    }

    public function markOfRecord(int $runId, ?int $userId): void
    {
        DB::table('run_records')->where('id', $runId)->update(['of_record_at' => now(), 'of_record_by' => $userId, 'of_record_until' => null, 'updated_at' => now()]);
    }

    /** On unlock: the run stays on record as the one the month reported until now. */
    public function releaseOfRecord(string $period): void
    {
        if (! self::available()) {
            return;
        }
        DB::table('run_records')->where('reporting_period', $period)->whereNotNull('of_record_at')->whereNull('of_record_until')->update(['of_record_until' => now(), 'updated_at' => now()]);
    }
}
