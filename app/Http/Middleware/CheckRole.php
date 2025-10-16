<?php

namespace App\Http\Middleware;

use App\Models\Client;
use App\Models\PatientUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckRole
{
    public $except = [
        'user/profile-information',
        'user/other-browser-sessions',
        'user/profile-photo',
        'user',
        'user/password',
        'user/confirm-password',
        'two-factor-challenge',
        'user/two-factor-authentication',
        'user/two-factor-recovery-codes',
        'email/verification-notification',
        'logout',
        'login',
        'two-factor*',
        'user-profile-information.update',
        'portal.profile.*',
        'broadcasting*',
        'file/download',
    ];

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {

        if($this->inExceptArray($request)){
            return $next($request);
        }

        if (Auth::check() && Auth::user()->hasRole('member')) {
            if (empty(session('member_id'))) {
                session(['member_id' => Auth::user()->member_id]);
            }
        }
        if (Auth::check() && Auth::user()->hasRole('member') && !$request->is('portal*')) {
            return redirect()->route('portal.dashboard');
        }
        return $next($request);
    }
    /**
     * Determine if the request has a URI that should pass through CSRF verification.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function inExceptArray($request)
    {
        foreach ($this->except as $except) {
            if ($except !== '/') {
                $except = trim($except, '/');
            }

            if ($request->fullUrlIs($except) || $request->is($except)) {
                return true;
            }
        }

        return false;
    }
}
