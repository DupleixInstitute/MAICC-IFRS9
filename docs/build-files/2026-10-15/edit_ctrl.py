import io
p = r'c:\xampp\htdocs\MAICC-IFRS9\app\Http\Controllers\ExpectedCreditLossController.php'
s = io.open(p, encoding='utf-8', newline='').read()


import re
def rep(old, new, count=1):
    global s
    NL = chr(10)
    lines = old.split(NL)
    pat = re.compile((r'[ \t]*' + r'\n').join(re.escape(x.rstrip(' \t')) for x in lines))
    found = pat.findall(s)
    assert len(found) == count, (old[:100], len(found))
    s = pat.sub(lambda m: new, s)


rep("""use App\\Services\\Ecl\\EclDiscountingService;
use App\\Services\\Ecl\\TimePhasedEclService;""", """use App\\Services\\Ecl\\EclExposure;
use App\\Services\\Ecl\\EclRunRefused;
use App\\Services\\Ecl\\TimePhasedEclService;""")

rep("""            try {
                $latestPeriod = ReportingPeriods::where('ecl_calculated', 1)
                    ->orderByDesc('period')
                    ->value(DB::raw("DATE_FORMAT(period, '%Y-%m')"));
            } catch (\\Exception $e) {
                // Fallback: get the latest period from loan_books if reporting_periods is not accessible
                $latestPeriod = LoanBook::max('reporting_period');
                \\Log::warning('Reporting periods table not accessible, using loan_books fallback: ' . $e->getMessage());
            }""", """            // the latest month the ECL was calculated for; an unreadable table is an error, never
            // replaced by the latest month loaded (that month may hold no ECL at all)
            $latestPeriod = ReportingPeriods::where('ecl_calculated', 1)
                ->orderByDesc('period')
                ->value(DB::raw("DATE_FORMAT(period, '%Y-%m')"));""")

rep("""            try {
                $latestPeriod = ExpectedCreditLoss::select('reporting_period')
                    ->orderBy('reporting_period', 'desc')
                    ->value('reporting_period');

                $previousPeriod = ExpectedCreditLoss::select('reporting_period')
                    ->where('reporting_period', '!=', $latestPeriod)
                    ->orderBy('reporting_period', 'desc')
                    ->value('reporting_period');
            } catch (\\Exception $e) {
                // Fallback: get periods from loan_books if ExpectedCreditLoss is not accessible
                $periods = LoanBook::select('reporting_period')
                    ->distinct()
                    ->orderBy('reporting_period', 'desc')
                    ->pluck('reporting_period')
                    ->toArray();

                $latestPeriod = $periods[0] ?? null;
                $previousPeriod = $periods[1] ?? null;
                \\Log::warning('ExpectedCreditLoss table not accessible, using loan_books fallback: ' . $e->getMessage());
            }""", """            // the periods the ECL was calculated for, never the loaded months in their place
            $latestPeriod = ExpectedCreditLoss::select('reporting_period')
                ->orderBy('reporting_period', 'desc')
                ->value('reporting_period');

            $previousPeriod = ExpectedCreditLoss::select('reporting_period')
                ->where('reporting_period', '!=', $latestPeriod)
                ->orderBy('reporting_period', 'desc')
                ->value('reporting_period');""")

rep("""            try {
                $totalCalculations = ExpectedCreditLoss::select('reporting_period')->distinct()->count();
                $periods = ExpectedCreditLoss::select('reporting_period')
                    ->distinct()
                    ->orderBy('reporting_period', 'desc')
                    ->pluck('reporting_period')
                    ->take(5);
            } catch (\\Exception $e) {
                // Fallback: use loan_books data
                $totalCalculations = LoanBook::select('reporting_period')->distinct()->count();
                $periods = LoanBook::select('reporting_period')
                    ->distinct()
                    ->orderBy('reporting_period', 'desc')
                    ->pluck('reporting_period')
                    ->take(5);
                \\Log::warning('ExpectedCreditLoss table not accessible for summary, using loan_books fallback: ' . $e->getMessage());
            }""", """            $totalCalculations = ExpectedCreditLoss::select('reporting_period')->distinct()->count();
            $periods = ExpectedCreditLoss::select('reporting_period')
                ->distinct()
                ->orderBy('reporting_period', 'desc')
                ->pluck('reporting_period')
                ->take(5);""")

rep("""         public function calculateECL(Request $request, EclDiscountingService $discounting, ?TimePhasedEclService $timePhased = null)""",
    """         public function calculateECL(Request $request, ?TimePhasedEclService $timePhased = null)""")

rep("""                $exposedSql = '(IFNULL(carrying_amount, 0) + IFNULL(commitments, 0) * IFNULL(facility_utilisation_rate, 1)) > 0';""",
    """                // the engine's EAD (EclExposure): floored at nil, and nil for a loan written off to nil
                $hasBasis = EclExposure::hasBasis();
                $eadSql = EclExposure::sql($hasBasis);
                $exposedSql = "{$eadSql} > 0";""")

rep("""                    return redirect()->route('expected-credit-loss.index')->with('error', $message);
                }

                /*
                |--------------------------------------------------------------------------
                | CONCURRENCY GUARD""", """                    return redirect()->route('expected-credit-loss.index')->with('error', $message);
                }

                /*
                | Discounted ECL is the time-phased engine's or nothing. It used to fall
                | back to a single-horizon proxy (EclDiscountingService) when a run had
                | neither the governed scenario set on its loans nor the legacy
                | assumptions; the run is now refused with the reason instead, and the
                | engine refuses a run with no scenario set itself.
                */
                if ($discountingMode === 'discounted' && ! \\Schema::hasTable('ecl_projection_runs')) {
                    $message = "Discounted ECL not calculated for {$period}: the time-phased engine's tables (ecl_projection_runs) are not installed; run the migrations. No proxy is applied.";
                    if (app()->runningInConsole()) {
                        throw new EclRunRefused($message);
                    }

                    return redirect()->route('expected-credit-loss.index')->with('error', $message);
                }

                /*
                |--------------------------------------------------------------------------
                | CONCURRENCY GUARD""")

rep("""                            ecl_value = IFNULL($stagePdExpr, 0) * IFNULL($lgdExpr, 0)
                                * (CASE WHEN (IFNULL(carrying_amount, 0) + IFNULL(commitments, 0) * IFNULL(facility_utilisation_rate, 1)) > 0
                                    THEN (IFNULL(carrying_amount, 0) + IFNULL(commitments, 0) * IFNULL(facility_utilisation_rate, 1)) ELSE 0 END)""",
    """                            ecl_value = IFNULL($stagePdExpr, 0) * IFNULL($lgdExpr, 0) * $eadSql""")

rep("""                    if ($discountingMode === 'discounted') {
                        $loans = (clone $scope)->get();
                        // The complete time-phased engine is authoritative on
                        // current schemas. The fallback keeps isolated legacy
                        // test schemas operational and remains visibly labelled.
                        // the time-phased engine runs on the governed set the route wrote on
                        // the loans (fli_by_scenario) as well as on the legacy assumptions; it
                        // used to need the legacy table, so a book run under the governed set
                        // fell to the horizon proxy below
                        $governedOnLoans = \\Schema::hasColumn('loan_books', 'fli_by_scenario') && $loans->contains(fn ($l) => ! empty($l->fli_by_scenario) && $l->fli_by_scenario !== '[]');
                        if (\\Schema::hasTable('ecl_projection_runs') && ($governedOnLoans || DB::table('ecl_scenario_assumptions')->where('status','APPROVED')->exists())) {
                            $discountResult = ($timePhased ?? app(TimePhasedEclService::class))->run(
                                $loans, $period, $validated['lgd_type'], auth()->id(), $validated['pd_type']
                            );
                        } else {
                            $discountResult = $discounting->apply($loans, $period, $runId);
                        }
                    } else {
                        (clone $scope)->update([""", """                    if ($discountingMode === 'discounted') {
                        // the time-phased engine, on the governed set the route wrote on the loans
                        // or the approved legacy assumptions; with neither it refuses the run
                        // (EclRunRefused) and the transaction is rolled back whole
                        $discountResult = ($timePhased ?? app(TimePhasedEclService::class))->run(
                            (clone $scope)->get(), $period, $validated['lgd_type'], auth()->id(), $validated['pd_type']
                        );
                    } else {
                        // an undiscounted run leaves nothing of an earlier discounted one on the loans
                        (clone $scope)->update((\\Schema::hasColumn('loan_books', 'ecl_by_scenario') ? ['ecl_by_scenario' => null] : []) + [""")

rep("""                            SUM(COALESCE(carrying_amount, 0) + COALESCE(commitments, 0) * COALESCE(facility_utilisation_rate, 1)) AS total_ead,""",
    """                            SUM(" . EclExposure::unflooredSql($hasBasis) . ") AS total_ead,""")

rep("""                } catch (\\Throwable $e) {
                    DB::rollBack();
                    throw $e;
                } finally {""", """                } catch (\\Throwable $e) {
                    DB::rollBack();
                    if ($e instanceof EclRunRefused && ! app()->runningInConsole()) {
                        return redirect()->route('expected-credit-loss.index')->with('error', $e->getMessage());
                    }
                    throw $e;
                } finally {""")

io.open(p, 'w', encoding='utf-8', newline='').write(s)
print('ok')
