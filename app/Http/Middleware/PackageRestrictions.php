<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PackageRestrictions
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $decodedPurchaseCode = config('app.decoded_purchase_code');
        if ($request->is(['branch/store'])) {
            //checks limits for starter
            if ($decodedPurchaseCode->product_package->name == 'Starter' && Branch::count() >= 1) {
                return redirect()->back()->with('error', 'Branch limit exceeded for your package, please upgrade your package.');
            }
            //checks limits for standard
            if ($decodedPurchaseCode->product_package->name == 'Standard' && Branch::count() >= 2) {
                return redirect()->back()->with('error', 'Branch limit exceeded for your package, please upgrade your package.');
            }
        }
        if ($request->is(['user/store'])) {
            //checks limits for starter
            if ($decodedPurchaseCode->product_package->name == 'Starter' && User::count() >= 2) {
                return redirect()->back()->with('error', 'User limit exceeded for your package, please upgrade your package.');
            }
            //checks limits for standard
            if ($decodedPurchaseCode->product_package->name == 'Standard' && User::count() >= 5) {
                return redirect()->back()->with('error', 'User limit exceeded for your package, please upgrade your package.');
            }
        }
        if ($request->is(['portal*'])) {
            //checks limits for starter
            if ($decodedPurchaseCode->product_package->name == 'Starter') {
                Auth::logout();
                abort(403, "Portal not available for this package, please contact support");

            }

        }
        return $next($request);
    }
}
