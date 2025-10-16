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
        $logo = Setting::where('setting_key', 'company_logo')->first()->setting_value;
        $smallLogo = Setting::where('setting_key', 'company_small_logo')->first()->setting_value;
        return array_merge(parent::share($request), [
            'flash' => function () use ($request) {
                return [
                    'success' => $request->session()->get('success'),
                    'error' => $request->session()->get('error'),
                ];
            },
            'user' => Auth::user(),
            'menu' => (Auth::check()&&Auth::user()->hasRole('member'))?config('menu.member'):config('menu.admin'),
            'logoUrl' => $logo ? asset('storage/' . $logo) : asset('images/TNMMpamba-logo.png'),
            'smallLogoUrl' => $smallLogo ? asset('storage/' . $smallLogo) : null,
            'companyName' => Setting::where('setting_key', 'company_name')->first()->setting_value,
            'companyAddress' => Setting::where('setting_key', 'company_address')->first()->setting_value,
            'companyMobile' => Setting::where('setting_key', 'company_mobile')->first()->setting_value,
            'companyTel' => Setting::where('setting_key', 'company_tel')->first()->setting_value,
            'companyEmail' => Setting::where('setting_key', 'company_email')->first()->setting_value,
            'currency' => Currency::find(Setting::where('setting_key', 'currency')->first()->setting_value),
            'route_name' => Route::currentRouteName(),
        ]);
    }
}
