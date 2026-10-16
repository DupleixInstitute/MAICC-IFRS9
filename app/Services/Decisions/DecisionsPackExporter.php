<?php

namespace App\Services\Decisions;

use App\Models\ContractFee;
use App\Support\MakerChecker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Writes the decisions pack of a database (Staging Module Specification v1,
 * section 8.10). It reads and never writes: not a row, not an audit entry
 * (acceptance 24). It is run on MAIIC's server by MAIIC ICT or Edward
 * (decision S14), so the database it reads is never opened by anyone else.
 *
 * What it carries, each by business key: the users (no password, no
 * two-factor secret); the governance settings people approved; the fee rules
 * people wrote or approved; Finance's fee workbook lines and the months they
 * cover; the held GL fees people tied to a loan or dismissed; the fee lines
 * people moved between loans; the overlap decisions; every classification and
 * review; the reopen requests (as history); the EIRs a person locked, with the
 * rate and the fingerprint of the cash flows they were solved on.
 */
class DecisionsPackExporter
{
    /** @var array<int, ?string> user id => e-mail */
    private array $emails = [];

    /**
     * @return array{folder: string, counts: array<string, int>, manifest: array}
     */
    public function export(string $folder, string $pack): array
    {
        if (! Schema::hasTable('contract_fees') || ! Schema::hasTable('contract_eir')) {
            throw new RuntimeException('This database has no fee lines or EIRs to export.');
        }
        if (is_dir($folder) && glob($folder . '/*') !== []) {
            throw new RuntimeException("{$folder} already holds files; a pack is written once, into an empty folder.");
        }
        $this->emails = DB::table('users')->pluck('email', 'id')->map(fn ($e) => $e === null ? null : strtolower(trim((string) $e)))->all();

        [$keys, $finance] = $this->feeKeys();
        if (! is_dir($folder) && ! mkdir($folder, 0775, true) && ! is_dir($folder)) {
            throw new RuntimeException("Cannot create {$folder}.");
        }
        $data = [
            'users' => $this->users(),
            'documents' => $this->documents($folder),
            'account_exclusions' => $this->accountExclusions(),
            'governance' => $this->governance(),
            'fee_rules' => $this->feeRules(),
            'finance_lines' => $this->financeLines($keys, $finance),
            'finance_coverage' => $this->financeCoverage(),
            'held_ties' => $this->heldTies(),
            'held_dismissals' => $this->heldDismissals(),
            'fee_moves' => $this->feeMoves($keys, $finance),
            'overlap_decisions' => $this->overlapDecisions($keys),
            'classifications' => $this->classifications($keys),
            'reopen_requests' => $this->reopenRequests(),
            'eir_locks' => $this->eirLocks(),
        ];

        $files = [];
        foreach (DecisionsPack::FILES as $kind => $file) {
            $json = self::json($data[$kind]);
            file_put_contents($folder . '/' . $file, $json);
            $files[$kind] = ['file' => $file, 'rows' => count($data[$kind]), 'sha256' => hash('sha256', $json)];
        }
        $manifest = [
            'pack' => $pack,
            'source_database' => (string) DB::connection()->getDatabaseName(),
            'exported_at' => now()->toDateTimeString(),
            'specification' => 'MAIIC Staging Module Specification v1, section 8.10',
            // when the supporting-document rule came into force on the source (null: not yet); an approval
            // without a document made before it is carried as made before supporting documents were required
            'evidence_rule_from' => \App\Support\EvidenceRule::since()?->toDateTimeString(),
            'files' => $files,
            'tie_out' => $this->tieOut($data),
        ];
        file_put_contents($folder . '/' . DecisionsPack::MANIFEST, self::json($manifest));

        return ['folder' => $folder, 'counts' => array_map(fn ($f) => $f['rows'], $files), 'manifest' => $manifest];
    }

    public static function json(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION) . "\n";
    }

    private function email(mixed $userId): ?string
    {
        return $userId === null ? null : ($this->emails[(int) $userId] ?? null);
    }

    /**
     * Every fee line's key. A line that is not Finance's is keyed as it was
     * before any move (the fresh database builds it there); a Finance line is
     * carried whole as it stands now, so it is keyed as it stands.
     *
     * @return array{0: array<int, string>, 1: array<int, bool>}
     */
    private function feeKeys(): array
    {
        $origins = [];
        foreach ($this->moveAudits() as $a) {
            $old = (array) $a->old_values;
            $origins[(int) $a->entity_id] ??= [(string) ($old['contract_id'] ?? ''), $old['transaction_date'] ?? null];
        }
        $fees = DB::table('contract_fees')->orderBy('id')->get(['id', 'contract_id', 'fee_type', 'amount', 'transaction_date', 'description', 'source_system', 'external_transaction_id']);
        $finance = [];
        foreach ($fees as $f) {
            if (DecisionsPack::isFinanceLine($f->source_system)) {
                $finance[(int) $f->id] = true;
                unset($origins[(int) $f->id]);
            }
        }

        return [DecisionsPack::keys($fees, $origins), $finance];
    }

    /** The audit entries of fee lines moved between loans, oldest first (the move has no table of its own). */
    private function moveAudits()
    {
        return DB::table('audit_logs')->where('action', 'EIR Fee Line Moved To Another Loan')->orderBy('id')->get()
            ->map(function ($a) {
                foreach (['old_values', 'new_values', 'meta'] as $c) {
                    $a->{$c} = is_string($a->{$c}) ? json_decode($a->{$c}, true) : (array) $a->{$c};
                }

                return $a;
            });
    }

    /**
     * The supporting documents behind the decisions the pack carries: those attached or linked to a
     * Governance Centre change people made, or to a fee rule people wrote or approved. Each file is
     * copied into the pack under its SHA-256; a removed document is not carried.
     */
    private function documents(string $folder): array
    {
        if (! Schema::hasTable('supporting_documents')) {
            return [];
        }
        $targets = [];
        foreach (DB::table('governance_settings')->where('status', 'APPROVED')->where('key', '!=', MakerChecker::SETTING)->where(fn ($q) => $q->whereNotNull('set_by')->orWhereNotNull('approved_by'))->get() as $s) {
            $targets[\App\Models\GovernanceSetting::class][(int) $s->id] = ['kind' => 'governance', 'key' => $s->key, 'value' => $s->value, 'effective_from' => substr((string) $s->effective_from, 0, 10)];
        }
        if (Schema::hasTable('account_exclusions')) {
            foreach (DB::table('account_exclusions')->get(['id', 'contract_id']) as $x) {
                $targets['account_exclusions'][(int) $x->id] = ['kind' => 'account_exclusion', 'contract_id' => (string) $x->contract_id];
            }
        }
        foreach (DB::table('eir_accounting_rules')->get(['id', 'name']) as $r) {
            $targets['eir_accounting_rules'][(int) $r->id] = ['kind' => 'fee_rule', 'name' => $r->name];
            $targets[\App\Models\EirAccountingRule::class][(int) $r->id] = ['kind' => 'fee_rule', 'name' => $r->name];
        }
        $docs = [];
        foreach (DB::table('supporting_documents')->whereNull('deleted_at')->orderBy('id')->get() as $d) {
            $on = [];
            if (isset($targets[$d->documentable_type][(int) $d->documentable_id])) {
                $on[] = $targets[$d->documentable_type][(int) $d->documentable_id];
            }
            if (Schema::hasTable('supporting_document_links')) {
                foreach (DB::table('supporting_document_links')->where('supporting_document_id', $d->id)->orderBy('id')->get() as $l) {
                    if (isset($targets[$l->documentable_type][(int) $l->documentable_id])) {
                        $on[] = $targets[$l->documentable_type][(int) $l->documentable_id] + ['link_reason' => $l->reason];
                    }
                }
            }
            if ($on === []) {
                continue;
            }
            $file = DecisionsPack::DOCUMENTS . '/' . $d->hash . '.' . $d->extension;
            $source = \Illuminate\Support\Facades\Storage::disk($d->disk)->path($d->path);
            $missing = ! is_file($source);
            if (! $missing) {
                @mkdir($folder . '/' . DecisionsPack::DOCUMENTS, 0775, true);
                copy($source, $folder . '/' . $file);
            }
            $docs[] = ['sha256' => $d->hash, 'file' => $missing ? null : $file, 'missing' => $missing, 'original_name' => $d->original_name, 'mime_type' => $d->mime_type,
                'size' => (int) $d->size, 'extension' => $d->extension, 'document_type' => $d->document_type, 'description' => $d->description,
                'uploaded_by' => $this->email($d->uploaded_by), 'uploaded_at' => (string) $d->created_at, 'supports' => $on];
        }

        return $docs;
    }

    private function users(): array
    {
        $roles = DB::table('model_has_roles')->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', \App\Models\User::class)->get(['model_has_roles.model_id', 'roles.name'])->groupBy('model_id');
        $perms = DB::table('model_has_permissions')->join('permissions', 'permissions.id', '=', 'model_has_permissions.permission_id')
            ->where('model_has_permissions.model_type', \App\Models\User::class)->get(['model_has_permissions.model_id', 'permissions.name'])->groupBy('model_id');
        $out = [];
        foreach (DB::table('users')->orderBy('id')->get() as $u) {
            $row = array_diff_key((array) $u, array_flip(DecisionsPack::USER_SECRETS));
            $row['email'] = strtolower(trim((string) $u->email));
            $row['roles'] = ($roles[$u->id] ?? collect())->pluck('name')->sort()->values()->all();
            $row['permissions'] = ($perms[$u->id] ?? collect())->pluck('name')->sort()->values()->all();
            $out[] = $row;
        }

        return $out;
    }

    /** The settings a person proposed or approved, oldest approval first; the UAT switch is the server's own and stays. */
    private function governance(): array
    {
        return DB::table('governance_settings')->where('status', 'APPROVED')->where('key', '!=', MakerChecker::SETTING)
            ->where(fn ($q) => $q->whereNotNull('set_by')->orWhereNotNull('approved_by'))
            ->orderBy('approved_at')->orderBy('id')->get()
            ->map(fn ($s) => ['key' => $s->key, 'value' => $s->value, 'effective_from' => substr((string) $s->effective_from, 0, 10), 'reason' => $s->reason,
                'proposed_by' => $this->email($s->set_by), 'proposed_at' => (string) $s->created_at, 'approved_by' => $this->email($s->approved_by), 'approved_at' => (string) $s->approved_at])
            ->values()->all();
    }

    private function feeRules(): array
    {
        $people = ['created_by', 'updated_by', 'approved_by', 'rejected_by', 'withdrawn_by', 'switch_proposed_by'];
        $present = array_values(array_filter($people, fn ($c) => Schema::hasColumn('eir_accounting_rules', $c)));

        return DB::table('eir_accounting_rules')->where(fn ($q) => array_map(fn ($c) => $q->orWhereNotNull($c), $present))->orderBy('id')->get()
            ->map(function ($r) use ($present) {
                $row = array_diff_key((array) $r, array_flip(['id', 'created_at', 'updated_at']));
                foreach ($present as $c) {
                    $row[$c] = $this->email($r->{$c});
                }

                return $row;
            })->values()->all();
    }

    private function financeLines(array $keys, array $finance): array
    {
        $cols = ['contract_id', 'fee_type', 'description', 'amount', 'transaction_date', 'cashflow_direction', 'currency', 'source_system', 'source_reference', 'external_transaction_id', 'basis', 'gl_account_ref'];
        $out = [];
        foreach (DB::table('contract_fees')->orderBy('id')->get() as $f) {
            if (! isset($finance[(int) $f->id])) {
                continue;
            }
            $row = ['key' => $keys[(int) $f->id]];
            foreach ($cols as $c) {
                $row[$c] = $f->{$c} ?? null;
            }
            $row['amount'] = (float) $f->amount;
            $row['transaction_date'] = $f->transaction_date ? substr((string) $f->transaction_date, 0, 10) : null;
            $row['loaded_at'] = (string) $f->created_at;
            $out[] = $row;
        }

        return $out;
    }

    private function financeCoverage(): array
    {
        if (! Schema::hasTable('fee_source_coverage')) {
            return [];
        }

        return DB::table('fee_source_coverage')->where('tier', \App\Services\Eir\FeeSourcePrecedence::TIER_FINANCE)->orderBy('id')->get()
            ->map(fn ($c) => ['month_from' => $c->month_from, 'month_to' => $c->month_to, 'origin' => $c->origin, 'recorded_by' => $this->email($c->recorded_by), 'recorded_at' => (string) $c->created_at])
            ->values()->all();
    }

    private function heldTies(): array
    {
        return DB::table('audit_logs')->where('action', 'GL Fee Tied To Loan')->orderBy('id')->get()
            ->map(function ($a) {
                $new = is_string($a->new_values) ? json_decode($a->new_values, true) : (array) $a->new_values;
                $meta = is_string($a->meta) ? json_decode($a->meta, true) : (array) $a->meta;

                return ['external_transaction_id' => $new['external_transaction_id'] ?? null, 'contract_id' => (string) ($new['contract_id'] ?? ''),
                    'amount' => isset($new['amount']) ? (float) $new['amount'] : null, 'reason' => (string) ($new['reason'] ?? ''),
                    'tied_by' => $this->email($meta['user'] ?? $a->user_id), 'tied_at' => (string) $a->created_at];
            })->filter(fn ($t) => $t['external_transaction_id'] !== null)->values()->all();
    }

    /**
     * The account exclusions people decided (AccountExclusionService), keyed by the account: every
     * entry a person proposed, approved, closed or asked to withdraw. An exclusion the bootstrap
     * landed from the committed client input and no person touched is not carried; a fresh
     * bootstrap lands it again from the same file.
     */
    private function accountExclusions(): array
    {
        if (! Schema::hasTable('account_exclusions')) {
            return [];
        }

        return DB::table('account_exclusions')->orderBy('id')->get()
            ->filter(fn ($x) => $x->proposed_by !== null || $x->approved_by !== null || $x->closed_by !== null || $x->withdrawal_requested_by !== null)
            ->map(fn ($x) => ['contract_id' => (string) $x->contract_id, 'account' => $x->account, 'customer_name' => $x->customer_name, 'category' => $x->category,
                'reason' => $x->reason, 'evidence' => $x->evidence, 'sister_contract_id' => $x->sister_contract_id, 'status' => $x->status, 'source' => $x->source,
                'proposed_by' => $this->email($x->proposed_by), 'proposed_at' => $x->proposed_at === null ? null : (string) $x->proposed_at,
                'approved_by' => $this->email($x->approved_by), 'approved_at' => $x->approved_at === null ? null : (string) $x->approved_at, 'approver_label' => $x->approver_label,
                'withdrawal_requested_by' => $this->email($x->withdrawal_requested_by), 'withdrawal_requested_at' => $x->withdrawal_requested_at === null ? null : (string) $x->withdrawal_requested_at,
                'withdrawal_reason' => $x->withdrawal_reason, 'closed_by' => $this->email($x->closed_by), 'closed_at' => $x->closed_at === null ? null : (string) $x->closed_at,
                'close_reason' => $x->close_reason])
            ->values()->all();
    }

    private function heldDismissals(): array
    {
        if (! Schema::hasTable('fee_gl_candidate_dismissals')) {
            return [];
        }

        return DB::table('fee_gl_candidate_dismissals')->orderBy('id')->get()
            ->map(fn ($d) => ['external_transaction_id' => $d->external_transaction_id, 'contract_id' => $d->contract_id, 'reason' => $d->reason,
                'dismissed_by' => $this->email($d->dismissed_by), 'dismissed_at' => (string) $d->created_at])->values()->all();
    }

    private function feeMoves(array $keys, array $finance): array
    {
        $out = [];
        foreach ($this->moveAudits() as $a) {
            $id = (int) $a->entity_id;
            if (! isset($keys[$id])) {
                continue; // the line is gone: nothing to move in the fresh database
            }
            $out[] = ['key' => $keys[$id], 'finance_line' => isset($finance[$id]),
                'from_contract' => (string) ($a->old_values['contract_id'] ?? ''), 'from_date' => $a->old_values['transaction_date'] ?? null,
                'to_contract' => (string) ($a->new_values['contract_id'] ?? ''), 'to_date' => $a->new_values['transaction_date'] ?? null,
                'reason' => (string) ($a->meta['reason'] ?? ''), 'moved_by' => $this->email($a->user_id), 'moved_at' => (string) $a->created_at];
        }

        return $out;
    }

    private function overlapDecisions(array $keys): array
    {
        if (! Schema::hasTable('fee_source_decisions')) {
            return [];
        }

        return DB::table('fee_source_decisions')->orderBy('id')->get()
            ->filter(fn ($d) => isset($keys[(int) $d->contract_fee_id]))
            ->map(fn ($d) => ['key' => $keys[(int) $d->contract_fee_id], 'decision' => $d->decision, 'reason' => $d->reason,
                'decided_by' => $this->email($d->decided_by), 'decided_at' => (string) $d->created_at])->values()->all();
    }

    /** Every line a person classified (and, for most, a second person reviewed), as it stands now. */
    private function classifications(array $keys): array
    {
        $rules = DB::table('eir_accounting_rules')->pluck('name', 'id')->all();
        $ruleOf = DB::table('eir_fee_classification_events')->where('action', 'CLASSIFIED')->orderBy('id')->get(['contract_fee_id', 'accounting_rule_id'])
            ->mapWithKeys(fn ($e) => [(int) $e->contract_fee_id => $e->accounting_rule_id])->all();
        $out = [];
        foreach (DB::table('contract_fees')->whereIn('classification_status', ['CLASSIFIED', 'REVIEWED'])->orderBy('id')->get() as $f) {
            $rule = $ruleOf[(int) $f->id] ?? null;
            $out[] = ['key' => $keys[(int) $f->id], 'contract_id' => $f->contract_id, 'amount' => (float) $f->amount,
                'status' => $f->classification_status, 'integral' => $f->integral === null ? null : (bool) $f->integral,
                'cashflow_direction' => $f->cashflow_direction, 'reason' => $f->classification_reason, 'rule' => $rule === null ? null : ($rules[$rule] ?? null),
                'classified_by' => $this->email($f->classified_by), 'classified_at' => $f->classified_at === null ? null : (string) $f->classified_at,
                'reviewed_by' => $this->email($f->reviewed_by), 'reviewed_at' => $f->reviewed_at === null ? null : (string) $f->reviewed_at,
                'superseded' => ($f->superseded_by_source ?? null) !== null];
        }

        return $out;
    }

    private function reopenRequests(): array
    {
        if (! Schema::hasTable('eir_reopen_requests')) {
            return [];
        }

        return DB::table('eir_reopen_requests')->orderBy('id')->get()
            ->map(fn ($r) => ['contract_id' => $r->contract_id, 'status' => $r->status, 'reason' => $r->reason,
                'requested_by' => $this->email($r->requested_by), 'requested_at' => (string) $r->requested_at,
                'decided_by' => $this->email($r->decided_by), 'decided_at' => $r->decided_at === null ? null : (string) $r->decided_at,
                'decision_reason' => $r->decision_reason])->values()->all();
    }

    /** The EIRs a person locked; a lock under the System Bootstrap label is the bootstrap's and is made again by it. */
    private function eirLocks(): array
    {
        return DB::table('contract_eir')->whereNotNull('locked_at')->whereNull('locked_label')->orderBy('contract_id')->get()
            ->map(function ($c) {
                $snapshot = is_string($c->input_snapshot) ? json_decode($c->input_snapshot, true) : (array) $c->input_snapshot;

                return ['contract_id' => $c->contract_id, 'eir_effective_annual' => $c->eir_effective_annual === null ? null : (float) $c->eir_effective_annual,
                    'eir_period' => $c->eir_period === null ? null : (float) $c->eir_period, 'fingerprint' => DecisionsPack::eirFingerprint($snapshot),
                    'calculated_by' => $this->email($c->calculated_by), 'calculated_at' => (string) $c->calculated_at,
                    'locked_by' => $this->email($c->locked_by), 'locked_at' => (string) $c->locked_at];
            })->values()->all();
    }

    /** What the source database held, for the replay's tie-out (acceptance 25). */
    private function tieOut(array $data): array
    {
        $reviewed = array_filter($data['classifications'], fn ($c) => $c['status'] === 'REVIEWED');

        return [
            'users' => count($data['users']),
            'governance_settings' => count($data['governance']),
            'fee_rules' => count($data['fee_rules']),
            'finance_lines' => count($data['finance_lines']),
            'classified_lines' => count($data['classifications']) - count($reviewed),
            'reviewed_lines' => count($reviewed),
            'reviewed_lines_in_force' => count(array_filter($reviewed, fn ($c) => ! $c['superseded'])),
            'person_locked_eirs' => count($data['eir_locks']),
            'documents' => count($data['documents']),
            'fee_moves' => count($data['fee_moves']),
            'reopen_requests' => count($data['reopen_requests']),
            'account_exclusions_approved' => count(array_filter($data['account_exclusions'], fn ($x) => $x['status'] === 'APPROVED')),
        ];
    }
}
