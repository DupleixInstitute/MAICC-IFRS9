<?php

namespace App\Providers;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use App\Listeners\ForcePasswordChange;
use App\Listeners\UpdateUserLastLogin;
use Illuminate\Auth\Events\Registered;
use App\Events\LoanApplicationStatusChanged;
use App\Listeners\SendLoanApplicationStatusChangedNotification;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        Login::class => [
            ForcePasswordChange::class,
            UpdateUserLastLogin::class,
        ],
        LoanApplicationStatusChanged::class => [
            SendLoanApplicationStatusChangedNotification::class,
        ],
        //if its a first time login, send force password change

    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
