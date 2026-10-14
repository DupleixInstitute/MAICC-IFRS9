<?php

namespace App\Support;

/**
 * A web request that reads the whole landing-zone ledger (the loan-book build,
 * the write-offs the ledger carries, the EIR as at a date, the RBM return)
 * reads the Mega Farm ledger of pack 4 with it, some 300,000 postings. PHP's
 * web defaults (1 GB, 60 seconds) stopped those screens with a blank 500 where
 * the console, run with 4 GB, succeeded (UI audit of 14 October 2026).
 *
 * This raises the limits for that request only. The lasting fix is in the
 * reader (stream the family and leave the Mega Farm ledger out unless asked
 * for); until then the screens work instead of failing.
 */
final class LedgerReadLimits
{
    public static function raise(): void
    {
        ini_set('memory_limit', '4096M');
        set_time_limit(1800);
    }
}
