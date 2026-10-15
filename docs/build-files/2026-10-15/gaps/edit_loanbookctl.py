import os
os.chdir('C:/xampp/htdocs/MAICC-IFRS9')
p = 'app/Http/Controllers/LoanBookController.php'
s = open(p, encoding='utf-8').read()
# store(): unrouted; its legacy staging ladder and provision rates go with it
a = s.index('    public function store(Request $request)')
b = s.index('    public function summary(Request $request)')
s = s[:a] + s[b:]
a = s.index('    public static function calculateIfrs9Stage($record, $reportingPeriod)')
b = s.index('    public function createImport(): Response')
s = s[:a] + s[b:]
open(p, 'w', encoding='utf-8', newline='').write(s)
print('done')
