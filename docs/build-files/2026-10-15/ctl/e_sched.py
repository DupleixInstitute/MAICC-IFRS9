from ed import edit
p='app/Services/Eir/ScheduleWorkflowService.php'
edit(p, r"""    public function generate(ContractEir $contract): array
    {""", r"""    /**
     * @param ?int $userId the person who generates the draft, recorded so that a
     *                     second person approves it (schedule_approval_control, O18);
     *                     null for a system build (the bootstrap, a sweep)
     */
    public function generate(ContractEir $contract, ?int $userId=null): array
    {""")
edit(p, r"""        $rate=(float)$contract->contractual_rate;
        if ($rate>1) $rate/=100;
""", r"""        // contract_eir.contractual_rate is a fraction (0.3210 for 32.10 percent),
        // as the column is defined and every loader writes it. A value above 1 is
        // a loader fault, not a percent to be guessed back (audit L5): refused.
        $rate=self::rateAsFraction($contract->contractual_rate);
""")
edit(p, r"""            $contract->update(['schedule_source'=>'GENERATED','schedule_approval_status'=>'DRAFT',
                'schedule_generated_at'=>$now,'schedule_approved_at'=>null,'schedule_approved_by'=>null,""",
r"""            $contract->update(['schedule_source'=>'GENERATED','schedule_approval_status'=>'DRAFT',
                'schedule_generated_at'=>$now,'schedule_approved_at'=>null,'schedule_approved_by'=>null,
                'schedule_prepared_by'=>$userId,'schedule_approver_label'=>null,""")
edit(p, r"""    public function generateEligible(): array
    {
        $generated=0; $skipped=[];
        ContractEir::orderBy('contract_id')->each(function($contract) use (&$generated,&$skipped) {
            if ($contract->schedule_approval_status==='APPROVED') return;
            try { $this->generate($contract); $generated++; } catch (\Throwable $e) { $skipped[$contract->contract_id]=$e->getMessage(); }
        });""", r"""    public function generateEligible(?int $userId=null): array
    {
        $generated=0; $skipped=[];
        ContractEir::orderBy('contract_id')->each(function($contract) use (&$generated,&$skipped,$userId) {
            if ($contract->schedule_approval_status==='APPROVED') return;
            try { $this->generate($contract,$userId); $generated++; } catch (\Throwable $e) { $skipped[$contract->contract_id]=$e->getMessage(); }
        });""")
edit(p, r"""    public function approve(ContractEir $contract, int $userId, ?string $notes=null): void
    {
        if (!DB::table('contract_cashflow_schedule')->where('contract_id',$contract->contract_id)->where('schedule_version',1)->exists())
            throw new InvalidArgumentException('Generate or import an original schedule before approval.');
        if (!in_array($contract->schedule_approval_status,['DRAFT','PENDING_REVIEW'],true))
            throw new InvalidArgumentException('Only a draft schedule can be approved.');""", r"""    /**
     * Approve a version 1 schedule. The governed control schedule_approval_control
     * (O18) is read at the date of approval: under "Second person must approve"
     * the approver must be named and must not be the person who prepared the
     * draft (system audit H9). The bootstrap may approve under its own label,
     * which is recorded on the contract and is never a MAIIC approval.
     */
    public function approve(ContractEir $contract, ?int $userId, ?string $notes=null, ?string $label=null): void
    {
        if (!DB::table('contract_cashflow_schedule')->where('contract_id',$contract->contract_id)->where('schedule_version',1)->exists())
            throw new InvalidArgumentException('Generate or import an original schedule before approval.');
        if (!in_array($contract->schedule_approval_status,['DRAFT','PENDING_REVIEW'],true))
            throw new InvalidArgumentException('Only a draft schedule can be approved.');
        $bootstrap=$label===\App\Services\Ebanker\LoanBookBuildService::BOOTSTRAP_LABEL;
        if ($label!==null && !$bootstrap)
            throw new InvalidArgumentException('Only the bootstrap label may stand in for a named approver.');
        if (!$bootstrap) $this->assertSecondPerson($contract,$userId);""")
edit(p, r"""        $contract->update(['schedule_approval_status'=>'APPROVED','schedule_comparison_status'=>$comparison['status'],
            'schedule_review_notes'=>$notes,'schedule_approved_at'=>now(),'schedule_approved_by'=>$userId]);
        AuditLoggerService::log('EIR Schedule Approved', ContractEir::class, $contract->id, ['new_values'=>[
            'contract_id'=>$contract->contract_id,'comparison_status'=>$comparison['status'],'notes'=>$notes,
        ],'meta'=>['approved_by'=>$userId]]);
    }""", r"""        $contract->update(['schedule_approval_status'=>'APPROVED','schedule_comparison_status'=>$comparison['status'],
            'schedule_review_notes'=>$notes,'schedule_approved_at'=>now(),'schedule_approved_by'=>$userId,
            'schedule_approver_label'=>$bootstrap ? $label : null]);
        AuditLoggerService::log('EIR Schedule Approved', ContractEir::class, $contract->id, ['new_values'=>[
            'contract_id'=>$contract->contract_id,'comparison_status'=>$comparison['status'],'notes'=>$notes,
        ],'meta'=>['approved_by'=>$userId,'prepared_by'=>$contract->schedule_prepared_by,'label'=>$bootstrap ? $label : null]]);
    }

    /** The governed maker-checker on a schedule (O18), read at the date of approval. */
    private function assertSecondPerson(ContractEir $contract, ?int $userId): void
    {
        if ($userId===null)
            throw new InvalidArgumentException('A schedule is approved by a named person.');
        // no value in code: with no approved setting the approval stops (GovernanceSettingMissingException)
        $control=app(GovernanceService::class)->get('schedule_approval_control');
        if ($control==='One person may draft and approve') return;
        if ($control!=='Second person must approve')
            throw new InvalidArgumentException("The schedule approval control '{$control}' is not one the engine knows.");
        if ($contract->schedule_prepared_by!==null && (int)$contract->schedule_prepared_by===$userId)
            throw new InvalidArgumentException('Maker-checker: the person who prepared the schedule cannot approve it (schedule_approval_control: Second person must approve).');
    }

    /**
     * The contractual rate as the fraction the column holds. A missing rate, a
     * negative one, or one above 1 (a percent written into a fraction column)
     * is refused with the reason, never rescaled.
     */
    public static function rateAsFraction($rate): float
    {
        if ($rate===null || $rate==='' || !is_numeric($rate))
            throw new InvalidArgumentException('The contractual rate is missing.');
        $rate=(float)$rate;
        if ($rate<0)
            throw new InvalidArgumentException('The contractual rate is negative.');
        if ($rate>1)
            throw new InvalidArgumentException("The contractual rate {$rate} is above 1: contract_eir.contractual_rate holds a fraction (0.3210 for 32.10 percent), so this is a loader fault; correct the contract input.");
        return $rate;
    }""")
# readiness: the unit stated
edit(p, r"""        if ($contract->contractual_rate===null || (float)$contract->contractual_rate<0) $issues[]='Contractual rate is missing or invalid';""",
r"""        if ($contract->contractual_rate===null || (float)$contract->contractual_rate<0) $issues[]='Contractual rate is missing or invalid';
        elseif ((float)$contract->contractual_rate>1) $issues[]='Contractual rate is above 1: the column holds a fraction, so the contract input is wrong';""")
