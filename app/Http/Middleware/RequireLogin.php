<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Every web route needs a login unless it is on the public list below.
 *
 * System audit of 9 October 2026, finding C7: 180 routes of the product the
 * platform was forked from (loan-book import, collateral, the transition
 * matrix and LGD writes into the loan book, the ECL recalculation, the legacy
 * regression approval, the macro and scenario CRUD) had no auth middleware in
 * the route file or the controller. Rather than touch each of them, this one
 * gate runs in the web group after the session starts: a guest is sent to
 * the login page (or gets 401 on a JSON request), and the legacy financial
 * modules also require the suite permission they belong to, by URI prefix.
 * A route that declares its own, stricter permission keeps it.
 */
class RequireLogin
{
    /** Route names, or name prefixes ending in '*', that need no login. */
    public const PUBLIC_ROUTES = [
        'login', 'logout', 'register', 'password.*', 'two-factor.*', 'verification.*',
        'terms.show', 'policy.show', 'captcha', 'captcha.*', 'ebanker-feed.api',
        'sanctum.csrf-cookie', 'livewire.*', 'ignition.*',
    ];

    /** URIs (first segment or full path pattern) that need no login. */
    public const PUBLIC_URIS = [
        'login', 'logout', 'register', 'captcha', 'forgot-password', 'reset-password', 'reset-password/*',
        'two-factor-challenge', 'email/verify/*', 'email/verification-notification', 'user/confirm-password',
        'privacy-policy', 'terms-of-service', 'broadcasting/auth', 'websockets', 'websockets/*', 'livewire/*',
        'sanctum/csrf-cookie', 'api/ebanker-feed/pack', '_ignition/*',
    ];

    /**
     * Legacy financial modules and the suite permission they belong to. The
     * first matching prefix wins; a module absent from the list is gated by
     * the login alone.
     */
    public const MODULE_PERMISSIONS = [
        'loan_application' => 'loans',
        'loan-books' => 'loans',
        'save-loan-book' => 'loans',
        'contracts' => 'loans',
        'groups' => 'loans',
        'collateral' => 'loans',
        'transition-matrix' => 'reports.ifrs9',
        'transition-matrix-cummulative' => 'reports.ifrs9',
        'transition-profile' => 'reports.ifrs9',
        'transition-profiles' => 'reports.ifrs9',
        'loss-given-default' => 'reports.ifrs9',
        'lgd-calculations' => 'reports.ifrs9',
        'lgd-payment-report' => 'reports.ifrs9',
        'expected-credit-loss' => 'reports.ifrs9',
        'credit-loss-data' => 'reports.ifrs9',
        'regression' => 'reports.ifrs9',
        'macro-statistics' => 'reports.ifrs9',
        'macro-forecast-weighted' => 'reports.ifrs9',
        'scenarios' => 'reports.ifrs9',
        'scenario-profiles' => 'reports.ifrs9',
        'external-calculations' => 'reports.ifrs9',
        'file' => 'loans',
        'communication' => 'settings',
        'docs' => 'manual.view',
    ];

    public function handle(Request $request, Closure $next)
    {
        if ($this->isPublic($request)) {
            return $next($request);
        }

        if (! Auth::check()) {
            if ($request->expectsJson()) {
                throw new HttpException(401, 'Login required.');
            }
            return redirect()->guest(route('login'));
        }

        $permission = $this->modulePermission($request);
        if ($permission !== null && ! Auth::user()->can($permission)) {
            throw new HttpException(403, "User does not have the right permissions ({$permission}).");
        }

        return $next($request);
    }

    private function isPublic(Request $request): bool
    {
        $name = (string) optional($request->route())->getName();
        foreach (self::PUBLIC_ROUTES as $pattern) {
            if ($name !== '' && ($pattern === $name || (str_ends_with($pattern, '*') && str_starts_with($name, rtrim($pattern, '*'))))) {
                return true;
            }
        }
        foreach (self::PUBLIC_URIS as $uri) {
            if ($request->is($uri)) {
                return true;
            }
        }

        return false;
    }

    private function modulePermission(Request $request): ?string
    {
        $first = explode('/', trim($request->path(), '/'))[0] ?? '';
        if ($first === 'api') {
            $first = explode('/', trim($request->path(), '/'))[1] ?? '';
        }

        return self::MODULE_PERMISSIONS[$first] ?? null;
    }
}
