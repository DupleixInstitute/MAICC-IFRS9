<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The accounts the approved exclusions take out of the book (the account
 * exclusion register, MAIIC's confirmation of 16 October 2026; see
 * App\Services\Ebanker\AccountExclusionService).
 *
 * Every build reads the landing zone through LandingZoneReader, and the
 * reader leaves these accounts out, so the loan book, the contract master,
 * the fees, the drawdowns, the cash movements, the interest posted, the
 * instalment charts and the take-on population never see them, and nothing
 * downstream of those tables (the EIR, the revenue, staging, the PD and LGD,
 * the ECL, the S&P basis, the reports) needs a rule of its own. The importers
 * that take a file from a person check here too, so an excluded account does
 * not come back through an upload.
 *
 * Only an APPROVED exclusion counts: a proposal changes nothing until a
 * second person approves it, and a withdrawn one stops counting once the
 * withdrawal is approved. The set is read once per process and kept in the
 * container; the register calls forget() whenever it changes.
 */
final class AccountExclusions
{
    private const KEY = 'maiic.account_exclusions.approved';

    /** @return array<string, int> canonical contract id => exclusion id, of the approved exclusions */
    public static function approved(): array
    {
        $app = app();
        if ($app->bound(self::KEY)) {
            return $app->make(self::KEY);
        }
        $ids = [];
        if (Schema::hasTable('account_exclusions')) {
            foreach (DB::table('account_exclusions')->where('status', 'APPROVED')->get(['id', 'contract_id']) as $r) {
                $ids[(string) $r->contract_id] = (int) $r->id;
            }
        }
        $app->instance(self::KEY, $ids);

        return $ids;
    }

    /** Whether an account (padded as E-Banker writes it, or not) is excluded by an approved exclusion. */
    public static function isExcluded(mixed $account): bool
    {
        $id = ContractId::normalise($account);

        return $id !== null && isset(self::approved()[$id]);
    }

    /** The approved exclusion of an account, or null. */
    public static function exclusionOf(mixed $account): ?int
    {
        $id = ContractId::normalise($account);

        return $id === null ? null : (self::approved()[$id] ?? null);
    }

    /** The account padded to E-Banker's fifteen characters, the form the extracts carry. */
    public static function padded(string $contractId): string
    {
        return str_pad(ltrim($contractId, '0'), 15, '0', STR_PAD_LEFT);
    }

    /** Read the register again on the next call (after an approval, a withdrawal or a landing). */
    public static function forget(): void
    {
        app()->forgetInstance(self::KEY);
    }

    /** The reason a row of an excluded account is refused, for an importer's skipped list. */
    public static function refusal(mixed $account): string
    {
        return 'the account is excluded from the book by account exclusion #' . self::exclusionOf($account) . ' (Account Exclusions register)';
    }
}
