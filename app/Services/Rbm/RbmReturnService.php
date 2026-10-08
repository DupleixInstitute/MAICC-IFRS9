<?php

namespace App\Services\Rbm;

use App\Services\Ebanker\LandingZoneReader;
use App\Services\Ebanker\LoanBookBuildService;
use Illuminate\Support\Facades\DB;

/**
 * The classification and provisioning return to the Reserve Bank of Malawi
 * (the DFI directive of 2018, section 17), PROVISIONAL LAYOUT.
 *
 * The directive requires the return "in the prescribed form"; the form
 * itself is MAIIC Risk's and is not yet in the repository. This service
 * fills the lines such a return invariably carries, from the system, in
 * the directive's own order: the facilities by class and term under the
 * directive's day bands (section 10 and 11), the minimum provision per
 * class (section 12) against the IFRS 9 allowance held, interest in
 * suspense on non-performing facilities (section 13), restructured
 * facilities (section 15) and the security held. When the prescribed form
 * arrives, LINES is re-ordered and re-worded to it line for line and the
 * figures stay as they are; until then every output says "provisional".
 *
 * Bands, by the term of the facility (section 2: short-term 12 months or
 * less; medium and long term over 12), from section 10 of the Gazette
 * (docs/regulatory, pages 683 and 685):
 *                     short-term   | medium and long
 *   Pass (standard)   0 to 30      | 0 to 90          provision  0 percent
 *   Special mention   31 to 90     | 91 to 180        provision  5 percent
 *   Substandard       91 to 180    | 181 to 365       provision 20 percent
 *   Doubtful          181 to 365   | 366 to 746       provision 50 percent
 *   Loss              over 365     | over 746         provision 100 percent
 */
class RbmReturnService
{
    public const CLASSES = ['Pass', 'Special mention', 'Substandard', 'Doubtful', 'Loss'];
    public const MINIMUM = ['Pass' => 0.00, 'Special mention' => 0.05, 'Substandard' => 0.20, 'Doubtful' => 0.50, 'Loss' => 1.00];
    public const BANDS = ['short' => [30, 90, 180, 365], 'long' => [90, 180, 365, 746]];

    public function __construct(private LandingZoneReader $zone)
    {
    }

    public static function classify(int $dpd, int $tenorMonths): string
    {
        $b = self::BANDS[$tenorMonths <= 12 ? 'short' : 'long'];
        if ($dpd <= $b[0]) { return 'Pass'; }
        if ($dpd <= $b[1]) { return 'Special mention'; }
        if ($dpd <= $b[2]) { return 'Substandard'; }
        if ($dpd <= $b[3]) { return 'Doubtful'; }

        return 'Loss';
    }

    /** @return array{period:string,status:string,sections:array,notes:list<string>} */
    public function build(string $period, bool $includeMegaFarm = false): array
    {
        $q = DB::table('loan_books')->where('reporting_period', $period);
        if (! $includeMegaFarm) {
            $q->whereIn('product_code', array_keys(LandingZoneReader::LOAN_GLS));
        }
        $loans = $q->get(['contract_id', 'customer_name', 'product_group', 'product_code', 'tenor', 'overdue_days', 'overdue_principal_date', 'carrying_amount', 'principal_balance', 'interest_to_date', 'ecl_value', 'ifrs9stage_post_qualitative', 'contract_status']);
        $notes = ['PROVISIONAL LAYOUT: the lines follow the directive\'s own order; the prescribed form from MAIIC Risk re-orders and re-words them line for line when it arrives (directive s.17).'];

        // A. classification by class and term
        $a = [];
        foreach (self::CLASSES as $c) {
            foreach (['short', 'long'] as $t) {
                $a[$c][$t] = ['accounts' => 0, 'balance' => 0.0, 'principal' => 0.0, 'interest' => 0.0, 'ifrs9_allowance' => 0.0];
            }
        }
        $perLoan = [];
        foreach ($loans as $l) {
            $term = (int) $l->tenor <= 12 ? 'short' : 'long';
            $class = self::classify((int) $l->overdue_days, (int) $l->tenor);
            $a[$class][$term]['accounts']++;
            $a[$class][$term]['balance'] += (float) $l->carrying_amount;
            $a[$class][$term]['principal'] += (float) $l->principal_balance;
            $a[$class][$term]['interest'] += (float) ($l->interest_to_date ?? 0);
            $a[$class][$term]['ifrs9_allowance'] += (float) ($l->ecl_value ?? 0);
            $perLoan[$l->contract_id] = ['class' => $class, 'term' => $term, 'balance' => (float) $l->carrying_amount, 'dpd' => (int) $l->overdue_days, 'stage' => $l->ifrs9stage_post_qualitative];
        }
        $sectionA = [];
        foreach (self::CLASSES as $c) {
            $row = ['class' => $c, 'short_term' => $a[$c]['short'], 'medium_long_term' => $a[$c]['long']];
            $row['total'] = ['accounts' => $a[$c]['short']['accounts'] + $a[$c]['long']['accounts'], 'balance' => round($a[$c]['short']['balance'] + $a[$c]['long']['balance'], 2), 'ifrs9_allowance' => round($a[$c]['short']['ifrs9_allowance'] + $a[$c]['long']['ifrs9_allowance'], 2)];
            $sectionA[] = $row;
        }
        $totalBalance = (float) $loans->sum('carrying_amount');
        $npl = array_sum(array_map(fn ($r) => in_array($r['class'], ['Substandard', 'Doubtful', 'Loss'], true) ? $r['total']['balance'] : 0, $sectionA));

        // B. provisions: the minimum per class on the gross balance (the eligible security to net is not yet held), against the IFRS 9 allowance
        $sectionB = [];
        $minTotal = 0.0; $heldTotal = 0.0;
        foreach ($sectionA as $r) {
            $min = round($r['total']['balance'] * self::MINIMUM[$r['class']], 2);
            $minTotal += $min; $heldTotal += $r['total']['ifrs9_allowance'];
            $sectionB[] = ['class' => $r['class'], 'balance' => $r['total']['balance'], 'minimum_rate' => self::MINIMUM[$r['class']], 'minimum_provision' => $min, 'ifrs9_allowance' => $r['total']['ifrs9_allowance'], 'shortfall' => round(max(0, $min - $r['total']['ifrs9_allowance']), 2)];
        }
        $notes[] = 'Section B applies the minimum rates to the gross balance: the eligible security to net against classified facilities is not yet held (31 active accounts carry no security on the register).';

        // C. interest in suspense: on non-performing facilities, the contractual interest posted in the period less the EIR interest on the net basis
        $ym = $period;
        $suspense = 0.0; $suspenseLoans = 0;
        $npLoans = array_keys(array_filter($perLoan, fn ($p) => in_array($p['class'], ['Substandard', 'Doubtful', 'Loss'], true)));
        if ($npLoans !== []) {
            $posted = [];
            foreach ($this->zone->ledgerByAccount(\Carbon\CarbonImmutable::parse($period . '-01')->endOfMonth()->toDateString()) as $account => $posts) {
                $cid = ltrim($account, '0');
                if (! isset($perLoan[$cid]) || ! in_array($perLoan[$cid]['class'], ['Substandard', 'Doubtful', 'Loss'], true)) {
                    continue;
                }
                foreach ($posts as $p) {
                    if (substr((string) $p['row_date'], 0, 7) === $ym && in_array((string) ($p['payload']['TRANTYPE'] ?? ''), LoanBookBuildService::TYPE_INTEREST, true)) {
                        $posted[$cid] = ($posted[$cid] ?? 0) - (float) str_replace(',', '', (string) ($p['payload']['TRANSAMT'] ?? 0));
                    }
                }
            }
            $eir = DB::table('eir_amortisation')->where('reporting_period', $period)->whereIn('contract_id', $npLoans)->pluck('interest_accrued', 'contract_id');
            foreach ($posted as $cid => $amt) {
                $suspense += $amt - (float) ($eir[$cid] ?? 0);
                $suspenseLoans++;
            }
        }
        $sectionC = ['non_performing_accounts' => count($npLoans), 'non_performing_balance' => round($npl, 2), 'interest_posted_in_period' => round(array_sum($posted ?? []), 2), 'interest_recognised_eir_net_basis' => round(array_sum(array_map(fn ($cid) => (float) ($eir[$cid] ?? 0), array_keys($posted ?? []))), 2), 'interest_in_suspense' => round($suspense, 2), 'accounts_with_interest_posted' => $suspenseLoans];
        $notes[] = 'Section C: E-Banker continues to post contractual interest on non-performing accounts; the suspense the directive requires is the excess over the interest the EIR engine recognises on the net basis (spec v4 section 3.4).';

        // D. restructured facilities: awaiting the Reschedule Report
        $sectionD = ['accounts' => null, 'balance' => null, 'note' => 'The restructured-loan register (E-Banker\'s Reschedule Report) is awaited from the vendor; the line is filled when it lands.'];
        // E. security held against classified facilities
        $security = DB::table('ebanker_raw_rows')->where('query_id', 'P2_11')->whereNull('superseded_at')->get(['account', 'payload']);
        $secured = 0.0; $securedAccounts = 0;
        foreach ($security as $s) {
            $cid = ltrim((string) $s->account, '0');
            if (isset($perLoan[$cid]) && in_array($perLoan[$cid]['class'], ['Substandard', 'Doubtful', 'Loss'], true)) {
                $v = json_decode($s->payload, true);
                $secured += (float) str_replace(',', '', (string) ($v['SECURITY_VALUE'] ?? 0));
                $securedAccounts++;
            }
        }
        $sectionE = ['classified_accounts' => count($npLoans), 'security_rows_against_them' => $securedAccounts, 'security_value_on_register' => round($secured, 2), 'note' => 'Register values as recorded in E-Banker (P2_11), not valuations; eligibility under the directive is to be applied once Credit supplies the valuations.'];

        return ['period' => $period, 'status' => 'PROVISIONAL', 'loans' => $loans->count(), 'total_balance' => round($totalBalance, 2), 'npl_ratio' => $totalBalance > 0 ? round($npl / $totalBalance, 6) : null,
            'sections' => ['A_classification' => $sectionA, 'B_provisions' => $sectionB, 'B_totals' => ['minimum_provision' => round($minTotal, 2), 'ifrs9_allowance' => round($heldTotal, 2), 'higher_of_the_two' => round(max($minTotal, $heldTotal), 2)], 'C_interest_in_suspense' => $sectionC, 'D_restructured' => $sectionD, 'E_security' => $sectionE],
            'per_loan' => $perLoan, 'notes' => $notes];
    }
}
