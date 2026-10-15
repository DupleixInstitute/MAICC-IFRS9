P = 'C:/xampp/htdocs/MAICC-IFRS9/app/Support/SectionTabCounts.php'
s = open(P, encoding='utf-8').read()
a = s.index('    private static function count(string $route): ?int')
b = s.rindex('}')
s = s[:a] + r'''    /** Counts worked out once per request: a chip is never older than the page it sits on. */
    private static array $memo = [];

    private static function count(string $route): ?int
    {
        if (array_key_exists($route, self::$memo)) {
            return self::$memo[$route];
        }
        // the views that list something other than a whole table
        $special = [
            'eir-feed.index?tab=periods' => fn () => Schema::hasTable('loan_books') ? DB::table('loan_books')->distinct()->count('reporting_period') : null,
            'eir-as-at.index' => fn () => Schema::hasTable('contract_eir') ? DB::table('contract_eir')->where('calculation_status', 'LOCKED')->count() : null,
        ];
        if (isset($special[$route])) {
            return self::$memo[$route] = $special[$route]();
        }
        if (! isset(self::TABLES[$route])) {
            return self::$memo[$route] = null;
        }
        $total = 0;
        foreach ((array) self::TABLES[$route] as $table) {
            if (! Schema::hasTable($table)) {
                return self::$memo[$route] = null;
            }
            $q = DB::table($table);
            if (Schema::hasColumn($table, 'deleted_at')) {
                $q->whereNull('deleted_at');
            }
            $total += $q->count();
        }

        return self::$memo[$route] = $total;
    }
''' + s[b:]
open(P, 'w', encoding='utf-8').write(s)
print('ok')
