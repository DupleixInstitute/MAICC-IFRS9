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
            // Slim user payload: the full model serialized 174 permission
            // objects TWICE (user.can + user.roles) = ~40KB on every response.
            'user' => function () {
                $u = Auth::user();
                if (! $u) {
                    return null;
                }
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'profile_photo_url' => $u->profile_photo_url,
                    'current_role' => $u->current_role,
                    'can' => $u->getAllPermissions()->pluck('name'),
                    'theme_preference' => $u->theme_preference ?? 'system',
                ];
            },
            'menu' => fn () => \App\Support\SectionTabCounts::annotate($this->visibleMenu(
                (Auth::check() && Auth::user()->hasRole('member')) ? config('menu.member') : config('menu.admin')
            ), Route::currentRouteName()),
            'logoUrl' => $logo ? asset('storage/' . $logo) : asset('images/maiic-logo-white.png'),
            'smallLogoUrl' => $smallLogo ? asset('storage/' . $smallLogo) : asset('images/maiic-logo-white.png'),
            'companyName' => $settings['company_name'] ?? 'MAIIC',
            'companyAddress' => $settings['company_address'] ?? '',
            'companyMobile' => $settings['company_mobile'] ?? '',
            'companyTel' => $settings['company_tel'] ?? '',
            'companyEmail' => $settings['company_email'] ?? 'info@company.com',
            'currency' => $currency,
            'notifications_unread' => fn () => Auth::check() ? Auth::user()->unreadNotifications()->count() : 0,
            'route_name' => Route::currentRouteName(),
            // The period chip in the top bar: the latest financial period and
            // whether it is open or closed. A display, not a control (spec v4 s.11.4).
            'currentPeriod' => fn () => cache()->remember('inertia.current_period', 60, function () {
                $p = \App\Models\FinancialPeriod::query()->orderByDesc('end_date')->first();

                return $p ? ['name' => $p->name ?? ($p->start_date . ' to ' . $p->end_date), 'closed' => (bool) $p->closed] : null;
            }),
        ]);
    }

    /**
     * Drop the menu entries whose 'permissions' the user lacks, and any group
     * left with no children. An entry with no permission stays visible, so
     * only entries that declare one are affected.
     */
    private function visibleMenu(?array $items): array
    {
        $user = Auth::user();
        $visible = [];
        foreach ($items ?? [] as $item) {
            $permission = $item['permissions'] ?? '';
            if ($permission !== '' && ! ($user && $user->can($permission))) {
                continue;
            }
            if (! empty($item['dropdown'])) {
                $item['children'] = $this->visibleMenu($item['children'] ?? []);
                if ($item['children'] === []) {
                    continue;
                }
            }
            // A tabbed section keeps the tabs the user may open, and opens the first.
            if (! empty($item['tabs'])) {
                $item['tabs'] = array_values(array_filter($item['tabs'], fn ($t) => ($t['permissions'] ?? '') === '' || ($user && $user->can($t['permissions']))));
                if ($item['tabs'] === []) {
                    continue;
                }
                $item['route'] = $item['route_check'] = $item['tabs'][0]['route'];
            }
            $visible[] = $item;
        }

        return $visible;
    }
}
