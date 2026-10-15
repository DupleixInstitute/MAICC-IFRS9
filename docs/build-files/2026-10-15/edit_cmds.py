import io
B = chr(92)
CRLF = chr(13) + chr(10)
LF = chr(10)


def edit(p, pairs):
    s = io.open(p, encoding='utf-8', newline='').read()
    crlf = CRLF in s
    s = s.replace(CRLF, LF)
    for old, new in pairs:
        old = old.replace('~', B)
        new = new.replace('~', B)
        assert s.count(old) == 1, (p, old[:80], s.count(old))
        s = s.replace(old, new)
    io.open(p, 'w', encoding='utf-8', newline='').write(s.replace(LF, CRLF) if crlf else s)


root = r'c:\xampp\htdocs\MAICC-IFRS9\app\Console\Commands' + B
edit(root + 'RecalculateEcl.php', [
    ("use App~Services~Ecl~EclDiscountingService;\n", ""),
    ("public function handle(ExpectedCreditLossController $controller, EclDiscountingService $discounting): int", "public function handle(ExpectedCreditLossController $controller): int"),
    ("$controller->calculateECL($request, $discounting);", "$controller->calculateECL($request);"),
])
edit(root + 'SegmentEcl.php', [
    ("use App~Services~Ecl~EclDiscountingService;\n", "use App~Services~Ecl~EclExposure;\n"),
    ("    private const EAD = 'COALESCE(carrying_amount,0) + COALESCE(commitments,0) * COALESCE(facility_utilisation_rate,1)';\n\n", ""),
    ("public function handle(ExpectedCreditLossController $controller, EclDiscountingService $discounting): int", "public function handle(ExpectedCreditLossController $controller): int"),
    ("DB::transaction(function () use ($controller, $discounting, $period, $pid, $pdCol, $lgdCol) {", "DB::transaction(function () use ($controller, $period, $pid, $pdCol, $lgdCol) {"),
    ("                        ]), $discounting);", "                        ]));"),
    ("->whereRaw('(' . self::EAD . ') <> 0');", "->whereRaw(EclExposure::sql() . ' > 0'); // the loans the engine measures: a credit balance and a loan written off to nil carry none"),
])
print('ok')
