P = 'C:/xampp/htdocs/MAICC-IFRS9/app/Http/Controllers/Reports/Ifrs9ReportsController.php'
s = open(P, encoding='utf-8').read()

def rep(a, b):
    global s
    assert a in s, a[:70]
    s = s.replace(a, b, 1)

rep("""        $raw = DB::table('loan_books')->where('reporting_period', $period)
            ->selectRaw($this->rbmClassCase() . " rbm, COUNT(*) n, SUM(" . self::EAD_SQL . ") ead,
                SUM(COALESCE(ecl_value,0)) ecl")""", """        // the directive's minimum rates apply to the gross balance (carrying amount), as on the RBM Return;
        // undrawn commitments are not a classified balance
        $raw = DB::table('loan_books')->where('reporting_period', $period)
            ->selectRaw($this->rbmClassCase() . " rbm, COUNT(*) n, SUM(COALESCE(carrying_amount,0)) ead,
                SUM(COALESCE(ecl_value,0)) ecl")""")
rep("""the same bands and rates as the RBM Return',""", """the same bands, rates and gross-balance basis as the RBM Return',""")
rep("""                'columns' => ['RBM Class', 'Loans', 'Exposure (EAD)', 'Minimum Rate', 'RBM Provision', 'IFRS 9 ECL', 'ECL − RBM'],""",
"""                'columns' => ['RBM Class', 'Loans', 'Gross balance', 'Minimum Rate', 'RBM Provision', 'IFRS 9 ECL', 'ECL − RBM'],""")
open(P, 'w', encoding='utf-8').write(s)
print('ok')
