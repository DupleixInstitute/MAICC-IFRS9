<?php

namespace App\Services;

use App\Models\GovernanceSetting;
use App\Services\Ebanker\LoanBookBuildService;
use App\Support\Approvals;
use App\Support\SupportingDocuments;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One register of approvals (Staging Module Specification v1, section 8.7;
 * acceptance 15): every item of the approval workflows (section 8.6) and every
 * Governance Centre change, pending or decided, on one list: who proposed it
 * and when, the decision, who made it, when and why, and the documents behind
 * it (attached or linked).
 *
 * It reads; it decides nothing. Each item's columns differ by workflow, so
 * COLUMNS names, per workflow, the column of its title, of when it was
 * proposed, of who decided it and when, of the approver label and of the
 * reasons; a column a table lacks is skipped. reconcile() checks the register
 * against the audit log both ways.
 */
class ApprovalsRegisterService
{
    public const GOVERNANCE = 'governance';

    /** Statuses of an item not yet in a workflow (a detected candidate, a draft): not on the register. */
    public const NOT_IN_WORKFLOW = ['NONE', 'CANDIDATE', 'DETECTED', 'NOT_GENERATED', 'DRAFT'];

    /** The audit actions that record a decision, for the reconciliation. */
    public const DECISION_ACTION = '/(Approved|Rejected|Withdrawn|Published|Confirmed|Signed Off|Retired|Closed)$/';

    /** @var array<string, array{title:?string, proposed_at:list<string>, decided_by:list<string>, decided_at:list<string>, label:?string, reason:list<string>}> */
    public const COLUMNS = [
        'staging-threshold' => ['title' => 'facility_class', 'proposed_at' => ['created_at'], 'decided_by' => ['approved_by'], 'decided_at' => ['approved_at'], 'label' => null, 'reason' => ['reason']],
        'sicr-trigger' => ['title' => null, 'proposed_at' => ['created_at'], 'decided_by' => ['approved_by', 'rejected_by', 'closed_by'], 'decided_at' => ['approved_at', 'rejected_at', 'closed_at'], 'label' => 'approver_label', 'reason' => ['rejected_reason', 'closed_reason', 'reason']],
        'fee-rule' => ['title' => 'name', 'proposed_at' => ['created_at'], 'decided_by' => ['approved_by', 'rejected_by', 'withdrawn_by'], 'decided_at' => ['approved_at', 'rejected_at', 'withdrawn_at'], 'label' => null, 'reason' => ['approval_reason', 'rejection_reason', 'withdrawal_reason', 'rationale']],
        'scenario-set' => ['title' => 'name', 'proposed_at' => ['proposed_at', 'created_at'], 'decided_by' => ['approved_by'], 'decided_at' => ['approved_at'], 'label' => 'approver_label', 'reason' => ['version_reason']],
        'fli-overlay' => ['title' => 'reporting_period', 'proposed_at' => ['proposed_at', 'created_at'], 'decided_by' => ['approved_by', 'rejected_by'], 'decided_at' => ['approved_at', 'rejected_at'], 'label' => 'approver_label', 'reason' => ['rejected_reason', 'reason']],
        'fli-fit' => ['title' => 'reporting_period', 'proposed_at' => ['proposed_at', 'computed_at'], 'decided_by' => ['approved_by'], 'decided_at' => ['approved_at'], 'label' => 'approver_label', 'reason' => ['declined_reason']],
        'fli-model' => ['title' => 'version', 'proposed_at' => ['proposed_at', 'created_at'], 'decided_by' => ['published_by', 'retired_by'], 'decided_at' => ['published_at', 'retired_at'], 'label' => 'approver_label', 'reason' => ['retired_reason']],
        'schedule' => ['title' => 'contract_id', 'proposed_at' => ['schedule_generated_at'], 'decided_by' => ['schedule_approved_by'], 'decided_at' => ['schedule_approved_at'], 'label' => 'schedule_approver_label', 'reason' => []],
        'eir-reopen' => ['title' => 'contract_id', 'proposed_at' => ['requested_at'], 'decided_by' => ['decided_by'], 'decided_at' => ['decided_at'], 'label' => null, 'reason' => ['decision_reason', 'reason']],
        'restructure' => ['title' => 'contract_id', 'proposed_at' => ['proposed_at'], 'decided_by' => ['approved_by', 'closed_by'], 'decided_at' => ['approved_at', 'closed_at'], 'label' => 'approver_label', 'reason' => ['close_reason', 'reason']],
        'rate-reset' => ['title' => 'contract_id', 'proposed_at' => ['proposed_at'], 'decided_by' => ['approved_by'], 'decided_at' => ['approved_at'], 'label' => 'approver_label', 'reason' => ['close_reason', 'reason']],
        'write-off' => ['title' => 'contract_id', 'proposed_at' => ['proposed_at', 'created_at'], 'decided_by' => ['approved_by', 'closed_by'], 'decided_at' => ['approved_at', 'closed_at'], 'label' => 'approver_label', 'reason' => ['close_reason', 'reason']],
        'takeon-build' => ['title' => null, 'proposed_at' => ['proposed_at', 'created_at'], 'decided_by' => ['approved_by'], 'decided_at' => ['approved_at'], 'label' => 'approver_label', 'reason' => ['reason']],
        'loan-book-build' => ['title' => null, 'proposed_at' => ['created_at'], 'decided_by' => ['approved_by'], 'decided_at' => ['approved_at'], 'label' => 'approver_label', 'reason' => []],
        'period-unlock' => ['title' => 'reporting_period', 'proposed_at' => ['requested_at'], 'decided_by' => ['decided_by'], 'decided_at' => ['decided_at'], 'label' => null, 'reason' => ['decision_reason', 'reason']],
        'compliance-signoff' => ['title' => null, 'proposed_at' => ['signed_at', 'created_at'], 'decided_by' => ['approved_by'], 'decided_at' => ['approved_at'], 'label' => null, 'reason' => []],
        'fli-month' => ['title' => 'reporting_period', 'proposed_at' => ['created_at'], 'decided_by' => ['signed_off_by'], 'decided_at' => ['signed_off_at'], 'label' => 'approver_label', 'reason' => []],
        'macro-override' => ['title' => 'series_code', 'proposed_at' => ['created_at'], 'decided_by' => ['approved_by'], 'decided_at' => ['approved_at'], 'label' => null, 'reason' => ['reason']],
        'account-exclusion' => ['title' => 'contract_id', 'proposed_at' => ['proposed_at', 'created_at'], 'decided_by' => ['approved_by', 'closed_by'], 'decided_at' => ['approved_at', 'closed_at'], 'label' => 'approver_label', 'reason' => ['close_reason', 'reason']],
    ];

    /** @var array<int, string> */
    private array $names = [];

    /**
     * Every item on the register, newest first, filtered.
     *
     * @param  array{workflow?:?string, person?:?int, status?:?string, from?:?string, to?:?string}  $filters
     *         status: 'pending', 'decided', or a status value (APPROVED, REJECTED, ...)
     * @return list<array>
     */
    public function rows(array $filters = []): array
    {
        $this->names = DB::table('users')->pluck('name', 'id')->map(fn ($n) => (string) $n)->all();
        $rows = [];
        $only = $filters['workflow'] ?? null;
        if (($only === null || $only === self::GOVERNANCE) && Schema::hasTable('governance_settings')) {
            $rows = array_merge($rows, $this->governanceRows());
        }
        foreach (array_keys(Approvals::WORKFLOWS) as $workflow) {
            if ($only !== null && $only !== $workflow) {
                continue;
            }
            $rows = array_merge($rows, $this->workflowRows($workflow));
        }
        $rows = array_values(array_filter($rows, fn ($r) => $this->matches($r, $filters)));
        usort($rows, fn ($a, $b) => [$b['last_at'] ?? '', $b['workflow'], $b['id']] <=> [$a['last_at'] ?? '', $a['workflow'], $a['id']]);

        return $rows;
    }

    /** @return array<string, string> workflow => label, the Governance Centre first */
    public static function workflows(): array
    {
        return [self::GOVERNANCE => 'Governance Centre change'] + array_map(fn ($w) => $w['label'], Approvals::WORKFLOWS);
    }

    private function matches(array $r, array $f): bool
    {
        $status = $f['status'] ?? null;
        if ($status === 'pending' && ! $r['pending']) {
            return false;
        }
        if ($status === 'decided' && $r['pending']) {
            return false;
        }
        if ($status === 'grandfathered' && $r['label'] !== \App\Support\EvidenceRule::LABEL) {
            return false;
        }
        if ($status !== null && ! in_array($status, ['pending', 'decided', 'grandfathered'], true) && $r['status'] !== $status) {
            return false;
        }
        if (($person = $f['person'] ?? null) !== null && ! in_array((int) $person, [$r['proposed_by_id'], $r['decided_by_id']], true)) {
            return false;
        }
        $at = substr((string) ($r['last_at'] ?? ''), 0, 10);
        if (($from = $f['from'] ?? null) && $at < $from) {
            return false;
        }
        if (($to = $f['to'] ?? null) && $at > $to) {
            return false;
        }

        return true;
    }

    private function governanceRows(): array
    {
        $out = [];
        // a seeded default (no proposer, no approver) is the installation's, not a decision
        $since = \App\Support\EvidenceRule::since();
        $settings = DB::table('governance_settings')->where(fn ($q) => $q->whereNotNull('set_by')->orWhereNotNull('approved_by')->orWhere('status', '!=', GovernanceSetting::STATUS_APPROVED))->orderBy('id')->get();
        foreach ($settings as $s) {
            $pending = $s->status === GovernanceSetting::STATUS_PROPOSED;
            $decidedBy = $s->status === GovernanceSetting::STATUS_APPROVED ? $s->approved_by : ($s->reviewed_by ?? null);
            $decidedAt = $s->status === GovernanceSetting::STATUS_APPROVED ? $s->approved_at : ($s->reviewed_at ?? null);
            $docs = SupportingDocuments::forItem(GovernanceSetting::class, (int) $s->id)->where('removed', false);
            // approved without a document before the rule required one (section 8.10): labelled, never hidden
            $grandfathered = \App\Support\EvidenceRule::grandfathered($s, $since, true);
            $out[] = $this->row(self::GOVERNANCE, (int) $s->id, "{$s->key} = {$s->value} from " . substr((string) $s->effective_from, 0, 10), (string) $s->status, $pending,
                $s->set_by, (string) $s->created_at, $decidedBy, $decidedAt, $grandfathered ? \App\Support\EvidenceRule::LABEL : null, $pending ? $s->reason : trim(($s->reason ?? '') . (($s->review_reason ?? null) ? ' | ' . $s->review_reason : '')),
                $docs, GovernanceSetting::class, route('eir-governance.history', $s->key, false));
        }

        return $out;
    }

    private function workflowRows(string $workflow): array
    {
        $w = Approvals::workflow($workflow);
        if (! Schema::hasTable($w['table'])) {
            return [];
        }
        $c = self::COLUMNS[$workflow];
        $have = array_flip(Schema::getColumnListing($w['table']));
        $pick = fn (array $cols) => array_values(array_filter($cols, fn ($col) => isset($have[$col])));
        $decidedBy = $pick($c['decided_by']);
        $q = DB::table($w['table']);
        // on the register: waiting for a decision, decided by someone, or in a decided status
        $q->where(function ($q) use ($w, $decidedBy) {
            if ($w['pending'] === []) {
                $q->whereNotNull($w['status']);
            } else {
                $q->whereIn($w['status'], $w['pending']);
            }
            foreach ($decidedBy as $col) {
                $q->orWhereNotNull($col);
            }
            if ($w['pending'] !== []) {
                $q->orWhere(fn ($q) => $q->whereNotNull($w['status'])->whereNotIn($w['status'], array_merge(self::NOT_IN_WORKFLOW, $w['pending'])));
            }
        });
        $out = [];
        foreach ($q->orderBy('id')->get() as $r) {
            $pending = Approvals::pending($workflow, $r);
            $first = function (array $cols) use ($r) {
                foreach ($cols as $col) {
                    if (($r->{$col} ?? null) !== null && $r->{$col} !== '') {
                        return $r->{$col};
                    }
                }

                return null;
            };
            $maker = $w['maker'] !== null && isset($have[$w['maker']]) ? $r->{$w['maker']} : null;
            $label = $c['label'] !== null && isset($have[$c['label']]) ? ($r->{$c['label']} ?? null) : null;
            if (! $pending && $maker === null && $label === null && $first($decidedBy) === null) {
                continue; // seeded with the installation (no proposer, no decider, no label): not a decision
            }
            $status = (string) ($r->{$w['status']} ?? '');
            if ($workflow === 'compliance-signoff' && ! $pending) {
                $status = 'APPROVED';
            }
            $title = $c['title'] !== null && isset($have[$c['title']]) ? (string) $r->{$c['title']} : "#{$r->id}";
            $out[] = $this->row($workflow, (int) $r->id, $title, $status, $pending, $maker, $first($pick($c['proposed_at'])),
                $pending ? null : $first($decidedBy), $pending ? null : $first($pick($c['decided_at'])), $label, $first($pick($c['reason'])),
                SupportingDocuments::forItem($w['table'], (int) $r->id)->where('removed', false), $w['table'], route('approvals.record', [$workflow, $r->id], false));
        }

        return $out;
    }

    private function row(string $workflow, int $id, string $title, string $status, bool $pending, mixed $proposedBy, mixed $proposedAt, mixed $decidedBy, mixed $decidedAt,
        ?string $label, ?string $reason, $documents, string $table, string $url): array
    {
        $person = fn ($id) => $id === null ? null : ($this->names[(int) $id] ?? "user {$id}");

        return [
            'workflow' => $workflow, 'workflow_label' => self::workflows()[$workflow], 'id' => $id, 'title' => $title, 'status' => $status, 'pending' => $pending,
            'proposed_by_id' => $proposedBy === null ? null : (int) $proposedBy, 'proposed_by' => $person($proposedBy), 'proposed_at' => $proposedAt === null ? null : (string) $proposedAt,
            'decided_by_id' => $decidedBy === null ? null : (int) $decidedBy, 'decided_by' => $label === LoanBookBuildService::BOOTSTRAP_LABEL ? 'System Bootstrap' : $person($decidedBy),
            'decided_at' => $decidedAt === null ? null : (string) $decidedAt, 'label' => $label, 'bootstrap' => $label === LoanBookBuildService::BOOTSTRAP_LABEL,
            'grandfathered' => $label === \App\Support\EvidenceRule::LABEL,
            'reason' => $reason === null ? null : mb_substr((string) $reason, 0, 500),
            'documents' => $documents->count(), 'document_names' => $documents->map(fn ($d) => $d['name'] . ($d['linked'] ? ' (linked)' : ''))->implode('; '),
            'last_at' => (string) ($decidedAt ?? $proposedAt ?? ''), 'url' => $url, 'table' => $table,
        ];
    }

    /**
     * The register against the audit log, both ways (acceptance 15): every decided item has at least one
     * audit entry under its entity, and every decision entry in the audit log names an item that exists.
     *
     * @return array{items:int, decided:int, decided_without_entry:list<array>, entries_without_item:list<array>, reconciled:bool}
     */
    public function reconcile(?array $rows = null): array
    {
        $rows ??= $this->rows();
        $entities = [self::GOVERNANCE => [GovernanceSetting::class]] + array_map(fn ($w) => $w['entities'], Approvals::WORKFLOWS);
        $tables = [self::GOVERNANCE => 'governance_settings'] + array_map(fn ($w) => $w['table'], Approvals::WORKFLOWS);
        $logged = [];
        $byType = [];
        foreach ($entities as $workflow => $types) {
            foreach ($types as $type) {
                $byType[$type] = $workflow;
            }
        }
        $entries = DB::table('audit_logs')->whereIn('entity_type', array_keys($byType))->whereNotNull('entity_id')->get(['id', 'action', 'entity_type', 'entity_id', 'created_at']);
        foreach ($entries as $e) {
            $logged[$byType[$e->entity_type]][(int) $e->entity_id] = true;
        }
        $without = [];
        $decided = 0;
        foreach ($rows as $r) {
            if ($r['pending']) {
                continue;
            }
            $decided++;
            if (! isset($logged[$r['workflow']][$r['id']])) {
                $without[] = ['workflow' => $r['workflow_label'], 'id' => $r['id'], 'title' => $r['title'], 'status' => $r['status'], 'decided_by' => $r['decided_by'], 'decided_at' => $r['decided_at']];
            }
        }
        $orphans = [];
        $exists = [];
        foreach ($entries as $e) {
            if (preg_match(self::DECISION_ACTION, (string) $e->action) !== 1) {
                continue;
            }
            $workflow = $byType[$e->entity_type];
            $table = $tables[$workflow];
            $exists[$table] ??= Schema::hasTable($table) ? array_flip(DB::table($table)->pluck('id')->map(fn ($i) => (int) $i)->all()) : [];
            if (! isset($exists[$table][(int) $e->entity_id])) {
                $orphans[] = ['entry' => (int) $e->id, 'action' => $e->action, 'workflow' => self::workflows()[$workflow], 'id' => (int) $e->entity_id, 'at' => (string) $e->created_at];
            }
        }

        return ['items' => count($rows), 'decided' => $decided, 'decided_without_entry' => $without, 'entries_without_item' => $orphans,
            'reconciled' => $without === [] && $orphans === []];
    }

    /** The register's columns for an export, in order. */
    public const EXPORT_HEADINGS = ['Workflow', 'Item', 'What', 'Status', 'Proposed by', 'Proposed at', 'Decided by', 'Decided at', 'Reason', 'Documents'];

    /** @return list<list<mixed>> */
    public static function exportRows(array $rows): array
    {
        return array_map(fn ($r) => [$r['workflow_label'], $r['id'], $r['title'], ($r['pending'] ? 'PENDING (' . $r['status'] . ')' : $r['status']) . ($r['grandfathered'] ? ', ' . \App\Support\EvidenceRule::LABEL : ''),
            $r['proposed_by'], $r['proposed_at'], $r['decided_by'], $r['decided_at'], $r['reason'], $r['document_names']], $rows);
    }
}
