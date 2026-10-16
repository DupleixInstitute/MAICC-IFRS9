<?php
namespace App\Services\Eir;

use App\Models\ContractEir;
use App\Models\Scheme;
use App\Services\AuditLoggerService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class ScheduleWorkflowService
{
    public function __construct(private readonly ScheduleGeneratorService $generator) {}

    public function readiness(ContractEir $contract): array
    {
        $issues=[];
        if (!$contract->isInEirScope()) $issues[]='Equity-excluded instrument';
        if ($contract->locked_at) $issues[]='EIR is already locked';
        if (!$contract->origination_date) $issues[]='Origination date is missing';
        if (!$contract->maturity_date) $issues[]='Maturity date is missing';
        if ((float)$contract->drawn_amount<=0) $issues[]='Drawn amount is not positive';
        if ($contract->contractual_rate===null || (float)$contract->contractual_rate<0) $issues[]='Contractual rate is missing or invalid';
        elseif ((float)$contract->contractual_rate>1) $issues[]='Contractual rate is above 1: the column holds a fraction, so the contract input is wrong';
        if (!in_array((int)$contract->payments_per_year,[1,2,4,6,12],true)) $issues[]='Payment frequency is invalid';
        if ($contract->frequency_source!=='STATED') $issues[]='Payment frequency was not stated by the source';
        if ($contract->origination_date && $contract->maturity_date && $contract->maturity_date->lte($contract->origination_date)) $issues[]='Maturity must be after origination';
        // The shapes the generator has to be told, so the dry run reports them
        // contract by contract instead of one refusal at a time (spec 7.1).
        $scheme=$this->scheme($contract);
        if ((int)$contract->moratorium_months>0 && !$contract->moratorium_type && !($scheme['default_moratorium_type']??null))
            $issues[]='A moratorium is stated but its type is not, on the contract or on its scheme. E-Banker offers "Principle Only" and "Both (Interest + Principle)" and the two give different cash flows';
        if (strtoupper((string)($contract->emi_calc_type ?: $scheme['emi_calc_type'] ?? ''))===ScheduleGeneratorService::EMI_FLEXIBLE)
            $issues[]='EMI calculation type F (flexible) is not built; no example of the instalment pattern it produces exists';
        if ($this->partlyDrawn($contract)) {
            if (!$this->stated($contract->interest_calc_base) && !$this->stated($scheme['interest_calc_base']??null))
                $issues[]='The facility is drawn in part and neither the contract nor its scheme says whether interest is charged on the approved amount or on the balance (Loan Interest Cal. Base On)';
            if (!$this->stated($contract->installment_based_on) && !$this->stated($scheme['installment_based_on']??null))
                $issues[]='The facility is drawn in part and neither the contract nor its scheme says whether the instalment is sized on the sanctioned amount or on the amount disbursed (Installment Based On)';
        }
        return ['ready'=>$issues===[],'issues'=>$issues];
    }

    public function dryRun(): array
    {
        $eligible=0; $blocked=[];
        ContractEir::orderBy('contract_id')->each(function($contract) use (&$eligible,&$blocked) {
            $r=$this->readiness($contract); if ($r['ready']) $eligible++; else $blocked[$contract->contract_id]=$r['issues'];
        });
        return ['contracts'=>ContractEir::count(),'eligible'=>$eligible,'blocked'=>count($blocked),'exceptions'=>$blocked];
    }

    /**
     * @param ?int $userId the person who generates the draft, recorded so that a
     *                     second person approves it (schedule_approval_control, O18);
     *                     null for a system build (the bootstrap, a sweep)
     */
    public function generate(ContractEir $contract, ?int $userId=null): array
    {
        // A version 1 schedule from a source (the take-on workbook of spec 6.9,
        // an imported original schedule) is the contract's evidenced schedule
        // and is never generated over. Deleting only the GENERATED rows and
        // inserting a second set beside it doubled the scheduled principal of
        // the take-on loans (41 on the fresh bootstrap of 13 October 2026) and
        // blocked their EIR; the take-on build runs before the contract master
        // exists, so the contract is labelled here with the source it holds.
        $held=DB::table('contract_cashflow_schedule')->where('contract_id',$contract->contract_id)->where('schedule_version',1)
            ->where('schedule_source','<>','GENERATED')->distinct()->pluck('schedule_source')->all();
        if ($held!==[]) {
            if (!$contract->schedule_source || $contract->schedule_source==='GENERATED') {
                $contract->update(['schedule_source'=>$held[0]]);
            }
            // The take-on workbook's schedule is the version 1 schedule and is
            // reviewed like a generated draft before any EIR is solved on it
            // (results review of 13 October 2026, E2): a person approves it.
            if ($contract->schedule_approval_status==='NOT_GENERATED' && in_array(\App\Services\Ebanker\TakeonLandingService::ROUTE,$held,true)) {
                $contract->update(['schedule_approval_status'=>'PENDING_REVIEW']);
            }
            throw new InvalidArgumentException('The contract holds its version 1 schedule from '.implode(', ',$held).'; a schedule is not generated over it.');
        }
        $check=$this->readiness($contract);
        if (!$check['ready']) throw new InvalidArgumentException(implode('; ',$check['issues']));
        // An approved schedule stands, except one generated without the tranche
        // register while the EIR is not locked (spec v4 E1): it treats the
        // facility as drawn in full at signing and the EIR, solved on the money
        // actually advanced, cannot rest on it. Generating it again returns it
        // to a draft that a second person approves again (maker-checker).
        $replacing=null;
        if ($contract->schedule_approval_status==='APPROVED') {
            $replacing=$contract->locked_at ? null : app(EirReadinessService::class)->trancheMismatch($contract);
            if ($replacing===null) throw new InvalidArgumentException('Approved schedule cannot be overwritten.');
        }

        $frequency=(int)$contract->payments_per_year;
        $interval=intdiv(12,$frequency);
        $start=$contract->origination_date->copy();
        $maturity=$contract->maturity_date->copy();
        // The moratorium runs from the first disbursement (E-Banker p.31), not
        // from approval, so the instalments start from the date it ends.
        $moratoriumFrom=($contract->moratorium_from ?: $contract->interest_start_date ?: $contract->origination_date)->copy();
        $derivedFirst=$moratoriumFrom->copy()->addMonthsNoOverflow((int)$contract->moratorium_months)->addMonthsNoOverflow($interval);
        // Some Extract A rows carry a first-repayment date at/before the
        // origination date. It cannot be contractual cash flow evidence;
        // derive the first due date from the stated moratorium and frequency.
        $sourceFirst=($contract->first_instalment_date ?: $contract->first_repayment_date)?->copy();
        $first=$sourceFirst && $sourceFirst->gte($derivedFirst) ? $sourceFirst : $derivedFirst;
        $payments=self::instalmentsTo($first,$maturity,$interval);
        if ($payments===0) throw new InvalidArgumentException('First repayment date falls after maturity.');

        // contract_eir.contractual_rate is a fraction (0.3210 for 32.10 percent),
        // as the column is defined and every loader writes it. A value above 1 is
        // a loader fault, not a percent to be guessed back (audit L5): refused.
        $rate=self::rateAsFraction($contract->contractual_rate);
        // The drawdown profile (results review of 13 October 2026, E1): the
        // tranches as the ledger drew them, so interest runs on what was lent
        // and a later tranche earns interest from its own date. A register on
        // one date at or before the start changes nothing and is not passed.
        [$drawdowns,$observed]=$this->drawdownTerms($contract,$moratoriumFrom);
        $result=$this->generator->generate([
            'principal'=>(float)$contract->drawn_amount,
            'approved_amount'=>(float)$contract->approved_amount,
            'annual_rate'=>$rate,
            'payments_per_year'=>$frequency,
            'n_payments'=>$payments,
            'start_date'=>$start,
            'interest_start_date'=>$contract->interest_start_date,
            'first_due_date'=>$first,
            'moratorium_months'=>(int)$contract->moratorium_months,
            'moratorium_type'=>$contract->moratorium_type ?: $contract->moratorium_type_verbatim,
            'moratorium_from'=>$moratoriumFrom,
            'grace_period_months'=>(int)$contract->grace_period_months,
            'emi_calc_type'=>$contract->emi_calc_type,
            'interest_calc_base'=>$contract->interest_calc_base,
            'installment_based_on'=>$contract->installment_based_on,
            // The conventions are read as at origination, so a later change to
            // the Governance Centre never restates a schedule already built.
            'as_of'=>$start,
            'scheme'=>$this->scheme($contract),
            'drawdowns'=>$drawdowns,
            'interest_calc_base_observed'=>$observed,
        ]);

        DB::transaction(function() use ($contract,$result,$replacing,$userId) {
            DB::table('contract_cashflow_schedule')->where('contract_id',$contract->contract_id)
                ->where('schedule_version',1)->where('schedule_source','GENERATED')->delete();
            $now=now();
            DB::table('contract_cashflow_schedule')->insert(array_map(fn($row)=>[
                'contract_id'=>$contract->contract_id,'schedule_version'=>1,'effective_from'=>$contract->origination_date,
                'due_date'=>$row['due_date'],'principal_due'=>$row['principal_due'],'interest_due'=>$row['interest_due'],
                'fee_due'=>0,'schedule_source'=>'GENERATED','source_system'=>'EIR_GENERATOR',
                'source_reference'=>'Extract A contract terms','external_transaction_id'=>null,'created_at'=>$now,'updated_at'=>$now,
            ],$result['rows']));
            $contract->update(['schedule_source'=>'GENERATED','schedule_approval_status'=>'DRAFT',
                'schedule_generated_at'=>$now,'schedule_approved_at'=>null,'schedule_approved_by'=>null,
                'schedule_prepared_by'=>$userId,'schedule_approver_label'=>null,
                'schedule_amortising_balance'=>$result['retires'] ?? $result['amortising_opening_balance'],
                'schedule_moratorium_type'=>$result['moratorium_type'],
                'schedule_emi_calc_type'=>$result['emi_calc_type'],
                'schedule_interest_basis'=>$result['interest_basis'],
                'schedule_instalment_basis'=>$result['instalment_basis'],
                'schedule_day_count'=>$result['day_count'],
                'schedule_basis_sources'=>$this->basisSourcesText($result['basis_sources']),
            ]);
            AuditLoggerService::log('EIR Schedule Generated', ContractEir::class, $contract->id, ['new_values'=>[
                'contract_id'=>$contract->contract_id,'rows'=>count($result['rows']),
                'instalment'=>$result['instalment'],'instalment_shape'=>$result['instalment_shape'],
                'moratorium_type'=>$result['moratorium_type'],'moratorium_months'=>$result['moratorium_months'],
                'interest_basis'=>$result['interest_basis'],'instalment_basis'=>$result['instalment_basis'],
                'day_count'=>$result['day_count'],'amortising_opening_balance'=>$result['amortising_opening_balance'],
                'capitalised_interest'=>$result['capitalised_interest'],
                'drawdowns'=>$result['drawdowns'] ?? [],'tranched'=>$result['tranched'] ?? false,'replaced_approved_schedule'=>$replacing,
            ],'meta'=>['basis_sources'=>$result['basis_sources'],'grace_period_months'=>$result['grace_period_months'],
                'interest_basis_evidence'=>$result['interest_basis_evidence'] ?? null]]);
            // Reset versions are built on version 1; a new draft makes them
            // stale, so they go and are rebuilt from it (P5 step 3). Only
            // where the P5 schema is in place.
            if (Schema::hasColumn('contract_cashflow_schedule','schedule_type') && Schema::hasTable('rate_reset_events')) {
                app(ResetScheduleService::class)->discard($contract->contract_id);
            }
        });
        $comparison=$this->comparison($contract->fresh());
        $contract->update(['schedule_comparison_status'=>$comparison['status']]);
        // the draft against E-Banker's live instalment chart, recorded for the reviewer and the approval guard (ScheduleChartCheck)
        app(ScheduleChartCheck::class)->record($contract->fresh());
        return ['rows'=>count($result['rows']),'comparison'=>$comparison,'shape'=>[
            'instalment'=>$result['instalment'],'instalment_shape'=>$result['instalment_shape'],
            'moratorium_type'=>$result['moratorium_type'],'interest_basis'=>$result['interest_basis'],
            'instalment_basis'=>$result['instalment_basis'],'day_count'=>$result['day_count'],
            'capitalised_interest'=>$result['capitalised_interest'],'basis_sources'=>$result['basis_sources'],
        ]];
    }

    /**
     * The number of instalments from the first due date to the maturity, one
     * every $interval months on the dates the generator writes (the first due
     * date plus whole intervals, ScheduleGeneratorService).
     *
     * E-Banker writes a facility's maturity as the day before the anniversary
     * of its start (MATURITY_DATE = start + tenor - 1 day: Nature's Gift,
     * 22 April 2022 for three years, matures on 21 April 2025; its chart's last
     * instalment is the third anniversary). The term runs to the end of the
     * maturity day, so an instalment due the day after the maturity is the
     * contract's last one (termEnd). Until 16 October 2026 the count stopped at
     * the maturity date itself and every loan written this way lost its last
     * instalment: Nature's Gift two annual instalments of E-Banker's three,
     * Kadava 23 monthly of 24, and the locked schedules of Udon Business
     * (59 rows to 2 January 2029, maturity 1 February 2029) and Mtengo wa
     * Kumunda (20 rows to 5 June 2024, maturity 4 July 2024). The loop also
     * stepped from the previous date, so a date clamped at a month end (31
     * January to 28 February) drifted and the count could differ from the dates
     * written; it now counts the written dates. 0 when the first instalment
     * falls after the term.
     */
    public static function instalmentsTo(Carbon $first, Carbon $maturity, int $interval): int
    {
        $end=self::termEnd($maturity);
        if ($interval<1 || $first->copy()->startOfDay()->gt($end)) return 0;
        $n=1;
        while ($first->copy()->addMonthsNoOverflow($n*$interval)->startOfDay()->lte($end)) $n++;
        return $n;
    }

    /** The last day an instalment may fall on: the day after the maturity E-Banker writes (anniversary less one day). */
    public static function termEnd(Carbon $maturity): Carbon
    {
        return $maturity->copy()->startOfDay()->addDay();
    }

    public function generateEligible(?int $userId=null): array
    {
        $generated=0; $skipped=[];
        ContractEir::orderBy('contract_id')->each(function($contract) use (&$generated,&$skipped,$userId) {
            if ($contract->schedule_approval_status==='APPROVED') return;
            try { $this->generate($contract,$userId); $generated++; } catch (\Throwable $e) { $skipped[$contract->contract_id]=$e->getMessage(); }
        });
        return ['generated'=>$generated,'skipped'=>count($skipped),'exceptions'=>$skipped];
    }

    /** Statuses the comparison can end in. */
    public const COMPARISON_WITHIN_TOLERANCE = 'WITHIN_TOLERANCE';
    public const COMPARISON_CASH_VARIANCE = 'CASH_VARIANCE';
    public const COMPARISON_NOT_COMPARABLE = 'NOT_COMPARABLE';
    public const COMPARISON_NO_REMAINING_DATA = 'NO_REMAINING_DATA';

    /**
     * Version 1 against E-Banker's own schedule (Extract B or the EMI chart).
     *
     * Three rules, all learned on JAT Group (104430000087):
     *
     *  - Cash, not principal and interest. E-Banker adds the interest of the
     *    months with no instalment to the balance and repays it as principal,
     *    so its principal ran 77,672,610 above ours while the cash was the same
     *    thing split differently. Each instalment is compared on its total.
     *
     *  - Only the instalments before E-Banker recalculated. The EMI chart is
     *    E-Banker's current schedule: it is regenerated at every rate reset and
     *    whenever arrears are spread over the instalments left. Version 1 is the
     *    promise at origination, so it can only be held to the rows before the
     *    first regeneration; the rest are labelled and belong to P5 (resets)
     *    and P6 (arrears). A level instalment that changes, other than the last
     *    one or the first after an interest-only spell, marks a regeneration.
     *
     *  - Instalment by instalment, not date by date. The nth instalment is set
     *    against the nth, and a different due date is named as a cause instead
     *    of leaving both rows unmatched.
     */
    public function comparison(ContractEir $contract): array
    {
        $remaining=DB::table('contract_remaining_cashflow_schedule')->where('contract_id',$contract->contract_id)->orderBy('due_date')->orderBy('id')->get()->values();
        $empty=['cutoff_date'=>null,'generated_rows'=>0,'remaining_rows'=>0,'compared_rows'=>0,'recalculated_rows'=>0,
            'recalculated_from'=>null,'cash_variance'=>null,'principal_variance'=>null,'interest_variance'=>null,'matched_dates'=>0,'rows'=>[]];
        if ($remaining->isEmpty()) return ['status'=>self::COMPARISON_NO_REMAINING_DATA]+$empty;

        $cutoff=(string)$remaining->min('due_date');
        $generated=DB::table('contract_cashflow_schedule')->where('contract_id',$contract->contract_id)->where('schedule_version',1)
            ->whereDate('due_date','>=',$cutoff)->orderBy('due_date')->get()->values();

        $total=fn($r)=>$r===null?null:round((float)$r->principal_due+(float)$r->interest_due+(float)$r->fee_due,2);
        $recalculatedFrom=$this->firstRegeneration($remaining,(string)$contract->emi_calc_type);
        $resetDates=$this->repricesWithPlr($contract)
            ? DB::table('reference_rate_series')->whereDate('effective_date','>',$contract->origination_date ?? $cutoff)->orderBy('effective_date')->pluck('effective_date')->map(fn($d)=>substr((string)$d,0,10))->all()
            : [];

        $rows=[]; $compared=0; $recalculated=0; $cash=0.0; $pVar=0.0; $iVar=0.0; $ebankerCash=0.0; $sameDate=0;
        $count=max($generated->count(),$remaining->count());
        for ($i=0; $i<$count; $i++) {
            $g=$generated[$i]??null; $e=$remaining[$i]??null;
            $gTotal=$total($g); $eTotal=$total($e);
            $due=(string)($e->due_date??$g->due_date);
            $causes=[];
            if ($e!==null && $recalculatedFrom!==null && $i>=$recalculatedFrom) {
                $segment='RECALCULATED'; $recalculated++;
                if ($i===$recalculatedFrom) {
                    $previous=(string)$remaining[$i-1]->due_date;
                    foreach ($resetDates as $d) if ($d>$previous && $d<=$due) { $causes[]='Rate reset on '.$d.' (compared in P5)'; break; }
                    if ($this->inArrearsBetween($contract->contract_id,$previous,$due)) $causes[]='Arrears spread over the instalments left (handled in P6)';
                    if ($causes===[]) $causes[]='E-Banker regenerated its schedule here: a rate reset (P5) or arrears spread over the instalments left (P6)';
                } else {
                    $causes[]='After E-Banker recalculated';
                }
            } elseif ($g!==null && $e!==null) {
                $segment='COMPARED'; $compared++;
                $cash+=$gTotal-$eTotal; $ebankerCash+=$eTotal;
                $pVar+=(float)$g->principal_due-(float)$e->principal_due; $iVar+=(float)$g->interest_due-(float)$e->interest_due;
                if ((string)$g->due_date===(string)$e->due_date) $sameDate++; else $causes[]='Due dates differ ('.$g->due_date.' against '.$e->due_date.')';
                if (abs($gTotal-$eTotal)>1) $causes[]='Instalment differs: check the rate and how the instalment is sized';
                if ($causes===[]) $causes[]='Agrees';
            } elseif ($g!==null) {
                $segment='ONLY_GENERATED'; $causes[]='No E-Banker instalment here: the two schedules have a different number of instalments';
            } else {
                $segment='ONLY_EBANKER'; $causes[]='No generated instalment here: the two schedules have a different number of instalments';
            }
            $rows[]=['number'=>$i+1,'generated_due_date'=>$g?->due_date,'ebanker_due_date'=>$e?->due_date,
                'generated_total'=>$gTotal,'ebanker_total'=>$eTotal,
                'difference'=>$gTotal!==null&&$eTotal!==null?round($gTotal-$eTotal,2):null,
                'segment'=>$segment,'causes'=>$causes];
        }

        $status=$compared===0 ? self::COMPARISON_NOT_COMPARABLE
            : (abs($cash)<=max(1,abs($ebankerCash)*.01) ? self::COMPARISON_WITHIN_TOLERANCE : self::COMPARISON_CASH_VARIANCE);

        return ['status'=>$status,'cutoff_date'=>$cutoff,'generated_rows'=>$generated->count(),'remaining_rows'=>$remaining->count(),
            'compared_rows'=>$compared,'recalculated_rows'=>$recalculated,
            'recalculated_from'=>$recalculatedFrom===null?null:(string)$remaining[$recalculatedFrom]->due_date,
            'cash_variance'=>$compared?round($cash,2):null,
            // Kept for the screens that show them, and measured on the same
            // compared instalments only, so capitalised interest after a
            // regeneration cannot swell them.
            'principal_variance'=>$compared?round($pVar,2):null,'interest_variance'=>$compared?round($iVar,2):null,
            'matched_dates'=>$sameDate,'rows'=>$rows];
    }

    /**
     * Index of the first E-Banker row that belongs to a regenerated schedule,
     * or null. A level instalment (EMI type E) is regenerated when it changes;
     * an equal-principal one (type P) when its principal changes. The final
     * row may differ (it retires what is left), and the first row after an
     * interest-only spell is the instalment starting, not a regeneration.
     */
    private function firstRegeneration($rows, string $emiCalcType): ?int
    {
        $level=fn($r)=>strtoupper($emiCalcType)==='P'
            ? round((float)$r->principal_due,2)
            : round((float)$r->principal_due+(float)$r->interest_due+(float)$r->fee_due,2);
        $last=$rows->count()-1;
        for ($i=1; $i<$last; $i++) {
            if ((float)$rows[$i-1]->principal_due<=0.0) continue;
            if (abs($level($rows[$i])-$level($rows[$i-1]))>1) return $i;
        }
        return null;
    }

    private function repricesWithPlr(ContractEir $contract): bool
    {
        return (bool)$contract->reprice_flag || strtoupper((string)$contract->interest_policy)==='P';
    }

    /** Any loan-book month-end between two dates that shows the account overdue. */
    private function inArrearsBetween(string $contractId, string $from, string $to): bool
    {
        return DB::table('loan_books')->where('contract_id',$contractId)
            ->where('reporting_period','>=',substr($from,0,7))->where('reporting_period','<=',substr($to,0,7))
            ->where('overdue_days','>',0)->exists();
    }

    /**
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
        if (!$bootstrap) $this->assertSecondPerson($contract,$userId);
        // A generated schedule is set against E-Banker's live instalment chart before it is approved
        // (ScheduleChartCheck). One that differs is never approved by the automated path: it stays a
        // draft with the reason and the two sets of figures, for a person to review; a person may still
        // approve it, with a reason.
        $chart=$contract->schedule_source==='GENERATED' ? app(ScheduleChartCheck::class)->record($contract) : null;
        if ($chart!==null && $chart['status']===ScheduleChartCheck::DIFFERS) {
            $why=ScheduleChartCheck::describe($chart);
            if ($bootstrap) {
                $contract->update(['schedule_review_notes'=>mb_substr('Not approved by the bootstrap: '.$why,0,2000)]);
                AuditLoggerService::log('EIR Schedule Held', ContractEir::class, $contract->id, ['new_values'=>['contract_id'=>$contract->contract_id,'chart_check'=>$chart],'meta'=>['label'=>$label]]);
                throw new InvalidArgumentException('Not approved automatically: '.$why);
            }
            if (trim((string)$notes)==='')
                throw new InvalidArgumentException('Give the reason for approving a schedule that differs from E-Banker\'s chart. '.$why);
        }
        $comparison=$this->comparison($contract);
        if ($comparison['status'] !== 'WITHIN_TOLERANCE' && trim((string)$notes) === '')
            throw new InvalidArgumentException('A review note is required when the generated schedule does not reconcile within tolerance to Extract B remaining cash flows.');
        $contract->update(['schedule_approval_status'=>'APPROVED','schedule_comparison_status'=>$comparison['status'],
            'schedule_review_notes'=>$notes,'schedule_approved_at'=>now(),'schedule_approved_by'=>$userId,
            'schedule_approver_label'=>$bootstrap ? $label : null]);
        AuditLoggerService::log('EIR Schedule Approved', ContractEir::class, $contract->id, ['new_values'=>[
            'contract_id'=>$contract->contract_id,'comparison_status'=>$comparison['status'],'notes'=>$notes,
            'chart_check'=>$chart===null ? null : ['status'=>$chart['status'],'reasons'=>$chart['reasons']],
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
        if ($contract->schedule_prepared_by!==null && (int)$contract->schedule_prepared_by===$userId && ! \App\Support\MakerChecker::selfApprovalAllowed('Schedule approval', 'contract_eirs', (string) $contract->contract_id, $userId))
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
    }

    /**
     * The scheme's settings, used only where the contract row states none
     * (spec v3 section 6.3). The scheme in force is the latest one effective
     * on or before origination.
     *
     * @return array<string,mixed>
     */
    private function scheme(ContractEir $contract): array
    {
        $scheme=Scheme::inForce($contract->scheme_code, $contract->origination_date ?: Carbon::today());
        return $scheme===null ? [] : [
            'interest_policy'=>$scheme->interest_policy,
            'floating_flag'=>$scheme->floating_flag,
            'interest_calc_base'=>$scheme->interest_calc_base,
            'installment_based_on'=>$scheme->installment_based_on,
            'emi_calc_type'=>$scheme->emi_calc_type,
            'default_moratorium_type'=>$scheme->default_moratorium_type,
            'scheme_code'=>$scheme->scheme_code,
        ];
    }

    /**
     * The drawdown terms the generator is given: the tranche register (one row
     * per drawdown date, fee debits included in their drawdown, spec 7.8.4)
     * when it says anything the start date does not, and, for a facility drawn
     * in full but in tranches whose contract and scheme state no interest
     * basis, the basis read from the interest E-Banker posted while it was
     * partly drawn (governed setting partly_drawn_interest_basis).
     *
     * @return array{0: ?list<array{date:string,amount:float}>, 1: ?array}
     */
    private function drawdownTerms(ContractEir $contract, Carbon $moratoriumFrom): array
    {
        if (!DB::getSchemaBuilder()->hasTable('contract_disbursements')) return [null,null]; // no register: no profile
        $tranches=app(DisbursementService::class)->tranchesFor($contract->contract_id);
        if ($tranches===[]) return [null,null];
        $profileStart=((int)$contract->moratorium_months>0 ? $moratoriumFrom : ($contract->interest_start_date ?: $contract->origination_date))->toDateString();
        $later=array_values(array_filter($tranches,fn($t)=>$t['disbursement_date']>$profileStart));
        if ($later===[]) return [null,null];
        $sum=round(array_sum(array_column($tranches,'amount')),2);
        $dates=array_unique(array_column($tranches,'disbursement_date'));
        if (count($dates)===1 && abs($sum-round((float)$contract->drawn_amount,2))>0.01) {
            // one drawdown whose amount the register and the contract master
            // tell differently: the timing is known but not the amount, so
            // the schedule stays on the contract master's amount at the start
            return [null,null];
        }
        $drawdowns=array_map(fn($t)=>['date'=>$t['disbursement_date'],'amount'=>$t['amount']],$tranches);
        // partly drawn for a while only when something was drawn before the last tranche
        $opening=round(array_sum(array_column(array_filter($tranches,fn($t)=>$t['disbursement_date']<=$profileStart),'amount')),2);
        $window=$opening>0 || count(array_unique(array_column($later,'disbursement_date')))>1;
        $scheme=$this->scheme($contract);
        if (!$window || $this->stated($contract->interest_calc_base) || $this->stated($scheme['interest_calc_base']??null)) return [$drawdowns,null];
        try {
            $setting=app(GovernanceService::class)->get('partly_drawn_interest_basis',$contract->origination_date);
        } catch (\Throwable $e) {
            throw new InvalidArgumentException('The interest basis of a facility drawn in tranches is governed (partly_drawn_interest_basis) and the setting could not be read: '.$e->getMessage());
        }
        if (str_starts_with($setting,'Balance-wise')) {
            return [$drawdowns,['basis'=>'B','source'=>'GOVERNED_BALANCE_WISE','evidence'=>['setting'=>$setting]]];
        }
        return [$drawdowns,$this->observedInterestBasis($contract,$tranches)];
    }

    /**
     * Which basis E-Banker charged interest on while the facility was partly
     * drawn, read from its own interest postings in the ledger (types 303 and
     * 120) against what each basis gives at the rate E-Banker charged: the
     * principal outstanding at the month before plus each tranche from its
     * date, or the sanctioned amount, over the days from the first drawdown.
     * The rate is the one the posting narrates ("IntDR Fm 27-NOV-24 To
     * 30-NOV-24 @ 85%"), else the month's loan-book rate. The basis whose
     * figure is nearer the postings is the one E-Banker applied; both figures
     * are kept as the evidence. With no postings for those months nothing can
     * be read and null is returned, so the generator refuses rather than
     * assumes.
     *
     * @param list<array{disbursement_date:string, amount:float}> $tranches
     */
    private function observedInterestBasis(ContractEir $contract, array $tranches): ?array
    {
        $first=Carbon::parse($tranches[0]['disbursement_date'])->startOfDay();
        $last=Carbon::parse($tranches[count($tranches)-1]['disbursement_date'])->startOfDay();
        $total=round(array_sum(array_column($tranches,'amount')),2);
        $approved=max((float)$contract->approved_amount,$total);
        $account=str_pad((string)$contract->contract_id,15,'0',STR_PAD_LEFT);
        try {
            $ledger=app(\App\Services\Ebanker\LandingZoneReader::class)->ledgerByAccount($last->copy()->endOfMonth()->toDateString(),$account)[$account] ?? [];
        } catch (\Throwable) {
            return null;
        }
        $posted=[]; $narrated=[];
        foreach ($ledger as $row) {
            $x=$row['payload'];
            if (!in_array((string)($x['TRANTYPE'] ?? ''),\App\Services\Ebanker\LoanBookBuildService::TYPE_INTEREST,true) || $row['row_date']===null) continue;
            $ym=substr((string)$row['row_date'],0,7);
            $posted[$ym]=($posted[$ym] ?? 0.0)-(float)str_replace(',','',(string)($x['TRANSAMT'] ?? 0));
            if (!isset($narrated[$ym]) && preg_match('/@\s*(\d+(?:\.\d+)?)\s*%/',(string)($x['PARTICULARS'] ?? ''),$m)===1) $narrated[$ym]=(float)$m[1]/100;
        }
        $schema=DB::getSchemaBuilder();
        $book=fn(string $ym)=>$schema->hasTable('loan_books') ? DB::table('loan_books')->where('contract_id',$contract->contract_id)
            ->whereRaw("REPLACE(SUBSTR(reporting_period, 1, 7), '-', '') = ?",[str_replace('-','',$ym)])->orderByDesc('id')->first() : null;
        $months=[]; $sumPosted=0.0; $onBalance=0.0; $onSanction=0.0;
        for ($m=$first->copy()->startOfMonth(); $m->lte($last); $m->addMonthNoOverflow()) {
            $ym=$m->format('Y-m');
            $p=$posted[$ym] ?? 0.0;
            $row=$book($ym);
            $rate=$narrated[$ym] ?? ($row && is_numeric($row->interest_rate) && (float)$row->interest_rate>0 ? (float)$row->interest_rate/100 : (float)$contract->contractual_rate);
            if ($p<=0 || $rate<=0) continue;
            $from=$m->copy()->max($first); $end=$m->copy()->endOfMonth()->startOfDay();
            $prior=$m->isSameMonth($first) ? 0.0 : (float)($book($m->copy()->subMonthNoOverflow()->format('Y-m'))->principal_balance ?? 0);
            $b=$prior*$rate*($from->diffInDays($end)+1)/365;
            foreach ($tranches as $t) {
                $d=Carbon::parse($t['disbursement_date'])->startOfDay();
                if ($d->isSameMonth($m)) $b+=$t['amount']*$rate*($d->diffInDays($end)+1)/365;
            }
            $sanction=$approved*$rate*($from->diffInDays($end)+1)/365;
            $months[]=['month'=>$ym,'posted'=>round($p,2),'on_balance'=>round($b,2),'on_sanction'=>round($sanction,2),'rate'=>$rate,'rate_from'=>isset($narrated[$ym])?'narration':'loan book'];
            $sumPosted+=$p; $onBalance+=$b; $onSanction+=$sanction;
        }
        if ($months===[] || $sumPosted<=0 || $onBalance<=0 || $onSanction<=0) return null;
        $basis=abs(log($sumPosted/$onBalance))<=abs(log($sumPosted/$onSanction)) ? 'B' : 'S';
        return ['basis'=>$basis,'source'=>ScheduleGeneratorService::BASIS_OBSERVED,'evidence'=>[
            'months'=>$months,'posted'=>round($sumPosted,2),'expected_on_balance'=>round($onBalance,2),'expected_on_sanction'=>round($onSanction,2),
            'read_as'=>$basis==='B'?'the drawn balance':'the sanctioned amount',
        ]];
    }

    /** True when the approved amount is above the amount drawn. */
    private function partlyDrawn(ContractEir $contract): bool
    {
        return round((float)$contract->approved_amount - (float)$contract->drawn_amount, 2) > 0.0;
    }

    /** E-Banker writes N where no basis was chosen, which is the same as blank. */
    private function stated($value): bool
    {
        $text=strtoupper(trim((string)$value));
        return $text !== '' && $text !== 'N';
    }

    private function basisSourcesText(array $sources): string
    {
        $parts=[];
        foreach ($sources as $key=>$source) $parts[]=$key.'='.$source;
        return mb_substr(implode(', ',$parts),0,255);
    }
}
