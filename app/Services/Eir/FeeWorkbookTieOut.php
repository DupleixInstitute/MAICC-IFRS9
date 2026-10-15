<?php

namespace App\Services\Eir;

use App\Services\Ebanker\GlFeeBuildService;
use App\Support\ContractId;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Finance's fee workbook, proved against the GL fee postings before it loads
 * (15 October 2026; the 2024 and 2025 reconciliations of that day).
 *
 * The GL is the book of record, but a fee credited by transfer has no loan leg
 * in its batch, so the GL alone cannot say whose fee it is (GlFeeBuildService
 * holds those postings). Finance's workbook says whose fee each one is. It is
 * relied on only because this check proves it, line by line and month by
 * month, against the postings the system holds (GL_04: the lines tied to a
 * loan and the held ones). A file that fails any check loads nothing.
 *
 * A line is refused when:
 *  - it has no loan account, no date, or a fee type with no fee GL and no GL named;
 *  - its loan has fees from the take-on workbook (those are the take-on loan's
 *    record and never compete under fee_source_precedence, so a second copy
 *    would count twice);
 *  - its date is before the first GL fee posting the system holds (nothing to prove it against);
 *  - no posting, or set of postings, accounts for it within the governed
 *    recon_tolerance band.
 *
 * Matching, in order (each posting is used once): a posting tied to the line's
 * loan; a held posting whose candidate is the loan; a held posting naming the
 * customer; a held posting of the same amount near the date (to the unit, no
 * band); a posting tied to another loan of a customer of the same name (a
 * conflict, reported); one held posting paying two lines' fees together; two
 * or three postings naming the loan that add up (a fee and its corrections).
 *
 * Completeness: for each fee GL the file reaches, the postings of the months
 * from its first to its last matched posting that no line accounts for must
 * net to nil within the band (a posting and its reversal); otherwise the file
 * leaves fees out and is refused. Mega Farm programme postings (held as
 * MEGA_FARM, outside the EIR, decision D30) are left out of both sides.
 *
 * A held posting that one line accounts for on its own (alone or with its
 * corrections) is returned in ties: the import ties it to the line's loan
 * through GlFeeBuildService::assignHeld, recorded and audited. A posting that
 * pays two loans together is not tied (it cannot be split); the workbook line
 * stands for each loan.
 */
class FeeWorkbookTieOut
{
    public const MATCH_WINDOW = [-90, 120];
    public const COMBO_WINDOW = 250;

    public function __construct(private readonly GovernanceService $governance)
    {
    }

    /**
     * @param  list<array<string,mixed>>  $rows  the mapped fee rows, in file order
     * @return array{status:string, refusals:list<array>, lines:list<array>, unaccounted:list<array>, ties:list<array>, conflicts:list<array>, coverage:array, tolerance:array}
     */
    public function check(array $rows): array
    {
        $tolerance = $this->tolerance();
        $postings = $this->postings();
        $first = $postings === [] ? null : min(array_map(fn ($p) => $p['date'], $postings));
        $takeon = DB::table('contract_fees')->where('source_system', FeeSourcePrecedence::SOURCE_TAKEON)->distinct()->pluck('contract_id')->flip()->all();

        $lines = []; $refusals = [];
        foreach (array_values($rows) as $i => $row) {
            $n = $i + 2; // the row number in the file (header on row 1)
            $contract = ContractId::normalise($row['contract_id'] ?? null);
            $amount = round((float) ($row['amount'] ?? 0), 2);
            if ($amount == 0.0) {
                continue; // the import skips a zero line too
            }
            $gl = FeeGlReconciliationService::glFor($row['fee_type'] ?? null, isset($row['gl_account_ref']) ? (string) $row['gl_account_ref'] : null);
            $date = $this->date($row['transaction_date'] ?? null);
            $line = ['row' => $n, 'contract_id' => $contract, 'fee_type' => strtolower(trim((string) ($row['fee_type'] ?? ''))), 'gl' => $gl, 'amount' => $amount, 'date' => $date?->toDateString(), 'postings' => [], 'how' => []];
            $why = match (true) {
                $contract === null => 'No loan account: the fee cannot be placed.',
                $date === null => 'No date: the line cannot be matched to a GL posting.',
                $gl === null => "Fee type \"{$line['fee_type']}\" has no fee GL of its own: give gl_account_ref (4871, 4873, 4874 or 4875).",
                isset($takeon[$contract]) => "Loan {$contract} is a take-on loan: its fees come from the take-on workbook, so loading them here would count them twice.",
                $first === null => 'The system holds no GL fee postings yet (GL_04), so nothing can prove the line.',
                $date->lt($first->startOfMonth()) => 'Dated before the first GL fee posting the system holds (' . $first->toDateString() . '): nothing to prove it against.',
                default => null,
            };
            if ($why !== null) {
                $refusals[] = ['row' => $n, 'contract_id' => $contract, 'amount' => $amount, 'reason' => $why];
                continue;
            }
            $lines[] = $line;
        }

        $names = $this->names(array_unique(array_merge(array_column($lines, 'contract_id'), array_filter(array_column($postings, 'loan')))));
        $used = [];
        $free = function (string $gl) use ($postings, &$used) {
            return array_filter($postings, fn ($p) => ! isset($used[$p['id']]) && $p['gl'] === $gl && ! $p['programme']);
        };
        $near = fn (array $p, array $l, int $from, int $to) => ($d = CarbonImmutable::parse($l['date'])->diffInDays($p['date'], false)) >= $from && $d <= $to;
        $named = fn (array $p, array $l) => $this->tokens($p['narration']) && array_intersect($this->tokens($p['narration']), $this->tokens($names[$l['contract_id']] ?? '')) !== [];
        $take = function (int $li, array $ps, string $why) use (&$lines, &$used) {
            foreach ($ps as $p) {
                $used[$p['id']] = true;
                $lines[$li]['postings'][] = $p;
                $lines[$li]['how'][] = $p['id'] . ': ' . $why;
            }
        };

        $exact = fn (float $sum, float $want) => abs($sum - $want) < 1.0;
        $banded = fn (float $sum, float $want) => $this->within($sum - $want, $want, $tolerance);
        $isMine = fn (array $p, array $l) => $p['loan'] === $l['contract_id'] || $named($p, $l);
        // one posting for one line
        $single = function (string $why, callable $test, callable $fits) use (&$lines, $free, $near, $take) {
            foreach ($lines as $li => $l) {
                if ($l['postings'] !== []) {
                    continue;
                }
                $c = array_filter($free($l['gl']), fn ($p) => $near($p, $l, ...self::MATCH_WINDOW) && $test($p, $l) && $fits($p['amount'], $l['amount']));
                usort($c, fn ($a, $b) => [abs($a['amount'] - $l['amount']), abs($a['date']->diffInDays(CarbonImmutable::parse($l['date'])))]
                    <=> [abs($b['amount'] - $l['amount']), abs($b['date']->diffInDays(CarbonImmutable::parse($l['date'])))]);
                if ($c !== []) {
                    $take($li, [reset($c)], $why);
                }
            }
        };
        // a fee in parts or with its corrections: two or three postings, at least one tied to, suggested for or naming the loan
        $several = function (callable $fits) use (&$lines, $free, $near, $take, $isMine) {
            foreach ($lines as $li => $l) {
                if ($l['postings'] !== []) {
                    continue;
                }
                $pool = array_values(array_filter($free($l['gl']), fn ($p) => $near($p, $l, -self::COMBO_WINDOW, self::COMBO_WINDOW)
                    && ($isMine($p, $l) || ! $p['tied'])));
                foreach ([2, 3] as $k) {
                    foreach ($this->combinations($pool, $k) as $set) {
                        if (array_filter($set, fn ($p) => $isMine($p, $l)) !== [] && $fits(array_sum(array_column($set, 'amount')), $l['amount'])) {
                            $take($li, $set, 'in parts or with its corrections');
                            continue 3;
                        }
                    }
                }
            }
        };
        $tiedHere = fn ($p, $l) => $p['tied'] && $p['loan'] === $l['contract_id'];
        $candidate = fn ($p, $l) => ! $p['tied'] && $p['loan'] === $l['contract_id'];
        $heldNamed = fn ($p, $l) => ! $p['tied'] && $named($p, $l);

        // to the unit first, so a fee posted in parts is not taken as its larger part within the band
        $single('tied to this loan', $tiedHere, $exact);
        $single('held; the system\'s candidate loan', $candidate, $exact);
        $single('held; the customer\'s name is in the narration', $heldNamed, $exact);
        $several($exact);
        // one held posting paying two lines' fees together
        foreach ($lines as $a => $la) {
            foreach ($lines as $b => $lb) {
                if ($b <= $a || $lines[$a]['postings'] !== [] || $lines[$b]['postings'] !== [] || $la['gl'] !== $lb['gl']) {
                    continue;
                }
                foreach ($free($la['gl']) as $p) {
                    if (! $p['tied'] && $exact($la['amount'] + $lb['amount'], $p['amount']) && $near($p, $la, -60, 60) && $near($p, $lb, -60, 60)) {
                        $used[$p['id']] = true;
                        foreach ([[$a, $lb], [$b, $la]] as [$li, $other]) {
                            $lines[$li]['postings'][] = ['shared' => true, 'amount' => $lines[$li]['amount']] + $p;
                            $lines[$li]['how'][] = $p['id'] . ': one posting of ' . number_format($p['amount'], 2) . " pays this fee and loan {$other['contract_id']}'s";
                        }
                        break;
                    }
                }
            }
        }
        $single('held; same amount near the date (no name: confirm)', fn ($p, $l) => ! $p['tied'], $exact);
        $single('tied by the system to another loan of the same customer', fn ($p, $l) => $p['tied'] && $p['loan'] !== $l['contract_id']
            && $this->sameCustomer($names[$p['loan']] ?? '', $names[$l['contract_id']] ?? ''), $exact);
        // then within the governed band (a difference is reported on the line)
        $single('tied to this loan (within the band)', $tiedHere, $banded);
        $single('held; the system\'s candidate loan (within the band)', $candidate, $banded);
        $single('held; the customer\'s name is in the narration (within the band)', $heldNamed, $banded);
        $several($banded);

        $conflicts = []; $ties = [];
        foreach ($lines as $li => $l) {
            if ($l['postings'] === []) {
                $refusals[] = ['row' => $l['row'], 'contract_id' => $l['contract_id'], 'amount' => $l['amount'],
                    'reason' => "No GL fee posting on {$l['gl']} accounts for this line (amount, date and loan or name)."];
                continue;
            }
            $sum = array_sum(array_column($l['postings'], 'amount'));
            $lines[$li]['difference'] = round($l['amount'] - $sum, 2);
            foreach ($l['postings'] as $p) {
                if ($p['tied'] && $p['loan'] !== $l['contract_id']) {
                    $conflicts[] = ['row' => $l['row'], 'contract_id' => $l['contract_id'], 'posting' => $p['id'], 'tied_to' => $p['loan'], 'amount' => $p['amount'],
                        'note' => "The system tied this posting to loan {$p['loan']} (the loan in its batch); the workbook puts it on loan {$l['contract_id']}. A person decides which is right."];
                } elseif (! $p['tied'] && ! ($p['shared'] ?? false)) {
                    $ties[] = ['held_id' => $p['held_id'], 'contract_id' => $l['contract_id'], 'row' => $l['row'], 'posting' => $p['id'], 'amount' => $p['amount']];
                }
            }
        }

        // completeness: the months each fee GL's matched postings span
        $coverage = [];
        foreach ($lines as $l) {
            foreach ($l['postings'] as $p) {
                $m = $p['date']->format('Y-m');
                $coverage[$p['gl']] = [min($coverage[$p['gl']][0] ?? $m, $m), max($coverage[$p['gl']][1] ?? $m, $m)];
            }
        }
        $unaccounted = [];
        foreach ($coverage as $gl => [$from, $to]) {
            $all = array_values($free((string) $gl));
            $left = array_filter($all, fn ($p) => ($m = $p['date']->format('Y-m')) >= $from && $m <= $to);
            // a posting and its reversal cancel, in whichever months they fall (the reversal may lie outside the span)
            $gone = [];
            foreach ($left as $i => $p) {
                foreach ($all as $j => $q) {
                    if ($j !== $i && ! isset($gone[$i]) && ! isset($gone[$j]) && abs($p['amount'] + $q['amount']) < 1.0) {
                        $gone[$i] = $gone[$j] = true;
                    }
                }
            }
            $byMonth = [];
            foreach ($left as $i => $p) {
                if (! isset($gone[$i])) {
                    $byMonth[$p['date']->format('Y-m')][] = $p;
                }
            }
            ksort($byMonth);
            foreach ($byMonth as $m => $ps) {
                $net = round(array_sum(array_column($ps, 'amount')), 2);
                if (! $this->within($net, 0.0, $tolerance)) {
                    $unaccounted[] = ['gl' => (string) $gl, 'month' => $m, 'net' => $net, 'postings' => array_map(fn ($p) => [
                        'posting' => $p['id'], 'date' => $p['date']->toDateString(), 'amount' => $p['amount'], 'loan' => $p['loan'], 'narration' => $p['narration']], $ps)];
                }
            }
        }
        foreach ($unaccounted as $u) {
            $refusals[] = ['row' => null, 'contract_id' => null, 'amount' => $u['net'],
                'reason' => "GL {$u['gl']}, {$u['month']}: " . count($u['postings']) . ' posting(s) netting ' . number_format($u['net'], 2) . ' are not in the file, so it leaves fees out: '
                    . implode('; ', array_map(fn ($p) => "{$p['posting']} {$p['date']} " . number_format($p['amount'], 2) . ' ' . mb_substr((string) $p['narration'], 0, 60), array_slice($u['postings'], 0, 8)))];
        }

        return [
            'status' => $refusals === [] ? 'PASS' : 'REFUSED',
            'refusals' => $refusals,
            'lines' => array_map(fn ($l) => array_diff_key($l, ['postings' => 1]) + ['postings' => array_map(fn ($p) => $p['id'], $l['postings'])], $lines),
            'unaccounted' => $unaccounted,
            'ties' => $ties,
            'conflicts' => $conflicts,
            'coverage' => $coverage,
            'tolerance' => $tolerance,
        ];
    }

    /** @return list<array{id:string, held_id:?int, tied:bool, loan:?string, gl:string, amount:float, date:CarbonImmutable, narration:string, programme:bool}> */
    private function postings(): array
    {
        $out = [];
        foreach (DB::table('contract_fees')->where('source_system', GlFeeBuildService::SOURCE)->get(['id', 'contract_id', 'gl_account_ref', 'amount', 'transaction_date', 'description']) as $r) {
            if ($r->transaction_date === null) {
                continue;
            }
            $out[] = ['id' => 'T' . $r->id, 'held_id' => null, 'tied' => true, 'loan' => (string) $r->contract_id, 'gl' => (string) $r->gl_account_ref,
                'amount' => (float) $r->amount, 'date' => CarbonImmutable::parse($r->transaction_date), 'narration' => (string) $r->description, 'programme' => false];
        }
        foreach (DB::table('fee_gl_unassigned')->get(['id', 'gl_code', 'amount', 'transaction_date', 'narration', 'candidate_contract_id', 'reason']) as $r) {
            $out[] = ['id' => 'H' . $r->id, 'held_id' => (int) $r->id, 'tied' => false, 'loan' => $r->candidate_contract_id ? ltrim((string) $r->candidate_contract_id, '0') : null,
                'gl' => (string) $r->gl_code, 'amount' => (float) $r->amount, 'date' => CarbonImmutable::parse($r->transaction_date), 'narration' => (string) $r->narration,
                'programme' => str_starts_with((string) $r->reason, 'MEGA_FARM')];
        }

        return $out;
    }

    /** @return array<string,string> loan => customer name (contract master, else the latest loan book) */
    private function names(array $contracts): array
    {
        $contracts = array_values(array_filter($contracts));
        if ($contracts === []) {
            return [];
        }
        $out = DB::table('loan_books')->whereIn('contract_id', $contracts)->where('customer_name', '<>', '')->orderBy('reporting_period')
            ->pluck('customer_name', 'contract_id')->all();
        foreach (DB::table('contract_eir')->whereIn('contract_id', $contracts)->where('customer_name', '<>', '')->pluck('customer_name', 'contract_id') as $c => $n) {
            $out[$c] = $n;
        }

        return array_map('strval', $out);
    }

    /** The distinctive words of a name or narration (three letters or more, common words and fee words left out). */
    private function tokens(?string $text): array
    {
        static $stop = ['limited', 'ltd', 'company', 'group', 'the', 'and', 'for', 'from', 'investment', 'investments', 'holdings', 'services', 'general',
            'farm', 'farms', 'trf', 'fees', 'fee', 'legal', 'arrangement', 'arrangment', 'loan', 'drawdown', 'mega', 'trading', 'sacco', 'africa', 'malawi',
            'enterprises', 'enterprise', 'international', 'being', 'reversal', 'reallocation', 'allocation', 'over', 'deducted', 'initial', 'drawn', 'down',
            'maiic', 'batch', 'one', 'account', 'debit', 'same', 'amount', 'own', 'repayment', 'insurance', 'guarantee'];
        preg_match_all('/[a-z]{3,}/', mb_strtolower((string) $text), $m);

        return array_values(array_diff(array_unique($m[0]), $stop));
    }

    private function sameCustomer(string $a, string $b): bool
    {
        return $a !== '' && $b !== '' && array_intersect($this->tokens($a), $this->tokens($b)) !== [];
    }

    private function date(mixed $value): ?CarbonImmutable
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        try {
            return CarbonImmutable::parse((string) $value)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    /** The reconciliation band in force (recon_tolerance); to the cent when none is set (fail closed). */
    private function tolerance(): array
    {
        try {
            return $this->governance->reconciliationTolerance();
        } catch (Throwable) {
            return ['percent' => 0.0, 'floor' => 0.01];
        }
    }

    private function within(float $difference, ?float $base, array $tolerance): bool
    {
        return abs($difference) <= max((float) $tolerance['floor'], abs((float) $base) * (float) $tolerance['percent'] / 100) + 0.005;
    }

    /** @return \Generator<list<array>> */
    private function combinations(array $items, int $k, int $start = 0, array $prefix = []): \Generator
    {
        if (count($prefix) === $k) {
            yield $prefix;

            return;
        }
        for ($i = $start; $i < count($items); $i++) {
            yield from $this->combinations($items, $k, $i + 1, [...$prefix, $items[$i]]);
        }
    }
}
