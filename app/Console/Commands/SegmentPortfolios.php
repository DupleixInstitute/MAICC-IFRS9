<?php

namespace App\Console\Commands;

use App\Support\ReportingPeriodLock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * The book split into its real programmes.
 *
 * The imported book is one blended "Loans" portfolio, which makes a PD, an
 * LGD and an ECL by portfolio meaningless (seasonal agricultural risk
 * blended with industrial and term lending). E-Banker's product group
 * already carries the programme; this command gives each programme its own
 * portfolio and moves every loan of an open period into it. The user
 * approved the split on 9 October 2026.
 *
 * It does NOT touch the sector. Until 9 October 2026 it back-filled a
 * sector label wherever industry_type was blank, guessed from the product
 * group, and on the July to November 2024 rows (which carry E-Banker's
 * industry_code but no label) that overwrote E-Banker's code. The sector
 * comes from E-Banker only; a blank or default sector is reported, never
 * guessed (see pd_sector_unverified_codes).
 *
 * Idempotent: portfolios are matched by name and loans re-mapped from the
 * product group. A locked period is never touched. --rollback returns every
 * loan of an open period to portfolio 1.
 */
class SegmentPortfolios extends Command
{
    protected $signature = 'ifrs9:segment-portfolios
        {--dry-run : Show what would change without writing}
        {--rollback : Re-map every loan back to the original portfolio (id 1)}';

    protected $description = 'Give each lending programme (E-Banker product group) its own portfolio and move its loans into it';

    /** product_group => portfolio name. The programme is the product group; the Mega Farm schemes are one programme. */
    public const MAP = [
        'MAIIC Industrial Loans'          => 'MAIIC Industrial',
        'MAIIC Agricultural Loans'        => 'MAIIC Agricultural',
        'MAIIC Term Loans'                => 'MAIIC Term',
        'MAIIC Loans (1050103)'           => 'MAIIC Loans (GL 1050103)',
        'FInES Industrial Loans'          => 'FInES Industrial',
        'FInES Agricultural Loans'        => 'FInES Agricultural',
        'Mega Farm Fertilizer Loans'      => 'Mega Farm',
        'Mega Farm Seed Loans'            => 'Mega Farm',
        'Mega Farm-Pesticides Loans'      => 'Mega Farm',
        'Mega Farm Equipment Loans'       => 'Mega Farm',
        'Mega Farms Irrigation'           => 'Mega Farm',
        'Mega Farm Working Capital Loans' => 'Mega Farm',
    ];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $locked = DB::getSchemaBuilder()->hasTable('reporting_period_locks') ? DB::table('reporting_period_locks')->pluck('reporting_period')->all() : [];
        $open = fn ($q) => $q->whereNotIn('reporting_period', $locked);
        if ($locked !== []) {
            $this->warn('Locked periods left as they are: ' . implode(', ', $locked));
        }

        if ($this->option('rollback')) {
            $n = $open(DB::table('loan_books')->where('loan_portfolio_id', '!=', 1))->count();
            if (! $dry) {
                $open(DB::table('loan_books'))->update(['loan_portfolio_id' => 1]);
            }
            $this->info(($dry ? '[dry-run] would re-map ' : 'Re-mapped ') . "{$n} loans back to portfolio 1. The programme portfolios are left in place.");

            return self::SUCCESS;
        }

        // 0. The product group of a contract is its identity: the rows before
        //    July 2024 carry none, so they take the contract's own group from
        //    its later rows (a contract never changes group in the book).
        $conflicting = DB::table('loan_books')->whereNotNull('product_group')->groupBy('contract_id')->havingRaw('COUNT(DISTINCT product_group) > 1')->pluck('contract_id');
        if ($conflicting->isNotEmpty()) {
            $this->error('Contracts with more than one product group: ' . $conflicting->take(10)->implode(', ') . '. Nothing changed.');

            return self::FAILURE;
        }
        $missing = $open(DB::table('loan_books')->whereNull('product_group')->whereIn('contract_id', fn ($q) => $q->select('contract_id')->from('loan_books')->whereNotNull('product_group')))->count();
        if ($missing > 0 && ! $dry) {
            $groups = DB::table('loan_books')->whereNotNull('product_group')->groupBy('contract_id')->selectRaw('contract_id, MAX(product_group) pg')->pluck('pg', 'contract_id');
            foreach ($groups as $contract => $group) {
                $open(DB::table('loan_books')->where('contract_id', (string) $contract)->whereNull('product_group'))->update(['product_group' => $group]);
            }
        }
        $this->line(($dry ? '[dry-run] would back-fill' : 'Back-filled') . " the product group on {$missing} earlier rows from the contract's own later rows.");

        // 1. The programme portfolios, matched by name
        $ids = [];
        foreach (array_unique(array_values(self::MAP)) as $name) {
            $existing = DB::table('loan_portfolios')->where('name', $name)->value('id');
            if ($existing) {
                $ids[$name] = (int) $existing;
                continue;
            }
            if ($dry) {
                $ids[$name] = '(new)';
                continue;
            }
            $ids[$name] = (int) DB::table('loan_portfolios')->insertGetId(['name' => $name, 'description' => 'Lending programme: the E-Banker product group(s) ' . implode(', ', array_keys(self::MAP, $name)) . '.',
                'active' => 1, 'created_by_id' => DB::table('users')->min('id') ?? 1, 'created_at' => now(), 'updated_at' => now()]);
            $this->info("Created portfolio {$name} (id {$ids[$name]})");
        }

        // 2. Every loan of an open period into its programme
        $rows = [];
        foreach (self::MAP as $group => $name) {
            $q = $open(DB::table('loan_books')->where('product_group', $group));
            $n = (clone $q)->count();
            if ($n === 0) {
                continue;
            }
            $moved = (clone $q)->where('loan_portfolio_id', '!=', is_int($ids[$name]) ? $ids[$name] : 0)->count();
            if (! $dry) {
                (clone $q)->update(['loan_portfolio_id' => $ids[$name]]);
            }
            $rows[] = [$group, "{$name} ({$ids[$name]})", $n, $moved, (clone $q)->min('reporting_period') . ' to ' . (clone $q)->max('reporting_period')];
        }
        $this->table(['Product group', 'Portfolio', 'Rows', 'Moved', 'Periods'], $rows);

        $unmapped = $open(DB::table('loan_books')->where(fn ($q) => $q->whereNull('product_group')->orWhereNotIn('product_group', array_keys(self::MAP))));
        $n = (clone $unmapped)->count();
        if ($n > 0) {
            $this->warn("{$n} rows have no product group in the map and stay in portfolio 1 (" . (clone $unmapped)->min('reporting_period') . ' to ' . (clone $unmapped)->max('reporting_period') . '); the data quality report lists them.');
        }
        $noSector = $open(DB::table('loan_books')->where(fn ($q) => $q->whereNull('industry_type')->orWhere('industry_type', '')))->where('reporting_period', '>=', '2025-01')->where('product_group', 'not like', 'Mega Farm%')->count();
        $this->line("Sector: not changed. {$noSector} rows from 2025 on (Mega Farm aside) carry no E-Banker sector label and are reported as 'no sector captured'.");

        return self::SUCCESS;
    }
}
