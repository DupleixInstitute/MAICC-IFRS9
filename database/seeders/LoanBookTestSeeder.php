<?php

// namespace Database\Seeders;

// use Illuminate\Database\Seeder;
// use Illuminate\Support\Carbon;
// use Illuminate\Support\Facades\DB;

// /**
//  * Coherent test data for LGD / reconciliation / disbursement / discounting.
//  *
//  * Window: 2025-01 .. 2025-05 (reporting_period stored as "Y-m").
//  * Contracts:
//  *   C001 - Stage 3 cohort (Jan), partial recovery, stays Stage 3
//  *   C002 - Stage 3 -> Stage 2 -> Stage 1 (cured) + fully paid
//  *   C003 - Stage 3, derecognised after Mar (disappears)
//  *   C004 - New loan originated Feb, Stage 1, disbursement growth
//  *   C005 - Healthy Stage 1 throughout (baseline)
//  *   C006 - Stage 1 -> 2 -> 3 transition (new Stage 3 mid-window)
//  *
//  * Re-runnable: keyed on the (contract_id, reporting_period) unique index.
//  */
// class LoanBookTestSeeder extends Seeder
// {
//     public function run(): void
//     {
//         $userId = DB::table('users')->min('id') ?? 1;

//         $portfolioId = DB::table('loan_portfolios')->where('name', 'Test Portfolio')->value('id');
//         if (! $portfolioId) {
//             $portfolioId = DB::table('loan_portfolios')->insertGetId([
//                 'name' => 'Test Portfolio',
//                 'description' => 'Seeded portfolio for LGD/discounting testing',
//                 'active' => 1,
//                 'created_by_id' => $userId,
//                 'created_at' => now(),
//                 'updated_at' => now(),
//             ]);
//         }

//         // [contract, customer_name, create_date, [ 'YYYY-MM' => [stage, balance], ... ]]
//         $contracts = [
//             ['C001', 'Alpha Holdings', '2024-06-01', [
//                 '2025-01' => ['3', 100000], '2025-02' => ['3', 80000],
//                 '2025-03' => ['3', 60000],  '2025-04' => ['3', 50000],
//                 '2025-05' => ['3', 40000],
//             ]],
//             ['C002', 'Beta Traders', '2024-08-01', [
//                 '2025-01' => ['3', 50000], '2025-02' => ['3', 30000],
//                 '2025-03' => ['2', 10000], '2025-04' => ['1', 0],
//                 '2025-05' => ['1', 0],
//             ]],
//             ['C003', 'Gamma Mining', '2024-05-01', [
//                 '2025-01' => ['3', 70000], '2025-02' => ['3', 70000],
//                 '2025-03' => ['3', 70000],
//                 // absent Apr/May -> derecognised
//             ]],
//             ['C004', 'Delta Agro', '2025-02-01', [
//                 '2025-02' => ['1', 40000], '2025-03' => ['1', 45000],
//                 '2025-04' => ['1', 45000], '2025-05' => ['1', 45000],
//             ]],
//             ['C005', 'Epsilon Retail', '2024-01-01', [
//                 '2025-01' => ['1', 20000], '2025-02' => ['1', 20000],
//                 '2025-03' => ['1', 20000], '2025-04' => ['1', 20000],
//                 '2025-05' => ['1', 20000],
//             ]],
//             ['C006', 'Zeta Logistics', '2024-03-01', [
//                 '2025-01' => ['1', 30000], '2025-02' => ['2', 30000],
//                 '2025-03' => ['3', 30000], '2025-04' => ['3', 25000],
//                 '2025-05' => ['3', 20000],
//             ]],
//         ];

//         foreach ($contracts as [$contractId, $customerName, $createDate, $months]) {
//             foreach ($months as $period => [$stage, $balance]) {
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
//                         'create_date' => $createDate,
//                         'due_date' => Carbon::parse($createDate)->addYears(2)->toDateString(),
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

//         $this->command?->info('Seeded portfolio #' . $portfolioId . ' with ' . DB::table('loan_books')->where('loan_portfolio_id', $portfolioId)->count() . ' loan_book rows.');
//     }
// }
