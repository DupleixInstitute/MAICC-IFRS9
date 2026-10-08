<?php

namespace App\Services\Eir;

use App\Services\Ebanker\LandingZoneReader;
use App\Services\Ebanker\LoanBookBuildService;
use Illuminate\Support\Facades\DB;

/**
 * The acceptance ties of spec v4 section 9 and the golden numbers of 6.10.2,
 * read from the database: run by eir:bootstrap --verify, by eir:baselines,
 * and by the compliance workbooks' Baselines sheet (section 12.3), so that
 * all three show the same figures.
 */
class BaselineService
{
    public function __construct(private LandingZoneReader $zone)
    {
    }

    /** @return list<array{what:string,expected:string,actual:string,ok:bool,expected_value:?float,actual_value:?float}> */
    public function checks(): array
    {
        $zone = $this->zone;
        $checks = [];

        // contractual interest from the ledger, Jan 2025 to Jul 2026 (section 9)
        $total = 0.0;
        foreach ($zone->ledgerByAccount('2026-07-31') as $posts) {
            foreach ($posts as $p) {
                if (in_array((string) ($p['payload']['TRANTYPE'] ?? ''), LoanBookBuildService::TYPE_INTEREST, true) && $p['row_date'] >= '2025-01-01') {
                    $total -= (float) str_replace(',', '', (string) ($p['payload']['TRANSAMT'] ?? 0));
                }
            }
        }
        $checks[] = ['Interest posted Jan 2025 to Jul 2026 (ledger, types 303 and 120)', '5,293,988,207.06', number_format($total, 2), abs($total - 5293988207.06) < 0.01];

        // method B ties to the stored report on every account-month but the one-cent row
        $flagged = DB::table('loan_books')->where('build_method', 'B')->whereNotNull('stored_carrying_amount')->whereRaw('abs(carrying_amount - stored_carrying_amount) > 0.02')->count();
        $tied = DB::table('loan_books')->where('build_method', 'B')->whereNotNull('stored_carrying_amount')->count();
        $checks[] = ['Derived carrying amount against the stored report (over 2 cents)', '0 differences', "{$flagged} of {$tied} account-months", $flagged === 0];

        // the Diff-Int year-end batch nets to the two income GLs (section 3.4)
        $net = ['4215' => 0.0, '4216' => 0.0];
        foreach ($zone->family(['GL_03']) as $r) {
            $gl = (string) ($r['payload']['AC_GLCODE'] ?? '');
            if (isset($net[$gl])) {
                $net[$gl] += (float) ($r['payload']['TRANSAMT'] ?? 0);
            }
        }
        $checks[] = ['Year-end interest batch contra on 4215', '32,956,675.55', number_format($net['4215'], 2), abs($net['4215'] - 32956675.55) < 0.01];
        $checks[] = ['Year-end interest batch contra on 4216', '-21,733,262.70', number_format($net['4216'], 2), abs($net['4216'] + 21733262.70) < 0.01];

        // loan balances by GL at 31 Dec 2025 against the audited mapping (when the TB bridge is loaded)
        $dec = DB::table('loan_books')->where('reporting_period', '2025-12')->whereIn('product_code', array_keys(LandingZoneReader::LOAN_GLS))->selectRaw('product_code, round(sum(carrying_amount), 2) ca')->groupBy('product_code')->pluck('ca', 'product_code');
        $tbTable = DB::getSchemaBuilder()->hasTable('gl_trial_balance_lines');
        // the keyed GL openings of section 3.5, accepted on the load until Finance corrects them
        $accepted = ['1050201' => -400000.00, '1050202' => 1000000.00];
        foreach ($dec as $gl => $ca) {
            if (! $tbTable) {
                continue;
            }
            $line = DB::table('gl_trial_balance_lines')->where('period', 'like', '2025-12%')->where('gl_code', $gl)->orderByDesc('id')->first();
            $tb = $line ? (float) $line->debit - (float) $line->credit : null;
            if ($tb === null && abs((float) $ca) < 0.005) {
                continue; // no balance and no line: nothing to tie
            }
            $diff = $tb === null ? null : round((float) $ca - $tb, 2);
            $ok = $diff !== null && abs($diff) < 1;
            $label = "Loan book {$gl} at 31 Dec 2025 against the trial balance";
            if (! $ok && $diff !== null && isset($accepted[$gl]) && abs($diff - $accepted[$gl]) < 1) {
                $label .= ' (accepted exception, spec 3.5: ' . number_format($accepted[$gl], 2) . ')';
                $ok = true;
            }
            $checks[] = [$label, $tb === null ? 'no TB line' : number_format($tb, 2), number_format((float) $ca, 2), $ok];
        }

        $out = [];
        foreach ($checks as [$what, $expected, $actual, $ok]) {
            $out[] = ['what' => $what, 'expected' => $expected, 'actual' => $actual, 'ok' => (bool) $ok,
                'expected_value' => is_numeric(str_replace(',', '', $expected)) ? (float) str_replace(',', '', $expected) : null,
                'actual_value' => is_numeric(str_replace(',', '', $actual)) ? (float) str_replace(',', '', $actual) : null];
        }

        return $out;
    }
}
