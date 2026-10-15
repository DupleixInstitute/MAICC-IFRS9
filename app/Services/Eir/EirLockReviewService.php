<?php

namespace App\Services\Eir;

use App\Models\ContractEir;
use App\Services\AuditLoggerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Locked EIRs flagged for review when fee evidence for the loan was not in the inputs they were locked on
 * (15 October 2026; results review of 15 October, section 6.2).
 *
 * A flag is raised automatically (detect) when, for a locked contract, a held GL fee posting names the
 * loan as its likely owner, or a fee line (of a source in force, not superseded) was recorded after the
 * lock; and by a person (raise) where the evidence is outside the system, such as Finance's workbook.
 * A flag never reopens the EIR and never changes a figure: a second person clears it with the reason
 * (the fees were in fact considered, or are not the loan's), or it closes when a reopening of the EIR
 * is approved (EirReopenRequestService::approve), after which the EIR is solved again with its fees.
 */
class EirLockReviewService
{
    public const TABLE = 'eir_lock_reviews';

    /** Raise the automatic flags; a flag already raised for the same evidence is left as it is. @return int new flags */
    public function detect(?int $userId = null): int
    {
        if (! Schema::hasTable(self::TABLE)) {
            return 0;
        }
        $new = 0;
        foreach (ContractEir::whereNotNull('locked_at')->get(['contract_id', 'locked_at']) as $c) {
            $id = (string) $c->contract_id;
            foreach (HeldFeeEvidence::forContract($id) as $h) {
                $new += $this->insert($id, 'AUTOMATIC', 'held:' . ($h['external_transaction_id'] ?? $h['held_id']),
                    'A GL fee posting held without a loan names this loan as its likely owner, and was not in the inputs the EIR was locked on: ' . HeldFeeEvidence::describe([$h]) . '.',
                    $h, $userId);
            }
            $late = EirReadinessService::feeQuery()->where('contract_id', $id)->where('created_at', '>', $c->locked_at)->get(['id', 'fee_type', 'amount', 'source_system', 'transaction_date', 'created_at']);
            foreach ($late as $f) {
                $new += $this->insert($id, 'AUTOMATIC', 'fee:' . $f->id,
                    'A ' . $f->fee_type . ' fee of ' . number_format((float) $f->amount, 2) . ' (' . $f->source_system . ') was recorded on ' . $f->created_at . ', after the EIR was locked on ' . $c->locked_at . '.',
                    (array) $f, $userId);
            }
        }
        if ($new > 0) {
            AuditLoggerService::log('Locked EIR Review Flags Raised', self::TABLE, null, ['new_values' => ['flags' => $new, 'source' => 'AUTOMATIC'], 'meta' => ['user' => $userId]]);
        }

        return $new;
    }

    /** A person flags a locked EIR, with the evidence (for example Finance's workbook). */
    public function raise(string $contractId, string $reason, array $evidence, int $userId, ?string $key = null): int
    {
        $id = ltrim(trim($contractId), '0');
        $c = ContractEir::where('contract_id', $id)->first();
        if ($c === null || $c->locked_at === null) {
            throw new InvalidArgumentException("Loan {$id} has no locked EIR to review.");
        }
        if (mb_strlen(trim($reason)) < 10) {
            throw new InvalidArgumentException('Say why the locked EIR needs review (at least 10 characters).');
        }
        $this->insert($id, 'MANUAL', $key ?? 'manual:' . md5($reason), trim($reason), $evidence, $userId);
        $flag = DB::table(self::TABLE)->where('contract_id', $id)->where('evidence_key', $key ?? 'manual:' . md5($reason))->first();
        AuditLoggerService::log('Locked EIR Review Flag Raised', self::TABLE, $flag->id, ['new_values' => ['contract_id' => $id, 'reason' => $reason], 'meta' => ['user' => $userId]]);

        return (int) $flag->id;
    }

    /** A second person clears a flag with the reason; the person who raised it cannot. */
    public function clear(int $flagId, int $userId, string $reason): void
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 10) {
            throw new InvalidArgumentException('Give the reason the flag can be cleared (at least 10 characters).');
        }
        $flag = DB::table(self::TABLE)->where('id', $flagId)->first();
        if ($flag === null || $flag->status !== 'OPEN') {
            throw new InvalidArgumentException('Only an open review flag can be cleared.');
        }
        if ($flag->raised_by !== null && (int) $flag->raised_by === $userId) {
            throw new InvalidArgumentException('The person who raised the flag cannot clear it; a second person does.');
        }
        DB::table(self::TABLE)->where('id', $flagId)->update(['status' => 'CLEARED', 'closed_by' => $userId, 'closed_at' => now(), 'closed_reason' => $reason, 'updated_at' => now()]);
        AuditLoggerService::log('Locked EIR Review Flag Cleared', self::TABLE, $flagId, ['new_values' => ['contract_id' => $flag->contract_id, 'reason' => $reason], 'meta' => ['user' => $userId]]);
    }

    /** An approved reopening closes the loan's open flags: the EIR is solved again with its fees. */
    public function closeOnReopen(string $contractId, int $userId): int
    {
        if (! Schema::hasTable(self::TABLE)) {
            return 0;
        }

        return DB::table(self::TABLE)->where('contract_id', ltrim($contractId, '0'))->where('status', 'OPEN')
            ->update(['status' => 'REOPENED', 'closed_by' => $userId, 'closed_at' => now(), 'closed_reason' => 'The EIR was reopened (approved reopening request) to be solved again with its fees.', 'updated_at' => now()]);
    }

    /** @return list<array> the open flags, newest first, with the loan's locked EIR */
    public function open(): array
    {
        if (! Schema::hasTable(self::TABLE)) {
            return [];
        }

        return DB::table(self::TABLE . ' as r')->leftJoin('contract_eir as c', 'c.contract_id', '=', 'r.contract_id')
            ->where('r.status', 'OPEN')->orderBy('r.contract_id')->orderBy('r.id')
            ->get(['r.id', 'r.contract_id', 'c.id as contract_eir_id', 'c.customer_name', 'c.eir_effective_annual', 'c.eir_nominal_annual', 'c.locked_at', 'c.locked_label', 'r.source', 'r.reason', 'r.raised_at', 'r.raised_by'])
            ->map(fn ($r) => (array) $r)->all();
    }

    private function insert(string $contractId, string $source, string $key, string $reason, array $evidence, ?int $userId): int
    {
        return DB::table(self::TABLE)->insertOrIgnore([
            'contract_id' => $contractId, 'source' => $source, 'evidence_key' => mb_substr($key, 0, 160), 'reason' => mb_substr($reason, 0, 1000),
            'evidence' => json_encode($evidence), 'status' => 'OPEN', 'raised_by' => $userId, 'raised_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
