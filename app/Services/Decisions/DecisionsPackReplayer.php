<?php

namespace App\Services\Decisions;

use App\Models\ContractEir;
use App\Models\ContractFee;
use App\Models\EirFeeClassificationEvent;
use App\Models\User;
use App\Services\AuditLoggerService;
use App\Services\Ebanker\GlFeeBuildService;
use App\Services\Eir\EirCalculationService;
use App\Services\Eir\FeeImportService;
use App\Services\Eir\FeeSourcePrecedence;
use App\Services\Eir\GovernanceService;
use App\Services\Eir\HeldFeeEvidence;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Replays a decisions pack into a freshly bootstrapped database (Staging
 * Module Specification v1, section 8.10).
 *
 * Two places in the bootstrap: replay() after the fee build and before the
 * schedules are approved, and carryLock() in place of the bootstrap's own lock
 * for each loan a person locked in the source database.
 *
 * Each decision keeps its original person and date on the record and writes
 * one audit entry naming the pack (acceptance 26); none carries the System
 * Bootstrap label. A decision whose row is missing or different here is not
 * applied: it is listed on the replay report with the reason, for a person to
 * decide again (acceptance 27).
 */
class DecisionsPackReplayer
{
    public const APPLIED = 'APPLIED';
    public const ALREADY = 'ALREADY';
    public const HISTORY = 'HISTORY';
    public const NOT_FOUND = 'NOT_FOUND';
    public const DIFFERENT = 'DIFFERENT';
    public const REFUSED = 'REFUSED';
    /** Carried, approved on the source before the supporting-document rule required a document (section 8.10). */
    public const GRANDFATHERED = 'GRANDFATHERED';

    private array $data = [];

    private array $manifest = [];

    /** @var array<string, int> e-mail => user id here */
    private array $userIds = [];

    /** @var list<array{kind: string, key: string, outcome: string, detail: string}> */
    private array $report = [];

    /** Whether the users are carried already (replayExclusions runs before replay). */
    private bool $usersCarried = false;

    /** @var array<string, int> fee key => fee id here, as built before any move */
    private array $feeIds = [];

    private string $label;

    /** @var array<string, list<array>> 'governance|key|value|from' or 'fee_rule|name' => the documents that support it */
    private array $documentsFor = [];

    /** @var array<string, int> SHA-256 => the document stored here, so a second decision it supports gets a link */
    private array $stored = [];

    public function __construct(private readonly string $folder)
    {
        $path = $folder . '/' . DecisionsPack::MANIFEST;
        if (! is_file($path)) {
            throw new RuntimeException("No decisions pack at {$folder} (no " . DecisionsPack::MANIFEST . ').');
        }
        $this->manifest = json_decode((string) file_get_contents($path), true) ?: [];
        foreach (DecisionsPack::FILES as $kind => $file) {
            if (! isset($this->manifest['files'][$kind]) && in_array($kind, ['documents', 'account_exclusions'], true)) {
                $this->data[$kind] = []; // a pack exported before documents, or the account exclusions, were carried
                continue;
            }
            $json = (string) @file_get_contents($folder . '/' . $file);
            $expected = $this->manifest['files'][$kind]['sha256'] ?? null;
            if ($expected === null || ! hash_equals($expected, hash('sha256', $json))) {
                throw new RuntimeException("The decisions pack file {$file} does not match its manifest: the pack was changed after export.");
            }
            $this->data[$kind] = json_decode($json, true) ?? [];
        }
        foreach ($this->data['documents'] as $d) {
            if ($d['file'] !== null && hash_file('sha256', $folder . '/' . $d['file']) !== $d['sha256']) {
                throw new RuntimeException("The document {$d['original_name']} in the decisions pack does not match its SHA-256: the pack was changed after export.");
            }
            foreach ($d['supports'] as $s) {
                $this->documentsFor[self::supportKey($s)][] = $d + ['link_reason' => $s['link_reason'] ?? null];
            }
        }
        $this->label = DecisionsPack::label((string) ($this->manifest['pack'] ?? basename($folder)), (string) ($this->manifest['source_database'] ?? 'the source database'));
    }

    public function label(): string
    {
        return $this->label;
    }

    public function pack(): string
    {
        return (string) ($this->manifest['pack'] ?? basename($this->folder));
    }

    /** The first place: everything but the EIR locks, in the order of DecisionsPack::FILES. */
    public function replay(int $bootstrapUser): array
    {
        \App\Support\LedgerReadLimits::raise();
        if (! $this->usersCarried) {
            $this->users();
        }
        $this->governance();
        $this->feeRules();
        $this->financeLines();
        $this->financeCoverage();
        $this->heldTies($bootstrapUser);
        $this->heldDismissals($bootstrapUser);
        $this->indexFees();
        $this->feeMoves();
        $precedence = app(FeeSourcePrecedence::class);
        $precedence->apply($bootstrapUser);
        if ($this->overlapDecisions() > 0) {
            $precedence->apply($bootstrapUser);
        }
        $this->classifications();
        $this->reopenRequests();

        return $this->summary();
    }

    /**
     * The account exclusions people decided in the source database, carried before the loan book is
     * built (the bootstrap calls this first, then replay()): an exclusion here keeps its people and
     * dates, and an approved one is applied (AccountExclusionService) so the build leaves the account
     * out. The users are carried first, since the exclusions name them.
     *
     *  - not in the register here: recorded as it stood in the source (APPLIED);
     *  - here in the same status: ALREADY;
     *  - here APPROVED from the committed client input, and withdrawn by people in the source: the
     *    withdrawal is carried and the account comes back into the book (APPLIED);
     *  - here in another status: DIFFERENT, listed for a person to decide again;
     *  - a person it names is not known here: NOT_FOUND.
     *
     * @return array<string, array<string, int>>
     */
    public function replayExclusions(): array
    {
        if (! $this->usersCarried) {
            $this->users();
            $this->usersCarried = true;
        }
        if (! Schema::hasTable('account_exclusions')) {
            return [];
        }
        $service = app(\App\Services\Ebanker\AccountExclusionService::class);
        foreach ($this->data['account_exclusions'] as $x) {
            $key = (string) $x['contract_id'];
            $people = [];
            foreach (['proposed_by', 'approved_by', 'withdrawal_requested_by', 'closed_by'] as $col) {
                $people[$col] = $this->userId($x[$col] ?? null);
                if (($x[$col] ?? null) !== null && $people[$col] === null) {
                    $this->note('account_exclusions', $key, self::NOT_FOUND, "{$col} {$x[$col]} is not known here");
                    continue 2;
                }
            }
            $here = DB::table('account_exclusions')->where('contract_id', $key)->first();
            try {
                if ($here !== null && $here->status === $x['status']) {
                    $this->note('account_exclusions', $key, self::ALREADY, strtolower($x['status']) . ' here already (#' . $here->id . ')');
                    continue;
                }
                if ($here !== null && $here->status === 'APPROVED' && $here->source === 'CLIENT_INPUT' && $x['status'] === 'WITHDRAWN' && $people['closed_by'] !== null) {
                    // withdrawn by people in the source: the request and its approval, as they were made there
                    DB::table('account_exclusions')->where('id', $here->id)->update(['withdrawal_requested_by' => $people['withdrawal_requested_by'] ?? $people['closed_by'],
                        'withdrawal_requested_at' => $x['withdrawal_requested_at'] ?? $x['closed_at'], 'withdrawal_reason' => $x['close_reason'] ?? $x['withdrawal_reason'] ?? 'Withdrawn in the source database']);
                    $service->approveWithdrawal((int) $here->id, (int) $people['closed_by']);
                    $this->audit('account_exclusions', (int) $here->id, ['contract_id' => $key, 'status' => 'WITHDRAWN'], $x['closed_by'], $x['closed_at'], ['withdrawn_in_source' => true]);
                    $this->note('account_exclusions', $key, self::APPLIED, 'withdrawn by ' . $x['closed_by'] . ' in the source; the account is back in the book');
                    continue;
                }
                if ($here !== null) {
                    $this->note('account_exclusions', $key, self::DIFFERENT, strtolower($here->status) . ' here (#' . $here->id . '), ' . strtolower($x['status']) . ' in the source');
                    continue;
                }
                $id = (int) DB::table('account_exclusions')->insertGetId([
                    'contract_id' => $key, 'account' => $x['account'] ?? null, 'customer_name' => $x['customer_name'] ?? null, 'category' => $x['category'], 'reason' => $x['reason'],
                    'evidence' => $x['evidence'], 'sister_contract_id' => $x['sister_contract_id'] ?? null, 'status' => 'PROPOSED', 'source' => 'DECISIONS_PACK',
                    'proposed_by' => $people['proposed_by'], 'proposed_at' => $x['proposed_at'] ?? null, 'created_at' => now(), 'updated_at' => now(),
                ]);
                foreach (array_values(array_filter($this->documentsFor[self::supportKey(['kind' => 'account_exclusion', 'contract_id' => $key])] ?? [], fn ($d) => $d['file'] !== null)) as $d) {
                    $this->attachDocument($d, 'account_exclusions', $id, \App\Services\ApprovalDocumentService::FOLDER . '/account_exclusions/' . $id);
                }
                if ($x['status'] === 'APPROVED') {
                    // applied here as it was in the source: the rows the account holds (none on a fresh install) go to the archive
                    $service->approve($id, $people['approved_by'], $x['approver_label'] ?? null);
                    DB::table('account_exclusions')->where('id', $id)->update(['approved_at' => $x['approved_at'] ?? now(),
                        'withdrawal_requested_by' => $people['withdrawal_requested_by'], 'withdrawal_requested_at' => $x['withdrawal_requested_at'] ?? null, 'withdrawal_reason' => $x['withdrawal_reason'] ?? null]);
                } else {
                    DB::table('account_exclusions')->where('id', $id)->update(['status' => $x['status'], 'closed_by' => $people['closed_by'], 'closed_at' => $x['closed_at'] ?? null,
                        'close_reason' => $x['close_reason'] ?? null, 'approved_by' => $people['approved_by'], 'approved_at' => $x['approved_at'] ?? null, 'approver_label' => $x['approver_label'] ?? null]);
                }
                $this->audit('account_exclusions', $id, ['contract_id' => $key, 'status' => $x['status'], 'category' => $x['category']], $x['approved_by'] ?? $x['closed_by'] ?? $x['proposed_by'], $x['approved_at'] ?? $x['closed_at'] ?? $x['proposed_at'], []);
                $this->note('account_exclusions', $key, self::APPLIED, strtolower($x['status']) . ($x['status'] === 'APPROVED' ? ' by ' . ($x['approved_by'] ?? $x['approver_label']) : ''));
            } catch (Throwable $e) {
                $this->note('account_exclusions', $key, self::REFUSED, $e->getMessage());
            }
        }
        \App\Support\AccountExclusions::forget();

        return array_intersect_key($this->summary(), array_flip(['users', 'account_exclusions']));
    }

    /** @return array<string, array> contract id => the person's lock in the source database */
    public function locks(): array
    {
        $out = [];
        foreach ($this->data['eir_locks'] as $l) {
            $out[(string) $l['contract_id']] = $l;
        }

        return $out;
    }

    /**
     * The second place: solve the EIR as the person who calculated it, and lock it as the person who
     * locked it, only if the rate and the cash flows come out the same as in the source database.
     */
    public function carryLock(array $lock, int $bootstrapUser): string
    {
        $contractId = (string) $lock['contract_id'];
        $calc = app(EirCalculationService::class);
        $maker = $this->userId($lock['calculated_by']) ?? $bootstrapUser;
        $checker = $this->userId($lock['locked_by']);
        if ($checker === null) {
            return $this->note('eir_locks', $contractId, self::REFUSED, 'the person who locked it is not known here');
        }
        $r = $calc->calculate($contractId, $maker);
        if (($r['status'] ?? null) !== 'CALCULATED') {
            return $this->note('eir_locks', $contractId, self::DIFFERENT, 'the EIR could not be solved here: ' . mb_substr((string) ($r['error'] ?? ''), 0, 200));
        }
        $here = ContractEir::where('contract_id', $contractId)->first();
        $fingerprint = DecisionsPack::eirFingerprint(is_array($here->input_snapshot) ? $here->input_snapshot : json_decode((string) $here->input_snapshot, true));
        if (! DecisionsPack::sameRate($here->eir_effective_annual, $lock['eir_effective_annual']) || $fingerprint !== $lock['fingerprint']) {
            return $this->note('eir_locks', $contractId, self::DIFFERENT, sprintf('solved here at %.8f%% against %.8f%% in the source%s; left calculated, not locked, for a person to review',
                (float) $here->eir_effective_annual * 100, (float) $lock['eir_effective_annual'] * 100, $fingerprint !== $lock['fingerprint'] ? ', and its cash flows differ' : ''));
        }
        try {
            // the override is needed only where one person both calculated and locked in the source (a UAT self-approval there); it is then recorded as one
            $calc->lock($contractId, $checker, true, null);
        } catch (Throwable $e) {
            return $this->note('eir_locks', $contractId, self::REFUSED, $e->getMessage());
        }
        ContractEir::where('contract_id', $contractId)->update(['calculated_at' => $lock['calculated_at'], 'locked_at' => $lock['locked_at']]);
        $this->audit(ContractEir::class, (int) $here->id, ['contract_id' => $contractId, 'eir_effective_annual' => $lock['eir_effective_annual']], $lock['locked_by'], $lock['locked_at'],
            ['calculated_by' => $lock['calculated_by'], 'self_approval' => $lock['calculated_by'] === $lock['locked_by']]);

        return $this->note('eir_locks', $contractId, self::APPLIED, 'locked by ' . $lock['locked_by'] . ' on ' . $lock['locked_at']);
    }

    /** A lock in the pack whose loan the bootstrap never reached (no approved schedule, or already locked). */
    public function lockNotReached(string $contractId, string $why): void
    {
        $this->note('eir_locks', $contractId, self::NOT_FOUND, $why);
    }

    // ----- the kinds of decision ---------------------------------------------

    private function users(): void
    {
        $columns = array_flip(Schema::getColumnListing('users'));
        foreach ($this->data['users'] as $u) {
            $email = strtolower(trim((string) $u['email']));
            $existing = User::whereRaw('LOWER(email) = ?', [$email])->first();
            if ($existing === null) {
                $row = array_intersect_key(array_diff_key($u, array_flip(array_merge(DecisionsPack::USER_SECRETS, ['roles', 'permissions']))), $columns);
                $row['password'] = Hash::make(Str::random(48)); // decision S13: a new password through the reset link
                $row['created_at'] ??= now();
                $row['updated_at'] = now();
                $id = (int) DB::table('users')->insertGetId($row);
                $existing = User::find($id);
                $outcome = self::APPLIED;
            } else {
                $outcome = self::ALREADY;
            }
            $roles = array_values(array_filter($u['roles'] ?? [], fn ($r) => DB::table('roles')->where('name', $r)->exists()));
            $perms = array_values(array_filter($u['permissions'] ?? [], fn ($p) => DB::table('permissions')->where('name', $p)->exists()));
            $roles !== [] && $existing->assignRole($roles);
            $perms !== [] && $existing->givePermissionTo($perms);
            $missing = array_merge(array_diff($u['roles'] ?? [], $roles), array_diff($u['permissions'] ?? [], $perms));
            $this->userIds[$email] = (int) $existing->id;
            $this->note('users', $email, $outcome, ($outcome === self::APPLIED ? 'created; signs in through the password reset' : 'already here')
                . ($roles !== [] ? '; roles ' . implode(', ', $roles) : '') . ($missing !== [] ? '; not here, so not given: ' . implode(', ', $missing) : ''));
            if ($outcome === self::APPLIED) {
                $this->audit(User::class, (int) $existing->id, ['email' => $email, 'roles' => $roles], null, null, []);
            }
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function governance(): void
    {
        $gov = app(GovernanceService::class);
        $ruleFrom = $this->manifest['evidence_rule_from'] ?? null;
        foreach ($this->data['governance'] as $g) {
            $key = "{$g['key']} = {$g['value']} from {$g['effective_from']}";
            try {
                $inForce = $gov->inForce($g['key'], \Carbon\CarbonImmutable::parse($g['effective_from']));
                if ($inForce !== null && $inForce->value === $g['value'] && substr((string) $inForce->effective_from, 0, 10) === $g['effective_from']) {
                    $this->note('governance', $key, self::ALREADY, 'already in force here');
                    continue;
                }
                $proposer = $this->userId($g['proposed_by']);
                $approver = $this->userId($g['approved_by']);
                if ($approver === null) {
                    $this->note('governance', $key, self::REFUSED, 'the approver is not known here');
                    continue;
                }
                $docs = $this->documentsFor[self::supportKey(['kind' => 'governance'] + $g)] ?? [];
                $carriedDocs = array_values(array_filter($docs, fn ($d) => $d['file'] !== null));
                // a setting that needs evidence and has none: carried only if its source approved it before the rule (section 8.10)
                $grandfathered = GovernanceService::evidenceRequired($g['key']) && $carriedDocs === [];
                if ($grandfathered && ! \App\Support\EvidenceRule::before($g['approved_at'], $ruleFrom)) {
                    $this->note('governance', $key, self::REFUSED, 'needs a supporting document and the pack carries none, yet the source approved it after the rule required one (from ' . $ruleFrom . ')'
                        . ($docs !== [] ? '; its document was missing from the source storage' : ''));
                    continue;
                }
                $id = DB::transaction(function () use ($gov, $g, $proposer, $approver, $carriedDocs, $grandfathered) {
                    $p = $gov->propose($g['key'], $g['value'], $g['effective_from'], (string) $g['reason'], $proposer);
                    foreach ($carriedDocs as $d) {
                        $this->attachDocument($d, \App\Models\GovernanceSetting::class, (int) $p->id, \App\Services\Eir\GovernanceDocumentService::FOLDER . '/' . $p->id);
                    }
                    $gov->approve($p->id, $approver, true, $grandfathered);
                    DB::table('governance_settings')->where('id', $p->id)->update(['created_at' => $g['proposed_at'] ?: now(), 'approved_at' => $g['approved_at']]);

                    return (int) $p->id;
                });
                $this->audit(\App\Models\GovernanceSetting::class, $id, ['key' => $g['key'], 'value' => $g['value'], 'effective_from' => $g['effective_from']], $g['approved_by'], $g['approved_at'],
                    ['proposed_by' => $g['proposed_by'], 'self_approval' => $g['proposed_by'] !== null && $g['proposed_by'] === $g['approved_by'], 'documents' => count($carriedDocs),
                        'evidence' => $grandfathered ? \App\Support\EvidenceRule::LABEL : null]);
                $this->note('governance', $key, $grandfathered ? self::GRANDFATHERED : self::APPLIED, 'proposed by ' . ($g['proposed_by'] ?? 'unknown') . ', approved by ' . $g['approved_by'] . ' on ' . $g['approved_at']
                    . ($carriedDocs !== [] ? '; ' . count($carriedDocs) . ' document(s) carried' : '') . ($grandfathered ? '; ' . \App\Support\EvidenceRule::LABEL : ''));
            } catch (Throwable $e) {
                $this->note('governance', $key, self::REFUSED, $e->getMessage());
            }
        }
    }

    /** The key a document's support is filed under, the same for the export's entry and the decision it names. */
    private static function supportKey(array $s): string
    {
        return match ($s['kind'] ?? '') {
            'fee_rule' => 'fee_rule|' . $s['name'],
            'account_exclusion' => 'account_exclusion|' . $s['contract_id'],
            default => 'governance|' . $s['key'] . '|' . $s['value'] . '|' . $s['effective_from'],
        };
    }

    /**
     * A carried document: stored here once, under its SHA-256, and attached to the first decision it
     * supports; a further decision it supports gets a link to it, as on the source (section 8.7).
     */
    private function attachDocument(array $d, string $type, int $id, string $folder): void
    {
        if (isset($this->stored[$d['sha256']])) {
            DB::table('supporting_document_links')->insertOrIgnore(['supporting_document_id' => $this->stored[$d['sha256']], 'documentable_type' => $type, 'documentable_id' => $id,
                'linked_by' => $this->userId($d['uploaded_by']), 'reason' => $d['link_reason'] ?? 'Carried by the decisions pack: supports this decision on the source', 'created_at' => now(), 'updated_at' => now()]);

            return;
        }
        $disk = \Illuminate\Support\Facades\Storage::disk(\App\Services\Eir\GovernanceDocumentService::DISK);
        $path = $folder . '/' . $d['sha256'] . '.' . $d['extension'];
        $disk->put($path, (string) file_get_contents($this->folder . '/' . $d['file']));
        $doc = \App\Models\SupportingDocument::create(['documentable_type' => $type, 'documentable_id' => $id, 'disk' => \App\Services\Eir\GovernanceDocumentService::DISK, 'path' => $path,
            'original_name' => $d['original_name'], 'mime_type' => $d['mime_type'], 'size' => $d['size'], 'extension' => $d['extension'], 'document_type' => $d['document_type'],
            'description' => $d['description'], 'hash' => $d['sha256'], 'uploaded_by' => $this->userId($d['uploaded_by'])]);
        DB::table('supporting_documents')->where('id', $doc->id)->update(['created_at' => $d['uploaded_at'] ?: now()]);
        $this->stored[$d['sha256']] = (int) $doc->id;
        $this->audit($type, $id, ['document' => $doc->id, 'name' => $d['original_name'], 'sha256' => $d['sha256']], $d['uploaded_by'], $d['uploaded_at'], ['kind' => 'supporting document']);
    }

    private function feeRules(): void
    {
        $columns = array_flip(Schema::getColumnListing('eir_accounting_rules'));
        $people = ['created_by', 'updated_by', 'approved_by', 'rejected_by', 'withdrawn_by', 'switch_proposed_by'];
        foreach ($this->data['fee_rules'] as $r) {
            $row = array_intersect_key($r, $columns);
            foreach ($people as $c) {
                if (array_key_exists($c, $row)) {
                    $row[$c] = $this->userId($r[$c]);
                }
            }
            $existing = DB::table('eir_accounting_rules')->where('name', $r['name'])->first();
            if ($existing === null) {
                $id = (int) DB::table('eir_accounting_rules')->insertGetId($row + ['created_at' => now(), 'updated_at' => now()]);
            } else {
                $id = (int) $existing->id;
                DB::table('eir_accounting_rules')->where('id', $id)->update($row + ['updated_at' => now()]);
            }
            $docs = array_values(array_filter($this->documentsFor[self::supportKey(['kind' => 'fee_rule', 'name' => $r['name']])] ?? [], fn ($d) => $d['file'] !== null));
            foreach ($docs as $d) {
                $this->attachDocument($d, 'eir_accounting_rules', $id, \App\Services\ApprovalDocumentService::FOLDER . '/eir_accounting_rules/' . $id);
            }
            $this->audit(\App\Models\EirAccountingRule::class, $id, ['name' => $r['name'], 'status' => $r['status'] ?? null], $r['approved_by'] ?? null, $r['approved_at'] ?? null, ['written_by' => $r['updated_by'] ?? $r['created_by'] ?? null, 'documents' => count($docs)]);
            $this->note('fee_rules', (string) $r['name'], self::APPLIED, ($existing === null ? 'added' : 'brought to the source wording') . '; ' . strtolower((string) ($r['status'] ?? 'unknown')));
        }
    }

    private function financeLines(): void
    {
        if ($this->data['finance_lines'] === []) {
            return;
        }
        $here = array_flip(DecisionsPack::keys(DB::table('contract_fees')->orderBy('id')->get()));
        $rows = [];
        foreach ($this->data['finance_lines'] as $l) {
            if (isset($here[$l['key']])) {
                $this->note('finance_lines', $l['key'], self::ALREADY, 'already loaded here');
                continue;
            }
            $rows[] = array_diff_key($l, array_flip(['key', 'loaded_at']));
            $this->note('finance_lines', $l['key'], self::APPLIED, 'loaded pending; its classification follows');
        }
        if ($rows !== []) {
            $r = app(FeeImportService::class)->import($rows, null, null, 'decisions pack ' . $this->pack());
            if (($r['loaded_rows'] ?? 0) !== count($rows)) {
                $this->note('finance_lines', 'the load', self::DIFFERENT, ($r['loaded_rows'] ?? 0) . ' of ' . count($rows) . ' lines loaded; ' . ($r['skipped_rows'] ?? 0) . ' skipped by the import');
            }
            AuditLoggerService::log(DecisionsPack::AUDIT_ACTION, 'contract_fees', null, ['new_values' => ['finance_lines' => count($rows)], 'meta' => ['pack' => $this->label]]);
        }
    }

    private function financeCoverage(): void
    {
        $precedence = app(FeeSourcePrecedence::class);
        foreach ($this->data['finance_coverage'] as $c) {
            $key = "{$c['month_from']} to {$c['month_to']}";
            $exists = Schema::hasTable('fee_source_coverage') && DB::table('fee_source_coverage')->where('tier', FeeSourcePrecedence::TIER_FINANCE)
                ->where('month_from', $c['month_from'])->where('month_to', $c['month_to'])->where('origin', mb_substr((string) $c['origin'], 0, 255))->exists();
            if ($exists) {
                $this->note('finance_coverage', $key, self::ALREADY, 'already recorded');
                continue;
            }
            $precedence->recordCoverage(FeeSourcePrecedence::TIER_FINANCE, $c['month_from'], $c['month_to'], (string) $c['origin'], $this->userId($c['recorded_by']));
            $this->note('finance_coverage', $key, self::APPLIED, (string) $c['origin']);
        }
    }

    private function heldTies(int $bootstrapUser): void
    {
        $gl = app(GlFeeBuildService::class);
        foreach ($this->data['held_ties'] as $t) {
            $key = "{$t['external_transaction_id']} to {$t['contract_id']}";
            $held = DB::table('fee_gl_unassigned')->where('external_transaction_id', $t['external_transaction_id'])->first();
            if ($held === null) {
                $on = DB::table('contract_fees')->where('external_transaction_id', $t['external_transaction_id'])->value('contract_id');
                $this->note('held_ties', $key, $on === null ? self::NOT_FOUND : (ltrim((string) $on, '0') === ltrim($t['contract_id'], '0') ? self::ALREADY : self::DIFFERENT),
                    $on === null ? 'the posting is not held here' : "the GL fee build put it on loan {$on} here");
                continue;
            }
            try {
                $reason = mb_strlen(trim($t['reason'])) >= 10 ? $t['reason'] : 'Tied by hand in the source database: ' . $t['reason'];
                $gl->assignHeld((int) $held->id, $t['contract_id'], $this->userId($t['tied_by']) ?? $bootstrapUser, $reason);
                $this->audit('contract_fees', null, ['external_transaction_id' => $t['external_transaction_id'], 'contract_id' => $t['contract_id']], $t['tied_by'], $t['tied_at'], []);
                $this->note('held_ties', $key, self::APPLIED, 'tied by ' . ($t['tied_by'] ?? 'unknown') . ' on ' . $t['tied_at']);
            } catch (Throwable $e) {
                $this->note('held_ties', $key, self::REFUSED, $e->getMessage());
            }
        }
    }

    private function heldDismissals(int $bootstrapUser): void
    {
        foreach ($this->data['held_dismissals'] as $d) {
            $key = "{$d['external_transaction_id']} not {$d['contract_id']}";
            $held = DB::table('fee_gl_unassigned')->where('external_transaction_id', $d['external_transaction_id'])->first();
            if ($held === null) {
                $this->note('held_dismissals', $key, self::NOT_FOUND, 'the posting is not held here');
                continue;
            }
            try {
                HeldFeeEvidence::dismiss((int) $held->id, $d['contract_id'], (string) $d['reason'], $this->userId($d['dismissed_by']) ?? $bootstrapUser);
                $this->audit('fee_gl_candidate_dismissals', null, ['external_transaction_id' => $d['external_transaction_id'], 'contract_id' => $d['contract_id']], $d['dismissed_by'], $d['dismissed_at'], []);
                $this->note('held_dismissals', $key, self::APPLIED, 'dismissed by ' . ($d['dismissed_by'] ?? 'unknown'));
            } catch (Throwable $e) {
                $this->note('held_dismissals', $key, self::REFUSED, $e->getMessage());
            }
        }
    }

    /** Every fee line's key here, taken once before any move, as the export keyed the lines it did not carry whole. */
    private function indexFees(): void
    {
        $this->feeIds = array_flip(DecisionsPack::keys(DB::table('contract_fees')->orderBy('id')->get()));
    }

    private function feeMoves(): void
    {
        foreach ($this->data['fee_moves'] as $m) {
            $key = $m['key'];
            if ($m['finance_line']) {
                $this->note('fee_moves', $key, self::HISTORY, "Finance's line was carried where it now stands (moved from {$m['from_contract']} to {$m['to_contract']} by " . ($m['moved_by'] ?? 'unknown') . ')');
                continue;
            }
            $id = $this->feeIds[$key] ?? null;
            $fee = $id === null ? null : ContractFee::find($id);
            if ($fee === null) {
                $this->note('fee_moves', $key, self::NOT_FOUND, 'the line is not built here');
                continue;
            }
            if ($fee->contract_id !== $m['from_contract']) {
                $this->note('fee_moves', $key, $fee->contract_id === $m['to_contract'] ? self::ALREADY : self::DIFFERENT, "the line is on loan {$fee->contract_id} here");
                continue;
            }
            if (! DB::table('contract_eir')->where('contract_id', $m['to_contract'])->exists()) {
                $this->note('fee_moves', $key, self::REFUSED, "no loan {$m['to_contract']} in the contract master here");
                continue;
            }
            $by = $this->userId($m['moved_by']);
            $reason = 'Moved from loan ' . $m['from_contract'] . ' to loan ' . $m['to_contract'] . ($m['to_date'] !== $m['from_date'] ? ", dated {$m['to_date']} (was " . ($m['from_date'] ?? 'undated') . ')' : '') . ': ' . $m['reason'];
            DB::transaction(function () use ($fee, $m, $reason, $by) {
                $fee->update(['contract_id' => $m['to_contract'], 'transaction_date' => $m['to_date'], 'classification_status' => 'PENDING', 'integral' => null,
                    'classified_by' => null, 'classified_at' => null, 'reviewed_by' => null, 'reviewed_at' => null]);
                $e = EirFeeClassificationEvent::create(['contract_fee_id' => $fee->id, 'action' => 'REOPENED', 'integral' => null, 'reason' => mb_substr($reason, 0, 2000), 'performed_by' => $by]);
                DB::table($e->getTable())->where('id', $e->id)->update(['created_at' => $m['moved_at'], 'updated_at' => $m['moved_at']]);
            });
            $this->audit(ContractFee::class, (int) $fee->id, ['contract_id' => $m['to_contract'], 'transaction_date' => $m['to_date'], 'from_contract' => $m['from_contract']], $m['moved_by'], $m['moved_at'], ['reason' => $m['reason']]);
            $this->note('fee_moves', $key, self::APPLIED, "moved from {$m['from_contract']} to {$m['to_contract']} by " . ($m['moved_by'] ?? 'unknown'));
        }
    }

    private function overlapDecisions(): int
    {
        $applied = 0;
        foreach ($this->data['overlap_decisions'] as $d) {
            $id = $this->feeIds[$d['key']] ?? null;
            $fee = $id === null ? null : DB::table('contract_fees')->where('id', $id)->first();
            if ($fee === null) {
                $this->note('overlap_decisions', $d['key'], self::NOT_FOUND, 'the line is not built here');
                continue;
            }
            if (($fee->source_overlap ?? null) === null) {
                $this->note('overlap_decisions', $d['key'], self::DIFFERENT, 'the line is not an overlap here');
                continue;
            }
            $by = $this->userId($d['decided_by']);
            if ($by === null) {
                $this->note('overlap_decisions', $d['key'], self::REFUSED, 'the person who decided is not known here');
                continue;
            }
            DB::table('fee_source_decisions')->updateOrInsert(['contract_fee_id' => $id],
                ['decision' => $d['decision'], 'reason' => mb_substr((string) $d['reason'], 0, 500), 'decided_by' => $by, 'created_at' => $d['decided_at'], 'updated_at' => now()]);
            $this->audit('contract_fees', $id, ['decision' => $d['decision']], $d['decided_by'], $d['decided_at'], ['reason' => $d['reason']]);
            $this->note('overlap_decisions', $d['key'], self::APPLIED, $d['decision'] . ' by ' . $d['decided_by']);
            $applied++;
        }

        return $applied;
    }

    private function classifications(): void
    {
        $rules = DB::table('eir_accounting_rules')->pluck('id', 'name')->all();
        foreach ($this->data['classifications'] as $c) {
            $id = $this->feeIds[$c['key']] ?? null;
            $fee = $id === null ? null : ContractFee::find($id);
            if ($fee === null) {
                $this->note('classifications', $c['key'], self::NOT_FOUND, 'the line is not built here');
                continue;
            }
            $problems = [];
            if ($fee->contract_id !== $c['contract_id']) {
                $problems[] = "on loan {$fee->contract_id} here, {$c['contract_id']} in the source";
            }
            if (abs((float) $fee->amount - (float) $c['amount']) >= 0.005) {
                $problems[] = 'amount ' . number_format((float) $fee->amount, 2) . ' here, ' . number_format((float) $c['amount'], 2) . ' in the source';
            }
            if ($fee->cashflow_direction !== null && $c['cashflow_direction'] !== null && $fee->cashflow_direction !== $c['cashflow_direction']) {
                $problems[] = "direction {$fee->cashflow_direction} here, {$c['cashflow_direction']} in the source";
            }
            if ($problems !== []) {
                $this->note('classifications', $c['key'], self::DIFFERENT, implode('; ', $problems) . '; left pending for a person');
                continue;
            }
            if ($fee->classification_status === $c['status'] && (bool) $fee->integral === (bool) $c['integral']) {
                $this->note('classifications', $c['key'], self::ALREADY, strtolower($c['status']) . ' here');
                continue;
            }
            $classifier = $this->userId($c['classified_by']);
            $reviewer = $c['status'] === 'REVIEWED' ? $this->userId($c['reviewed_by']) : null;
            if ($c['status'] === 'REVIEWED' && $reviewer === null) {
                $this->note('classifications', $c['key'], self::REFUSED, 'the reviewer is not known here');
                continue;
            }
            DB::transaction(function () use ($fee, $c, $classifier, $reviewer, $rules) {
                $fee->update(['integral' => $c['integral'], 'cashflow_direction' => $fee->cashflow_direction ?? $c['cashflow_direction'], 'classification_status' => $c['status'],
                    'classification_reason' => $c['reason'], 'classified_by' => $classifier, 'classified_at' => $c['classified_at'],
                    'reviewed_by' => $reviewer, 'reviewed_at' => $c['status'] === 'REVIEWED' ? $c['reviewed_at'] : null]);
                $events = [['CLASSIFIED', $classifier, $c['classified_at'], $c['rule'] === null ? null : ($rules[$c['rule']] ?? null)]];
                if ($c['status'] === 'REVIEWED') {
                    $events[] = ['REVIEWED', $reviewer, $c['reviewed_at'], null];
                }
                foreach ($events as [$action, $by, $at, $rule]) {
                    $e = EirFeeClassificationEvent::create(['contract_fee_id' => $fee->id, 'action' => $action, 'integral' => $c['integral'], 'reason' => $c['reason'], 'accounting_rule_id' => $rule, 'performed_by' => $by]);
                    if ($at !== null) {
                        DB::table($e->getTable())->where('id', $e->id)->update(['created_at' => $at, 'updated_at' => $at]);
                    }
                }
            });
            $self = $c['status'] === 'REVIEWED' && $c['classified_by'] !== null && $c['classified_by'] === $c['reviewed_by'];
            $this->audit(ContractFee::class, (int) $fee->id, ['status' => $c['status'], 'integral' => $c['integral']], $c['reviewed_by'] ?? $c['classified_by'], $c['reviewed_at'] ?? $c['classified_at'],
                ['classified_by' => $c['classified_by'], 'self_approval' => $self]);
            $this->note('classifications', $c['key'], self::APPLIED, strtolower($c['status']) . ($self ? ' (a UAT self-approval in the source, carried as one)' : ''));
        }
    }

    private function reopenRequests(): void
    {
        if (! Schema::hasTable('eir_reopen_requests')) {
            return;
        }
        foreach ($this->data['reopen_requests'] as $r) {
            $key = "{$r['contract_id']} on {$r['requested_at']}";
            $requester = $this->userId($r['requested_by']);
            if ($requester === null || ! DB::table('contract_eir')->where('contract_id', $r['contract_id'])->exists()) {
                $this->note('reopen_requests', $key, self::NOT_FOUND, $requester === null ? 'the requester is not known here' : 'no such loan here');
                continue;
            }
            if (DB::table('eir_reopen_requests')->where('contract_id', $r['contract_id'])->where('requested_at', $r['requested_at'])->exists()) {
                $this->note('reopen_requests', $key, self::ALREADY, 'already recorded');
                continue;
            }
            // history only: here the fee lines were moved before any lock, so nothing is reopened
            $id = (int) DB::table('eir_reopen_requests')->insertGetId(['contract_id' => $r['contract_id'], 'status' => $r['status'], 'reason' => $r['reason'],
                'requested_by' => $requester, 'requested_at' => $r['requested_at'], 'decided_by' => $this->userId($r['decided_by']), 'decided_at' => $r['decided_at'],
                'decision_reason' => $r['decision_reason'], 'created_at' => $r['requested_at'], 'updated_at' => now()]);
            $this->audit('eir_reopen_requests', $id, ['contract_id' => $r['contract_id'], 'status' => $r['status']], $r['decided_by'] ?? $r['requested_by'], $r['decided_at'] ?? $r['requested_at'], ['history_only' => true]);
            $this->note('reopen_requests', $key, self::HISTORY, strtolower((string) $r['status']) . '; recorded as history, nothing reopened');
        }
    }

    // ----- the report --------------------------------------------------------

    private function userId(?string $email): ?int
    {
        if ($email === null) {
            return null;
        }
        $email = strtolower(trim($email));
        if (! isset($this->userIds[$email])) {
            $id = User::whereRaw('LOWER(email) = ?', [$email])->value('id');
            if ($id === null) {
                return null;
            }
            $this->userIds[$email] = (int) $id;
        }

        return $this->userIds[$email];
    }

    private function audit(string $entity, ?int $id, array $values, ?string $by, ?string $at, array $meta): void
    {
        AuditLoggerService::log(DecisionsPack::AUDIT_ACTION, $entity, $id, ['new_values' => $values,
            'meta' => ['pack' => $this->label, 'original_by' => $by, 'original_at' => $at] + $meta]);
    }

    private function note(string $kind, string $key, string $outcome, string $detail): string
    {
        $this->report[] = compact('kind', 'key', 'outcome', 'detail');

        return $outcome;
    }

    /** @return list<array{kind: string, key: string, outcome: string, detail: string}> */
    public function report(): array
    {
        return $this->report;
    }

    /** @return array<string, array<string, int>> kind => outcome => count */
    public function summary(): array
    {
        $out = [];
        foreach ($this->report as $r) {
            $out[$r['kind']][$r['outcome']] = ($out[$r['kind']][$r['outcome']] ?? 0) + 1;
        }

        return $out;
    }

    /** Every decision not carried, for a person to decide again. */
    public function notCarried(): array
    {
        return array_values(array_filter($this->report, fn ($r) => in_array($r['outcome'], [self::NOT_FOUND, self::DIFFERENT, self::REFUSED], true)));
    }

    /**
     * The tie-out against the source database (acceptance 25): reviewed lines in force, person-locked
     * EIRs, carried governance settings and users, each against what the manifest says the source held.
     *
     * @return array<string, array{source: int, here: int, listed: int}>
     */
    public function tieOut(): array
    {
        $source = $this->manifest['tie_out'] ?? [];
        $listed = fn (string $kind) => count(array_filter($this->notCarried(), fn ($r) => $r['kind'] === $kind));
        $carried = fn (string $kind) => count(array_filter($this->report, fn ($r) => $r['kind'] === $kind && in_array($r['outcome'], [self::APPLIED, self::ALREADY, self::GRANDFATHERED], true)));
        $grandfathered = count(array_filter($this->report, fn ($r) => $r['outcome'] === self::GRANDFATHERED));
        $reviewedHere = DB::table('contract_fees')->where('classification_status', 'REVIEWED')->whereNull('superseded_by_source')->count();

        return [
            'users' => ['source' => (int) ($source['users'] ?? 0), 'here' => $carried('users'), 'listed' => $listed('users')],
            'governance settings' => ['source' => (int) ($source['governance_settings'] ?? 0), 'here' => $carried('governance'), 'listed' => $listed('governance')],
            'reviewed fee lines in force' => ['source' => (int) ($source['reviewed_lines_in_force'] ?? 0), 'here' => $reviewedHere, 'listed' => count(array_filter($this->notCarried(), fn ($r) => $r['kind'] === 'classifications'))],
            'EIRs locked by a person' => ['source' => (int) ($source['person_locked_eirs'] ?? 0), 'here' => DB::table('contract_eir')->whereNotNull('locked_at')->whereNull('locked_label')->count(), 'listed' => $listed('eir_locks')],
            'supporting documents' => ['source' => (int) ($source['documents'] ?? 0), 'here' => count($this->stored), 'listed' => 0],
            'account exclusions approved' => ['source' => (int) ($source['account_exclusions_approved'] ?? 0),
                'here' => Schema::hasTable('account_exclusions') ? DB::table('account_exclusions')->where('status', 'APPROVED')->where('source', '<>', 'CLIENT_INPUT')->count() : 0, 'listed' => $listed('account_exclusions')],
            // carried, and counted apart: approved on the source before a document was required (section 8.10)
            'approved before supporting documents were required' => ['source' => $grandfathered, 'here' => $grandfathered, 'listed' => 0],
        ];
    }

    public function writeReport(string $path): string
    {
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        $h = fopen($path, 'w');
        fputcsv($h, ['kind', 'key', 'outcome', 'detail']);
        foreach ($this->report as $r) {
            fputcsv($h, [$r['kind'], $r['key'], $r['outcome'], $r['detail']]);
        }
        fclose($h);

        return $path;
    }
}
