p = r'c:\xampp\htdocs\MAICC-IFRS9\app\Services\Reports\Ifrs9NoteService.php'
s = open(p, encoding='utf-8').read()


def rep(old, new):
    global s
    assert s.count(old) == 1, old[:80]
    s = s.replace(old, new, 1)


rep(""" * EclMovementAttribution (pure, unit-tested). Booked basis and write-offs
 * built 14 October 2026; until then the note read the undiscounted ecl_value
 * and showed nil written off.
 */""", """ * EclMovementAttribution (pure, unit-tested). Booked basis and write-offs
 * built 14 October 2026; until then the note read the undiscounted ecl_value
 * and showed nil written off.
 *
 * Modified financial assets (IFRS 7.35J(a), 35J(b) and 20A, with the IFRS 9
 * B5.4.6 catch-up) come from the confirmed restructure register as the
 * revenue run applied them (RestructureRegisterService::ifrs7Disclosures), for
 * the period from the day after the opening month-end to the closing
 * month-end. A candidate found in E-Banker's reschedule history is never a
 * modification until two people confirm it: the note says how many await
 * that, and discloses none of them (built 14 October 2026).
 */""")
rep("""            'enforcement' => WriteOffSource::enforcementDisclosure(),
        ];
        $report['checks'] = self::checks($report, $pairs, $newGroups);""", """            'enforcement' => WriteOffSource::enforcementDisclosure(),
            'modifications' => self::modifications($opening, $closing, $portfolioId),
        ];
        $report['checks'] = self::checks($report, $pairs, $newGroups);""")

method = r'''    /**
     * IFRS 7.35J(a), 35J(b) and 20A, and the B5.4.6 catch-up, for the note's period: the
     * day after the opening month-end to the closing month-end, from the modifications the
     * revenue run applied (RestructureRegisterService::ifrs7Disclosures), in the note's
     * scope. Honest when there are none, and about the candidates that await a person.
     */
    private static function modifications(string $opening, string $closing, ?int $portfolioId): array
    {
        $from = \Carbon\CarbonImmutable::parse($opening . '-01')->addMonthNoOverflow()->startOfMonth()->toDateString();
        $to = \Carbon\CarbonImmutable::parse($closing . '-01')->endOfMonth()->toDateString();
        if (! Schema::hasTable('restructure_events') || ! Schema::hasTable('contract_modifications')) {
            return ['installed' => false, 'from' => $from, 'to' => $to, 'none' => true, 'candidates' => 0, 'proposed' => 0, 'pending' => 0, 'blocked' => 0,
                'message' => 'The restructure register is not installed (run the migrations), so no modified financial asset can be disclosed (IFRS 7.35J, 20A).'];
        }
        $contracts = $portfolioId
            ? DB::table('loan_books')->where('loan_portfolio_id', $portfolioId)->whereIn('reporting_period', [$opening, $closing])->distinct()->pluck('contract_id')->map(fn ($c) => (string) $c)->all()
            : null;
        $d = (new \App\Services\Eir\RestructureRegisterService())->ifrs7Disclosures($from, $to, $contracts)
            + \App\Services\Eir\RestructureRegisterService::awaitingConfirmation($from, $to, $contracts);
        $d['installed'] = true;
        $d['none'] = $d['all_modified']['count'] === 0 && $d['derecognised']['count'] === 0 && $d['catch_up']['count'] === 0
            && $d['lifetime_modified_now_12_month']['count'] === 0 && $d['pending'] === 0 && $d['blocked'] === 0;
        $range = 'the end of ' . self::period($opening) . ' and the end of ' . self::period($closing);
        $parts = [$d['none']
            ? 'No modification of a financial asset was confirmed and applied between ' . $range . ': there is nothing to disclose under IFRS 7.35J(a), 35J(b) or 20A, and no B5.4.6 catch-up.'
            : 'Modifications confirmed on the restructure register by two people and applied by the revenue run of their month, between ' . $range . '.'];
        if ($d['pending'] > 0) {
            $parts[] = $d['pending'] . ' confirmed modification' . ($d['pending'] === 1 ? ' has' : 's have') . ' not yet been applied by the revenue run of ' . ($d['pending'] === 1 ? 'its' : 'their') . ' month, so ' . ($d['pending'] === 1 ? 'it is' : 'they are') . ' not in the figures.';
        }
        if ($d['blocked'] > 0) {
            $parts[] = $d['blocked'] . ' confirmed modification' . ($d['blocked'] === 1 ? ' was' : 's were') . ' stopped by the revenue run with the reason on the register (EIR Data, Restructures & Resets), so ' . ($d['blocked'] === 1 ? 'it is' : 'they are') . ' not in the figures.';
        }
        $awaiting = $d['candidates'] + $d['proposed'];
        $parts[] = $awaiting > 0
            ? $awaiting . ' candidate restructure' . ($awaiting === 1 ? '' : 's') . ' found in E-Banker\'s reschedule history ' . ($awaiting === 1 ? 'awaits' : 'await') . ' confirmation ('
                . $d['candidates'] . ' not yet classified, ' . $d['proposed'] . ' proposed and awaiting a second person); a candidate is not a modification until confirmed, and none is disclosed here.'
            : 'No candidate restructure awaits confirmation.';
        $d['message'] = implode(' ', $parts);

        return $d;
    }

'''
anchor = "    private static function monthTotals(string $period, ?int $portfolioId): array"
idx = s.index(anchor)
prev_end = s.rfind('*/', 0, idx)
if s[prev_end + 2:idx].strip() == '':
    ins = s.rfind('    /**', 0, prev_end)
else:
    ins = idx
s = s[:ins] + method + s[ins:]

rep("""                    . implode(', ', array_unique(array_column($r['write_offs_outside'], 'contract_id'))) . '.'];
        }

        return $checks;""", """                    . implode(', ', array_unique(array_column($r['write_offs_outside'], 'contract_id'))) . '.'];
        }

        // modified financial assets (IFRS 7.35J, 20A): a confirmed modification the revenue run has not
        // applied (pending) or stopped (blocked) leaves the disclosure incomplete, so the note is not issued
        $mod = $r['modifications'];
        $unapplied = (int) ($mod['pending'] ?? 0) + (int) ($mod['blocked'] ?? 0);
        $checks[] = ['key' => 'modifications', 'label' => 'Modified financial assets (IFRS 7.35J, 20A)',
            'level' => ! $mod['installed'] ? 'warn' : ($unapplied > 0 ? 'fail' : 'info'),
            'detail' => $mod['message'] . ($unapplied > 0 ? ' Run the revenue for the month of each confirmed modification (or clear the reason it was stopped) before the note is used.' : '')];

        return $checks;""")
open(p, 'w', encoding='utf-8', newline='').write(s)
print('ok')
