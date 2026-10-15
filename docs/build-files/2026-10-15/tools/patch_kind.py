import re
P = 'C:/xampp/htdocs/MAICC-IFRS9/app/'

def kind_all(p, kind):
    s = open(P + p, encoding='utf-8').read()
    n = 0
    # add the kind to every Import::create([ ... 'status' => 'pending', of this file
    def add(m):
        nonlocal n
        n += 1
        return m.group(0) + "\n" + m.group(1) + "'kind' => '" + kind + "',"
    s2 = re.sub(r"Import::create\(\[\n(\s+)", lambda m: "Import::create([\n" + m.group(1) + "'kind' => '" + kind + "',\n" + m.group(1), s)
    n = s2.count("'kind' => '" + kind + "'") - s.count("'kind' => '" + kind + "'")
    open(P + p, 'w', encoding='utf-8').write(s2)
    print(p, n)

kind_all('Http/Controllers/ClientImportController.php', 'clients')
kind_all('Http/Controllers/ClientsController.php', 'clients')
kind_all('Http/Controllers/CollateralController.php', 'collateral')
kind_all('Http/Controllers/CreditLossDataController.php', 'credit_loss')
kind_all('Http/Controllers/EirIntakeController.php', 'eir_intake')
kind_all('Http/Controllers/LoanBookController.php', 'loan_book')

def patch(p, pairs):
    s = open(P + p, encoding='utf-8').read()
    for a, b in pairs:
        assert a in s, (p, a[:60])
        s = s.replace(a, b, 1)
    open(P + p, 'w', encoding='utf-8').write(s)

cols = "->get(['id', 'name', 'status', 'records', 'failed_records', 'failed_file_path', 'created_at'])"
patch('Http/Controllers/ClientsController.php', [("'recentImports'     => Import::orderByDesc('id')->limit(15)" + cols, "'recentImports'     => Import::where('kind', 'clients')->orderByDesc('id')->limit(15)" + cols)])
patch('Http/Controllers/CollateralController.php', [("'recentImports' => Import::orderByDesc('id')->limit(15)" + cols, "'recentImports' => Import::where('kind', 'collateral')->orderByDesc('id')->limit(15)" + cols)])
patch('Http/Controllers/LoanBookController.php', [("'recentImports' => Import::orderByDesc('id')->limit(15)" + cols, "'recentImports' => Import::where('kind', 'loan_book')->orderByDesc('id')->limit(15)" + cols)])
patch('Models/Import.php', [("    protected $fillable = [\n", "    protected $fillable = [\n        'kind',\n")])
print('ok')
