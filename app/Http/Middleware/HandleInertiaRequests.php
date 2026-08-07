<?php

namespace App\Http\Middleware;

use App\Models\Currency;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Middleware;
use Illuminate\Support\Facades\Route;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     * @param \Illuminate\Http\Request $request
     * @return string|null
     */
    public function version(Request $request)
    {
        return parent::version($request);
    }

    /**
     * Defines the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function share(Request $request)
    {
        // One cached settings map instead of a query per setting on EVERY
        // request (this middleware runs for the whole web group). 60s TTL
        // keeps Settings-screen edits near-instant without an invalidation
        // hook.
        $settings = cache()->remember(
            'inertia.settings.map',
            60,
            fn () => Setting::pluck('setting_value', 'setting_key')
        );

        $currencyId = $settings['currency'] ?? 1;
        $currency = cache()->remember(
            'inertia.currency.' . $currencyId,
            60,
            fn () => Currency::find($currencyId)
        );

        $logo = $settings['company_logo'] ?? null;
        $smallLogo = $settings['company_small_logo'] ?? null;

        return array_merge(parent::share($request), [
            'flash' => function () use ($request) {
                return [
                    'success' => $request->session()->get('success'),
                    'error' => $request->session()->get('error'),
                ];
            },
            'user' => Auth::user(),
            'menu' => (Auth::check() && Auth::user()->hasRole('member')) ? config('menu.member') : config('menu.admin'),
            'logoUrl' => $logo ? asset('storage/' . $logo) : asset('images/maiic-logo-white.png'),
            'smallLogoUrl' => $smallLogo ? asset('storage/' . $smallLogo) : asset('images/maiic-logo-white.png'),
            'companyName' => $settings['company_name'] ?? 'MAIIC',
            'companyAddress' => $settings['company_address'] ?? '',
            'companyMobile' => $settings['company_mobile'] ?? '',
            'companyTel' => $settings['company_tel'] ?? '',
            'companyEmail' => $settings['company_email'] ?? 'info@company.com',
            'currency' => $currency,
            'notifications_unread' => Auth::check() ? Auth::user()->unreadNotifications()->count() : 0,
            'route_name' => Route::currentRouteName(),
        ]);
    }
}
