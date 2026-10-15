import os
os.chdir('C:/xampp/htdocs/MAICC-IFRS9')

def sub(path, pairs):
    s = open(path, encoding='utf-8').read()
    for a, b in pairs:
        assert a in s, (path, a[:80])
        s = s.replace(a, b, 1)
    open(path, 'w', encoding='utf-8', newline='').write(s)

# ---------------- ECL export ----------------
sub('app/Services/Reports/ECLExportService.php', [
("""use Illuminate\\Support\\Facades\\DB;
use Symfony\\Component\\HttpFoundation\\BinaryFileResponse;

class ECLExportService
{""",
"""use App\\Services\\Ecl\\EclExposure;
use Illuminate\\Support\\Facades\\DB;
use Symfony\\Component\\HttpFoundation\\BinaryFileResponse;

/**
 * The ECL export (Reports hub, Exports). Every figure is on the reports'
 * basis (documentation audit of 14 October 2026; it read the undiscounted
 * ecl_value and the calculated stage until then): the loss allowance is the
 * booked allowance (BookedEcl: discounted at the EIR, the Mega Farm programme
 * measure, or the undiscounted ECL where no discounted figure exists, named by
 * its basis), with the undiscounted ECL beside it; the stage is the one the
 * ECL was provided on, after the qualitative triggers
 * (EclBuildUpService::STAGE_SQL); the EAD is the engine's (EclExposure).
 */
class ECLExportService
{"""),
("""        // By the stage the ECL was provided on (after the qualitative
        // triggers), with the EAD the engine measured (carrying amount plus
        // the drawn share of undrawn commitments) and the PD after the
        // forward-looking adjustment, so the totals tie to the saved ECL run.
        $ead = 'COALESCE(carrying_amount,0) + COALESCE(commitments,0) * COALESCE(facility_utilisation_rate,1)';
        $data = DB::table('loan_books')
            ->selectRaw("
                ifrs9stage_post_qualitative as stage,
                COUNT(*) as loans,
                SUM(COALESCE(carrying_amount,0)) as gross,
                SUM({$ead}) as total_ead,
                SUM(COALESCE(pd_post_fli, pd_prefli, 0) * ({$ead})) as pd_x_ead,
                SUM(COALESCE(lgd_value, 0) * ({$ead})) as lgd_x_ead,
                SUM(COALESCE(ecl_value,0)) as total_ecl
            ")
            ->where('loan_portfolio_id', $portfolioId)
            ->where('reporting_period', $reportingPeriod)
            ->groupBy('ifrs9stage_post_qualitative')
            ->orderBy('ifrs9stage_post_qualitative')
            ->get();

        $row = fn ($stage, $loans, $gross, $ead, $pdx, $lgdx, $ecl) => [
            'Stage' => $stage,
            'Loans' => (int) $loans,
            'Gross carrying amount' => round((float) $gross, 2),
            'EAD' => round((float) $ead, 2),
            'PD (EAD-weighted)' => $ead != 0 ? round($pdx / $ead, 6) : null,
            'LGD (EAD-weighted)' => $ead != 0 ? round($lgdx / $ead, 6) : null,
            'ECL' => round((float) $ecl, 2),
            'Coverage of EAD' => $ead != 0 ? round($ecl / $ead, 6) : null,
        ];

        $rows = $data->map(fn ($r) => $row($r->stage, $r->loans, $r->gross, (float) $r->total_ead, (float) $r->pd_x_ead, (float) $r->lgd_x_ead, (float) $r->total_ecl))->values();
        if ($rows->isNotEmpty()) {
            $rows->push($row('Total', $data->sum('loans'), $data->sum('gross'), (float) $data->sum('total_ead'),
                (float) $data->sum('pd_x_ead'), (float) $data->sum('lgd_x_ead'), (float) $data->sum('total_ecl')));
        }

        return $rows->toArray();""",
"""        // By the stage the ECL was provided on (after the qualitative
        // triggers), with the EAD the engine measured (EclExposure: floored at
        // nil, nil for a loan written off to nil) and the PD after the
        // forward-looking adjustment; the allowance is the booked one, with the
        // undiscounted ECL beside it and the loans not on the booked basis counted.
        $ead = EclExposure::sql();
        $stage = EclBuildUpService::STAGE_SQL;
        $data = DB::table('loan_books')
            ->selectRaw("
                {$stage} as stage,
                COUNT(*) as loans,
                SUM(COALESCE(carrying_amount,0)) as gross,
                SUM({$ead}) as total_ead,
                SUM(COALESCE(pd_post_fli, pd_prefli, 0) * ({$ead})) as pd_x_ead,
                SUM(COALESCE(lgd_value, 0) * ({$ead})) as lgd_x_ead,
                SUM(" . BookedEcl::sql() . ") as total_ecl,
                SUM(COALESCE(ecl_value,0)) as total_ecl_undiscounted,
                SUM(CASE WHEN " . BookedEcl::basisSql() . " = 'undiscounted' THEN 1 ELSE 0 END) as not_booked
            ")
            ->where('loan_portfolio_id', $portfolioId)
            ->where('reporting_period', $reportingPeriod)
            ->groupByRaw($stage)
            ->orderByRaw($stage)
            ->get();

        $row = fn ($stage, $loans, $gross, $ead, $pdx, $lgdx, $ecl, $undiscounted, $notBooked) => [
            'Stage' => $stage,
            'Loans' => (int) $loans,
            'Gross carrying amount' => round((float) $gross, 2),
            'EAD' => round((float) $ead, 2),
            'PD (EAD-weighted)' => $ead != 0 ? round($pdx / $ead, 6) : null,
            'LGD (EAD-weighted)' => $ead != 0 ? round($lgdx / $ead, 6) : null,
            'Loss allowance (booked)' => round((float) $ecl, 2),
            'ECL undiscounted' => round((float) $undiscounted, 2),
            'Loans without a discounted allowance' => (int) $notBooked,
            'Coverage of EAD' => $ead != 0 ? round($ecl / $ead, 6) : null,
        ];

        $rows = $data->map(fn ($r) => $row($r->stage, $r->loans, $r->gross, (float) $r->total_ead, (float) $r->pd_x_ead, (float) $r->lgd_x_ead, (float) $r->total_ecl, (float) $r->total_ecl_undiscounted, $r->not_booked))->values();
        if ($rows->isNotEmpty()) {
            $rows->push($row('Total', $data->sum('loans'), $data->sum('gross'), (float) $data->sum('total_ead'),
                (float) $data->sum('pd_x_ead'), (float) $data->sum('lgd_x_ead'), (float) $data->sum('total_ecl'), (float) $data->sum('total_ecl_undiscounted'), $data->sum('not_booked')));
        }

        return $rows->toArray();"""),
("""            'pd_value',
            'lgd_value',
            'ecl_value',
            'calculated_ifrs9_stage',
            'reporting_period',""",
"""            'pd_value',
            'lgd_value',
            'ecl_booked',
            'ecl_value',
            'ecl_basis',
            'stage',
            'reporting_period',"""),
("""                'pd_value' => DB::raw('COALESCE(pd_post_fli, pd_prefli, pd_value) as pd_value'),
                default => $column,""",
"""                'pd_value' => DB::raw('COALESCE(pd_post_fli, pd_prefli, pd_value) as pd_value'),
                // the booked allowance, its basis, and the stage the ECL was provided on
                'ecl_booked' => DB::raw(BookedEcl::sql() . ' as ecl_booked'),
                'ecl_basis' => DB::raw(BookedEcl::basisSql() . ' as ecl_basis'),
                'stage' => DB::raw(EclBuildUpService::STAGE_SQL . ' as stage'),
                default => $column,"""),
("""        $selected = !empty($columns) ? array_unique(array_merge(['id'], $columns)) : $exportable;""",
"""        // only the columns offered; a name outside the list is never passed to the query
        $columns = array_values(array_intersect($columns, $exportable));
        $selected = !empty($columns) ? array_unique(array_merge(['id'], $columns)) : $exportable;"""),
("""            ->orderBy('calculated_ifrs9_stage')
            ->orderBy('carrying_amount', 'desc')""",
"""            ->orderByRaw(EclBuildUpService::STAGE_SQL)
            ->orderBy('carrying_amount', 'desc')"""),
("""            'lgd_value' => 'LGD',
            'ecl_value' => 'ECL Value',
            'calculated_ifrs9_stage' => 'IFRS9 Stage',""",
"""            'lgd_value' => 'LGD',
            'ecl_booked' => 'Loss allowance (booked)',
            'ecl_value' => 'ECL undiscounted',
            'ecl_basis' => 'Allowance basis',
            'stage' => 'IFRS 9 stage (after qualitative triggers)',"""),
])

# ---------------- Loan book export ----------------
sub('app/Services/Reports/LoanBookExportService.php', [
("""use Illuminate\\Support\\Facades\\DB;
use Symfony\\Component\\HttpFoundation\\StreamedResponse;

class LoanBookExportService
{""",
"""use Illuminate\\Support\\Facades\\DB;
use Symfony\\Component\\HttpFoundation\\StreamedResponse;

/**
 * The loan book export (Reports hub, Exports). By the stage the ECL was
 * provided on, after the qualitative triggers (EclBuildUpService::STAGE_SQL),
 * with the booked loss allowance (BookedEcl) and the undiscounted ECL beside
 * it (documentation audit of 14 October 2026; it split by the calculated
 * stage and carried no allowance until then).
 */
class LoanBookExportService
{"""),
("""    private static function generateSummaryData(string $startPeriod, string $endPeriod, ?int $portfolioId): array
    {
        $query = DB::table('loan_books as lb')
            ->select([
                'lb.reporting_year',
                'lb.reporting_month',
                DB::raw('CONCAT(lb.reporting_year, "-", LPAD(lb.reporting_month, 2, "0")) as reporting_period'),
                DB::raw('SUM(CASE WHEN lb.calculated_ifrs9_stage = 1 THEN lb.carrying_amount ELSE 0 END) as stage_1_balance'),
                DB::raw('SUM(CASE WHEN lb.calculated_ifrs9_stage = 2 THEN lb.carrying_amount ELSE 0 END) as stage_2_balance'),
                DB::raw('SUM(CASE WHEN lb.calculated_ifrs9_stage = 3 THEN lb.carrying_amount ELSE 0 END) as stage_3_balance'),
                DB::raw('SUM(lb.carrying_amount) as total_principal_balance'),
                DB::raw('COUNT(CASE WHEN lb.calculated_ifrs9_stage = 1 THEN 1 END) as stage_1_count'),
                DB::raw('COUNT(CASE WHEN lb.calculated_ifrs9_stage = 2 THEN 1 END) as stage_2_count'),
                DB::raw('COUNT(CASE WHEN lb.calculated_ifrs9_stage = 3 THEN 1 END) as stage_3_count'),
                DB::raw('COUNT(*) as total_count'),
            ])
            ->whereRaw("CONCAT(lb.reporting_year, '-', LPAD(lb.reporting_month, 2, '0')) >= ?", [$startPeriod])
            ->whereRaw("CONCAT(lb.reporting_year, '-', LPAD(lb.reporting_month, 2, '0')) <= ?", [$endPeriod])
            ->groupBy('lb.reporting_year', 'lb.reporting_month')
            ->orderBy('lb.reporting_year', 'desc')
            ->orderBy('lb.reporting_month', 'desc');

        if ($portfolioId) {
            $query->where('lb.loan_portfolio_id', $portfolioId);
        }

        return $query->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();
    }

    private static function generateDetailedData(string $startPeriod, string $endPeriod, ?int $portfolioId): array
    {
        $query = DB::table('loan_books as lb')
            ->select([
                'lb.contract_id',
                DB::raw('lb.carrying_amount as principal_balance'),
                'lb.calculated_ifrs9_stage',
                DB::raw('CONCAT(lb.reporting_year, "-", LPAD(lb.reporting_month, 2, "0")) as reporting_period'),
            ])
            ->whereRaw("CONCAT(lb.reporting_year, '-', LPAD(lb.reporting_month, 2, '0')) >= ?", [$startPeriod])
            ->whereRaw("CONCAT(lb.reporting_year, '-', LPAD(lb.reporting_month, 2, '0')) <= ?", [$endPeriod])
            ->orderBy('lb.reporting_year', 'desc')
            ->orderBy('lb.reporting_month', 'desc')
            ->orderBy('lb.contract_id');""",
"""    /** The stage the ECL was provided on, for the aliased table. */
    private static function stageSql(): string
    {
        return 'COALESCE(lb.ifrs9stage_post_qualitative, lb.calculated_ifrs9_stage, lb.ifrs9stage_pre_qualitative)';
    }

    private static function generateSummaryData(string $startPeriod, string $endPeriod, ?int $portfolioId): array
    {
        $stage = self::stageSql();
        $booked = BookedEcl::sql('lb.');
        $query = DB::table('loan_books as lb')
            ->select([
                DB::raw('SUBSTR(lb.reporting_period, 1, 7) as reporting_period'),
                DB::raw("SUM(CASE WHEN {$stage} = '1' THEN lb.carrying_amount ELSE 0 END) as stage_1_balance"),
                DB::raw("SUM(CASE WHEN {$stage} = '2' THEN lb.carrying_amount ELSE 0 END) as stage_2_balance"),
                DB::raw("SUM(CASE WHEN {$stage} = '3' THEN lb.carrying_amount ELSE 0 END) as stage_3_balance"),
                DB::raw('SUM(lb.carrying_amount) as total_principal_balance'),
                DB::raw("SUM(CASE WHEN {$stage} = '1' THEN 1 ELSE 0 END) as stage_1_count"),
                DB::raw("SUM(CASE WHEN {$stage} = '2' THEN 1 ELSE 0 END) as stage_2_count"),
                DB::raw("SUM(CASE WHEN {$stage} = '3' THEN 1 ELSE 0 END) as stage_3_count"),
                DB::raw('COUNT(*) as total_count'),
                DB::raw("SUM({$booked}) as allowance_booked"),
                DB::raw('SUM(COALESCE(lb.ecl_value, 0)) as ecl_undiscounted'),
                DB::raw('SUM(CASE WHEN ' . BookedEcl::basisSql('lb.') . " = 'undiscounted' THEN 1 ELSE 0 END) as not_booked_count"),
            ])
            ->whereRaw('SUBSTR(lb.reporting_period, 1, 7) >= ?', [$startPeriod])
            ->whereRaw('SUBSTR(lb.reporting_period, 1, 7) <= ?', [$endPeriod])
            ->groupByRaw('SUBSTR(lb.reporting_period, 1, 7)')
            ->orderByRaw('SUBSTR(lb.reporting_period, 1, 7) desc');

        if ($portfolioId) {
            $query->where('lb.loan_portfolio_id', $portfolioId);
        }

        return $query->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();
    }

    private static function generateDetailedData(string $startPeriod, string $endPeriod, ?int $portfolioId): array
    {
        $query = DB::table('loan_books as lb')
            ->select([
                'lb.contract_id',
                DB::raw('lb.carrying_amount as principal_balance'),
                DB::raw(self::stageSql() . ' as stage'),
                DB::raw(BookedEcl::sql('lb.') . ' as allowance_booked'),
                DB::raw('lb.ecl_value as ecl_undiscounted'),
                DB::raw(BookedEcl::basisSql('lb.') . ' as allowance_basis'),
                DB::raw('SUBSTR(lb.reporting_period, 1, 7) as reporting_period'),
            ])
            ->whereRaw('SUBSTR(lb.reporting_period, 1, 7) >= ?', [$startPeriod])
            ->whereRaw('SUBSTR(lb.reporting_period, 1, 7) <= ?', [$endPeriod])
            ->orderByRaw('SUBSTR(lb.reporting_period, 1, 7) desc')
            ->orderBy('lb.contract_id');"""),
("""                fputcsv($handle, [
                    'Reporting Period',
                    'Stage 1',
                    'Stage 2',
                    'Stage 3',
                    'Total Carrying Amount',
                    'Stage 1 Count',
                    'Stage 2 Count',
                    'Stage 3 Count',
                    'Total Count',
                ]);""",
"""                fputcsv($handle, ['Stage: after the qualitative triggers. Loss allowance: the booked allowance (discounted at the EIR; the Mega Farm programme measure; the undiscounted ECL where no discounted figure exists, counted in the last column).']);
                fputcsv($handle, [
                    'Reporting Period',
                    'Stage 1',
                    'Stage 2',
                    'Stage 3',
                    'Total Carrying Amount',
                    'Stage 1 Count',
                    'Stage 2 Count',
                    'Stage 3 Count',
                    'Total Count',
                    'Loss Allowance (booked)',
                    'ECL Undiscounted',
                    'Loans Without a Discounted Allowance',
                ]);"""),
("""                        $row['stage_3_count'],
                        $row['total_count'],
                    ]);""",
"""                        $row['stage_3_count'],
                        $row['total_count'],
                        number_format((float) $row['allowance_booked'], 2, '.', ','),
                        number_format((float) $row['ecl_undiscounted'], 2, '.', ','),
                        $row['not_booked_count'],
                    ]);"""),
("""                fputcsv($handle, [
                    'Contract ID',
                    'Carrying Amount',
                    'IFRS9 Stage',
                    'Reporting Period',
                ]);

                foreach ($data as $row) {
                    fputcsv($handle, [
                        $row['contract_id'],
                        number_format((float) $row['principal_balance'], 2, '.', ','),
                        $row['calculated_ifrs9_stage'],
                        $row['reporting_period'],
                    ]);
                }""",
"""                fputcsv($handle, [
                    'Contract ID',
                    'Carrying Amount',
                    'IFRS 9 Stage (after qualitative triggers)',
                    'Loss Allowance (booked)',
                    'ECL Undiscounted',
                    'Allowance Basis',
                    'Reporting Period',
                ]);

                foreach ($data as $row) {
                    fputcsv($handle, [
                        $row['contract_id'],
                        number_format((float) $row['principal_balance'], 2, '.', ','),
                        $row['stage'],
                        number_format((float) $row['allowance_booked'], 2, '.', ','),
                        $row['ecl_undiscounted'] === null ? '' : number_format((float) $row['ecl_undiscounted'], 2, '.', ','),
                        BookedEcl::LABELS[$row['allowance_basis']] ?? $row['allowance_basis'],
                        $row['reporting_period'],
                    ]);
                }"""),
])
print('done')
