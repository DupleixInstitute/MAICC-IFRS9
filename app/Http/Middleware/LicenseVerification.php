<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Carbon\Carbon;
use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;

class LicenseVerification
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
        if ($request->is('license*') || $request->is('login*') || App::environment('demo') || App::environment('testing')) {
            return $next($request);
        }
        $decodedPurchaseCode = config('app.decoded_purchase_code');
        if (empty($decodedPurchaseCode)) {
            return redirect()->route('license.index')->with('error', 'License Verification failed. Please verify your license');
        }

        if (Carbon::parse($decodedPurchaseCode->end_date)->lessThan(Carbon::today()) && $decodedPurchaseCode->expires) {
            //license has expired
            return redirect()->route('license.index')->with('error', 'License has expired. Please verify your license');
        }
        //warn 10 days left

        return $next($request);
    }
}
