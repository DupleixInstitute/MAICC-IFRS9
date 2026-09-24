<?php

namespace App\Services\Eir;

use App\Support\ReportingPeriod;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Drawdowns and undrawn commitments (spec v3 section 7.6).
 *
 * A facility can be drawn in tranches. What has been drawn by a date is the sum
 * of its drawdowns up to that date, and the undrawn commitment is the approved
 * amount less what has been drawn. That commitment is reported separately under
 * IFRS 9, and at 31 August 2026 eight facilities carried MWK 3,472,435,397 of
 * it.
 *
 * Where no drawdown rows have been loaded for a facility the figures fall back
 * to the monthly Loan Book Report, whose "Not Yet Disbursed" column reconciles
 * to approved less disbursed to the kwacha. The fallback is always named, never
 * silent, because it carries no dates: a month with a tranche in it cannot be
 * reconciled from it (section 7.7). Where neither source states an approved
 * amount the undrawn commitment is null, not zero: an empty table is not
 * evidence that a facility is fully drawn.
 */
class DisbursementService
{
    public const SOURCE_DRAWDOWNS = 'DRAWDOWN_ROWS';

    public const SOURCE_LOAN_BOOK = 'LOAN_BOOK_DISBURSED';

    public const SOURCE_CONTRACT_MASTER = 'CONTRACT_MASTER';

    public const SOURCE_NONE = 'NOT_KNOWN';

    /**
     * Every drawdown on a facility, oldest first.
     *
     * @return list<array{id:int, tranche_no:?int, disbursement_date:string, amount:float, reference:?string, sub_account_no:?string, source_system:?string, external_transaction_id:?string, loaded_at:?string}>
     */
    public function tranchesFor(string $contractId): array
    {
        return DB::table('contract_disbursements')
            ->where('contract_id', $contractId)
            ->orderBy('disbursement_date')
            ->orderBy('tranche_no')
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'tranche_no' => $row->tranche_no === null ? null : (int) $row->tranche_no,
                'disbursement_date' => substr((string) $row->disbursement_date, 0, 10),
                'amount' => (float) $row->amount,
                'reference' => $row->reference,
                'sub_account_no' => $row->sub_account_no,
                'source_system' => $row->source_system,
                'external_transaction_id' => $row->external_transaction_id,
                'loaded_at' => $row->created_at === null ? null : substr((string) $row->created_at, 0, 19),
            ])
            ->values()
            ->all();
    }

    /**
     * What had been drawn on a facility by a date. Null when nothing says.
     */
    public function drawnAt(string $contractId, CarbonInterface|string|null $date = null): ?float
    {
        return $this->facility($contractId, $date)['drawn'];
    }

    /**
     * The undrawn commitment on a facility at a date: the approved amount less
     * what had been drawn, floored at zero. Null when the approved amount or
     * the amount drawn is not known from any source.
     */
    public function undrawn(string $contractId, CarbonInterface|string|null $date = null): ?float
    {
        return $this->facility($contractId, $date)['undrawn'];
    }

    /**
     * One facility in full: the approved amount, what has been drawn, the
     * undrawn commitment, where each figure came from and every tranche.
     *
     * @return array{contract_id:string, customer:?string, approved:?float, drawn:?float, undrawn:?float, approved_source:string, drawn_source:string, tranches:list<array>, tranche_count:int, loan_book_period:?string, as_of:string}
     */
    public function facility(string $contractId, CarbonInterface|string|null $date = null): array
    {
        $asOf = $this->date($date);
        $tranches = $this->tranchesFor($contractId);
        $contract = DB::table('contract_eir')->where('contract_id', $contractId)
            ->first(['approved_amount', 'drawn_amount']);
        $book = $this->loanBookRow($contractId, $asOf);

        return $this->assemble($contractId, null, $asOf, $tranches, $contract, $book);
    }

    /**
     * Every facility the engine knows, for the Drawdowns screen: the EIR
     * contract population plus any facility that has drawdowns loaded but no
     * contract profile yet, so nothing is hidden.
     *
     * @return list<array>
     */
    public function facilities(CarbonInterface|string|null $date = null, ?string $search = null): array
    {
        $asOf = $this->date($date);

        $contracts = DB::table('contract_eir')
            ->orderBy('contract_id')
            ->get(['contract_id', 'approved_amount', 'drawn_amount'])
            ->keyBy('contract_id');
        $withDrawdowns = DB::table('contract_disbursements')->distinct()->orderBy('contract_id')->pluck('contract_id');

        $ids = $contracts->keys()->merge($withDrawdowns)->map(fn ($id) => (string) $id)->unique()->sort()->values();
        if ($search !== null && trim($search) !== '') {
            $needle = strtolower(trim($search));
            $ids = $ids->filter(fn ($id) => str_contains(strtolower($id), $needle))->values();
        }
        if ($ids->isEmpty()) {
            return [];
        }

        $tranches = DB::table('contract_disbursements')
            ->whereIn('contract_id', $ids->all())
            ->orderBy('disbursement_date')->orderBy('tranche_no')->orderBy('id')
            ->get()
            ->groupBy('contract_id');
        $books = $this->loanBookRows($ids->all(), $asOf);
        $names = $this->customerNames($ids->all());

        $facilities = [];
        foreach ($ids as $id) {
            $rows = ($tranches->get($id) ?? collect())->map(fn ($row) => [
                'id' => (int) $row->id,
                'tranche_no' => $row->tranche_no === null ? null : (int) $row->tranche_no,
                'disbursement_date' => substr((string) $row->disbursement_date, 0, 10),
                'amount' => (float) $row->amount,
                'reference' => $row->reference,
                'sub_account_no' => $row->sub_account_no,
                'source_system' => $row->source_system,
                'external_transaction_id' => $row->external_transaction_id,
                'loaded_at' => $row->created_at === null ? null : substr((string) $row->created_at, 0, 19),
            ])->values()->all();

            $facilities[] = $this->assemble(
                $id,
                $names[$id] ?? null,
                $asOf,
                $rows,
                $contracts->get($id),
                $books[$id] ?? null
            );
        }

        return $facilities;
    }

    /**
     * The total row the screen shows: approved, drawn and undrawn across the
     * facilities, and how many of them fall back to the loan book.
     *
     * @param  list<array>  $facilities
     * @return array{facilities:int, approved:float, drawn:float, undrawn:float, tranches:int, from_drawdowns:int, from_loan_book:int, not_known:int}
     */
    public function totals(array $facilities): array
    {
        $totals = [
            'facilities' => count($facilities),
            'approved' => 0.0,
            'drawn' => 0.0,
            'undrawn' => 0.0,
            'tranches' => 0,
            'from_drawdowns' => 0,
            'from_loan_book' => 0,
            'not_known' => 0,
        ];

        foreach ($facilities as $facility) {
            $totals['approved'] = round($totals['approved'] + (float) ($facility['approved'] ?? 0), 2);
            $totals['drawn'] = round($totals['drawn'] + (float) ($facility['drawn'] ?? 0), 2);
            $totals['undrawn'] = round($totals['undrawn'] + (float) ($facility['undrawn'] ?? 0), 2);
            $totals['tranches'] += $facility['tranche_count'];
            $totals[match ($facility['drawn_source']) {
                self::SOURCE_DRAWDOWNS => 'from_drawdowns',
                self::SOURCE_NONE => 'not_known',
                default => 'from_loan_book',
            }]++;
        }

        return $totals;
    }

    /**
     * A later drawdown as the EIR solver reads it: money out of MAIIC after
     * origination, so a negative amount in the cash-flow vector. The solver
     * only accepts a negative amount on a row that says it is a drawdown, so
     * the flow carries that flag with it.
     *
     * @return list<array{due_date:string, amount:float, flow_type:string, reference:?string}>
     */
    public function laterDrawdownFlows(string $contractId, CarbonInterface|string $originationDate): array
    {
        $origination = $this->date($originationDate);
        $flows = [];
        foreach ($this->tranchesFor($contractId) as $tranche) {
            if (CarbonImmutable::parse($tranche['disbursement_date'])->lte($origination)) {
                continue;
            }
            $flows[] = [
                'due_date' => $tranche['disbursement_date'],
                'amount' => -1 * $tranche['amount'],
                'flow_type' => 'DISBURSEMENT',
                'reference' => $tranche['reference'],
            ];
        }

        return $flows;
    }

    /** @param list<array> $tranches */
    private function assemble(string $contractId, ?string $customer, CarbonImmutable $asOf, array $tranches, $contract, $book): array
    {
        $upTo = array_values(array_filter($tranches, fn ($row) => $row['disbursement_date'] <= $asOf->toDateString()));

        $drawn = null;
        $drawnSource = self::SOURCE_NONE;
        if ($upTo !== []) {
            $drawn = round(array_sum(array_column($upTo, 'amount')), 2);
            $drawnSource = self::SOURCE_DRAWDOWNS;
        } elseif ($book !== null && (float) $book->disbursed > 0) {
            $drawn = round((float) $book->disbursed, 2);
            $drawnSource = self::SOURCE_LOAN_BOOK;
        } elseif ($contract !== null && (float) $contract->drawn_amount > 0) {
            $drawn = round((float) $contract->drawn_amount, 2);
            $drawnSource = self::SOURCE_CONTRACT_MASTER;
        }

        $approved = null;
        $approvedSource = self::SOURCE_NONE;
        if ($contract !== null && (float) $contract->approved_amount > 0) {
            $approved = round((float) $contract->approved_amount, 2);
            $approvedSource = self::SOURCE_CONTRACT_MASTER;
        } elseif ($book !== null && (float) $book->approved_amount > 0) {
            $approved = round((float) $book->approved_amount, 2);
            $approvedSource = self::SOURCE_LOAN_BOOK;
        }

        return [
            'contract_id' => $contractId,
            'customer' => $customer,
            'approved' => $approved,
            'drawn' => $drawn,
            'undrawn' => $approved === null || $drawn === null ? null : round(max(0.0, $approved - $drawn), 2),
            'approved_source' => $approvedSource,
            'drawn_source' => $drawnSource,
            'tranches' => $upTo,
            'tranche_count' => count($upTo),
            'loan_book_period' => $book === null ? null : ReportingPeriod::normalise($book->reporting_period),
            'as_of' => $asOf->toDateString(),
        ];
    }

    /** The latest loan-book row for a facility whose month end is on or before the date. */
    private function loanBookRow(string $contractId, CarbonImmutable $asOf)
    {
        return $this->loanBookRows([$contractId], $asOf)[$contractId] ?? null;
    }

    /**
     * The latest loan-book row per facility on or before the date. The period
     * column is a free string, so it is normalised in PHP through the one
     * helper that knows its shapes rather than with driver-specific SQL.
     *
     * @param  list<string>  $contractIds
     * @return array<string,object>
     */
    private function loanBookRows(array $contractIds, CarbonImmutable $asOf): array
    {
        if ($contractIds === [] || ! $this->hasTable('loan_books')) {
            return [];
        }

        $latest = [];
        DB::table('loan_books')
            ->whereIn('contract_id', $contractIds)
            ->orderBy('id')
            ->select(['contract_id', 'reporting_period', 'approved_amount', 'disbursed', 'commitments'])
            ->get()
            ->each(function ($row) use (&$latest, $asOf) {
                $period = ReportingPeriod::normalise($row->reporting_period);
                if ($period === null) {
                    return;
                }
                $monthEnd = ReportingPeriod::monthEnd($period);
                if ($monthEnd === null || $monthEnd->gt($asOf)) {
                    return;
                }
                $id = (string) $row->contract_id;
                $held = $latest[$id] ?? null;
                if ($held === null || ReportingPeriod::normalise($held->reporting_period) < $period) {
                    $latest[$id] = $row;
                }
            });

        return $latest;
    }

    /** @param list<string> $contractIds @return array<string,string> */
    private function customerNames(array $contractIds): array
    {
        if ($contractIds === [] || ! $this->hasTable('loan_books')) {
            return [];
        }

        $names = [];
        DB::table('loan_books')
            ->whereIn('contract_id', $contractIds)
            ->orderBy('id')
            ->select(['contract_id', 'customer_name'])
            ->get()
            ->each(function ($row) use (&$names) {
                $name = trim((string) $row->customer_name);
                if ($name !== '') {
                    $names[(string) $row->contract_id] = $name;
                }
            });

        return $names;
    }

    private function hasTable(string $table): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }

    private function date(CarbonInterface|string|null $date): CarbonImmutable
    {
        if ($date === null || $date === '') {
            return CarbonImmutable::today();
        }
        if ($date instanceof CarbonInterface) {
            return CarbonImmutable::parse($date->toDateString())->startOfDay();
        }

        return CarbonImmutable::parse($date)->startOfDay();
    }
}
