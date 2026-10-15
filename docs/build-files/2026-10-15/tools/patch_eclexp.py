P = 'C:/xampp/htdocs/MAICC-IFRS9/app/'

def patch(p, pairs):
    s = open(P + p, encoding='utf-8').read()
    for a, b in pairs:
        assert a in s, (p, a[:60])
        s = s.replace(a, b, 1)
    open(P + p, 'w', encoding='utf-8').write(s)

# the PD a loan's ECL used: after FLI when the route ran, else before FLI (pd_value is the legacy column, empty on the engine's months)
patch('Services/Reports/ECLExportService.php', [("""        $select = array_map(function ($column) {
            return $column === 'principal_balance'
                ? DB::raw('carrying_amount as principal_balance')
                : $column;
        }, $selected);""", """        $select = array_map(function ($column) {
            return match ($column) {
                'principal_balance' => DB::raw('carrying_amount as principal_balance'),
                // the PD the ECL used: after FLI where the route ran, else before FLI; pd_value is the legacy column
                'pd_value' => DB::raw('COALESCE(pd_post_fli, pd_prefli, pd_value) as pd_value'),
                default => $column,
            };
        }, $selected);""")])
patch('Http/Controllers/ExpectedCreditLossController.php', [
("""                'collateral_id',
                'allocated_gross_value',
                'allocated_discounted_value',
                'commitments',""", """                'collateral_id',
                'commitments',"""),
("""                        AVG(pd_value_used) as avg_pd,
                        AVG(lgd_value_used) as avg_lgd,""", """                        AVG(COALESCE(pd_post_fli, pd_prefli, pd_value)) as avg_pd,
                        AVG(lgd_value) as avg_lgd,"""),
("""                $data = DB::table('loan_books')
                    ->select($selected)
                    ->where('loan_portfolio_id', $portfolioId)
                    ->where('reporting_period', $period)
                    ->get();""", """                $data = DB::table('loan_books')
                    ->select(array_map(fn ($c) => $c === 'pd_value' ? DB::raw('COALESCE(pd_post_fli, pd_prefli, pd_value) as pd_value') : $c, $selected))
                    ->where('loan_portfolio_id', $portfolioId)
                    ->where('reporting_period', $period)
                    ->get();"""),
])
print('ok')
