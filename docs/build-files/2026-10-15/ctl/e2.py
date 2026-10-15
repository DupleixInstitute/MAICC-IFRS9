from ed import edit
p='app/Http/Controllers/EirScheduleController.php'
edit(p, r"""    public function generateAll(ScheduleWorkflowService $workflow)
    {
        $r=$workflow->generateEligible();""", r"""    public function generateAll(Request $request, ScheduleWorkflowService $workflow)
    {
        // the person who generates is recorded, so a second person approves (O18)
        $r=$workflow->generateEligible((int)$request->user()->id);""")
edit(p, r"""    public function generate(ContractEir $contractEir, ScheduleWorkflowService $workflow)
    {
        try { $r=$workflow->generate($contractEir); return""", r"""    public function generate(Request $request, ContractEir $contractEir, ScheduleWorkflowService $workflow)
    {
        try { $r=$workflow->generate($contractEir,(int)$request->user()->id); return""")
p='app/Console/Commands/GenerateContractSchedules.php'
edit(p, r"""    protected $signature = 'eir:generate-schedules {--dry-run : Assess readiness without writing}';""",
r"""    protected $signature = 'eir:generate-schedules {--dry-run : Assess readiness without writing} {--user= : The person generating, recorded so that a second person approves}';""")
edit(p, r"""$workflow->dryRun() : $workflow->generateEligible();""", r"""$workflow->dryRun() : $workflow->generateEligible($this->option('user') !== null ? (int) $this->option('user') : null);""")
p='app/Models/ContractEir.php'
edit(p, r"""        'schedule_approved_at',
        'schedule_approved_by',
""", r"""        'schedule_approved_at',
        'schedule_approved_by',
        // maker-checker on the schedule (O18) and the bootstrap label; the over-sanction flag (spec 7.3)
        'schedule_prepared_by',
        'schedule_approver_label',
        'over_sanction_amount',
""")
