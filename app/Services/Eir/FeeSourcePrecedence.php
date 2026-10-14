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
 * fee_source_precedence orders the sources; for each contract the highest
 * source that holds any fee line wins, and every line from a lower source is
 * marked superseded: kept and shown, never entering the EIR, never counted
 * again by the fee GL check. Nothing is deleted, and a superseded line comes
 * back if the higher source's lines are later removed.
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
     * Mark, for every contract, the lines of every source below the highest
     * one that holds a fee for it; clear the mark on the winning source. The
     * cross-check of 7.8.5 comes back with it: for each contract and fee type
     * where a superseded source carries a different amount from the source of
     * record, both amounts and the difference, for Finance on Fee
     * Classification.
     *
     * @return array{order:list<string>, contracts:int, superseded_lines:int, restored_lines:int, by_winner:array<string,int>, exceptions:list<array>}
     */
    public function apply(?int $userId = null): array
    {
        $order = $this->order();
        $rank = array_flip($order);
        $now = now();
        $lines = DB::table('contract_fees')->orderBy('contract_id')->orderBy('id')
            ->get(['id', 'contract_id', 'fee_type', 'amount', 'source_system', 'superseded_by_source'])->groupBy('contract_id');

        $superseded = 0; $restored = 0; $byWinner = []; $exceptions = [];
        DB::transaction(function () use ($lines, $rank, $now, &$superseded, &$restored, &$byWinner, &$exceptions) {
            foreach ($lines as $contractId => $rows) {
                $tiers = [];
                foreach ($rows as $r) {
                    $tier = self::tierOf($r->source_system);
                    if ($tier !== null) {
                        $tiers[$tier] = true;
                    }
                }
                if ($tiers === []) {
                    continue;
                }
                $winner = collect(array_keys($tiers))->sortBy(fn ($t) => $rank[$t])->first();
                $byWinner[$winner] = ($byWinner[$winner] ?? 0) + 1;

                $winning = []; $losing = [];
                foreach ($rows as $r) {
                    $tier = self::tierOf($r->source_system);
                    if ($tier === null) {
                        continue;
                    }
                    if ($tier === $winner) {
                        $winning[$r->fee_type] = ($winning[$r->fee_type] ?? 0.0) + (float) $r->amount;
                        if ($r->superseded_by_source !== null) {
                            DB::table('contract_fees')->where('id', $r->id)->update(['superseded_by_source' => null, 'superseded_at' => null, 'updated_at' => $now]);
                            $restored++;
                        }
                        continue;
                    }
                    $losing[$tier][$r->fee_type] = ($losing[$tier][$r->fee_type] ?? 0.0) + (float) $r->amount;
                    if ($r->superseded_by_source !== $winner) {
                        DB::table('contract_fees')->where('id', $r->id)->update(['superseded_by_source' => $winner, 'superseded_at' => $now, 'updated_at' => $now]);
                        $superseded++;
                    }
                }
                foreach ($losing as $tier => $byType) {
                    foreach (array_unique(array_merge(array_keys($byType), array_keys($winning))) as $type) {
                        $kept = round($winning[$type] ?? 0.0, 2);
                        $other = round($byType[$type] ?? 0.0, 2);
                        if (abs($kept - $other) >= 0.01) {
                            $exceptions[] = ['contract_id' => (string) $contractId, 'fee_type' => $type, 'source_of_record' => $winner, 'amount_of_record' => $kept,
                                'other_source' => $tier, 'other_amount' => $other, 'difference' => round($other - $kept, 2)];
                        }
                    }
                }
            }
        });

        $result = ['order' => $order, 'contracts' => count($lines), 'superseded_lines' => $superseded, 'restored_lines' => $restored, 'by_winner' => $byWinner, 'exceptions' => $exceptions];
        AuditLoggerService::log('Fee Source Precedence Applied', 'contract_fees', null, ['new_values' => array_diff_key($result, ['exceptions' => 1]) + ['exception_count' => count($exceptions)], 'meta' => ['user' => $userId]]);

        return $result;
    }
}
