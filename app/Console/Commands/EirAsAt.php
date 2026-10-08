<?php

namespace App\Console\Commands;

use App\Services\Eir\EirAsAtService;
use Illuminate\Console\Command;
use Throwable;

/**
 * The EIR computation as at any date (spec v4 section 6.11).
 *
 *   php artisan eir:as-at 2025-12-31 --contract=104420000005
 *   php artisan eir:as-at 2026-03-15                 the whole book, by product and GL
 *   php artisan eir:as-at 2025-12-31 --json
 */
class EirAsAt extends Command
{
    protected $signature = 'eir:as-at {date : YYYY-MM-DD} {--contract= : One contract; otherwise the book} {--json : Print the full structure}';

    protected $description = 'Show the EIR computation for a loan, or the book, as at any date: amortised cost, gross, EIR and contractual interest to the date, the difference';

    public function handle(EirAsAtService $service): int
    {
        try {
            if ($this->option('contract')) {
                $v = $service->contract((string) $this->option('contract'), (string) $this->argument('date'));
                if ($this->option('json')) {
                    $this->line(json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                    return self::SUCCESS;
                }
                $this->info("Contract {$v['contract_id']} ({$v['customer_name']}) as at {$v['as_at']}" . ($v['locked_period'] ? ' [locked period]' : ''));
                $i = $v['interest'];
                $this->table(['Figure', 'Value', 'Basis'], [
                    ['EIR (effective annual)', $v['eir']['effective_annual'] !== null ? number_format($v['eir']['effective_annual'] * 100, 4) . '%' : '-', 'solved ' . ($v['eir']['solved_at'] ?? '-') . ', locked ' . ($v['eir']['locked_at'] ?? '-')],
                    ['Contractual rate', $v['eir']['contractual_rate'] !== null ? number_format($v['eir']['contractual_rate'] * 100, 2) . '%' : '-', $v['eir']['rate_type'] ?? ''],
                    ['Amortised cost', $v['amortised_cost'] !== null ? number_format($v['amortised_cost'], 2) : '-', $v['amortised_cost_basis']],
                    ['Gross carrying amount', number_format($v['gross_carrying_amount'], 2), $v['gross_basis']],
                    ['EIR interest, period to date', $i['eir_period_to_date'] !== null ? number_format($i['eir_period_to_date'], 2) : '-', ''],
                    ['Contractual interest posted, period to date', number_format($i['contractual_period_to_date'], 2), 'ledger types 303 and 120'],
                    ['EIR interest, year to date', number_format($i['eir_year_to_date'], 2), ''],
                    ['Contractual interest, year to date', number_format($i['contractual_year_to_date'], 2), ''],
                    ['Difference, year to date', number_format($i['difference_year_to_date'], 2), 'the revenue shift'],
                    ['Difference, cumulative', number_format($i['difference_cumulative'], 2), 'from ' . ($i['cumulative_from'] ?? '-')],
                    ['Remaining expected cash flows', count($v['remaining_cash_flows']) . ' lines', 'schedule version ' . ($v['eir']['schedule_version'] ?? '-')],
                    ['Modifications to the date', count($v['modifications']), ''],
                ]);
                return self::SUCCESS;
            }
            $b = $service->book((string) $this->argument('date'));
            if ($this->option('json')) {
                $this->line(json_encode($b, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                return self::SUCCESS;
            }
            $this->info("The book as at {$b['as_at']}: {$b['total']['contracts']} contracts with a locked EIR");
            $fmt = fn ($g) => [$g['key'], $g['contracts'], number_format($g['eir_ytd'], 2), number_format($g['contractual_ytd'], 2), number_format($g['difference'], 2), number_format($g['amortised_cost'], 2), number_format($g['gross'], 2)];
            $this->table(['By product', 'Contracts', 'EIR interest YTD', 'Contractual YTD', 'Difference', 'Amortised cost', 'Gross'], array_map($fmt, $b['by_product']));
            $this->table(['By GL', 'Contracts', 'EIR interest YTD', 'Contractual YTD', 'Difference', 'Amortised cost', 'Gross'], array_map($fmt, $b['by_gl']));
            $t = $b['total'];
            $this->line(sprintf('Total: EIR %s, contractual %s, difference %s; amortised cost %s, gross %s', number_format($t['eir_ytd'], 2), number_format($t['contractual_ytd'], 2), number_format($t['difference'], 2), number_format($t['amortised_cost'], 2), number_format($t['gross'], 2)));
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
