<?php

namespace App\Providers;

use Carbon\Carbon;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Models\Client;
use App\Models\Setting;
use Laravel\Sanctum\Sanctum;
use Laravel\Telescope\Telescope;
use App\Observers\ClientObserver;
use Illuminate\Support\Collection;
use Illuminate\Pagination\UrlWindow;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Models\LoanApplicationLinkedApprovalStage;
use App\Observers\LoanApplicationLinkedApprovalStageObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerLengthAwarePaginator();
        Telescope::ignoreMigrations();
        Sanctum::ignoreMigrations();
        if ($this->app->environment('local')) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }

    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Schema::defaultStringLength(199);
        //$this->configureEmailSettings();
        Client::observe(ClientObserver::class);
        LoanApplicationLinkedApprovalStage::observe(LoanApplicationLinkedApprovalStageObserver::class);
    }

    protected function registerLengthAwarePaginator()
    {
        $this->app->bind(LengthAwarePaginator::class, function ($app, $values) {
            return new class(...array_values($values)) extends LengthAwarePaginator {
                public function only(...$attributes)
                {
                    return $this->transform(function ($item) use ($attributes) {
                        return $item->only($attributes);
                    });
                }

                public function transform($callback)
                {
                    $this->items->transform($callback);

                    return $this;
                }

                public function toArray()
                {
                    return [
                        'data' => $this->items->toArray(),
                        'links' => $this->links(),
                    ];
                }

                public function links($view = null, $data = [])
                {
                    $this->appends(Request::all());

                    $window = UrlWindow::make($this);

                    $elements = array_filter([
                        $window['first'],
                        is_array($window['slider']) ? '...' : null,
                        $window['slider'],
                        is_array($window['last']) ? '...' : null,
                        $window['last'],
                    ]);

                    return Collection::make($elements)->flatMap(function ($item) {
                        if (is_array($item)) {
                            return Collection::make($item)->map(function ($url, $page) {
                                return [
                                    'url' => $url,
                                    'label' => $page,
                                    'active' => $this->currentPage() === $page,
                                ];
                            });
                        } else {
                            return [
                                [
                                    'url' => null,
                                    'label' => '...',
                                    'active' => false,
                                ],
                            ];
                        }
                    })->prepend([
                        'url' => $this->previousPageUrl(),
                        'label' => 'Previous',
                        'active' => false,
                    ])->push([
                        'url' => $this->nextPageUrl(),
                        'label' => 'Next',
                        'active' => false,
                    ]);
                }
            };
        });
    }

    protected function configureEmailSettings()
    {

        if (Schema::hasTable('settings')) {
            Config::set('mail.default', Setting::where('setting_key', 'mail_mailer')->first()->setting_value ?? env('MAIL_MAILER'));
            Config::set('mail.mailers.smtp.host', Setting::where('setting_key', 'mail_host')->first()->setting_value ?? env('MAIL_HOST'));
            Config::set('mail.mailers.smtp.port', Setting::where('setting_key', 'mail_port')->first()->setting_value ?? env('MAIL_PORT'));
            Config::set('mail.mailers.smtp.username', Setting::where('setting_key', 'mail_username')->first()->setting_value ?? env('MAIL_USERNAME'));
            Config::set('mail.mailers.smtp.password', Setting::where('setting_key', 'mail_password')->first()->setting_value ?? env('MAIL_PASSWORD'));
            Config::set('mail.mailers.smtp.encryption', Setting::where('setting_key', 'mail_encryption')->first()->setting_value ?? env('MAIL_ENCRYPTION'));
            Config::set('mail.from.address', Setting::where('setting_key', 'mail_from_address')->first()->setting_value ?? env('MAIL_FROM_ADDRESS'));
            Config::set('mail.from.name', Setting::where('setting_key', 'mail_from_name')->first()->setting_value ?? env('MAIL_FROM_NAME'));

        }
    }
}
