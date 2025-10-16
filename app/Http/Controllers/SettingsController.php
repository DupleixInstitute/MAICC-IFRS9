<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Setting;
use App\Models\Currency;
use App\Models\Timezone;
use App\Models\SmsGateway;
use Illuminate\Http\Request;
use App\Models\LoanApplicationBand;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class SettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(['permission:settings'])->only(['index', 'show']);
    }

    public function index()
    {
        return Inertia::render('Settings/Index', [
        ]);
    }

    public function organisation()
    {
        return Inertia::render('Settings/Organisation', [
        ]);
    }

    public function general()
    {
        $settings = Setting::where('category', 'general')->get();
        return Inertia::render('Settings/General', [
            'settings' => $settings->keyBy('setting_key'),
        ]);
    }

    public function generalUpdate(Request $request)
    {

        Setting::where('setting_key', 'company_name')->update(['setting_value' => $request->company_name]);
        Setting::where('setting_key', 'company_email')->update(['setting_value' => $request->company_email]);
        Setting::where('setting_key', 'company_mobile')->update(['setting_value' => $request->company_mobile]);
        Setting::where('setting_key', 'company_tel')->update(['setting_value' => $request->company_tel]);
        Setting::where('setting_key', 'invoice_reference_prefix')->update(['setting_value' => $request->invoice_reference_prefix]);
        Setting::where('setting_key', 'invoice_reference_format')->update(['setting_value' => $request->invoice_reference_format]);
        Setting::where('setting_key', 'sales_email')->update(['setting_value' => $request->sales_email]);
        Setting::where('setting_key', 'invoice_due_after_days')->update(['setting_value' => $request->invoice_due_after_days]);
        Setting::where('setting_key', 'invoice_terms_and_conditions')->update(['setting_value' => $request->invoice_terms_and_conditions]);
        if ($request->hasFile('company_logo')) {

            $fileName = $request->file('company_logo')->store('public');
            Setting::where('setting_key', 'company_logo')->update(['setting_value' => basename($fileName)]);
        }
        if ($request->hasFile('company_small_logo')) {

            $fileName = $request->file('company_small_logo')->store('public');
            Setting::where('setting_key', 'company_small_logo')->update(['setting_value' => basename($fileName)]);
        }
        if ($request->hasFile('company_letterhead')) {
            $fileName = $request->file('company_letterhead')->store('public');
            Setting::where('setting_key', 'company_letterhead')->update(['setting_value' => basename($fileName)]);
        }
        return redirect()->route('settings.general')->with('success', 'Successfully saved.');
    }

    public function system()
    {
        $settings = Setting::where('category', 'system')->get();
        return Inertia::render('Settings/System', [
            'settings' => $settings->keyBy('setting_key'),
            'timezones' => Timezone::get()->transform(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->zone_name,
                ];
            }),
            'currencies' => Currency::where('active', 1)->get()->transform(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
        ]);
    }

    public function systemUpdate(Request $request)
    {
        Setting::where('setting_key', 'site_online')->update(['setting_value' => $request->site_online]);
        Setting::where('setting_key', 'timezone')->update(['setting_value' => $request->timezone]);
        Setting::where('setting_key', 'currency')->update(['setting_value' => $request->currency]);
        Setting::where('setting_key', 'purchase_code')->update(['setting_value' => $request->purchase_code]);
        Setting::where('setting_key', 'purchase_code_type')->update(['setting_value' => $request->purchase_code_type]);
        Setting::where('setting_key', 'allow_self_registration')->update(['setting_value' => $request->allow_self_registration]);
        return redirect()->route('settings.system')->with('success', 'Successfully saved.');
    }

    public function email()
    {
        $settings = Setting::where('category', 'email')->get();
        return Inertia::render('Settings/Email', [
            'settings' => $settings->keyBy('setting_key'),
        ]);

    }

    public function emailUpdate(Request $request)
    {
        Setting::where('setting_key', 'mail_mailer')->update(['setting_value' => $request->mail_mailer]);
        Setting::where('setting_key', 'mail_host')->update(['setting_value' => $request->mail_host]);
        Setting::where('setting_key', 'mail_port')->update(['setting_value' => $request->mail_port]);
        Setting::where('setting_key', 'mail_username')->update(['setting_value' => $request->mail_username]);
        Setting::where('setting_key', 'mail_password')->update(['setting_value' => $request->mail_password]);
        Setting::where('setting_key', 'mail_encryption')->update(['setting_value' => $request->mail_encryption]);
        Setting::where('setting_key', 'mail_from_address')->update(['setting_value' => $request->mail_from_address]);
        Setting::where('setting_key', 'mail_from_name')->update(['setting_value' => $request->mail_from_name]);
        return redirect()->route('settings.email')->with('success', 'Successfully saved.');
    }

    public function sms()
    {
        $settings = Setting::where('category', 'sms')->get();
        return Inertia::render('Settings/Sms', [
            'settings' => $settings->keyBy('setting_key'),
            'smsGateways' => SmsGateway::where('active', 1)->get(),
        ]);
    }

    public function smsUpdate(Request $request)
    {
        Setting::where('setting_key', 'sms_enabled')->update(['setting_value' => $request->sms_enabled]);
        Setting::where('setting_key', 'active_sms_gateway')->update(['setting_value' => $request->active_sms_gateway]);
        return redirect()->route('settings.sms')->with('success', 'Successfully saved.');
    }

    public function other()
    {
        return Inertia::render('Settings/Other', [
        ]);
    }

    public function billing()
    {
        return Inertia::render('Settings/Billing', [
        ]);
    }

    public function update()
    {
        return Inertia::render('Settings/Billing', [
        ]);
    }
    public function loanBands()
    {
        $loanApplicationBands = LoanApplicationBand::all();
        return Inertia::render('Settings/LoanBands', [
            'loanApplicationBands' => $loanApplicationBands,
        ]);
    }
    public function storeLoanBands(Request $request)
    {
        $bands = $request->all();

        foreach ($bands as $band) {
            $validator = Validator::make($band, [
                'min' => 'required|integer|min:0|max:1000',
                'max' => 'required|integer|min:0|max:1000',
                'name' => 'required|string',
            ]);
            if ($validator->fails()) {
                //retun back with error message
                return redirect()->back()->withErrors($validator)->withInput();
            }
        }

        // Check for overlapping bands
        for ($i = 0; $i < count($bands); $i++) {
            for ($j = $i + 1; $j < count($bands); $j++) {
                if ($this->bandsOverlap($bands[$i], $bands[$j])) {
                    return redirect()->back()->with('error', 'Bands must not overlap')->withInput();
                }
            }
        }

        // Save bands
        LoanApplicationBand::truncate(); // Remove existing bands
        foreach ($bands as $band) {
            //add user id to the band
            $band['created_by'] = Auth::id();
            LoanApplicationBand::create($band);
        }

        return redirect()->route('settings.loan_bands')->with('success', 'Bands saved successfully!');

    }
     private function bandsOverlap($band1, $band2)
    {
        return $band1['min'] <= $band2['max'] && $band1['max'] >= $band2['min'];
    }
}
