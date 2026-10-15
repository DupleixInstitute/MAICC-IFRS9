<?php

namespace App\Services\Eir;

use App\Services\AuditLoggerService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * One fee, one source (spec v4 sections 7.8.2 and 7.8.6; results review of
 * 13 October 2026, E3).
 *
 * MAIIC holds the same fee in several places: the GL fee postings tied to the
 * loan through the drawdown batch (GL_04), Finance's fee workbook loaded
 * through EIR Data Intake, the origination system's charges table (P2_07) and
 * the contract master's fee columns (Extract A). Loading two of them adds the
 * same fee twice and overstates the EIR. The governed setting
 * fee_source_precedence orders the sources. Since 15 October 2026 (review R03)
 * it is resolved per fee event, not per contract (see apply()): a partial
 * source never suppresses another fee type, year or tranche, and an overlap
 * waits for a person. A superseded line is kept and shown, never entering the
 * EIR, never counted again by the fee GL check; nothing is deleted.
 *
 * The take-on workbook's fees are the only record of the take-on loans and
 * never compete (7.8.2), so they are left out of the precedence entirely.
 * Superseding a line changes no classification: a person still classifies
 * and reviews whatever the engine reads.
 */
class FeeSourcePrecedence
{
    public const GL_FIRST = 'GL posting, Finance workbook, LOS capture, Extract A';
    public const FINANCE_FIRST = 'Finance workbook, GL posting, LOS capture, Extract A';
    public const LOS_FIRST = 'LOS capture, GL posting, Finance workbook, Extract A';

    /** The tiers, and the source_system values that belong to each. */
    public const TIER_GL = 'GL posting';
    public const TIER_FINANCE = 'Finance workbook';
    public const TIER_LOS = 'LOS capture';
    public const TIER_EXTRACT_A = 'Extract A';

    /** source_system of a fee line built from the GL fee postings (GL_04). */
    public const SOURCE_GL = 'E-Banker GL fee posting';
    public const SOURCE_LOS = 'E-Banker LOS_DISB_CHARGES_POST';
    public const SOURCE_EXTRACT_A = ContractMasterImportService::SOURCE;
    public const SOURCE_TAKEON = 'TAKEON_WORKBOOK';

    public function __construct(private readonly GovernanceService $governance)
    {
    }

    /** The tier a line's source_system belongs to; null for the take-on workbook, which never competes. */
    public static function tierOf(?string $sourceSystem): ?string
    {
        return match (trim((string) $sourceSystem)) {
            self::SOURCE_TAKEON => null,
            self::SOURCE_GL => self::TIER_GL,
            self::SOURCE_LOS => self::TIER_LOS,
            self::SOURCE_EXTRACT_A => self::TIER_EXTRACT_A,
            // every other line came through EIR Data Intake, which is Finance's route (7.8.7 step 1)
            default => self::TIER_FINANCE,
        };
    }

    /** @return list<string> the tiers in the order the setting in force gives them */
    public function order(?CarbonImmutable $asOf = null): array
    {
        $value = $this->governance->get('fee_source_precedence', $asOf);

        return match ($value) {
            self::GL_FIRST => [self::TIER_GL, self::TIER_FINANCE, self::TIER_LOS, self::TIER_EXTRACT_A],
            self::FINANCE_FIRST => [self::TIER_FINANCE, self::TIER_GL, self::TIER_LOS, self::TIER_EXTRACT_A],
            self::LOS_FIRST => [self::TIER_LOS, self::TIER_GL, self::TIER_FINANCE, self::TIER_EXTRACT_A],
            default => throw new InvalidArgumentException("fee_source_precedence holds \"{$value}\", which is not one of its options."),
        };
    }

    /**
     * Resolve each fee event to one source (R03, 15 October 2026; it was one source per contract).
     *
     * For each contract and fee type, the highest source holding a line of that type is the source of
     * record. Each line of a lower source is paired with one of its lines for the same event: by date
     * (within EVENT_WINDOW_DAYS) where both are dated, otherwise by amount (equal first, then the nearest).
     * A paired line is superseded (kept and shown, never entering the EIR, never counted again by the fee
     * GL check) and any difference of amount is stored in fee_source_differences. A lower line with no
     * pair while the source of record holds that fee type is an overlap: it is not superseded, it is
     * marked (source_overlap) and it blocks the loan's EIR until a person decides, with the reason,
     * whether it is a fee of its own or the same fee (fee_source_decisions). A fee type the source of
     * record does not hold is never suppressed: the lower source stands for it (a missing row is not a
     * confirmed zero). Nothing is deleted; a superseded line comes back when the line it paired with goes.
     *
     * @return array{order:list<string>, contracts:int, superseded_lines:int, restored_lines:int, overlaps:int, by_winner:array<string,int>, exceptions:list<array>}
     */
    public function apply(?int $userId = null): array
    {
        $order = $this->order();
        $rank = array_flip($order);
        $now = now();
        $schema = DB::getSchemaBuilder();
        $hasOverlap = $schema->hasColumn('contract_fees', 'source_overlap');
        $cols = ['id', 'contract_id', 'fee_type', 'amount', 'source_system', 'superseded_by_source', 'transaction_date'];
        if ($hasOverlap) {
            $cols[] = 'source_overlap';
        }
        $lines = DB::table('contract_fees')->orderBy('contract_id')->orderBy('id')->get($cols)->groupBy('contract_id');
        $decisions = $schema->hasTable('fee_source_decisions') ? DB::table('fee_source_decisions')->pluck('decision', 'contract_fee_id')->all() : [];

        $superseded = 0; $restored = 0; $overlaps = 0; $byWinner = []; $exceptions = [];
        DB::transaction(function () use ($lines, $rank, $now, $decisions, $hasOverlap, &$superseded, &$restored, &$overlaps, &$byWinner, &$exceptions) {
            $set = function (object $r, ?string $by, ?string $overlap) use ($now, $hasOverlap, &$superseded, &$restored) {
                $changes = [];
                if ($r->superseded_by_source !== $by) {
                    $changes += ['superseded_by_source' => $by, 'superseded_at' => $by === null ? null : $now];
                    $by === null ? $restored++ : $superseded++;
                }
                if ($hasOverlap && ($r->source_overlap ?? null) !== $overlap) {
                    $changes['source_overlap'] = $overlap;
                }
                if ($changes !== []) {
                    DB::table('contract_fees')->where('id', $r->id)->update($changes + ['updated_at' => $now]);
                }
            };
            foreach ($lines as $contractId => $rows) {
                foreach ($rows->groupBy(fn ($r) => strtolower((string) $r->fee_type)) as $type => $typed) {
                    $tiered = $typed->filter(fn ($r) => self::tierOf($r->source_system) !== null);
                    if ($tiered->isEmpty()) {
                        continue;
                    }
                    $winner = $tiered->map(fn ($r) => self::tierOf($r->source_system))->unique()->sortBy(fn ($t) => $rank[$t])->first();
                    $byWinner[$winner] = ($byWinner[$winner] ?? 0) + 1;
                    $record = $tiered->filter(fn ($r) => self::tierOf($r->source_system) === $winner)->values();
                    foreach ($record as $r) {
                        $set($r, null, null);
                    }
                    foreach ($tiered->filter(fn ($r) => self::tierOf($r->source_system) !== $winner)->groupBy(fn ($r) => self::tierOf($r->source_system)) as $tier => $lower) {
                        $free = $record->keyBy('id')->all();
                        foreach ($lower as $r) {
                            $decision = $decisions[$r->id] ?? null;
                            if ($decision === 'SEPARATE') {
                                $set($r, null, null);
                                continue;
                            }
                            $pair = $this->pairFor($r, $free);
                            if ($pair === null && $decision !== 'SAME') {
                                $set($r, null, "No {$winner} line for this {$type} fee event, although {$winner} holds {$type} fees for the loan: a person decides whether it is a fee of its own or the same fee as one of record.");
                                $overlaps++;
                                continue;
                            }
                            $set($r, $winner, null);
                            if ($pair !== null) {
                                unset($free[$pair->id]);
                                if (abs((float) $pair->amount - (float) $r->amount) >= 0.01) {
                                    $exceptions[] = ['contract_id' => (string) $contractId, 'fee_type' => $type, 'record_fee_id' => (int) $pair->id, 'source_of_record' => $winner,
                                        'amount_of_record' => round((float) $pair->amount, 2), 'other_fee_id' => (int) $r->id, 'other_source' => $tier,
                                        'other_amount' => round((float) $r->amount, 2), 'difference' => round((float) $r->amount - (float) $pair->amount, 2)];
                                }
                            }
                        }
                    }
                }
            }
            if (DB::getSchemaBuilder()->hasTable('fee_source_differences')) {
                DB::table('fee_source_differences')->delete();
                foreach (array_chunk($exceptions, 500) as $chunk) {
                    DB::table('fee_source_differences')->insert(array_map(fn ($e) => $e + ['created_at' => $now, 'updated_at' => $now], $chunk));
                }
            }
        });

        $result = ['order' => $order, 'contracts' => count($lines), 'superseded_lines' => $superseded, 'restored_lines' => $restored, 'overlaps' => $overlaps, 'by_winner' => $byWinner, 'exceptions' => $exceptions];
        AuditLoggerService::log('Fee Source Precedence Applied', 'contract_fees', null, ['new_values' => array_diff_key($result, ['exceptions' => 1]) + ['exception_count' => count($exceptions)], 'meta' => ['user' => $userId]]);

        return $result;
    }

    /** The days within which two dated lines of different sources are the same fee event. */
    public const EVENT_WINDOW_DAYS = 120;

    /** The source of record's line this lower line is the same event as, or null (dated: nearest date within the window; else amount). */
    private function pairFor(object $r, array $free): ?object
    {
        if ($free === []) {
            return null;
        }
        $date = $r->transaction_date ? CarbonImmutable::parse($r->transaction_date) : null;
        $dated = $date ? array_filter($free, fn ($c) => $c->transaction_date !== null) : [];
        if ($date && $dated !== []) {
            $near = array_values(array_filter($dated, fn ($c) => abs(CarbonImmutable::parse($c->transaction_date)->diffInDays($date)) <= self::EVENT_WINDOW_DAYS));
            if ($near === []) {
                return null;
            }
            usort($near, fn ($a, $b) => [abs(CarbonImmutable::parse($a->transaction_date)->diffInDays($date)), abs((float) $a->amount - (float) $r->amount)]
                <=> [abs(CarbonImmutable::parse($b->transaction_date)->diffInDays($date)), abs((float) $b->amount - (float) $r->amount)]);

            return $near[0];
        }
        $all = array_values($free);
        usort($all, fn ($a, $b) => abs((float) $a->amount - (float) $r->amount) <=> abs((float) $b->amount - (float) $r->amount));

        return $all[0];
    }

    /** A person decides an overlap: SEPARATE (a fee of its own) or SAME (the same fee as the source of record's), with the reason. */
    public function decide(int $feeId, string $decision, string $reason, int $userId): array
    {
        $decision = strtoupper(trim($decision));
        if (! in_array($decision, ['SEPARATE', 'SAME'], true)) {
            throw new InvalidArgumentException('Decide SEPARATE (a fee of its own) or SAME (the same fee as the source of record).');
        }
        if (mb_strlen(trim($reason)) < 10) {
            throw new InvalidArgumentException('Give the reason (at least 10 characters).');
        }
        $fee = DB::table('contract_fees')->where('id', $feeId)->first();
        if ($fee === null || ($fee->source_overlap ?? null) === null) {
            throw new InvalidArgumentException("Fee line {$feeId} is not an overlap waiting for a decision.");
        }
        DB::table('fee_source_decisions')->updateOrInsert(['contract_fee_id' => $feeId],
            ['decision' => $decision, 'reason' => mb_substr(trim($reason), 0, 500), 'decided_by' => $userId, 'created_at' => now(), 'updated_at' => now()]);
        AuditLoggerService::log('Fee Source Overlap Decided', 'contract_fees', $feeId, ['new_values' => ['decision' => $decision, 'reason' => $reason, 'contract_id' => $fee->contract_id], 'meta' => ['user' => $userId]]);

        return $this->apply($userId);
    }
}
