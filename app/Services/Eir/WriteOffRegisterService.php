<?php

namespace App\Services\Eir;

use App\Services\AuditLoggerService;
use App\Services\Ebanker\LandingZoneReader;
use App\Services\Ebanker\WriteOffClassifier;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The governed write-off register (specification v4 section 3.8; built 13 October
 * 2026) and the view of every write-off the system knows of.
 *
 * Two kinds of write-off reach the engines:
 *  - in the ledger: credits the WriteOffClassifier marks (type 300, a batch that
 *    debits GL 1064, an ACCOUNT CLOSURE on a status-H account); they arrive with
 *    the E-Banker pack and need no approval here, because E-Banker's own posting
 *    is the evidence;
 *  - in the register: write-offs that are not on the loan account (MUSECO,
 *    31 December 2025, in the audit's TB-to-AFS mapping and at GL level in
 *    January 2026). One person proposes with the evidence; a different person
 *    approves; on approval the write-off is written to the loan's actual
 *    transactions as a Write-off (source WRITE_OFF_REGISTER), which the EIR
 *    revenue engine treats as derecognition, not cash. A rejected or withdrawn
 *    entry never reaches the engines; reversing an approved one removes its row.
 */
class WriteOffRegisterService
{
    public const SOURCE = 'WRITE_OFF_REGISTER';

    public function propose(array $data, int $userId): int
    {
        $contract = ltrim((string) $data['contract_id'], '0');
        $loan = DB::table('loan_books')->where('contract_id', $contract)->orderByDesc('reporting_period')->first(['customer_name', 'external_identity_id']);
        if (! $loan) {
            throw new RuntimeException("Contract {$contract} is not in the loan book.");
        }
        if ((float) $data['amount'] <= 0) {
            throw new RuntimeException('The amount written off must be more than nil.');
        }
        // refused at the door, not only at approval, when the month is closed
        \App\Support\ReportingPeriodLock::assertOpen(substr((string) $data['write_off_date'], 0, 7), 'a write-off');
        $open =DB::table('loan_write_offs')->where('contract_id', $contract)->whereIn('status', ['PROPOSED', 'APPROVED'])->exists();
        if ($open) {
            throw new RuntimeException("Contract {$contract} already has a write-off proposed or approved in the register.");
        }
        $id = (int) DB::table('loan_write_offs')->insertGetId([
            'contract_id' => $contract, 'account' => $loan->external_identity_id ?: str_pad($contract, 15, '0', STR_PAD_LEFT), 'customer_name' => $loan->customer_name,
            'write_off_date' => $data['write_off_date'], 'amount' => round((float) $data['amount'], 2), 'reason' => $data['reason'],
            'evidence' => $data['evidence'] ?? null, 'gl_reference' => $data['gl_reference'] ?? null,
            'status' => 'PROPOSED', 'proposed_by' => $userId, 'proposed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        AuditLoggerService::log('Write-off Proposed', 'loan_write_offs', $id, ['new_values' => ['contract_id' => $contract, 'date' => $data['write_off_date'], 'amount' => $data['amount'], 'reason' => $data['reason']]]);

        return $id;
    }

    /** A different person approves; the write-off then reaches the loan's actual transactions. */
    public function approve(int $id, int $userId, ?string $label = null): void
    {
        DB::transaction(function () use ($id, $userId, $label) {
            $w = DB::table('loan_write_offs')->where('id', $id)->lockForUpdate()->first();
            if (! $w || $w->status !== 'PROPOSED') {
                throw new RuntimeException('Only a proposed write-off can be approved.');
            }
            if ((int) $w->proposed_by === $userId) {
                throw new RuntimeException('The person who proposed a write-off cannot approve it.');
            }
            \App\Support\ReportingPeriodLock::assertOpen(substr((string) $w->write_off_date, 0, 7), 'a write-off');
            $txId = (int) DB::table('eir_actual_transactions')->insertGetId([
                'contract_id' => $w->contract_id, 'transaction_date' => $w->write_off_date, 'transaction_type' => 'Write-off',
                'principal_component' => 0, 'interest_component' => 0, 'fee_component' => 0, 'total_amount' => $w->amount,
                'source_system' => self::SOURCE, 'external_transaction_id' => 'WOR-' . $w->id,
                'source_reference' => mb_substr('Write-off register #' . $w->id . ': ' . $w->reason, 0, 199),
                'row_note' => $w->evidence, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('loan_write_offs')->where('id', $id)->update(['status' => 'APPROVED', 'approved_by' => $userId, 'approver_label' => $label,
                'approved_at' => now(), 'transaction_id' => $txId, 'updated_at' => now()]);
            AuditLoggerService::log('Write-off Approved', 'loan_write_offs', $id, ['new_values' => ['contract_id' => $w->contract_id, 'amount' => (float) $w->amount, 'transaction_id' => $txId, 'label' => $label]]);
        });
    }

    /** Reject (a reviewer), withdraw (the proposer) or reverse (an approved entry, with a reason). */
    public function close(int $id, int $userId, string $action, string $reason): void
    {
        DB::transaction(function () use ($id, $userId, $action, $reason) {
            $w = DB::table('loan_write_offs')->where('id', $id)->lockForUpdate()->first();
            if (! $w) {
                throw new RuntimeException('No such write-off.');
            }
            // the proposer withdraws; a rejection is a second person's act (documentation audit, 14 October 2026)
            if ($action === 'reject' && $w->status === 'PROPOSED' && (int) $w->proposed_by === $userId) {
                throw new RuntimeException('The person who proposed a write-off cannot reject it; withdraw it instead.');
            }
            $status = match ($action) {
                'reject' => $w->status === 'PROPOSED' ? 'REJECTED' : null,
                'withdraw' => $w->status === 'PROPOSED' && (int) $w->proposed_by === $userId ? 'WITHDRAWN' : null,
                'reverse' => $w->status === 'APPROVED' ? 'REVERSED' : null,
                default => null,
            };
            if ($status === null) {
                throw new RuntimeException(match ($action) {
                    'withdraw' => 'Only the person who proposed a write-off can withdraw it, and only before it is approved.',
                    'reverse' => 'Only an approved write-off can be reversed.',
                    default => 'Only a proposed write-off can be rejected.',
                });
            }
            if ($status === 'REVERSED') {
                \App\Support\ReportingPeriodLock::assertOpen(substr((string) $w->write_off_date, 0, 7), 'reversing a write-off');
                DB::table('eir_actual_transactions')->where('id', $w->transaction_id)->where('source_system', self::SOURCE)->delete();
            }
            DB::table('loan_write_offs')->where('id', $id)->update(['status' => $status, 'closed_by' => $userId, 'closed_at' => now(), 'close_reason' => $reason, 'updated_at' => now()]);
            AuditLoggerService::log('Write-off ' . ucfirst(strtolower($status)), 'loan_write_offs', $id, ['new_values' => ['contract_id' => $w->contract_id, 'reason' => $reason]]);
        });
    }

    /** Every write-off the ledger carries, by the classifier's rule, with the loan's name. */
    public function ledgerWriteOffs(?string $toDate = null): array
    {
        $zone = new LandingZoneReader();
        $classifier = new WriteOffClassifier($zone);
        $names = DB::table('loan_books')->orderBy('reporting_period')->pluck('customer_name', 'contract_id')->all();
        $out = [];
        foreach ($zone->ledgerByAccount($toDate) as $account => $posts) {
            foreach ($posts as $p) {
                $rule = $classifier->rule($p['payload'], $p['row_date'], $account);
                if ($rule === null) {
                    continue;
                }
                $contract = ltrim((string) $account, '0');
                $out[] = ['account' => $account, 'contract_id' => $contract, 'customer_name' => $names[$contract] ?? null,
                    'date' => substr((string) $p['row_date'], 0, 10), 'amount' => round((float) str_replace(',', '', (string) $p['payload']['TRANSAMT']), 2),
                    'type' => (string) ($p['payload']['TRANTYPE'] ?? ''), 'batch' => trim(($p['payload']['BATCH_TYPE'] ?? '') . ' ' . ($p['payload']['BATCH_NUMBER'] ?? '')),
                    'narration' => (string) ($p['payload']['PARTICULARS'] ?? ''), 'rule' => $rule];
            }
        }
        usort($out, fn ($a, $b) => [$b['date'], $a['account']] <=> [$a['date'], $b['account']]);

        return $out;
    }
}
