<?php

namespace App\Console\Commands;

use App\Models\CommunicationCampaign;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ProcessRecurringCommunicationCampaigns extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'campaigns:process-recurring';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Processes recurring campaigns';

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
        $campaigns = CommunicationCampaign::where('recurring', 1)
            ->where(function ($query) {
                $query->where('recur_start_date', date('Y-m-d'));
                $query->orWhere('recur_next_date', date('Y-m-d'));
            })
            ->get();
        $systemUserID = Setting::where('setting_key', 'system_user')->first()->setting_value;
        foreach ($campaigns as $campaign) {
            if ($campaign->recur_type === 'selected_days') {
                foreach ($campaign->selected_days as $day) {
                    $date = Carbon::parse($day);
                    if ($date->greaterThan(Carbon::now())) {
                        $campaign->recur_next_date = $date->format('Y-m-d');
                        break;
                    }
                }
            } else {
                $campaign->recur_next_date = Carbon::now()->add($campaign->recur_type, $campaign->recur_frequency)->format('Y-m-d');
            }
            $campaign->recur_last_run_date = date('Y-m-d');
            $campaign->save();
        }
        return 0;
    }
}
