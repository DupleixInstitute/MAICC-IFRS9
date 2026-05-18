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
        ]);
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
