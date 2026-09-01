<?php
namespace App\Console\Commands;

use App\Services\Eir\ScheduleWorkflowService;
use Illuminate\Console\Command;

/** Governed schedule generation from the imported Extract A contract master. */
class GenerateContractSchedules extends Command
{
    protected $signature = 'eir:generate-schedules {--dry-run : Assess readiness without writing}';
    protected $description = 'Generate draft version-1 schedules from Extract A terms for later review and approval';

    public function handle(ScheduleWorkflowService $workflow): int
    {
        $result = $this->option('dry-run') ? $workflow->dryRun() : $workflow->generateEligible();
        if ($this->option('dry-run')) $this->info("Eligible: {$result['eligible']}  Blocked: {$result['blocked']}");
        else $this->info("Drafts generated: {$result['generated']}  Skipped: {$result['skipped']}");
        foreach ($result['exceptions'] as $contract => $reasons) {
            $this->line("  {$contract}: ".(is_array($reasons) ? implode('; ', $reasons) : $reasons));
        }
        return self::SUCCESS;
    }
}
