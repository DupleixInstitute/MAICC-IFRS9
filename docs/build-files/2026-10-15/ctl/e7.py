from ed import edit
p='routes/web.php'
edit(p, r"""    Route::post('/store-loan-bands', [SettingsController::class, 'storeLoanBands'])->name('settings.other.store-bands');
});""", r"""    Route::post('/store-loan-bands', [SettingsController::class, 'storeLoanBands'])->name('settings.other.store-bands');
    // the days-past-due thresholds under maker-checker (system audit H11)
    Route::post('/staging-thresholds/propose', [\App\Http\Controllers\StagingThresholdController::class, 'propose'])->name('settings.staging-thresholds.propose');
    Route::post('/staging-thresholds/{threshold}/approve', [\App\Http\Controllers\StagingThresholdController::class, 'approve'])->name('settings.staging-thresholds.approve');
    Route::post('/staging-thresholds/{threshold}/reject', [\App\Http\Controllers\StagingThresholdController::class, 'reject'])->name('settings.staging-thresholds.reject');
});""")
p='app/Http/Controllers/SettingsController.php'
edit(p, r"""            $thresholds = StagingThreshold::query()
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
                ])->all();""", r"""            $hasStatus = \Illuminate\Support\Facades\Schema::hasColumn('staging_thresholds', 'status');
            $names = $hasStatus ? \App\Models\User::query()->whereIn('id', StagingThreshold::query()->pluck('proposed_by')->merge(StagingThreshold::query()->pluck('approved_by'))->filter()->unique())->pluck('name', 'id') : collect();
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
                    // maker-checker (H11): only an approved row whose date has come governs
                    'status' => $hasStatus ? $row->status : 'APPROVED',
                    'proposer' => $hasStatus && $row->proposed_by ? $names->get($row->proposed_by) : null,
                    'proposed_by' => $hasStatus ? $row->proposed_by : null,
                    'approver' => $hasStatus && $row->approved_by ? $names->get($row->approved_by) : null,
                    'reason' => $hasStatus ? $row->reason : null,
                    'in_force' => (! $hasStatus || $row->status === StagingThreshold::STATUS_APPROVED) && $row->effective_from !== null && $row->effective_from->toDateString() <= $today,
                ])->all();""")
edit(p, r"""        } catch (\Throwable) {
            // table absent: the classifier falls back to its own ladder
        }""", r"""        } catch (\Throwable) {
            // table absent: shown as none loaded; staging then stops and names the missing rule
        }""")
