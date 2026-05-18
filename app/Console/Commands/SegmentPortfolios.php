<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Real portfolio segmentation + sector tagging for the demo book.
 *
 * The imported book is one blended "Loans" bucket, which makes per-portfolio
 * PD/LGD/ECL meaningless (seasonal agri-input risk blended with equipment and
 * industrial risk). The product_group column already carries the real
 * segmentation; this command derives proper portfolios from it and back-fills
 * the missing RBM sector tag.
 *
 * Idempotent: safe to run repeatedly (portfolios are matched by name, loans
 * re-mapped deterministically from product_group). Reversible with --rollback
 * (loans returned to the original portfolio 1). The mapping is the single
 * source of truth below.
 */
class SegmentPortfolios extends Command
{
    protected $signature = 'ifrs9:segment-portfolios
        {--dry-run : Show what would change without writing}
        {--rollback : Re-map every loan back to the original portfolio (id 1)}';

    protected $description = 'Derive real loan portfolios from product_group and back-fill the RBM sector tag.';

    /**
     * product_group  =>  [portfolio name, industry_code, industry_type label]
     * Sector labels match the "N. Name" form already used elsewhere in loan_books.
     */
    private const MAP = [
        'Mega Farm Fertilizer Loans'      => ['Agri-Inputs',          '1', '1. Agriculture, Forestry and Fishing'],
        'Mega Farm Seed Loans'            => ['Agri-Inputs',          '1', '1. Agriculture, Forestry and Fishing'],
        'Mega Farm-Pesticides Loans'      => ['Agri-Inputs',          '1', '1. Agriculture, Forestry and Fishing'],
        'Mega Farm Equipment Loans'       => ['Farm Equipment',       '1', '1. Agriculture, Forestry and Fishing'],
        'Mega Farms Irrigation'           => ['Irrigation',           '1', '1. Agriculture, Forestry and Fishing'],
        'Mega Farm Working Capital Loans' => ['Agri Working Capital', '1', '1. Agriculture, Forestry and Fishing'],
        'FInES Agricultural Loans'        => ['Agri Working Capital', '1', '1. Agriculture, Forestry and Fishing'],
        'MAIIC Agricultural Loans'        => ['Agri Working Capital', '1', '1. Agriculture, Forestry and Fishing'],
        'FInES Industrial Loans'          => ['Industrial',           '3', '3. Manufacturing'],
        'MAIIC Industrial Loans'          => ['Industrial',           '3', '3. Manufacturing'],
    ];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        if ($this->option('rollback')) {
            return $this->rollback($dry);
        }

        // 0. Back-fill product_group on the historical monthly snapshots.
        //    Only the live 2025-11 snapshot carries product_group; the monthly
        //    rows are the same contracts tracked over time, so product_group is
        //    intrinsic and can be sourced from the contract's tagged row.
        $missing = DB::table('loan_books')
            ->whereNull('product_group')
            ->whereIn('contract_id', function ($q) {
                $q->select('contract_id')->from('loan_books')->whereNotNull('product_group');
            })->count();

        if ($missing > 0) {
            if ($dry) {
                $this->line("[dry-run] would back-fill product_group on {$missing} historical rows from matching contract_id");
            } else {
                DB::statement("
                    UPDATE loan_books h
                    JOIN (
                        SELECT contract_id,
                               MAX(product_group) AS pg,
                               MAX(product_code)  AS pc
                        FROM loan_books
                        WHERE product_group IS NOT NULL
                        GROUP BY contract_id
                    ) s ON s.contract_id = h.contract_id
                    SET h.product_group = s.pg,
                        h.product_code  = COALESCE(h.product_code, s.pc)
                    WHERE h.product_group IS NULL
                ");
                $this->info("Back-filled product_group on {$missing} historical rows from contract identity.");
            }
        }

        // 1. Ensure the real portfolios exist (matched by name => idempotent).
        $portfolioIds = [];
        foreach (array_unique(array_column(self::MAP, 0)) as $name) {
            $existing = DB::table('loan_portfolios')->where('name', $name)->first();
            if ($existing) {
                $portfolioIds[$name] = $existing->id;
                $this->line("Portfolio exists: {$name} (id {$existing->id})");
                continue;
            }
            if ($dry) {
                $this->line("[dry-run] would create portfolio: {$name}");
                $portfolioIds[$name] = '?';
                continue;
            }
            $id = DB::table('loan_portfolios')->insertGetId([
                'name'          => $name,
                'description'   => 'Derived from product_group during demo segmentation.',
                'active'        => 1,
                'created_by_id' => DB::table('users')->min('id') ?? 1,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
            $portfolioIds[$name] = $id;
            $this->info("Created portfolio: {$name} (id {$id})");
        }

        // 2. Re-map loans + back-fill sector tag, per product_group (all periods).
        $this->line('');
        $this->line(str_pad('product_group', 34) . str_pad('portfolio', 22) . 'loans   sector-filled');

        $totalMapped = 0;
        $totalTagged = 0;

        foreach (self::MAP as $group => [$pName, $iCode, $iType]) {
            $base = DB::table('loan_books')->where('product_group', $group);
            $count = (clone $base)->count();
            if ($count === 0) {
                continue;
            }

            $needTag = (clone $base)->whereNull('industry_type')->count();

            if ($dry) {
                $this->line(str_pad($group, 34) . str_pad($pName, 22) . str_pad((string) $count, 8) . $needTag);
                $totalMapped += $count;
                $totalTagged += $needTag;
                continue;
            }

            (clone $base)->update(['loan_portfolio_id' => $portfolioIds[$pName]]);

            // Only fill the sector where it is missing — never clobber a real tag.
            (clone $base)->whereNull('industry_type')->update([
                'industry_code' => $iCode,
                'industry_type' => $iType,
            ]);

            $this->line(str_pad($group, 34) . str_pad($pName, 22) . str_pad((string) $count, 8) . $needTag);
            $totalMapped += $count;
            $totalTagged += $needTag;
        }

        // 3. Anything product_group did not cover.
        $unmapped = DB::table('loan_books')
            ->whereNotIn('product_group', array_keys(self::MAP))
            ->orWhereNull('product_group')
            ->count();

        $this->line('');
        $this->info(($dry ? '[dry-run] ' : '') . "Mapped {$totalMapped} loans into " . count(array_unique(array_column(self::MAP, 0))) . ' portfolios; sector-tagged ' . $totalTagged . ' previously-untagged loans.');
        if ($unmapped > 0) {
            $this->warn("{$unmapped} loans have a product_group outside the map (left on portfolio 1, untagged) — flagged by the Audit & Data Quality report.");
        }

        return self::SUCCESS;
    }

    private function rollback(bool $dry): int
    {
        $n = DB::table('loan_books')->where('loan_portfolio_id', '!=', 1)->count();
        if ($dry) {
            $this->line("[dry-run] would re-map {$n} loans back to portfolio 1");
            return self::SUCCESS;
        }
        DB::table('loan_books')->update(['loan_portfolio_id' => 1]);
        $this->info("Rolled back: {$n} loans re-mapped to portfolio 1. (Derived portfolios left in place; harmless.)");
        return self::SUCCESS;
    }
}
