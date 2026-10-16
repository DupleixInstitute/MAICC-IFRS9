<?php

namespace App\Services\Decisions;

use App\Services\Eir\FeeSourcePrecedence;

/**
 * The decisions pack (Staging Module Specification v1, section 8.10): what
 * people decided in one database, carried into a freshly bootstrapped one.
 *
 * The bootstrap rebuilds a database from files; it cannot rebuild a person's
 * decision, which is in no file. The pack is written by decisions:export and
 * replayed by eir:bootstrap --with-decisions. Every row in it is named by its
 * business key, never by a database id, since a fresh load numbers its rows
 * differently; every person is named by e-mail.
 *
 * This class holds what the export and the replay must agree on: the file
 * names, the key of a fee line, the fingerprint of an EIR's cash flows and the
 * audit label of a carried decision.
 */
final class DecisionsPack
{
    public const FOLDER = 'docs/bootstrap/decisions';

    public const MANIFEST = 'manifest.json';

    /** One file per kind of decision, in the order the replay applies them. */
    public const FILES = [
        'users' => 'users.json',
        'governance' => 'governance.json',
        'fee_rules' => 'fee_rules.json',
        'finance_lines' => 'finance_lines.json',
        'finance_coverage' => 'finance_coverage.json',
        'held_ties' => 'held_ties.json',
        'held_dismissals' => 'held_dismissals.json',
        'fee_moves' => 'fee_moves.json',
        'overlap_decisions' => 'overlap_decisions.json',
        'classifications' => 'classifications.json',
        'reopen_requests' => 'reopen_requests.json',
        'eir_locks' => 'eir_locks.json',
    ];

    /** The audit action every replayed decision writes. */
    public const AUDIT_ACTION = 'Carried By Decisions Pack';

    /** User columns never carried (decision S13): each carried user sets a new password through the reset link. */
    public const USER_SECRETS = ['id', 'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at'];

    /** The audit label of a carried decision, naming the pack and the database it came from. */
    public static function label(string $pack, string $source): string
    {
        return "Carried from {$source} by the decisions pack of {$pack}";
    }

    /** A line is a Finance workbook line when the precedence would rank it as one: those lines are carried whole. */
    public static function isFinanceLine(?string $sourceSystem): bool
    {
        return FeeSourcePrecedence::tierOf($sourceSystem) === FeeSourcePrecedence::TIER_FINANCE;
    }

    /**
     * The business key of a fee line. A line with a source system and a source
     * transaction id is named by those two, which no move changes; any other is
     * named by its loan, type, amount, date, description and source, which
     * $contractId and $date may give as they were before a move.
     */
    public static function feeKey(object|array $fee, ?string $contractId = null, ?string $date = null): string
    {
        $f = (object) $fee;
        $source = trim((string) ($f->source_system ?? ''));
        $external = trim((string) ($f->external_transaction_id ?? ''));
        if ($source !== '' && $external !== '') {
            return "src|{$source}|{$external}";
        }

        return implode('|', ['row', $contractId ?? (string) $f->contract_id, strtolower((string) $f->fee_type), number_format((float) $f->amount, 2, '.', ''),
            substr((string) ($date ?? $f->transaction_date ?? ''), 0, 10), trim((string) ($f->description ?? '')), $source]);
    }

    /**
     * Keys for a set of fee lines in id order, an exact duplicate numbered by
     * its occurrence (#2, #3) so that two identical lines stay two decisions.
     *
     * @param  iterable<object>  $fees
     * @param  array<int, array{0: string, 1: ?string}>  $origins  per fee id, the loan and date before any move
     * @return array<int, string> fee id => key
     */
    public static function keys(iterable $fees, array $origins = []): array
    {
        $out = [];
        $seen = [];
        foreach ($fees as $fee) {
            [$contract, $date] = $origins[(int) $fee->id] ?? [null, null];
            $key = self::feeKey($fee, $contract, $date);
            $n = $seen[$key] = ($seen[$key] ?? 0) + 1;
            $out[(int) $fee->id] = $n === 1 ? $key : "{$key}#{$n}";
        }

        return $out;
    }

    /**
     * The fingerprint of the cash flows an EIR was solved on: the net
     * investment, the instalment frequency, the date and basis it discounts
     * from, the net fees and every dated flow, to the cent. Database ids in
     * the snapshot (the fee lines') are left out, so the same loan solved in
     * two databases gives the same fingerprint.
     */
    public static function eirFingerprint(?array $snapshot): ?string
    {
        if ($snapshot === null || ! isset($snapshot['cash_flows'])) {
            return null;
        }
        $flows = array_map(fn ($f) => [substr((string) ($f['due_date'] ?? ''), 0, 10), round((float) ($f['amount'] ?? 0), 2)], $snapshot['cash_flows']);

        return hash('sha256', json_encode([
            round((float) ($snapshot['initial_net_investment'] ?? 0), 2),
            (int) ($snapshot['payments_per_year'] ?? 0),
            (string) ($snapshot['metadata']['origination_date'] ?? ''),
            (string) ($snapshot['metadata']['day_count_basis'] ?? ''),
            round((float) ($snapshot['fee_adjustments']['net'] ?? 0), 2),
            $flows,
        ]));
    }

    /** Two solved rates are the same EIR when they agree to ten decimal places. */
    public static function sameRate(mixed $a, mixed $b): bool
    {
        return $a !== null && $b !== null && abs((float) $a - (float) $b) < 1e-10;
    }
}
