<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A starter set of qualitative SICR factors for an agricultural and
 * industrial development finance institution in Malawi (IFRS 9 5.5.3,
 * B5.5.17, B5.5.37 and Appendix A "credit-impaired financial asset"; the
 * Reserve Bank of Malawi's credit risk management directive for development
 * finance institutions sets the days-past-due rules the engine already
 * applies, these items are the judgement on top).
 *
 * Each item states the stage it implies: 2 for a significant increase in
 * credit risk since the loan was granted, 3 for objective evidence that the
 * loan is credit-impaired. It is a starting point for MAIIC's credit
 * committee to review, not MAIIC's approved policy.
 *
 * Idempotent: a group or item that already exists (by name) is left exactly
 * as it is, so edits made on the screens are never overwritten.
 */
class SicrQualitativeFactorsSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('finance_sicr_groups') || ! Schema::hasColumn('finance_sicr_items', 'stage_implied')) {
            $this->command?->warn('SICR tables are not migrated yet; nothing seeded.');

            return;
        }
        $now = now();
        $groups = 0;
        $items = 0;
        foreach (self::factors() as $order => [$group, $description, $list]) {
            $groupId = DB::table('finance_sicr_groups')->where('name', $group)->value('id');
            if ($groupId === null) {
                $groupId = DB::table('finance_sicr_groups')->insertGetId(['name' => $group, 'description' => $description, 'sort_order' => $order + 1, 'created_at' => $now, 'updated_at' => $now]);
                $groups++;
            }
            foreach ($list as [$name, $stage, $evidence, $reference, $text]) {
                if (DB::table('finance_sicr_items')->where('group_id', $groupId)->where('name', $name)->exists()) {
                    continue;
                }
                DB::table('finance_sicr_items')->insert(['group_id' => $groupId, 'name' => $name, 'description' => $text, 'stage_implied' => $stage,
                    'evidence_required' => $evidence, 'standard_reference' => $reference, 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
                $items++;
            }
        }
        $this->command?->info("SICR factors: {$groups} group(s) and {$items} item(s) added.");
    }

    /** @return list<array{0:string,1:string,2:list<array{0:string,1:int,2:bool,3:string,4:string}>}> */
    public static function factors(): array
    {
        return [
            ['Forbearance and restructuring', 'Changes to the loan\'s terms because the borrower is struggling to pay.', [
                ['Restructured or rescheduled because of financial difficulty', 2, true, 'IFRS 9 B5.5.17(m)',
                    'The repayment plan was changed because the borrower could not keep to it: a longer term, smaller instalments or a new schedule. If MAIIC gave up interest or principal it would not otherwise give up, use the credit-impaired item for a concession with a loss instead.'],
                ['Second restructuring within 12 months', 3, true, 'IFRS 9 Appendix A(b), (c)',
                    'The loan is restructured again within a year of an earlier restructuring. The first plan did not work, which is evidence the borrower cannot pay as agreed: treat the loan as credit-impaired.'],
                ['Payment holiday or moratorium extension not in the original terms', 2, true, 'IFRS 9 B5.5.17(m)',
                    'MAIIC agreed to pause or defer repayments, or to extend a grace period, beyond what the original contract allowed. A holiday the contract already provides for (for example a planned grace period before the first harvest) is not a trigger.'],
            ]],
            ['Borrower financial condition', 'What the borrower\'s own figures and reports say about its ability to repay.', [
                ['Significant fall in revenue or cash flow', 2, true, 'IFRS 9 B5.5.17(g)',
                    'Management accounts, bank statements or a site visit show sales or cash coming in well below what the loan was approved on, enough to put the instalments at risk.'],
                ['Breach of a financial covenant', 2, true, 'IFRS 9 B5.5.17(m), B5.5.37',
                    'The borrower has broken a covenant in the loan agreement (for example a debt service cover or gearing limit), whether or not MAIIC has waived it.'],
                ['Audited financial statements late or qualified', 2, true, 'IFRS 9 B5.5.17(g)',
                    'The audited accounts are more than three months overdue, or the auditor\'s opinion is qualified, adverse or carries a going-concern warning.'],
                ['Negative net worth', 2, true, 'IFRS 9 B5.5.17(g)',
                    'The latest balance sheet shows liabilities greater than assets, so the owners\' equity no longer stands between MAIIC and a loss.'],
                ['Loss of a key customer or off-taker', 2, true, 'IFRS 9 B5.5.17(g)',
                    'The borrower has lost a customer or buyer that brought in a large part of its income and has not replaced it.'],
            ]],
            ['Agricultural and climate', 'Weather, crop and commodity events that hit farm and agro-processing borrowers.', [
                ['Crop failure, drought or flood in the borrower\'s area', 2, true, 'IFRS 9 B5.5.17(f)',
                    'A drought, flood, pest outbreak or other event has damaged the financed crop or the borrower\'s area (for example a district declared affected by the Department of Disaster Management Affairs), so the harvest that repays the loan is at risk.'],
                ['Fall in the price of the financed crop or commodity', 2, true, 'IFRS 9 B5.5.17(f)',
                    'The market price of the crop or product the loan finances has fallen far enough that the expected sales no longer cover the instalments (for example tobacco, maize, soya or tea auction prices).'],
                ['Off-take agreement cancelled', 2, true, 'IFRS 9 B5.5.17(g)',
                    'The contract with the buyer that the repayment plan relied on has been cancelled or not renewed, and no replacement buyer is in place.'],
                ['Warehouse receipt or stored crop collateral deteriorated', 2, true, 'IFRS 9 B5.5.17(j)',
                    'Stock held as security under a warehouse receipt has spoiled, gone missing or lost much of its value, so the security no longer covers the loan as it did.'],
            ]],
            ['External and market', 'Changes outside the borrower that make repayment harder.', [
                ['Adverse change in the borrower\'s sector or region', 2, true, 'IFRS 9 B5.5.17(f), (i)',
                    'A change in the sector or region the borrower depends on (for example a ban, a plant closure or a collapse in demand) is expected to cut its ability to repay.'],
                ['Foreign exchange shock on a borrower with foreign currency costs', 2, true, 'IFRS 9 B5.5.17(f)',
                    'The kwacha has fallen sharply and the borrower pays for inputs, equipment or debt in foreign currency while earning in kwacha.'],
                ['Loss or suspension of a licence or permit', 2, true, 'IFRS 9 B5.5.17(i)',
                    'A licence, permit or registration the business needs to operate has been withdrawn, suspended or not renewed.'],
            ]],
            ['Behavioural and relationship', 'How the borrower is behaving with MAIIC and with other lenders.', [
                ['Placed on the watchlist or early-warning list', 2, true, 'IFRS 9 B5.5.17(o)',
                    'The credit committee has put the loan on the watchlist or early-warning list for closer monitoring. Record the committee minute as evidence.'],
                ['In arrears with another lender (credit reference bureau)', 2, true, 'IFRS 9 B5.5.17(n)',
                    'A credit reference bureau report shows the borrower behind on payments to another lender, even though it is up to date with MAIIC.'],
                ['Returned cheques or an account persistently overdrawn', 2, true, 'IFRS 9 B5.5.17(n)',
                    'Cheques or debit orders from the borrower have been returned unpaid, or its operating account has been over its limit for an extended period.'],
                ['Management change or loss of a key person', 2, true, 'IFRS 9 B5.5.17(g)',
                    'The owner, managing director or another person the business depends on has left, fallen seriously ill or been replaced, with no clear successor.'],
                ['Legal action against the borrower or suspected fraud', 2, true, 'IFRS 9 B5.5.17(g)',
                    'A significant claim, garnishee or court case has been brought against the borrower, or MAIIC suspects fraud or misuse of the loan funds.'],
            ]],
            ['Credit-impaired evidence', 'Events that show the loan is already credit-impaired (Stage 3), whatever the days past due.', [
                ['Borrower in liquidation, receivership or administration', 3, true, 'IFRS 9 Appendix A(d)',
                    'A liquidator, receiver or administrator has been appointed, or a winding-up petition has been filed against the borrower.'],
                ['Sole guarantor has died with no successor to the business', 3, true, 'IFRS 9 Appendix A(a)',
                    'The person whose guarantee and management the loan relied on has died and nobody has taken over the business or the obligation.'],
                ['Bankruptcy or financial reorganisation is probable', 3, true, 'IFRS 9 Appendix A(d)',
                    'It has become probable that the borrower will be declared bankrupt or enter a formal financial reorganisation or creditor arrangement.'],
                ['Concession with a loss to MAIIC because of financial difficulty', 3, true, 'IFRS 9 Appendix A(c)',
                    'Because of the borrower\'s financial difficulty, MAIIC has written off or waived interest or principal, or cut the rate below its own cost, a concession it would not otherwise grant.'],
                ['No active market left for the security', 3, true, 'IFRS 9 Appendix A(e); B5.5.17(j)',
                    'Repayment now depends on selling the security, and there is no working market for it (for example specialised equipment or a crop with no buyers), so a material loss is expected.'],
            ]],
        ];
    }
}
