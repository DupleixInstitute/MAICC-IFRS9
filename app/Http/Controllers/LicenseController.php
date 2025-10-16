<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\Vital;
use Carbon\Carbon;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class LicenseController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(['permission:vitals.index'])->only(['index', 'show']);
        $this->middleware(['permission:vitals.create'])->only(['create', 'store']);
        $this->middleware(['permission:vitals.update'])->only(['edit', 'update']);
        $this->middleware(['permission:vitals.destroy'])->only(['destroy']);
    }

    public function index()
    {
        $purchaseCode = Setting::where('setting_key', 'purchase_code')->first()->setting_value;
        $decodedPurchaseCode =  config('app.decoded_purchase_code');
        return Inertia::render('License/Index', [
            'purchaseCode' => $purchaseCode,
            'decodedPurchaseCode' => $decodedPurchaseCode,
        ]);
    }

    public function verify(Request $request)
    {
        $request->validate([
            'purchase_code' => ['required'],
        ]);
        try {
            $publicKey = file_get_contents(storage_path('app/keys/id_rsa_jwt.pub'));
            $decodedPurchaseCode = JWT::decode($request->purchase_code, new Key($publicKey, 'RS256'));
        } catch (\Exception $exception) {
            return redirect()->back()->with('error', 'Invalid license code ' . $exception->getMessage());
        }

        Setting::where('setting_key', 'purchase_code')->update(['setting_value' => $request->purchase_code]);
        return redirect()->route('license.index')->with('success', 'License verified successfully.');
    }


}
