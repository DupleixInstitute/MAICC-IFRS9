import io, sys
p = sys.argv[1]
new_block = io.open(sys.argv[2], encoding='utf-8').read()
s = io.open(p, encoding='utf-8', newline='').read()
nl = '\r\n' if '\r\n' in s else '\n'


def rep(old, new):
    global s
    old = old.replace('\n', nl)
    new = new.replace('\n', nl)
    assert s.count(old) == 1, old[:80]
    s = s.replace(old, new)


rep("""use App\\Support\\Fli\\AsAtSeries;
use App\\Support\\Fli\\SeriesAligner;
""", """use App\\Support\\Fli\\AsAtSeries;
use App\\Support\\Fli\\GovernedValues;
use App\\Support\\Fli\\SeriesAligner;
""")

rep("""    private FliModelRegistry $registry;

    public function __construct(""", """    private FliModelRegistry $registry;

    /** @var array<string,RegressionEngine> one read-only engine per period, for the inputs-hash check */
    private array $engines = [];

    /** @var array<int,array> the scenario paths of a set, read once per instance */
    private array $pathMemo = [];

    public function __construct(""")

rep("""        if ($fit->verdict !== 'applied') {
            throw new RuntimeException("Fit {$fitId} was declined by the guardrail ({$fit->declined_reason}); it cannot be proposed.");
        }
        DB::table('fli_fits')""", """        if ($fit->verdict !== 'applied') {
            throw new RuntimeException("Fit {$fitId} was declined by the guardrail ({$fit->declined_reason}); it cannot be proposed.");
        }
        if ($fit->approval_status === 'APPROVED') {
            throw new RuntimeException("Fit {$fitId} is already approved; it cannot be proposed again.");
        }
        $this->assertApplicable($fitId, 'proposed');
        DB::table('fli_fits')""")

rep("""            throw new RuntimeException('Maker-checker: the approver must be a different person from the proposer.');
        }
        DB::transaction(function () use ($fit, $fitId, $approverId, $label) {""", """            throw new RuntimeException('Maker-checker: the approver must be a different person from the proposer.');
        }
        // the data may have moved since the proposal: checked again at approval
        $this->assertApplicable($fitId, 'approved');
        DB::transaction(function () use ($fit, $fitId, $approverId, $label) {""")

anchor = """    /**
     * The model in force for a period (FLI module D-F1, D-F2): the latest"""
rep(anchor, new_block + anchor)
io.open(p, 'w', encoding='utf-8', newline='').write(s)
print('ok')
