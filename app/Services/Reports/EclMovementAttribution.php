<?php

namespace App\Services\Reports;

/**
 * Movement attribution for the IFRS 7 loss allowance (and gross carrying
 * amount) reconciliation. Pure PHP over plain arrays: no database, so it is
 * unit-tested directly and every output (screen, PDF, Word, Excel) is built
 * from the same numbers.
 *
 * Input is a list of contract groups between an opening month P0 and a
 * closing month P1. A single contract is a group of one; the report feeds
 * groups already summed by SQL per (opening stage, closing stage).
 *
 *   ['from' => 1|2|3|null,   stage at P0, null = not in the P0 book
 *    'to' => 1|2|3|null,     stage at P1, null = not in the P1 book
 *    'contracts' => int,
 *    'opening' => int,       value at P0 in cents (ECL or gross)
 *    'closing' => int,       value at P1 in cents
 *    'written_off' => bool]  only for groups gone at P1: written off, not repaid
 *
 * Rules:
 *  - in both months, stage changed: the opening value moves out of the old
 *    stage and into the new one (Transfers to Stage X, nets to nil in Total);
 *    closing less opening is remeasurement in the new stage;
 *  - in both months, same stage: closing less opening is remeasurement;
 *  - only in P1: new financial assets originated (closing value);
 *  - only in P0: derecognised (minus opening value), or written off when flagged.
 *
 * All arithmetic is in integer cents, so every column ties exactly.
 */
final class EclMovementAttribution
{
    public const STAGES = [1, 2, 3];

    public const LINES = [
        'opening', 'transfer_1', 'transfer_2', 'transfer_3', 'remeasurement',
        'originated', 'derecognised', 'written_off', 'closing',
    ];

    /** The movement lines between opening and closing. */
    public const MOVEMENTS = [
        'transfer_1', 'transfer_2', 'transfer_3', 'remeasurement', 'originated', 'derecognised', 'written_off',
    ];

    /**
     * @param array<int,array> $groups
     * @return array{lines: array<string,array<int|string,int>>, contracts: array<string,array<int|string,int>>, check: array}
     */
    public static function attribute(array $groups): array
    {
        $lines = [];
        $counts = [];
        foreach (self::LINES as $line) {
            $lines[$line] = self::emptyRow();
            $counts[$line] = self::emptyRow();
        }

        foreach ($groups as $g) {
            $from = self::stage($g['from'] ?? null);
            $to = self::stage($g['to'] ?? null);
            $n = (int) ($g['contracts'] ?? 0);
            $open = (int) ($g['opening'] ?? 0);
            $close = (int) ($g['closing'] ?? 0);

            if ($from !== null) {
                $lines['opening'][$from] += $open;
                $counts['opening'][$from] += $n;
            }
            if ($to !== null) {
                $lines['closing'][$to] += $close;
                $counts['closing'][$to] += $n;
            }

            if ($from !== null && $to !== null) {
                if ($from !== $to) {
                    $line = 'transfer_' . $to;
                    $lines[$line][$from] -= $open;
                    $lines[$line][$to] += $open;
                    $counts[$line][$to] += $n;
                }
                $lines['remeasurement'][$to] += $close - $open;
                $counts['remeasurement'][$to] += $n;
            } elseif ($to !== null) {
                $lines['originated'][$to] += $close;
                $counts['originated'][$to] += $n;
            } elseif ($from !== null) {
                $line = !empty($g['written_off']) ? 'written_off' : 'derecognised';
                $lines[$line][$from] -= $open;
                $counts[$line][$from] += $n;
            }
        }

        foreach (self::LINES as $line) {
            $lines[$line]['total'] = array_sum(array_intersect_key($lines[$line], array_flip(self::STAGES)));
            $counts[$line]['total'] = array_sum(array_intersect_key($counts[$line], array_flip(self::STAGES)));
        }

        return ['lines' => $lines, 'contracts' => $counts, 'check' => self::check($lines)];
    }

    /**
     * Opening plus every movement less closing, per column. All must be 0.
     * Transfers must also net to nil in the Total column.
     *
     * @return array{ok: bool, differences: array<int|string,int>, transfers_total: int, messages: string[]}
     */
    public static function check(array $lines): array
    {
        $differences = [];
        $messages = [];
        foreach (array_merge(self::STAGES, ['total']) as $col) {
            $sum = $lines['opening'][$col];
            foreach (self::MOVEMENTS as $m) {
                $sum += $lines[$m][$col];
            }
            $differences[$col] = $sum - $lines['closing'][$col];
            if ($differences[$col] !== 0) {
                $label = $col === 'total' ? 'Total' : 'Stage ' . $col;
                $messages[] = "{$label} does not tie: opening plus movements differs from closing by " . self::centsText($differences[$col]) . '.';
            }
        }
        $transfers = $lines['transfer_1']['total'] + $lines['transfer_2']['total'] + $lines['transfer_3']['total'];
        if ($transfers !== 0) {
            $messages[] = 'Transfers between stages do not net to nil (' . self::centsText($transfers) . ').';
        }

        return ['ok' => !$messages, 'differences' => $differences, 'transfers_total' => $transfers, 'messages' => $messages];
    }

    /**
     * The same reconciliation in rounded units (thousands or millions).
     * Each figure is rounded on its own, opening and closing are the rounded
     * loan book figures, a transfer row nets to nil (the receiving stage takes
     * what the others give up), and the rounding left in each column is
     * absorbed in net remeasurement so the column still casts. Totals are the sum of the
     * rounded stage figures, so every row cross-casts.
     *
     * @param int $step cents per rounded unit (100000 = thousands)
     * @return array{lines: array<string,array<int|string,int>>, recon: array, absorbed: array<int|string,int>}
     */
    public static function rounded(array $lines, int $step): array
    {
        $out = [];
        foreach (self::LINES as $line) {
            foreach (self::STAGES as $s) {
                $out[$line][$s] = self::roundTo($lines[$line][$s], $step);
            }
        }

        // A transfer row nets to nil: the receiving stage takes the rounded
        // amounts given up by the other stages.
        foreach (self::STAGES as $j) {
            $line = 'transfer_' . $j;
            $given = 0;
            foreach (self::STAGES as $s) {
                if ($s !== $j) {
                    $given += $out[$line][$s];
                }
            }
            $out[$line][$j] = -$given;
        }

        $absorbed = [];
        foreach (self::STAGES as $s) {
            $sum = $out['opening'][$s];
            foreach (self::MOVEMENTS as $m) {
                $sum += $out[$m][$s];
            }
            $absorbed[$s] = $out['closing'][$s] - $sum;
            $out['remeasurement'][$s] += $absorbed[$s];
        }

        foreach (self::LINES as $line) {
            $out[$line]['total'] = $out[$line][1] + $out[$line][2] + $out[$line][3];
        }
        $absorbed['total'] = $absorbed[1] + $absorbed[2] + $absorbed[3];

        // Loan book (each figure rounded directly) against the note (as shown).
        $recon = [];
        foreach (['opening', 'closing'] as $line) {
            $book = [];
            $note = [];
            $diff = [];
            foreach (self::STAGES as $s) {
                $book[$s] = self::roundTo($lines[$line][$s], $step);
                $note[$s] = $out[$line][$s];
                $diff[$s] = $note[$s] - $book[$s];
            }
            $book['total'] = self::roundTo($lines[$line]['total'], $step);
            $note['total'] = $out[$line]['total'];
            $diff['total'] = $note['total'] - $book['total'];
            $recon[$line] = ['book' => $book, 'note' => $note, 'difference' => $diff];
        }

        return ['lines' => $out, 'recon' => $recon, 'absorbed' => $absorbed];
    }

    /** Round cents to a whole number of $step units, half away from zero. */
    public static function roundTo(int $cents, int $step): int
    {
        if ($step <= 1) {
            return $cents;
        }
        $q = intdiv(abs($cents) * 2 + $step, 2 * $step);

        return $cents < 0 ? -$q : $q;
    }

    /**
     * Exact cents from a SQL decimal string ("-1234.5", "12.34", null).
     * Never goes through a float, so large sums stay exact.
     */
    public static function cents($value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }
        if (is_int($value)) {
            return $value * 100;
        }
        if (is_float($value)) {
            return (int) round($value * 100);
        }
        $s = trim((string) $value);
        if (!preg_match('/^(-?)(\d*)(?:\.(\d*))?$/', $s, $m)) {
            return (int) round((float) $s * 100);
        }
        $frac = substr(str_pad($m[3] ?? '', 3, '0'), 0, 3);
        $cents = ((int) ($m[2] === '' ? '0' : $m[2])) * 100 + intdiv((int) $frac + 5, 10);

        return $m[1] === '-' ? -$cents : $cents;
    }

    private static function stage($stage): ?int
    {
        if ($stage === null || $stage === '') {
            return null;
        }
        if (!is_numeric($stage) || (float) $stage != (int) $stage) {
            return null;
        }
        $n = (int) $stage;

        return in_array($n, self::STAGES, true) ? $n : null;
    }

    private static function emptyRow(): array
    {
        return [1 => 0, 2 => 0, 3 => 0];
    }

    private static function centsText(int $cents): string
    {
        return number_format($cents / 100, 2);
    }
}
