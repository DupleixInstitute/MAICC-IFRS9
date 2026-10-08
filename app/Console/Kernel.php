<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {

        // Route 2 of the E-Banker feed: poll the shared folder each morning; the first working day after month-end is when the pack lands
        $schedule->command('eir:poll-feed-folder --also-route-3')->dailyAt('06:30')->withoutOverlapping();
        // the World Bank series, monthly, live with the snapshot as the fallback
        $schedule->command('macro:import-worldbank')->monthlyOn(2, '07:00');
        $schedule->command('campaigns:process-recurring')->daily();
        $schedule->command('campaigns:process-scheduled')->everyFiveMinutes();
        $schedule->command('reminders:loan-applications')->everyTwoMinutes();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
