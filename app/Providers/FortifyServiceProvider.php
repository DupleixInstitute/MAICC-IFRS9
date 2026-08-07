<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->instance(LoginResponse::class, new class implements LoginResponse {
            public function toResponse($request)
            {
                if (Auth::user()->hasRole('patient')) {
                    return redirect()->route('portal.dashboard');
                }
                return redirect()->route('dashboard');
            }
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        // Verify the self-hosted CAPTCHA before checking credentials. The code
        // is single-use (pulled from the session) so each attempt requires the
        // freshly-shown challenge. See config/captcha.php and CaptchaController.
        Fortify::authenticateUsing(function (Request $request) {
            if (config('captcha.enabled', true)) {
                $key = config('captcha.session_key', 'login_captcha');
                $expected = (string) $request->session()->pull($key, '');
                $issuedAt = (int) $request->session()->pull($key . '_time', 0);
                $ttl = (int) config('captcha.ttl', 300);
                $given = strtoupper(trim((string) $request->input('captcha', '')));

                $expired = $issuedAt > 0 && (now()->timestamp - $issuedAt) > $ttl;

                if ($expected === '' || $expired || $given === '' || ! hash_equals(strtoupper($expected), $given)) {
                    throw ValidationException::withMessages([
                        'captcha' => __('The security code is incorrect or has expired. Please enter the new code shown.'),
                    ]);
                }
            }

            $username = Fortify::username();
            $user = User::where($username, $request->{$username})->first();

            if ($user && Hash::check($request->password, $user->password)) {
                return $user;
            }

            return null;
        });

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->email . $request->ip());
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }
}
