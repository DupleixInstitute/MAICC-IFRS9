<?php

namespace App\Services\Eir;

use App\Models\ContractEir;
use App\Services\AuditLoggerService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fees the loan origination system holds but that were never posted (pack 13, question F2; 16 October 2026).
 *
 * E-Banker's loan origination system captures the charges of a loan at application (LOS_DISB_CHARGES_POST,
 * P2_07). A charge that was posted reaches the ledger as a GL fee posting, and under fee_source_precedence
 * the GL line (or Finance's workbook line) is the source of record and the LOS line is superseded. An LOS
 * line that no other source holds stands on its own: it carries no batch and no income GL, so nothing shows
 * the borrower was ever charged it. The governed setting los_unposted_fee_basis decides whether such a line
 * enters the EIR inputs:
 *
 *  - AS_BUILT (the default, so nothing moves on deploy): it counts, like any other line of the source of record;
 *  - CONFIRMED_ONLY: it counts only where Finance has confirmed it was charged, by an approved answer on sheet
 *    4b of the fee reconciliation workbook (fee_unposted_confirmations, charged = true).
 *
 * The rule is read where the engine decides which fee lines count (EirReadinessService::feeQuery and
 * EirContractInputService), never by writing to the fee lines. A locked EIR is never reopened by it: when the
 * setting changes, or an answer changes which lines count, each locked EIR whose fee inputs would change is
 * flagged for review (EirLockReviewService::raise), naming the lines.
 */
class UnpostedFeeBasis
{
    public const KEY = 'los_unposted_fee_basis';
    public const AS_BUILT = 'Counted where no other source holds the fee (as built)';
    public const CONFIRMED_ONLY = 'Counted only where Finance confirms it was charged';
    public const TABLE = 'fee_unposted_confirmations';

    /**
     * The LOS lines that are the source of record for their fee and were never posted: no other source holds
     * them (not superseded) and they carry no income GL. Optionally for one contract.
     */
    public static function neverPostedQuery(?string $contractId = null): Builder
    {
        $q = DB::table('contract_fees')->where('source_system', FeeSourcePrecedence::SOURCE_LOS)
            ->where(fn ($q) => $q->whereNull('gl_account_ref')->orWhere('gl_account_ref', ''));
        if (Schema::hasColumn('contract_fees', 'superseded_by_source')) {
            $q->whereNull('superseded_by_source');
        }
        if ($contractId !== null) {
            $q->where('contract_id', $contractId);
        }

        return $q;
    }

    /** @return list<int> the never-posted lines Finance confirmed were charged (an approved 4b answer) */
    public static function confirmedCharged(): array
    {
        if (! Schema::hasTable(self::TABLE)) {
            return [];
        }

        return DB::table(self::TABLE)->where('charged', true)->whereNotNull('approved_at')->pluck('contract_fee_id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * The lines a value of the setting leaves out of the EIR inputs: none as built; under CONFIRMED_ONLY every
     * never-posted line without an approved "charged" answer.
     *
     * @return list<int>
     */
    public static function leftOutUnder(string $value, ?string $contractId = null): array
    {
        if ($value === self::AS_BUILT) {
            return [];
        }
        if ($value !== self::CONFIRMED_ONLY) {
            throw new \InvalidArgumentException(self::KEY . " holds \"{$value}\", which is not one of its options.");
        }
        $ids = self::neverPostedQuery($contractId)->pluck('id')->map(fn ($id) => (int) $id)->all();

        return array_values(array_diff($ids, self::confirmedCharged()));
    }

    /**
     * The lines the setting in force leaves out today. The setting is read only when there is a never-posted
     * line for it to govern; with one, a missing setting stops the calculation (fail closed).
     *
     * @return list<int>
     */
    public static function leftOut(?string $contractId = null): array
    {
        if (! Schema::hasTable('contract_fees') || ! self::neverPostedQuery($contractId)->exists()) {
            return [];
        }

        return self::leftOutUnder(app(GovernanceService::class)->get(self::KEY), $contractId);
    }

    /** Leave out of a fee-line query the lines the setting in force leaves out. */
    public static function constrain(Builder $query, ?string $contractId = null, string $idColumn = 'id'): Builder
    {
        $out = self::leftOut($contractId);
        if ($out !== []) {
            $query->whereNotIn($idColumn, $out);
        }

        return $query;
    }

    /**
     * The fee inputs of each locked EIR under a value of the setting: the reviewed integral lines that count,
     * by contract (id => amount).
     *
     * @return array<string, array<int, float>>
     */
    public static function lockedInputs(string $value): array
    {
        $locked = ContractEir::whereNotNull('locked_at')->pluck('contract_id')->map(fn ($c) => (string) $c)->all();
        if ($locked === []) {
            return [];
        }
        $out = array_flip(self::leftOutUnder($value));
        $q = DB::table('contract_fees')->whereIn('contract_id', $locked)->where('classification_status', 'REVIEWED')->where('integral', true);
        if (Schema::hasColumn('contract_fees', 'superseded_by_source')) {
            $q->whereNull('superseded_by_source');
        }
        $inputs = array_fill_keys($locked, []);
        foreach ($q->orderBy('id')->get(['id', 'contract_id', 'amount']) as $f) {
            if (! isset($out[(int) $f->id])) {
                $inputs[(string) $f->contract_id][(int) $f->id] = round((float) $f->amount, 2);
            }
        }

        return $inputs;
    }

    /**
     * The locked EIRs whose fee inputs differ between two values of the setting, each with the lines that
     * would leave or join its inputs. Reads only.
     *
     * @return list<array{contract_id:string, customer:?string, eir:?float, leaving:array<int,float>, joining:array<int,float>, amount:float}>
     */
    public static function lockedChanges(string $from, string $to): array
    {
        $before = self::lockedInputs($from);
        $after = self::lockedInputs($to);
        $names = ContractEir::whereIn('contract_id', array_keys($before))->get(['contract_id', 'customer_name', 'eir_effective_annual'])->keyBy(fn ($c) => (string) $c->contract_id);
        $changes = [];
        foreach ($before as $contract => $lines) {
            $leaving = array_diff_key($lines, $after[$contract] ?? []);
            $joining = array_diff_key($after[$contract] ?? [], $lines);
            if ($leaving === [] && $joining === []) {
                continue;
            }
            $c = $names->get($contract);
            $changes[] = ['contract_id' => $contract, 'customer' => $c?->customer_name !== null ? trim((string) $c->customer_name) : null,
                'eir' => $c?->eir_effective_annual !== null ? (float) $c->eir_effective_annual : null,
                'leaving' => $leaving, 'joining' => $joining, 'amount' => round(array_sum($leaving) - array_sum($joining), 2)];
        }

        return $changes;
    }

    /** What the second option would do to the locked EIRs today, from the value in force (for the screen and eir:unposted-fees). */
    public static function preview(): array
    {
        $current = app(GovernanceService::class)->get(self::KEY);
        $other = $current === self::AS_BUILT ? self::CONFIRMED_ONLY : self::AS_BUILT;

        return ['in_force' => $current, 'other' => $other, 'never_posted' => self::neverPostedQuery()->count(),
            'confirmed_charged' => count(self::confirmedCharged()), 'locked' => self::lockedChanges($current, $other)];
    }

    /**
     * The setting changed (GovernanceService::approve): flag every locked EIR whose fee inputs change, naming
     * the lines; never reopen it. @return int flags raised
     */
    public static function flagOnChange(?string $from, string $to, int $settingId, int $userId): int
    {
        if ($from === null || $from === $to || ! Schema::hasTable(EirLockReviewService::TABLE)) {
            return 0;
        }
        $reviews = app(EirLockReviewService::class);
        $raised = 0;
        foreach (self::lockedChanges($from, $to) as $c) {
            $reviews->raise($c['contract_id'], 'The fee basis for LOS charges never posted changed from "' . $from . '" to "' . $to . '" (Governance Centre change #' . $settingId . '): '
                . self::describe($c) . ' The locked EIR keeps its figures until a reopening is approved.', ['setting_id' => $settingId, 'from' => $from, 'to' => $to,
                    'leaving' => $c['leaving'], 'joining' => $c['joining']], $userId, 'unposted_basis:' . $settingId);
            $raised++;
        }
        AuditLoggerService::log('Unposted Fee Basis Changed', 'governance_settings', $settingId, ['old_values' => ['value' => $from],
            'new_values' => ['value' => $to, 'locked_eirs_flagged' => $raised], 'meta' => ['user' => $userId]]);

        return $raised;
    }

    /** One sentence naming the lines that would leave or join a locked EIR's fee inputs. */
    public static function describe(array $change): string
    {
        $parts = [];
        if ($change['leaving'] !== []) {
            $parts[] = count($change['leaving']) . ' fee line(s) of ' . number_format(array_sum($change['leaving']), 2) . ' would leave its fee inputs (line(s) ' . implode(', ', array_keys($change['leaving'])) . ')';
        }
        if ($change['joining'] !== []) {
            $parts[] = count($change['joining']) . ' fee line(s) of ' . number_format(array_sum($change['joining']), 2) . ' would join them (line(s) ' . implode(', ', array_keys($change['joining'])) . ')';
        }

        return ucfirst(implode('; ', $parts)) . '.';
    }
}
