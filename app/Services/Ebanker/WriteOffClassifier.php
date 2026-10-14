<?php

namespace App\Services\Ebanker;

/**
 * Which credits to a loan account are write-offs, not collections (specification
 * v4 section 3.8; review items R08 and R09; built 13 October 2026).
 *
 * MAIIC's write-offs are not all posted with the write-off transaction type. On
 * 29 June 2026 batch TRF 3 cleared MWK 38,492,032.54 on 20 status-H loans as type
 * 305 (the repayment type) narrated "ACCOUNT CLOSURE", with the other side debited
 * to GL 1064 Term Loans Write Offs; read at face value the ledger turns the loss
 * into cash collected. A credit to a loan account is therefore a write-off when any
 * of these holds:
 *
 *   1. its transaction type is 300, the write-off type;
 *   2. it sits in a batch (date, batch type, batch number) whose other leg debits
 *      a write-off GL (1064), read from the write-off batch legs (GL_05) and from
 *      any ledger family that carries GL legs;
 *   3. it is narrated "ACCOUNT CLOSURE" in a TRF batch on an account whose status
 *      in the account master is H (written off and closed).
 *
 * A debit is never a write-off; a reversal of a write-off arrives as a debit and is
 * left to the posting's own type. The loan book build (method B), the contract
 * inputs and the EIR's ledger cash all ask this one class, so the three agree.
 */
class WriteOffClassifier
{
    /** GL codes a write-off batch debits. */
    public const WRITE_OFF_GLS = ['1064'];

    /**
     * Query ids that carry GL legs of batches: the write-off legs (GL_05), the year-end
     * interest legs (GL_03) and the monthly ledger feed (M09_01), through which future
     * write-off batches arrive with the month's postings (added 14 October 2026). Only a
     * debit to a write-off GL marks a batch, so the loan legs of M09_01 never do.
     */
    public const GL_LEG_QUERIES = ['GL_05', 'GL_03', 'M09_01'];

    private ?array $batches = null;

    private ?array $statusH = null;

    public function __construct(private ?LandingZoneReader $zone = null)
    {
        $this->zone ??= new LandingZoneReader();
    }

    /** The rule that makes a posting a write-off, or null when it is not one. */
    public function rule(array $payload, ?string $rowDate, ?string $account = null): ?string
    {
        $amount = (float) str_replace(',', '', (string) ($payload['TRANSAMT'] ?? 0));
        if ($amount <= 0) {
            return null; // a debit, or nothing: never a write-off
        }
        $type = (string) ($payload['TRANTYPE'] ?? '');
        if (in_array($type, LoanBookBuildService::TYPE_WRITEOFF, true)) {
            return 'type 300';
        }
        $key = self::batchKey($rowDate, $payload);
        if ($key !== null && isset($this->batches()[$key])) {
            return 'batch ' . str_replace('|', ' ', $key) . ' debits GL ' . $this->batches()[$key];
        }
        $narrated = stripos((string) ($payload['PARTICULARS'] ?? ''), 'ACCOUNT CLOSURE') !== false;
        if ($narrated && strtoupper((string) ($payload['BATCH_TYPE'] ?? '')) === 'TRF' && $account !== null && isset($this->statusH()[$account])) {
            return 'ACCOUNT CLOSURE in a TRF batch on a status-H account';
        }

        return null;
    }

    public function isWriteOff(array $payload, ?string $rowDate, ?string $account = null): bool
    {
        return $this->rule($payload, $rowDate, $account) !== null;
    }

    /** The write-off batches: key (date|type|number) => the write-off GL it debits. */
    public function batches(): array
    {
        if ($this->batches !== null) {
            return $this->batches;
        }
        $out = [];
        foreach ($this->zone->family(self::GL_LEG_QUERIES) as $row) {
            $p = $row['payload'];
            $gl = trim((string) ($p['AC_GLCODE'] ?? ($p['CB_GLCODE'] ?? '')));
            $amount = (float) str_replace(',', '', (string) ($p['TRANSAMT'] ?? 0));
            if (! in_array($gl, self::WRITE_OFF_GLS, true) || $amount >= 0 || ($p['DELETE_FLAG'] ?? 'N') === 'Y') {
                continue; // only a debit to the write-off GL marks the batch
            }
            $key = self::batchKey($row['row_date'], $p);
            if ($key !== null) {
                $out[$key] = $gl;
            }
        }

        return $this->batches = $out;
    }

    /** date|batch type|batch number, the key that ties a batch's legs together. */
    public static function batchKey(?string $rowDate, array $payload): ?string
    {
        $number = trim((string) ($payload['BATCH_NUMBER'] ?? ''));
        if ($rowDate === null || $number === '') {
            return null;
        }

        return substr($rowDate, 0, 10) . '|' . strtoupper(trim((string) ($payload['BATCH_TYPE'] ?? ''))) . '|' . $number;
    }

    /** Accounts whose status in the account master is H. */
    private function statusH(): array
    {
        if ($this->statusH !== null) {
            return $this->statusH;
        }
        $out = [];
        foreach ($this->zone->accountMaster() as $account => $m) {
            if (strtoupper(trim((string) ($m['STATUS_CODE'] ?? ''))) === 'H') {
                $out[$account] = true;
            }
        }

        return $this->statusH = $out;
    }
}
