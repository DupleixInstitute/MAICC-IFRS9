<?php

// namespace Database\Seeders;

// use Illuminate\Database\Seeder;
// use Illuminate\Support\Facades\DB;

// /**
//  * Minimal LGD test set: exactly TWO periods so calculateLGD has a clean
//  * start vs reporting snapshot.
//  *
//  *   START period     = 2025-01   (all contracts Stage 3 = the cohort)
//  *   REPORTING period = 2025-06   (recovery / cure / derecognition outcomes)
//  *
//  * Run LGD with: Portfolio "LGD Two-Period Test", start_period 2025-01,
//  * reporting_period 2025-06.
//  *
//  * Expected (system, no discounting):
//  *   Start Stage-3 balance = 340,000
//  *   Cure amount  = 130,000  (rate ~0.382)
//  *   Recovered    = 220,000  (rate ~0.647)
//  *   LGD          ~ 0.218 (21.8%)
//  *
//  * Re-runnable (keyed on contract_id + reporting_period unique index).
//  */
// class LgdTwoPeriodSeeder extends Seeder
// {
//     public function run(): void
//     {
//         $userId = DB::table('users')->min('id') ?? 1;

//         $portfolioId = DB::table('loan_portfolios')->where('name', 'LGD Two-Period Test')->value('id');
//         if (! $portfolioId) {
//             $portfolioId = DB::table('loan_portfolios')->insertGetId([
//                 'name' => 'LGD Two-Period Test',
//                 'description' => 'Isolated 2-period portfolio for clean LGD calc testing',
//                 'active' => 1,
//                 'created_by_id' => $userId,
//                 'created_at' => now(),
//                 'updated_at' => now(),
//             ]);
//         }

//         $startPeriod = '2025-01';
//         $reportingPeriod = '2025-06';

//         // contract => [customer, [startStage,startBal], [endStage,endBal] | null (derecognised)]
//         $contracts = [
//             ['L001', 'Alpha Holdings',  ['3', 100000], ['3', 60000]],   // partial recovery, stays S3
//             ['L002', 'Beta Traders',    ['3', 80000],  ['1', 0]],       // cured S1 + fully paid
//             ['L003', 'Gamma Mining',    ['3', 50000],  ['2', 20000]],   // cured S2 + partial
//             ['L004', 'Delta Agro',      ['3', 70000],  null],           // derecognised (gone @ reporting)
//             ['L005', 'Epsilon Retail',  ['3', 40000],  ['3', 40000]],   // no movement
//         ];

//         foreach ($contracts as [$contractId, $customerName, $start, $end]) {
//             $rows = ["{$startPeriod}" => $start];
//             if ($end !== null) {
//                 $rows["{$reportingPeriod}"] = $end;
//             }

//             foreach ($rows as $period => [$stage, $balance]) {
//                 [$year, $month] = array_map('intval', explode('-', $period));

//                 DB::table('loan_books')->updateOrInsert(
//                     [
//                         'contract_id' => $contractId,
//                         'reporting_period' => $period,
//                     ],
//                     [
//                         'loan_portfolio_id' => $portfolioId,
//                         'client_id' => null,
//                         'customer_id' => 'CUST-' . $contractId,
//                         'customer_name' => $customerName,
//                         'external_identity_id' => 'TBA',
//                         'reporting_year' => $year,
//                         'reporting_month' => $month,
//                         'create_date' => '2024-06-01',
//                         'due_date' => '2026-06-01',
//                         'interest_rate' => 0.1800,
//                         'principal_balance' => $balance,
//                         'carrying_amount' => $balance,
//                         'approved_amount' => 100000,
//                         'disbursed' => 100000,
//                         'overdue_days' => $stage === '3' ? 120 : ($stage === '2' ? 45 : 0),
//                         'calculated_ifrs9_stage' => $stage,
//                         'ifrs9_stage' => (int) $stage,
//                         'ecl_value' => round($balance * 0.10, 2),
//                         'lgd_value' => 0.4500,
//                         'pd_value' => 0.1000,
//                         'contract_status' => 'active',
//                         'is_month_end' => 1,
//                         'created_at' => now(),
//                         'updated_at' => now(),
//                     ]
//                 );
//             }
//         }

//         $this->command?->info(
//             'Seeded 2-period LGD set on portfolio #' . $portfolioId .
//             " (start {$startPeriod}, reporting {$reportingPeriod}); rows: " .
//             DB::table('loan_books')
//                 ->where('loan_portfolio_id', $portfolioId)
//                 ->whereIn('reporting_period', [$startPeriod, $reportingPeriod])
//                 ->count()
//         );
//     }
// }
