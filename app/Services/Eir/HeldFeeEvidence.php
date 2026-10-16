<?php

namespace App\Services\Eir;

use App\Services\AuditLoggerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Held GL fee postings that name a loan as their likely owner (15 October 2026).
 *
 * The GL fee build holds a fee posting whose batch names no single loan (fee_gl_unassigned), and where
 * the narration names a loan's account holder who drew money that day it records that loan as the
 * candidate. A candidate is evidence, never an allocation: the posting is tied only by a person
 * (GlFeeBuildService::assignHeld). But while such a posting is unresolved the loan's fees are not
 * known to be complete, so the EIR must not be solved or locked (EirReadinessService, FEE_HELD_FOR_LOAN;
 * EirCalculationService::lock). A person who finds the candidate wrong says so with the reason
 * (dismiss), which is kept by the posting's external id so the GL fee build's rewrite does not lose it.
 */
class HeldFeeEvidence
{
    /** @return list<array{held_id:int, external_transaction_id:?string, date:?string, gl:string, amount:float, narration:?string}> */
    public static function forContract(string $contractId): array
    {
        if (! Schema::hasTable('fee_gl_unassigned')) {
            return [];
        }
        $id = ltrim(trim($contractId), '0');
        $rows = DB::table('fee_gl_unassigned')->whereNotNull('candidate_contract_id')
            ->whereIn('candidate_contract_id', array_values(array_unique([$id, str_pad($id, 15, '0', STR_PAD_LEFT), $contractId])))
            ->orderBy('transaction_date')->orderBy('id')
            ->get(['id', 'external_transaction_id', 'transaction_date', 'gl_code', 'amount', 'narration']);
        if ($rows->isEmpty()) {
            return [];
        }
        $dismissed = Schema::hasTable('fee_gl_candidate_dismissals')
            ? DB::table('fee_gl_candidate_dismissals')->where('contract_id', $id)->pluck('external_transaction_id')->flip()->all()
            : [];

        return $rows->reject(fn ($r) => $r->external_transaction_id !== null && isset($dismissed[$r->external_transaction_id]))
            ->map(fn ($r) => ['held_id' => (int) $r->id, 'external_transaction_id' => $r->external_transaction_id, 'date' => $r->transaction_date,
                'gl' => (string) $r->gl_code, 'amount' => (float) $r->amount, 'narration' => $r->narration])
            ->values()->all();
    }

    /** One line naming the postings, for a readiness message or a review flag. */
    public static function describe(array $held): string
    {
        return implode('; ', array_map(fn ($h) => ($h['date'] ?? '?') . ' GL ' . $h['gl'] . ' ' . number_format($h['amount'], 2) . ($h['narration'] ? ' "' . mb_substr($h['narration'], 0, 60) . '"' : ''), $held));
    }

    /**
     * A person states that a held posting is not this loan's fee, with the reason. The posting stays held
     * (for another loan, or for the person who ties it); only the candidate stops blocking this loan.
     *
     * @param  ?array  $origin  additive, optional (Held Fee Classification Export/Reimport Specification v1,
     *                          section 6.1): merged into this call's audit meta when given; the screen's own
     *                          calls never pass it, so they are byte-for-byte unaffected.
     */
    public static function dismiss(int $heldId, string $contractId, string $reason, int $userId, ?array $origin = null): void
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 10) {
            throw new InvalidArgumentException('Say why this posting is not the loan\'s fee (at least 10 characters).');
        }
        $held = DB::table('fee_gl_unassigned')->where('id', $heldId)->first();
        if ($held === null) {
            throw new InvalidArgumentException("No held fee posting {$heldId}.");
        }
        $id = ltrim(trim($contractId), '0');
        if (ltrim((string) $held->candidate_contract_id, '0') !== $id) {
            throw new InvalidArgumentException("Held posting {$heldId} does not name loan {$id} as its likely owner.");
        }
        if ($held->external_transaction_id === null) {
            throw new InvalidArgumentException("Held posting {$heldId} has no external id, so a dismissal could not survive the next GL fee build.");
        }
        DB::table('fee_gl_candidate_dismissals')->updateOrInsert(
            ['external_transaction_id' => $held->external_transaction_id, 'contract_id' => $id],
            ['reason' => mb_substr($reason, 0, 500), 'dismissed_by' => $userId, 'created_at' => now(), 'updated_at' => now()]
        );
        AuditLoggerService::log('Held Fee Candidate Dismissed', 'fee_gl_unassigned', $heldId, ['new_values' => ['contract_id' => $id,
            'external_transaction_id' => $held->external_transaction_id, 'amount' => (float) $held->amount, 'reason' => $reason], 'meta' => ($origin ?? []) + ['user' => $userId]]);
    }
}
