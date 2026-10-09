<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Setting;
use App\Models\Currency;
use App\Models\SmsGateway;
use Illuminate\Http\Request;
use App\Models\GovernanceSetting;
use App\Models\StagingThreshold;
use App\Models\LoanApplicationBand;
use App\Services\Eir\GovernanceService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class SettingsController extends Controller
{
    private const TABS = ['organisation', 'reporting', 'email', 'staging', 'more'];

    private const ORGANISATION_KEYS = [
        'company_name', 'company_email', 'company_mobile', 'company_tel', 'company_website', 'company_address',
    ];

    private const EMAIL_KEYS = [
        'mail_mailer', 'mail_host', 'mail_port', 'mail_username', 'mail_password',
        'mail_encryption', 'mail_from_address', 'mail_from_name',
    ];

    /** Governed staging settings shown read only, with a one-line help each. */
    private const STAGING_GOVERNED = [
        'dpd_basis' => 'Which date days past due are counted from when a month-end loan book is built.',
        'stage3_missed_instalments' => 'A loan that misses this many instalments in a row goes to Stage 3, whatever its days past due.',
        'stage_cure_months' => 'How many month-ends a loan stays in its stage after it catches up, before it moves down.',
        'staging_rebuttal' => 'Whether the 30-day Stage 2 rule may be set aside for a loan, with evidence approved by a second person.',
    ];

    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(['permission:settings'])->only(['index', 'show']);
    }

    /**
     * The settings screen: one page, in-page tabs. It shows only what an
     * IFRS 9 ECL and EIR system uses: organisation details, the reporting
     * currency, outgoing email, and (read only) the governed staging basis.
     * The settings inherited from the platform this system was cloned from
     * (SMS gateway, loan application score bands, approval stages, invoice
     * fields, licence type, self registration, timezone and site online,
     * which nothing reads) are no longer shown; their routes and rows are kept.
     */
    public function index(Request $request, GovernanceService $governance)
    {
        $settings = Setting::whereIn('category', ['general', 'system', 'email'])
            ->pluck('setting_value', 'setting_key');

        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'organisation';

        return Inertia::render('Settings/Index', [
            'tab' => $tab,
            'organisation' => collect(self::ORGANISATION_KEYS)
                ->mapWithKeys(fn ($key) => [$key => (string) ($settings[$key] ?? '')])
                ->merge([
                    'company_logo_url' => ! empty($settings['company_logo']) ? asset('storage/' . $settings['company_logo']) : null,
                    'company_small_logo_url' => ! empty($settings['company_small_logo']) ? asset('storage/' . $settings['company_small_logo']) : null,
                ]),
            'reporting' => [
                'currency' => isset($settings['currency']) ? (string) $settings['currency'] : '',
            ],
            // Every currency, active first: the reporting currency may be one
            // that is not marked active in the currency list.
            'currencies' => Currency::orderByDesc('active')->orderBy('name')->get(['id', 'name', 'code', 'active'])
                ->map(fn ($c) => [
                    'value' => (string) $c->id,
                    'label' => trim($c->name . ($c->code ? ' (' . $c->code . ')' : '')),
                    'code' => $c->code,
                    'active' => (bool) $c->active,
                ]),
            'email' => collect(self::EMAIL_KEYS)
                ->reject(fn ($key) => $key === 'mail_password')
                ->mapWithKeys(fn ($key) => [$key => (string) ($settings[$key] ?? '')])
                // the stored password is never sent to the browser
                ->merge(['mail_password_set' => ! empty($settings['mail_password'])]),
            'staging' => $this->stagingBasis($governance),
        ]);
    }

    /**
     * The governed staging basis, read only. The days-past-due basis, the
     * missed-instalment trigger, the cure period and the rebuttal rule are
     * governed settings (Governance Centre: maker-checker, dated, so a change
     * applies from the month-end on or after its effective date); the day
     * thresholds per facility class are the staging_thresholds rows the
     * StagingClassifier and StagingService read. Settings shows the values in
     * force today and never keeps a second editable copy.
     */
    private function stagingBasis(GovernanceService $governance): array
    {
        $catalogue = GovernanceService::catalogue();
        $today = now()->toDateString();

        $governed = [];
        foreach (self::STAGING_GOVERNED as $key => $help) {
            if (! isset($catalogue[$key])) {
                continue;
            }
            $inForce = null;
            $pending = 0;
            try {
                $inForce = $governance->inForce($key);
                $pending = GovernanceSetting::query()->where('key', $key)
                    ->where(fn ($q) => $q->where('status', GovernanceSetting::STATUS_PROPOSED)
                        ->orWhereDate('effective_from', '>', $today))
                    ->count();
            } catch (\Throwable) {
                // governance table absent (fresh install): shown as not set
            }
            $governed[] = [
                'key' => $key,
                'label' => $catalogue[$key]['label'],
                'help' => $help,
                'value' => $inForce?->value,
                'effective_from' => $inForce?->effective_from?->toDateString(),
                'approved_by' => $inForce?->approver?->name,
                'approved_at' => $inForce?->approved_at?->toDateString(),
                'pending' => $pending,
            ];
        }

        $thresholds = [];
        try {
            $thresholds = StagingThreshold::query()
                ->orderBy('facility_class')->orderBy('min_tenor_months')->orderBy('effective_from')
                ->get()
                ->map(fn ($row) => [
                    'id' => $row->id,
                    'facility_class' => $row->facility_class,
                    'min_tenor_months' => $row->min_tenor_months,
                    'stage2_dpd' => $row->stage2_dpd,
                    'stage3_dpd' => $row->stage3_dpd,
                    'effective_from' => $row->effective_from?->toDateString(),
                    'in_force' => $row->effective_from !== null && $row->effective_from->toDateString() <= $today,
                ])->all();
        } catch (\Throwable) {
            // table absent: the classifier falls back to its own ladder
        }

        return ['governed' => $governed, 'thresholds' => $thresholds];
    }

    /** The old sub-pages open the matching tab of the one settings screen. */
    public function organisation()
    {
        return redirect()->route('settings.index', ['tab' => 'organisation']);
    }

    public function general()
    {
        return redirect()->route('settings.index', ['tab' => 'organisation']);
    }

    public function generalUpdate(Request $request)
    {
        $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'company_email' => ['nullable', 'email', 'max:255'],
            'company_mobile' => ['nullable', 'string', 'max:50'],
            'company_tel' => ['nullable', 'string', 'max:50'],
            'company_website' => ['nullable', 'string', 'max:255'],
            'company_address' => ['nullable', 'string', 'max:1000'],
            'company_logo' => ['nullable', 'image', 'max:2048'],
            'company_small_logo' => ['nullable', 'image', 'max:2048'],
        ], [], [
            'company_name' => 'organisation name',
            'company_email' => 'organisation email',
            'company_mobile' => 'mobile number',
            'company_tel' => 'telephone number',
            'company_website' => 'website',
            'company_address' => 'postal address',
            'company_logo' => 'logo',
            'company_small_logo' => 'small logo',
        ]);

        // Only keys the form sends are written, so settings it does not show
        // (e.g. the old template's invoice fields) are never blanked.
        foreach (self::ORGANISATION_KEYS as $key) {
            if ($request->has($key)) {
                Setting::where('setting_key', $key)->update(['setting_value' => (string) $request->input($key)]);
            }
        }
        foreach (['company_logo', 'company_small_logo'] as $key) {
            if ($request->hasFile($key)) {
                $fileName = $request->file($key)->store('public');
                Setting::where('setting_key', $key)->update(['setting_value' => basename($fileName)]);
            }
        }

        // the layout's cached settings map (HandleInertiaRequests) is dropped so
        // the new name and logo show on the next page
        cache()->forget('inertia.settings.map');

        return redirect()->route('settings.index', ['tab' => 'organisation'])->with('success', 'Organisation details saved.');
    }

    public function system()
    {
        return redirect()->route('settings.index', ['tab' => 'reporting']);
    }

    public function systemUpdate(Request $request)
    {
        $request->validate([
            'currency' => ['required', 'integer', 'exists:currencies,id'],
        ], [], ['currency' => 'reporting currency']);

        // Only the reporting currency is edited here; the other system rows
        // (site online, timezone, licence, self registration) are not read by
        // the IFRS 9 or EIR engines and are left as they are.
        Setting::where('setting_key', 'currency')->update(['setting_value' => (string) $request->input('currency')]);

        cache()->forget('inertia.settings.map');

        return redirect()->route('settings.index', ['tab' => 'reporting'])->with('success', 'Reporting currency saved.');
    }

    public function email()
    {
        return redirect()->route('settings.index', ['tab' => 'email']);
    }

    public function emailUpdate(Request $request)
    {
        $request->validate([
            'mail_mailer' => ['required', 'in:smtp,sendmail'],
            'mail_host' => ['nullable', 'required_if:mail_mailer,smtp', 'string', 'max:255'],
            'mail_port' => ['nullable', 'required_if:mail_mailer,smtp', 'integer', 'between:1,65535'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:255'],
            'mail_encryption' => ['nullable', 'in:tls,ssl'],
            'mail_from_address' => ['nullable', 'email', 'max:255'],
            'mail_from_name' => ['nullable', 'string', 'max:255'],
        ], [], [
            'mail_mailer' => 'sending method',
            'mail_host' => 'mail server',
            'mail_port' => 'port',
            'mail_username' => 'username',
            'mail_password' => 'password',
            'mail_encryption' => 'encryption',
            'mail_from_address' => 'sender address',
            'mail_from_name' => 'sender name',
        ]);

        foreach (self::EMAIL_KEYS as $key) {
            if (! $request->has($key)) {
                continue;
            }
            // a blank password keeps the one already saved
            if ($key === 'mail_password' && ! $request->filled('mail_password')) {
                continue;
            }
            Setting::where('setting_key', $key)->update(['setting_value' => (string) $request->input($key)]);
        }

        return redirect()->route('settings.index', ['tab' => 'email'])->with('success', 'Email settings saved.');
    }

    // The screens below came with the platform this system was cloned from
    // (SMS gateway, loan application score bands). They are not linked from
    // Settings any more; the routes are kept so nothing that names them breaks.
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
        return redirect()->route('settings.index', ['tab' => 'more']);
    }

    public function billing()
    {
        return redirect()->route('settings.index');
    }

    public function update()
    {
        return redirect()->route('settings.index');
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

        LoanApplicationBand::truncate();
        foreach ($bands as $band) {
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
