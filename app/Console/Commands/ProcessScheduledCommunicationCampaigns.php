<?php

namespace App\Console\Commands;

use App\Jobs\ProcessCampaign;
use App\Models\CommunicationCampaign;
use App\Models\Setting;
use Illuminate\Console\Command;

class ProcessScheduledCommunicationCampaigns extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'campaigns:process-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Processes Scheduled Campaigns';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $campaigns = CommunicationCampaign::where('trigger_type', 'schedule')
            ->where(function ($query) {
                $query->where('scheduled_date', date('Y-m-d'));
                $query->orWhere('recur_next_date', date('Y-m-d'));
            })
            ->where('scheduled_time', date('H:i'))
            ->where('status', 'active')
            ->get();
        foreach ($campaigns as $campaign) {
            ProcessCampaign::dispatch($campaign);

        }
        return 0;
    }
}
