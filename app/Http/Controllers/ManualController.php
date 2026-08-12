<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Inertia\Inertia;

/**
 * The IFRS 9 user manual: an on-screen, navigable guide (manual.view) plus a
 * downloadable PDF (manual.pdf). Plain-language, step-by-step, IFRS 9
 * workflow only — no credit scoring.
 */
class ManualController extends Controller
{
    private function company(): string
    {
        try {
            return optional(Setting::where('setting_key', 'company_name')->first())->setting_value
                ?: config('app.name');
        } catch (\Throwable $e) {
            return config('app.name');
        }
    }

    public function page()
    {
        return Inertia::render('Manual/UserManual', [
            'company'      => $this->company(),
            'generated_at' => now()->format('d M Y'),
            'figures'      => $this->figures(),
        ]);
    }

    /**
     * Map manual section ids to the screenshots captured by
     * `php artisan manual:screenshots` (public/manual/screenshots). Only
     * files that actually exist are offered: no capture run, no figures.
     */
    private function figures(): array
    {
        $captions = [
            'login'          => 'The sign-in screen with the security check',
            'dashboard'      => 'IFRS 9 ECL dashboard with period, portfolio and compare-to filters',
            'workspace'      => 'Period workspace: close checklist and team messages',
            'clients'        => 'Client register',
            'portfolios'     => 'Loan portfolio setup',
            'loanbook'       => 'Loan book with stage, EAD, PD, LGD and ECL columns',
            'imports'        => 'Import job history',
            'collateral'     => 'Collateral allocations',
            'eir-intake'     => 'EIR schedule intake',
            'eir-fees'       => 'EIR fee classification work queue',
            'eir-rules'      => 'EIR accounting rules register',
            'staging'        => 'Staging and SICR thresholds',
            'tprofiles'      => 'Transition profile definitions',
            'tmatrix'        => 'Monthly transition matrices',
            'lgd'            => 'Loss Given Default runs',
            'fli'            => 'Probability-weighted macro forecast',
            'ecl'            => 'ECL calculation screen',
            'reports'        => 'IFRS 9 reporting suite',
            'stress-testing' => 'Stress testing: PD/LGD drivers and macro scenario modes',
            'ews'            => 'Early warning system watchlist',
            'tickets'        => 'Support ticket tracker',
            'users'          => 'User management',
            'roles'          => 'Roles and permission matrix',
            'settings'       => 'Settings hub',
        ];

        $sections = [
            'login'     => ['login'],
            'dashboard' => ['dashboard'],
            'workspace' => ['workspace'],
            'clients'   => ['clients'],
            'portfolios' => ['portfolios'],
            'loanbook'  => ['loanbook', 'imports'],
            'tprofiles' => ['tprofiles'],
            'tmatrix'   => ['tmatrix'],
            'lgd'       => ['lgd'],
            'fli'       => ['fli'],
            'ecl'       => ['ecl'],
            'reports'   => ['reports', 'stress-testing', 'ews'],
            'settings'  => ['settings', 'roles'],
        ];

        $figures = [];
        $n = 0;
        foreach ($sections as $sectionId => $keys) {
            foreach ($keys as $key) {
                if (! is_file(public_path("manual/screenshots/{$key}.jpg"))) {
                    continue;
                }
                $n++;
                $figures[$sectionId][] = [
                    'src'     => asset("manual/screenshots/{$key}.jpg"),
                    'caption' => 'Figure ' . $n . '. ' . ($captions[$key] ?? $key),
                ];
            }
        }

        return $figures;
    }

    public function show()
    {
        $company = config('app.name');

        try {
            $company = optional(Setting::where('setting_key', 'company_name')->first())->setting_value ?: $company;
        } catch (\Throwable $e) {
            // settings table may be unavailable in some environments
        }

        return Pdf::loadView('manual.ifrs9', [
            'company'      => $company,
            'generated_at' => now()->format('d M Y'),
        ])->setPaper('a4', 'portrait')
          ->download('IFRS9-User-Manual.pdf');
    }
}
