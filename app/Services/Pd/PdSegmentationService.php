<?php

namespace App\Services\Pd;

use App\Services\AuditLoggerService;
use App\Services\Eir\GovernanceService;
use App\Services\TransitionMatrixService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * The PD by segment (9 October 2026).
 *
 * The book is split into its real programmes, and the probability of
 * default is measured per segment on the governed basis
 * (pd_segmentation_basis): each portfolio, the pooled book, each RBM
 * economic sector, or each sector inside each portfolio. The measure is
 * the one PdEngineService uses: the loans in a stage at the start of a
 * twelve-month window, followed to its end; the PD from a stage is the
 * balance that reached Stage 3 (or left the book unpaid) over the balance
 * that started in it, annualised when the book holds a shorter window.
 * Every segment shares the same window, so the PDs are comparable.
 *
 * A stage of a segment is thin when it has fewer loans or defaults at the
 * window start than the governed minimum (pd_segment_min_observations).
 * A thin stage that the period needs then follows the governed rule
 * (pd_segment_thin_rule): it takes the same stage's PD from the segment
 * above it (a sector inside a portfolio takes its portfolio's, a portfolio
 * or a sector takes the pooled book's), provided that parent meets the
 * minimum itself; or, under the fail-closed option, the run stops. There
 * is no other fallback: a stage with no usable PD stops the run, names the
 * segment and the reason, and nothing is written to the loan book.
 *
 * Loans whose E-Banker industry code is one the governance holds apart
 * (pd_sector_unverified_codes, by default 4290, the code E-Banker gives a
 * loan whose sector was never captured) form their own "unverified
 * sector" segment in a sector basis. Their codes are never changed.
 *
 * Lineage: every segment measured is written to transition_matrices (level
 * book, portfolio, rbm_sector or portfolio_rbm_sector; the segment key in
 * pd_calculation_code) with its cells in transition_matrices_data; every
 * cell's outcome to segment_parameter_results; the run to
 * segment_parameter_runs; and each loan carries the segment it belongs
 * to, the segment whose PD it took, the run and the matrix.
 */
class PdSegmentationService
{
    public const BASES = [
        'By portfolio' => 'portfolio',
        'Pooled book' => 'book',
        'By RBM sector' => 'sector',
        'By portfolio and RBM sector' => 'portfolio_sector',
    ];

    /** transition_matrices.pd_calculation_level by segment kind */
    public const MATRIX_LEVEL = ['book' => 'book', 'portfolio' => 'portfolio', 'sector' => 'rbm_sector', 'portfolio_sector' => 'portfolio_rbm_sector'];

    public const UNVERIFIED = 'unverified';
    public const UNCLASSIFIED = 'none';

    private ?array $portfolioNames = null;
    private ?array $sectorNames = null;

    public function __construct(private GovernanceService $governance)
    {
    }

    /* ------------------------------------------------------------------ */
    /*  The governed settings, read as at the period end                   */
    /* ------------------------------------------------------------------ */

    /** @return array{basis:string,basis_option:string,min_loans:int,min_defaults:int,minimum_option:string,thin:string,thin_option:string,unverified:list<string>,unverified_option:string} */
    public function settings(string $period): array
    {
        $asOf = self::periodEnd($period);
        $basis = $this->governance->get('pd_segmentation_basis', $asOf);
        $minimum = $this->governance->get('pd_segment_min_observations', $asOf);
        $thin = $this->governance->get('pd_segment_thin_rule', $asOf);
        $unverified = $this->governance->get('pd_sector_unverified_codes', $asOf);
        [$loans, $defaults] = self::parseMinimum($minimum);

        return ['basis' => self::parseBasis($basis), 'basis_option' => $basis, 'min_loans' => $loans, 'min_defaults' => $defaults, 'minimum_option' => $minimum,
            'thin' => self::parseThinRule($thin), 'thin_option' => $thin, 'unverified' => self::parseUnverified($unverified), 'unverified_option' => $unverified];
    }

    public static function parseBasis(string $option): string
    {
        if (! isset(self::BASES[$option])) {
            throw new InvalidArgumentException("'{$option}' is not a PD segmentation basis the engine knows.");
        }

        return self::BASES[$option];
    }

    /** "10 loans and 1 default per stage" => [10, 1]; "10 loans per stage, defaults not counted" => [10, 0] */
    public static function parseMinimum(string $option): array
    {
        if (preg_match('/(\d+)\s+loans?/i', $option, $l) !== 1) {
            throw new InvalidArgumentException("The minimum '{$option}' names no number of loans.");
        }
        $defaults = preg_match('/(\d+)\s+defaults?/i', $option, $d) === 1 ? (int) $d[1] : 0;

        return [(int) $l[1], $defaults];
    }

    /** 'parent' or 'fail' */
    public static function parseThinRule(string $option): string
    {
        $t = strtolower(trim($option));
        if (str_starts_with($t, 'take the parent')) {
            return 'parent';
        }
        if (str_starts_with($t, 'fail closed')) {
            return 'fail';
        }

        throw new InvalidArgumentException("The thin-segment rule '{$option}' is neither the parent rule nor fail closed.");
    }

    /** @return list<string> the four-digit E-Banker industry codes held apart */
    public static function parseUnverified(string $option): array
    {
        preg_match_all('/\b(\d{4})\b/', $option, $m);

        return array_values(array_unique($m[1]));
    }

    public static function periodEnd(string $period): CarbonImmutable
    {
        return CarbonImmutable::parse(substr($period, 0, 7) . '-01')->endOfMonth()->startOfDay();
    }

    /* ------------------------------------------------------------------ */
    /*  Segments                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * The RBM sector code of a loan from E-Banker's sector label: the label
     * reads "<row>-<code>. <name>" ("3-3. Manufacturing", "8-9. Financial
     * and Insurance ..."), and the code after the dash is the directive's
     * sector (industry_types.code). Null when the loan carries no label.
     */
    public static function sectorCode(?string $industryType): ?string
    {
        $t = trim((string) $industryType);
        if ($t === '') {
            return null;
        }
        if (preg_match('/^\s*\d+\s*-\s*(\d+)\s*\./', $t, $m) === 1) {
            return $m[1];
        }
        if (preg_match('/^\s*(\d+)\s*\./', $t, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    /** The sector key of a loan row: its RBM sector, 'unverified' for a held-apart code, 'none' with no label. */
    public static function sectorKey(object $row, array $unverified): string
    {
        $code = trim((string) ($row->industry_code ?? ''));
        if ($code !== '' && in_array($code, $unverified, true)) {
            return self::UNVERIFIED;
        }

        return self::sectorCode($row->industry_type ?? null) ?? self::UNCLASSIFIED;
    }

    /** The segment key of a loan on a basis, e.g. "portfolio:3", "sector:5", "portfolio:3|sector:5", "book". */
    public static function segmentKey(string $basis, object $row, array $unverified): string
    {
        return match ($basis) {
            'book' => 'book',
            'portfolio' => 'portfolio:' . (int) $row->loan_portfolio_id,
            'sector' => 'sector:' . self::sectorKey($row, $unverified),
            'portfolio_sector' => 'portfolio:' . (int) $row->loan_portfolio_id . '|sector:' . self::sectorKey($row, $unverified),
            default => throw new InvalidArgumentException("Unknown segmentation basis '{$basis}'."),
        };
    }

    /** The segment above: portfolio x sector -> portfolio -> book; sector -> book; book -> none. */
    public static function parentOf(string $key): ?string
    {
        if ($key === 'book') {
            return null;
        }
        if (str_contains($key, '|')) {
            return explode('|', $key)[0];
        }

        return 'book';
    }

    /** The segment and every segment above it. @return list<string> */
    public static function chain(string $key): array
    {
        $out = [];
        for ($k = $key; $k !== null; $k = self::parentOf($k)) {
            $out[] = $k;
        }

        return $out;
    }

    public static function kindOf(string $key): string
    {
        return match (true) {
            $key === 'book' => 'book',
            str_contains($key, '|') => 'portfolio_sector',
            str_starts_with($key, 'portfolio:') => 'portfolio',
            default => 'sector',
        };
    }

    /** A readable name for a segment key. */
    public function label(string $key): string
    {
        $this->portfolioNames ??= Schema::hasTable('loan_portfolios') ? DB::table('loan_portfolios')->pluck('name', 'id')->all() : [];
        $this->sectorNames ??= Schema::hasTable('industry_types') ? DB::table('industry_types')->pluck('name', 'code')->all() : [];
        $portfolios = $this->portfolioNames;
        $sectors = $this->sectorNames;
        $parts = [];
        foreach (explode('|', $key) as $p) {
            if ($p === 'book') {
                $parts[] = 'Pooled book';
            } elseif (str_starts_with($p, 'portfolio:')) {
                $id = (int) substr($p, 10);
                $parts[] = $portfolios[$id] ?? "Portfolio {$id}";
            } else {
                $s = substr($p, 7);
                $parts[] = match ($s) {
                    self::UNVERIFIED => 'Unverified sector (E-Banker default code)',
                    self::UNCLASSIFIED => 'No sector captured',
                    default => isset($sectors[$s]) ? "{$s}. {$sectors[$s]}" : "Sector {$s}",
                };
            }
        }

        return implode(' / ', $parts);
    }

    /* ------------------------------------------------------------------ */
    /*  The measure (read only)                                            */
    /* ------------------------------------------------------------------ */

    /** The transition profile the PD engine grades on (M101). */
    private function profile(): array
    {
        $profile = DB::table('transition_profile_definitions')->where('profile_code', 'M101')->first()
            ?? DB::table('transition_profile_definitions')->orderBy('id')->first();
        if (! $profile) {
            throw new RuntimeException('No transition profile: run MaiicTransitionProfileSeeder.');
        }
        $options = DB::table('transition_profile_options')->where('profile_id', $profile->id)->orderBy('ordering_index')->get();
        $start = $options->filter(fn ($o) => strtolower($o->is_start_or_end) === 'start')->pluck('category_name')->map(fn ($v) => (string) $v)->values()->all();
        $end = $options->filter(fn ($o) => strtolower($o->is_start_or_end) === 'end')->pluck('category_name')->map(fn ($v) => (string) $v)->values()->all();
        $default = (string) ($options->first(fn ($o) => strtolower($o->is_start_or_end) === 'end' && (int) ($o->default_value ?? 0) === 1)->category_name ?? '3');

        return ['row' => $profile, 'start' => $start, 'end' => $end, 'default' => $default];
    }

    /** The window: twelve months (or the governed length) to the period, clipped to the first staged month of the scope. */
    public function window(string $period, int $windowMonths, array $portfolioIds): array
    {
        $stageExpr = TransitionMatrixService::gradeExpression('loan_books', 'ifrs9stage_post_qualitative');
        $end = CarbonImmutable::parse($period . '-01');
        $start = $end->subMonths($windowMonths);
        $available = DB::table('loan_books')->whereRaw("{$stageExpr} IS NOT NULL")->whereIn('loan_portfolio_id', $portfolioIds)->min('reporting_period');
        if ($available === null) {
            throw new RuntimeException('No staged loan book: run eir:stage first.');
        }
        if ($start->format('Y-m') < $available) {
            $start = CarbonImmutable::parse($available . '-01');
        }
        if ($start->format('Y-m') >= $end->format('Y-m')) {
            throw new RuntimeException("The window {$start->format('Y-m')} to {$end->format('Y-m')} holds no transition.");
        }

        return [$start->format('Y-m'), $end->format('Y-m'), max(1, (int) $start->diffInMonths($end))];
    }

    /** The portfolios with loans in the period. @return list<int> */
    public function scope(string $period): array
    {
        return DB::table('loan_books')->where('reporting_period', $period)->whereNotNull('loan_portfolio_id')
            ->distinct()->orderBy('loan_portfolio_id')->pluck('loan_portfolio_id')->map(fn ($v) => (int) $v)->all();
    }

    /**
     * Measure every segment of a basis, and every segment above them, over
     * the window to the period. Writes nothing.
     *
     * @return array{window_start:string,window_end:string,window_months:int,basis:string,profile:array,segments:array<string,array>}
     */
    public function measure(string $period, string $basis, int $windowMonths = 12, ?array $portfolioIds = null, ?array $unverified = null): array
    {
        $portfolioIds = $portfolioIds ?? $this->scope($period);
        if ($portfolioIds === []) {
            throw new RuntimeException("The loan book of {$period} holds no loan.");
        }
        $unverified = $unverified ?? [];
        $profile = $this->profile();
        $p = $profile['row'];
        [$startPeriod, $endPeriod, $months] = $this->window($period, $windowMonths, $portfolioIds);
        $sg = TransitionMatrixService::gradeExpression('s', $p->start_grading_col);
        $eg = TransitionMatrixService::gradeExpression('e', $p->end_grading_col);
        $id = $p->start_client_id_col;
        $endId = $p->end_client_id_col;

        $rows = DB::table("{$p->start_table} as s")
            ->leftJoin("{$p->end_table} as e", fn ($j) => $j->on("s.{$id}", '=', "e.{$endId}")->where('e.reporting_period', '=', $endPeriod))
            ->where('s.reporting_period', $startPeriod)->whereIn('s.loan_portfolio_id', $portfolioIds)
            ->get([DB::raw("s.{$id} as client_id"), 's.loan_portfolio_id', 's.industry_code', 's.industry_type', DB::raw('s.carrying_amount as start_bal'),
                DB::raw("{$sg} as start_grade"), DB::raw("{$eg} as end_grade"), DB::raw("e.{$endId} as end_client_id")]);

        $hasStatus = Schema::hasColumn($p->end_table, 'contract_status');
        $hasBalance = Schema::hasColumn($p->end_table, 'carrying_amount');
        $blank = fn (string $key) => ['key' => $key, 'kind' => self::kindOf($key), 'parent' => self::parentOf($key), 'label' => $this->label($key), 'portfolio_id' => null, 'sector_code' => null,
            'matrix' => [], 'stages' => []];
        $segments = [];
        foreach ($rows as $r) {
            if ($r->start_grade === null || $r->start_grade === '') {
                continue; // an unstaged start row is not an observation
            }
            $end = $r->end_grade;
            if ($r->end_client_id === null) {
                $end = TransitionMatrixService::exitGrade($p->end_table, $endId, (string) $r->client_id, $startPeriod, $endPeriod, $profile['default'], $hasStatus, $hasBalance);
            } elseif ($end === null || $end === '') {
                $end = 'Paid';
            }
            $start = (string) $r->start_grade;
            $end = (string) $end;
            $bal = (float) $r->start_bal;
            foreach (self::chain(self::segmentKey($basis, $r, $unverified)) as $key) {
                $segments[$key] ??= $blank($key);
                $s = &$segments[$key];
                $s['matrix'][$start][$end] = ($s['matrix'][$start][$end] ?? 0.0) + $bal;
                $s['stages'][$start] ??= ['loans' => 0, 'balance' => 0.0, 'default_loans' => 0, 'default_balance' => 0.0];
                $s['stages'][$start]['loans']++;
                $s['stages'][$start]['balance'] += $bal;
                if ($end === $profile['default']) {
                    $s['stages'][$start]['default_loans']++;
                    $s['stages'][$start]['default_balance'] += $bal;
                }
                unset($s);
            }
        }
        // the rate from each stage, and the annual rate
        $annualise = fn (float $x) => $months >= 12 ? $x : 1 - pow(1 - max(0.0, min(1.0, $x)), 12 / $months);
        foreach ($segments as $key => &$s) {
            foreach ($s['stages'] as $st => &$c) {
                $c['observed'] = $c['balance'] > 0 ? $c['default_balance'] / $c['balance'] : null;
                $c['annual'] = $c['observed'] === null ? null : $annualise($c['observed']);
            }
            unset($c);
            $this->describe($s);
        }
        unset($s);

        return ['window_start' => $startPeriod, 'window_end' => $endPeriod, 'window_months' => $months, 'basis' => $basis, 'profile' => $profile, 'segments' => $segments, 'portfolios' => $portfolioIds];
    }

    /** The portfolio id and sector code a segment key names. */
    private function describe(array &$s): void
    {
        foreach (explode('|', $s['key']) as $part) {
            if (str_starts_with($part, 'portfolio:')) {
                $s['portfolio_id'] = (int) substr($part, 10);
            } elseif (str_starts_with($part, 'sector:')) {
                $s['sector_code'] = substr($part, 7);
            }
        }
    }

    /** Does a stage of a measured segment meet the minimum? @return array{ok:bool,reason:?string} */
    public static function test(?array $segment, string $stage, int $minLoans, int $minDefaults): array
    {
        $c = $segment['stages'][$stage] ?? null;
        $loans = (int) ($c['loans'] ?? 0);
        $defaults = (int) ($c['default_loans'] ?? 0);
        $need = "{$minLoans} loan" . ($minLoans === 1 ? '' : 's') . ($minDefaults > 0 ? " and {$minDefaults} default" . ($minDefaults === 1 ? '' : 's') : '');
        if ($c === null || $loans === 0) {
            return ['ok' => false, 'reason' => "no loan in Stage {$stage} at the window start (minimum {$need})"];
        }
        if ((float) $c['balance'] <= 0) {
            return ['ok' => false, 'reason' => "the Stage {$stage} loans at the window start carry no balance"];
        }
        if ($loans < $minLoans || $defaults < $minDefaults) {
            return ['ok' => false, 'reason' => "{$loans} loan" . ($loans === 1 ? '' : 's') . " in Stage {$stage} at the window start and {$defaults} default" . ($defaults === 1 ? '' : 's') . ", below the minimum of {$need}"];
        }

        return ['ok' => true, 'reason' => null];
    }

    /**
     * The PD each needed cell takes under the governed rules: its own, its
     * parent's, or none (failed). Pure; used by run() and the reports.
     *
     * @param array<string,array<string,int>> $needed segment key => stage => loans of the period
     * @return array<string,array<string,array>> segment key => stage => outcome
     */
    public static function resolve(array $measure, array $needed, int $minLoans, int $minDefaults, string $thinRule): array
    {
        $segments = $measure['segments'];
        $out = [];
        foreach ($needed as $key => $stages) {
            foreach ($stages as $stage => $count) {
                $stage = (string) $stage;
                if ($stage === '3') {
                    $out[$key][$stage] = ['status' => 'in_default', 'from' => $key, 'pd' => 1.0, 'reason' => 'Stage 3 is in default: the PD is 1', 'loans' => $count];
                    continue;
                }
                $own = self::test($segments[$key] ?? null, $stage, $minLoans, $minDefaults);
                if ($own['ok']) {
                    $out[$key][$stage] = ['status' => 'own', 'from' => $key, 'pd' => (float) $segments[$key]['stages'][$stage]['annual'], 'reason' => null, 'loans' => $count];
                    continue;
                }
                if ($thinRule === 'fail') {
                    $out[$key][$stage] = ['status' => 'failed', 'from' => null, 'pd' => null, 'reason' => "Thin: {$own['reason']}; the governed rule is to fail closed", 'loans' => $count];
                    continue;
                }
                $found = null; $why = [$own['reason']];
                for ($parent = self::parentOf($key); $parent !== null; $parent = self::parentOf($parent)) {
                    $t = self::test($segments[$parent] ?? null, $stage, $minLoans, $minDefaults);
                    if ($t['ok']) {
                        $found = $parent;
                        break;
                    }
                    $why[] = "{$parent}: {$t['reason']}";
                }
                $out[$key][$stage] = $found === null
                    ? ['status' => 'failed', 'from' => null, 'pd' => null, 'reason' => 'Thin, and no segment above it meets the minimum: ' . implode('; ', $why), 'loans' => $count]
                    : ['status' => 'parent', 'from' => $found, 'pd' => (float) $segments[$found]['stages'][$stage]['annual'], 'reason' => "Thin: {$own['reason']}; takes the Stage {$stage} PD of {$found}", 'loans' => $count];
            }
        }

        return $out;
    }

    /* ------------------------------------------------------------------ */
    /*  The run                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Measure, resolve and apply the PD of a period on the governed basis.
     *
     * @return array{run_id:string,basis:string,window:string,window_months:int,updated:int,unstaged:int,cells:list<array>,failed:list<array>}
     * @throws RuntimeException when a needed stage has no PD (fail closed); the failed run is still recorded
     */
    public function run(string $period, ?array $portfolioIds = null, int $windowMonths = 12, ?int $userId = null, string $userName = 'system'): array
    {
        \App\Support\ReportingPeriodLock::assertOpen($period, 'the PD engine');
        $cfg = $this->settings($period);
        $portfolioIds = $portfolioIds ?? $this->scope($period);
        $measure = $this->measure($period, $cfg['basis'], $windowMonths, $portfolioIds, $cfg['unverified']);
        $stageExpr = TransitionMatrixService::gradeExpression('loan_books', 'ifrs9stage_post_qualitative');

        // the loans of the period, by segment and measured stage
        $loans = DB::table('loan_books')->where('reporting_period', $period)->whereIn('loan_portfolio_id', $portfolioIds)
            ->get(['id', 'loan_portfolio_id', 'industry_code', 'industry_type', 'remaining_tenor', DB::raw("{$stageExpr} as stage")]);
        $needed = [];
        $unstaged = [];
        foreach ($loans as $l) {
            if ($l->stage === null || $l->stage === '') {
                $unstaged[] = $l->id;
                continue;
            }
            $key = self::segmentKey($cfg['basis'], $l, $cfg['unverified']);
            $l->segment = $key;
            $needed[$key][(string) $l->stage] = ($needed[$key][(string) $l->stage] ?? 0) + 1;
        }
        $resolved = self::resolve($measure, $needed, $cfg['min_loans'], $cfg['min_defaults'], $cfg['thin']);
        $runId = (string) Str::uuid();
        $failed = [];
        foreach ($resolved as $key => $stages) {
            foreach ($stages as $stage => $o) {
                if ($o['status'] === 'failed') {
                    $failed[] = ['segment' => $key, 'label' => $this->label($key), 'stage' => $stage, 'loans' => $o['loans'], 'reason' => $o['reason']];
                }
            }
        }

        $window = "{$measure['window_start']} to {$measure['window_end']}";
        if ($failed !== []) {
            $reason = 'No PD for ' . implode('; ', array_map(fn ($f) => "{$f['label']} ({$f['segment']}) Stage {$f['stage']}, {$f['loans']} loan(s): {$f['reason']}", $failed));
            $this->record($runId, $period, $cfg, $measure, $resolved, [], 'failed', $reason, 0, $userId);
            AuditLoggerService::log('PD Segment Run Failed', 'segment_parameter_runs', null, ['reporting_period' => $period, 'new_values' => ['run_id' => $runId, 'basis' => $cfg['basis'], 'window' => $window, 'failed' => $failed], 'meta' => ['user' => $userId]]);
            throw new RuntimeException("PD by segment for {$period} stopped (fail closed). {$reason}");
        }

        $hasLineage = Schema::hasColumn('loan_books', 'pd_segment_key');
        $updated = 0;
        $matrices = [];
        DB::transaction(function () use ($period, $cfg, $measure, $resolved, $loans, $unstaged, $runId, $userName, $hasLineage, &$updated, &$matrices, $userId) {
            // a matrix for every segment that gives a PD, own or as a parent
            $used = [];
            foreach ($resolved as $stages) {
                foreach ($stages as $o) {
                    if (in_array($o['status'], ['own', 'parent'], true)) {
                        $used[$o['from']] = true;
                    }
                }
            }
            foreach (array_keys($used) as $key) {
                $matrices[$key] = $this->writeMatrix($period, $measure, $measure['segments'][$key], $runId, $userName);
            }
            foreach ($loans as $l) {
                if (! isset($l->segment)) {
                    continue;
                }
                $o = $resolved[$l->segment][(string) $l->stage];
                $pd = (float) $o['pd'];
                $row = ['pd_prefli' => round($pd, 8), '12m_pd' => round($pd * 100, 2), 'pd_post_fli' => null];
                if ($l->remaining_tenor !== null) {
                    $years = max(1.0, (float) $l->remaining_tenor) / 12;
                    $row['lifetime_pd'] = round(min(1.0, 1 - (1 - $pd) ** $years), 8);
                }
                if ($hasLineage) {
                    $row += ['pd_segment_key' => $l->segment, 'pd_applied_segment_key' => $o['from'], 'pd_segment_run_id' => $runId,
                        'pd_matrix_id' => $o['status'] === 'in_default' ? ($matrices[$l->segment] ?? null) : $matrices[$o['from']]];
                }
                $updated += DB::table('loan_books')->where('id', $l->id)->update($row);
            }
            // an unstaged loan carries no PD from an earlier run
            if ($unstaged !== []) {
                DB::table('loan_books')->whereIn('id', $unstaged)->update(array_merge(['pd_prefli' => null, 'pd_post_fli' => null],
                    $hasLineage ? ['pd_segment_key' => null, 'pd_applied_segment_key' => null, 'pd_segment_run_id' => $runId, 'pd_matrix_id' => null] : []));
            }
            $this->record($runId, $period, $cfg, $measure, $resolved, $matrices, 'applied', null, $updated, $userId);
        });

        $cells = [];
        foreach ($resolved as $key => $stages) {
            foreach ($stages as $stage => $o) {
                $c = $measure['segments'][$key]['stages'][$stage] ?? null;
                $cells[] = ['segment' => $key, 'label' => $this->label($key), 'stage' => $stage, 'status' => $o['status'], 'from' => $o['from'], 'pd' => $o['pd'],
                    'cohort' => (int) ($c['loans'] ?? 0), 'defaults' => (int) ($c['default_loans'] ?? 0), 'observed' => $c['observed'] ?? null, 'loans' => $o['loans'], 'reason' => $o['reason']];
            }
        }
        AuditLoggerService::log('PD Segment Run', 'segment_parameter_runs', null, ['reporting_period' => $period, 'rows_affected' => $updated,
            'new_values' => ['run_id' => $runId, 'basis' => $cfg['basis'], 'window' => $window, 'matrices' => $matrices,
                'substitutions' => array_values(array_filter($cells, fn ($c) => $c['status'] === 'parent'))], 'meta' => ['user' => $userId]]);

        return ['run_id' => $runId, 'basis' => $cfg['basis'], 'window' => $window, 'window_months' => $measure['window_months'], 'updated' => $updated,
            'unstaged' => count($unstaged), 'cells' => $cells, 'failed' => [], 'matrices' => $matrices];
    }

    /** One transition matrix per segment, with its cells, as the Monthly Probability screen shows them. */
    private function writeMatrix(string $period, array $measure, array $segment, string $runId, string $userName): int
    {
        $start = CarbonImmutable::parse($measure['window_start'] . '-01');
        $end = CarbonImmutable::parse($measure['window_end'] . '-01');
        $transitioned = array_sum(array_map(fn ($c) => $c['loans'], $segment['stages']));
        $balance = array_sum(array_map(fn ($c) => $c['balance'], $segment['stages']));
        $id = (int) DB::table('transition_matrices')->insertGetId([
            'transition_profile_id' => $measure['profile']['row']->id, 'start_reporting_period' => $measure['window_start'], 'end_reporting_period' => $measure['window_end'],
            'pd_start_stage_total_type' => '1', 'pd_calculation_level' => self::MATRIX_LEVEL[$segment['kind']], 'pd_calculation_id' => $segment['portfolio_id'], 'pd_calculation_code' => $segment['key'],
            'calculation_source' => 'system', 'start_year' => $start->year, 'start_month' => $start->month, 'end_year' => $end->year, 'end_month' => $end->month,
            'transition_years' => max(1, (int) round($measure['window_months'] / 12)), 'run_no' => 1, 'records_count_updated' => 0, 'records_count_transitioned' => $transitioned,
            'reporting_periods_count' => 0, 'updated_balance' => 0, 'transition_balance' => round($balance, 2), 'last_calculation_date' => now(), 'portfolio_count' => 0, 'book_updated_at' => now(),
            'take_on_flag' => 0, 'status' => 'closed', 'user_name' => mb_substr($userName, 0, 20),
            'comments' => "PdSegmentationService for {$period}: segment {$segment['key']} ({$segment['label']}), basis {$measure['basis']}, run {$runId}",
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $data = [];
        foreach ($measure['profile']['start'] as $s) {
            $total = (float) ($segment['stages'][$s]['balance'] ?? 0);
            foreach ($measure['profile']['end'] as $e) {
                $b = (float) ($segment['matrix'][$s][$e] ?? 0);
                $data[] = ['calculation_header_id' => $id, 'is_payments_included' => 1, 'start_period' => $measure['window_start'], 'start_year' => $start->year, 'start_month' => $start->month,
                    'start_stage' => $s, 'end_period' => $measure['window_end'], 'end_year' => $end->year, 'end_month' => $end->month, 'end_stage' => $e, 'stage_transition' => $s . 'to' . $e,
                    'transition_years' => max(1, (int) round($measure['window_months'] / 12)), 'transition_balance_month' => round($b, 2), 'start_total_balance_month' => round($total, 2),
                    'transition_probability_month' => $total > 0 ? round($b / $total * 100, 6) : 0, 'default_flag' => $e === $measure['profile']['default'] ? 1 : 0,
                    'created_at' => now(), 'updated_at' => now()];
            }
        }
        DB::table('transition_matrices_data')->insert($data);

        return $id;
    }

    /** The run and every cell measured (the needed cells and every segment above them). */
    private function record(string $runId, string $period, array $cfg, array $measure, array $resolved, array $matrices, string $status, ?string $reason, int $updated, ?int $userId): void
    {
        if (! Schema::hasTable('segment_parameter_runs')) {
            return;
        }
        DB::table('segment_parameter_runs')->insert(['run_id' => $runId, 'measure' => 'PD', 'reporting_period' => $period, 'basis' => $cfg['basis'],
            'window_start' => $measure['window_start'], 'window_end' => $measure['window_end'], 'window_months' => $measure['window_months'],
            'minimum_rule' => $cfg['minimum_option'], 'thin_rule' => $cfg['thin_option'], 'unverified_codes' => implode(',', $cfg['unverified']) ?: null,
            'status' => $status, 'failure_reason' => $reason, 'loans_updated' => $updated, 'user_id' => $userId, 'created_at' => now(), 'updated_at' => now()]);

        // the parents' loans: how many loans of the period take each segment's rate
        $takes = [];
        foreach ($resolved as $stages) {
            foreach ($stages as $stage => $o) {
                if ($o['status'] === 'parent') {
                    $takes[$o['from']][$stage] = ($takes[$o['from']][$stage] ?? 0) + $o['loans'];
                }
            }
        }
        $rows = [];
        $all = $measure['segments'];
        foreach (array_keys($resolved) as $key) {
            if (! isset($all[$key])) {
                $all[$key] = ['key' => $key, 'kind' => self::kindOf($key), 'parent' => self::parentOf($key), 'label' => $this->label($key), 'portfolio_id' => null, 'sector_code' => null, 'matrix' => [], 'stages' => []];
                $this->describe($all[$key]);
            }
        }
        foreach ($all as $key => $seg) {
            foreach (['1', '2', '3'] as $stage) {
                $c = $seg['stages'][$stage] ?? null;
                $o = $resolved[$key][$stage] ?? null;
                if ($c === null && $o === null && ! isset($takes[$key][$stage])) {
                    continue;
                }
                $test = $stage === '3' ? ['ok' => true, 'reason' => null] : self::test($seg, $stage, $cfg['min_loans'], $cfg['min_defaults']);
                $statusCell = $o['status'] ?? ($test['ok'] ? 'ok' : 'thin');
                $rows[] = ['run_id' => $runId, 'measure' => 'PD', 'reporting_period' => $period, 'segment_key' => $key, 'segment_label' => mb_substr($seg['label'], 0, 200), 'parent_key' => $seg['parent'],
                    'portfolio_id' => $seg['portfolio_id'], 'sector_code' => $seg['sector_code'], 'stage' => (int) $stage,
                    'cohort_loans' => (int) ($c['loans'] ?? 0), 'cohort_balance' => round((float) ($c['balance'] ?? 0), 2), 'default_loans' => (int) ($c['default_loans'] ?? 0), 'default_balance' => round((float) ($c['default_balance'] ?? 0), 2),
                    'observed_rate' => isset($c['observed']) ? round($c['observed'], 8) : null, 'annual_rate' => isset($c['annual']) ? round($c['annual'], 8) : null,
                    'status' => $statusCell, 'applied_from_key' => $o['from'] ?? null, 'applied_rate' => isset($o['pd']) ? round($o['pd'], 8) : null,
                    'period_loans' => (int) (($o['loans'] ?? 0) + ($takes[$key][$stage] ?? 0)), 'reason' => $o['reason'] ?? $test['reason'],
                    'matrix_id' => $matrices[$key] ?? null, 'created_at' => now(), 'updated_at' => now()];
            }
        }
        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('segment_parameter_results')->insert($chunk);
        }
    }
}
