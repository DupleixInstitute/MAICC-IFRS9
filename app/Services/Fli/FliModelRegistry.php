<?php

namespace App\Services\Fli;

use App\Services\AuditLoggerService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The FLI model registry (FLI module specification v1, decisions D-F1 and
 * D-F2).
 *
 * The fits stay the calibration evidence. When a second person approves a
 * fit it is published here as a model version with an effective month; the
 * forward-looking route reads the version in force at the reporting date,
 * which is the latest published version whose effective month is on or
 * before it. A month-end therefore applies the approved model; it does not
 * have to re-fit it. The model is recalibrated once a year, or when an event
 * breaks the relationship, and the new version takes effect from its month
 * forward. Old versions are never edited: a version that a later one
 * replaces from the same month is marked SUPERSEDED, a version withdrawn is
 * RETIRED, and the months it governed keep pointing at it.
 */
class FliModelRegistry
{
    public const DEFAULT_MODEL = 'PD-FLI-BOOK';

    /** Publish an approved fit as a model version effective from $effectiveFrom (default: the fit's own month). */
    public function publishFromFit(int $fitId, ?int $publishedBy, ?string $label = null, ?string $effectiveFrom = null): int
    {
        $fit = DB::table('fli_fits as f')->join('fli_relationships as r', 'r.id', '=', 'f.fli_relationship_id')->where('f.id', $fitId)
            ->first(['f.id', 'f.reporting_period', 'f.slope', 'f.intercept', 'f.r_squared', 'f.p_value', 'f.n_obs', 'f.proposed_by', 'f.approval_status', 'r.statistic_code', 'r.proxy_code', 'r.lag_months'])
            ?? throw new RuntimeException("No fit {$fitId}.");
        if ($fit->approval_status !== 'APPROVED') {
            throw new RuntimeException("Fit {$fitId} is not approved; only an approved fit can be published as a model version.");
        }
        $calibrated = substr((string) $fit->reporting_period, 0, 4) . '-' . substr((string) $fit->reporting_period, 4, 2);
        $effective = $effectiveFrom ?: $calibrated;
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $effective)) {
            throw new RuntimeException('The effective month must be YYYY-MM.');
        }
        if ($effective < $calibrated) {
            throw new RuntimeException("A version calibrated on {$calibrated} data cannot govern {$effective}: it would use information that was not available then (IFRS 9 B5.5.49).");
        }
        $existing = DB::table('fli_model_versions')->where('fli_fit_id', $fitId)->where('effective_from', $effective)->where('status', 'PUBLISHED')->value('id');
        if ($existing) {
            return (int) $existing;
        }
        // no back-dating into a locked month (Staging Module Specification v1, section 8.8, item 4)
        \App\Support\ReportingPeriodLock::assertNotBackDated($effective, 'A forward-looking model version');
        $modelId = $this->modelId();

        return DB::transaction(function () use ($fit, $modelId, $calibrated, $effective, $publishedBy, $label) {
            // a version published earlier for the same month is replaced, not edited
            $replaced = DB::table('fli_model_versions')->where('model_id', $modelId)->where('effective_from', $effective)->where('status', 'PUBLISHED')->pluck('id');
            DB::table('fli_model_versions')->whereIn('id', $replaced)->update(['status' => 'SUPERSEDED', 'updated_at' => now()]);
            $version = (int) DB::table('fli_model_versions')->where('model_id', $modelId)->max('version') + 1;
            $id = (int) DB::table('fli_model_versions')->insertGetId([
                'model_id' => $modelId, 'version' => $version, 'fli_fit_id' => $fit->id, 'statistic_code' => $fit->statistic_code, 'proxy_code' => $fit->proxy_code,
                'lag_months' => (int) $fit->lag_months, 'slope' => $fit->slope, 'intercept' => $fit->intercept, 'r_squared' => $fit->r_squared, 'p_value' => $fit->p_value, 'n_obs' => $fit->n_obs,
                'calibrated_period' => $calibrated, 'effective_from' => $effective, 'status' => 'PUBLISHED', 'proposed_by' => $fit->proposed_by, 'published_by' => $publishedBy,
                'approver_label' => $label, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            AuditLoggerService::log('FLI Model Version Published', 'fli_model_versions', $id, ['reporting_period' => $effective,
                'new_values' => ['version' => $version, 'fit' => (int) $fit->id, 'relationship' => "{$fit->statistic_code} -> {$fit->proxy_code} (lag {$fit->lag_months})", 'effective_from' => $effective, 'replaced' => $replaced->all()],
                'meta' => ['user' => $publishedBy, 'label' => $label]]);

            return $id;
        });
    }

    /**
     * Propose a model calibrated outside the system (External Calculations).
     * It reaches no loan until a second person approves it.
     *
     * @param array{statistic_code:string,proxy_code:string,lag_months:int,slope:float|string,intercept:float|string,r_squared?:?float,n_obs?:?int,calibrated_period:string,effective_from:string,evidence:string} $d
     */
    public function proposeExternal(array $d, ?int $userId): int
    {
        foreach (['calibrated_period', 'effective_from'] as $k) {
            if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) ($d[$k] ?? ''))) {
                throw new RuntimeException('The calibration month and the effective month must be YYYY-MM.');
            }
        }
        \App\Support\ReportingPeriodLock::assertNotBackDated($d['effective_from'], 'A forward-looking model version');
        if ($d['effective_from'] < $d['calibrated_period']) {
            throw new RuntimeException('A model cannot govern a month before the data it was calibrated on (IFRS 9 B5.5.49).');
        }
        if (! is_numeric($d['slope'] ?? null) || ! is_numeric($d['intercept'] ?? null)) {
            throw new RuntimeException('The slope and the intercept must be numbers.');
        }
        if (trim((string) ($d['evidence'] ?? '')) === '') {
            throw new RuntimeException('Say where the calibration comes from: the workbook, the consultant, the data and the period it was fitted on.');
        }
        if (! DB::table('macro_statistics')->where('statistic_code', $d['statistic_code'])->exists() && ! DB::table('macro_series')->where('statistic_code', $d['statistic_code'])->exists()) {
            throw new RuntimeException("The driver {$d['statistic_code']} is not a series the system holds, so the route could not read it.");
        }
        if (! DB::table('credit_loss_series')->where('proxy_code', $d['proxy_code'])->exists()) {
            throw new RuntimeException("The credit-loss measure {$d['proxy_code']} is not held, so the route could not read it.");
        }
        $modelId = $this->modelId();
        $id = (int) DB::table('fli_model_versions')->insertGetId([
            'model_id' => $modelId, 'version' => (int) DB::table('fli_model_versions')->where('model_id', $modelId)->max('version') + 1, 'fli_fit_id' => null,
            'statistic_code' => $d['statistic_code'], 'proxy_code' => $d['proxy_code'], 'lag_months' => (int) $d['lag_months'], 'slope' => $d['slope'], 'intercept' => $d['intercept'],
            'r_squared' => $d['r_squared'] ?? null, 'n_obs' => $d['n_obs'] ?? null, 'calibrated_period' => $d['calibrated_period'], 'effective_from' => $d['effective_from'],
            'status' => 'PROPOSED', 'source' => 'external', 'evidence' => trim((string) $d['evidence']), 'proposed_by' => $userId, 'proposed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        AuditLoggerService::log('FLI External Model Proposed', 'fli_model_versions', $id, ['reporting_period' => $d['effective_from'], 'new_values' => array_diff_key($d, ['evidence' => 1]), 'meta' => ['user' => $userId]]);

        return $id;
    }

    /** A second person approves an external proposal; it is published from its effective month. */
    public function approveExternal(int $versionId, ?int $approverId, ?string $label = null): void
    {
        $v = DB::table('fli_model_versions')->where('id', $versionId)->first() ?? throw new RuntimeException("No model version {$versionId}.");
        if ($v->status !== 'PROPOSED') {
            throw new RuntimeException("Version {$v->version} is {$v->status}, not proposed.");
        }
        if ($label === null && $approverId !== null && (int) $v->proposed_by === $approverId) {
            throw new RuntimeException('Maker-checker: the approver must be a different person from the proposer.');
        }
        // checked again on approval: the month may have been locked since the proposal
        \App\Support\ReportingPeriodLock::assertNotBackDated((string) $v->effective_from, "Forward-looking model version {$v->version}");
        DB::transaction(function () use ($v, $approverId, $label) {
            DB::table('fli_model_versions')->where('model_id', $v->model_id)->where('effective_from', $v->effective_from)->where('status', 'PUBLISHED')->update(['status' => 'SUPERSEDED', 'updated_at' => now()]);
            DB::table('fli_model_versions')->where('id', $v->id)->update(['status' => 'PUBLISHED', 'published_by' => $approverId, 'approver_label' => $label, 'published_at' => now(), 'updated_at' => now()]);
        });
        AuditLoggerService::log('FLI Model Version Published', 'fli_model_versions', $versionId, ['reporting_period' => $v->effective_from,
            'new_values' => ['version' => (int) $v->version, 'source' => 'external', 'relationship' => "{$v->statistic_code} -> {$v->proxy_code} (lag {$v->lag_months})", 'effective_from' => $v->effective_from], 'meta' => ['user' => $approverId, 'label' => $label]]);
    }

    /** Withdraw a proposal that was not approved. */
    public function rejectExternal(int $versionId, ?int $userId, string $reason): void
    {
        $v = DB::table('fli_model_versions')->where('id', $versionId)->first() ?? throw new RuntimeException("No model version {$versionId}.");
        if ($v->status !== 'PROPOSED') {
            throw new RuntimeException("Version {$v->version} is {$v->status}, not proposed.");
        }
        DB::table('fli_model_versions')->where('id', $versionId)->update(['status' => 'REJECTED', 'retired_by' => $userId, 'retired_at' => now(), 'retired_reason' => $reason, 'updated_at' => now()]);
        AuditLoggerService::log('FLI External Model Rejected', 'fli_model_versions', $versionId, ['new_values' => ['reason' => $reason], 'meta' => ['user' => $userId]]);
    }

    /** The version in force for a month: the latest published one effective on or before it. */
    public function inForce(string $period): ?object
    {
        $period = $this->period($period);

        return DB::table('fli_model_versions as v')->join('fli_models as m', 'm.id', '=', 'v.model_id')
            ->where('m.code', self::DEFAULT_MODEL)->where('v.status', 'PUBLISHED')->where('v.effective_from', '<=', $period)
            ->orderByDesc('v.effective_from')->orderByDesc('v.version')
            ->first(['v.*', 'm.code as model_code', 'm.name as model_name']);
    }

    /** Withdraw a version; the months after it fall back to the version before it, or to none. */
    public function retire(int $versionId, ?int $userId, string $reason): void
    {
        $v = DB::table('fli_model_versions')->where('id', $versionId)->first() ?? throw new RuntimeException("No model version {$versionId}.");
        if ($v->status !== 'PUBLISHED') {
            throw new RuntimeException("Version {$v->version} is {$v->status}; only a published version can be retired.");
        }
        if (trim($reason) === '') {
            throw new RuntimeException('Say why the version is retired.');
        }
        // a retired version stops governing every month it governed: none of them may be locked (section 8.8,
        // item 4); to change the model from an open month, publish a newer version effective from that month
        \App\Support\ReportingPeriodLock::assertNotBackDated((string) $v->effective_from, "Retiring forward-looking model version {$v->version}");
        DB::table('fli_model_versions')->where('id', $versionId)->update(['status' => 'RETIRED', 'retired_by' => $userId, 'retired_at' => now(), 'retired_reason' => $reason, 'updated_at' => now()]);
        AuditLoggerService::log('FLI Model Version Retired', 'fli_model_versions', $versionId, ['reporting_period' => $v->effective_from, 'new_values' => ['version' => (int) $v->version, 'reason' => $reason], 'meta' => ['user' => $userId]]);
    }

    /** Every version, newest first, with who published it. */
    public function versions(): Collection
    {
        return DB::table('fli_model_versions as v')->join('fli_models as m', 'm.id', '=', 'v.model_id')
            ->leftJoin('users as p', 'p.id', '=', 'v.published_by')->leftJoin('users as r', 'r.id', '=', 'v.retired_by')->leftJoin('users as q', 'q.id', '=', 'v.proposed_by')
            ->orderByDesc('v.effective_from')->orderByDesc('v.version')
            ->get(['v.*', 'm.code as model_code', 'm.name as model_name', 'p.name as publisher', 'r.name as retirer', 'q.name as proposer']);
    }

    /** The months each version governed, from the loans that ran under it. */
    public function monthsGoverned(): array
    {
        if (! DB::getSchemaBuilder()->hasColumn('loan_books', 'fli_model_version_id')) {
            return [];
        }

        return DB::table('loan_books')->whereNotNull('fli_model_version_id')->selectRaw('fli_model_version_id v, reporting_period p')->distinct()->get()
            ->groupBy('v')->map(fn ($g) => $g->pluck('p')->sort()->values()->all())->all();
    }

    private function modelId(): int
    {
        return (int) (DB::table('fli_models')->where('code', self::DEFAULT_MODEL)->value('id') ?? DB::table('fli_models')->insertGetId([
            'code' => self::DEFAULT_MODEL, 'name' => 'Book-level PD forward-looking model', 'target_metric' => 'PD', 'segment_basis' => 'book',
            'description' => 'One driver against one credit-loss measure, fitted under the guardrail; its version in force moves every loan\'s PD under each scenario.',
            'created_at' => now(), 'updated_at' => now(),
        ]));
    }

    private function period(string $p): string
    {
        $p = str_replace('-', '', trim($p));

        return substr($p, 0, 4) . '-' . substr($p, 4, 2);
    }
}
