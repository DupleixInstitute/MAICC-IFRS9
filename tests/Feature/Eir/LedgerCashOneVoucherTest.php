<?php

namespace Tests\Feature\Eir;

use App\Services\Ebanker\LandingZoneReader;
use App\Services\Ebanker\LoanBookBuildService;
use App\Services\Eir\ContractTransactionImportService;
use App\Services\Eir\EirLedgerCashService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

/**
 * One E-Banker voucher is one cash posting, whichever route loaded it first
 * (found by re-performance, 16 October 2026): the contract inputs route keys it
 * "CUMVOUCH:<id>" under MAIIC_EXTRACT_B, the ledger route by its id under
 * EBANKER_LEDGER, and the ledger route used to load it again, doubling the
 * month's cash wherever the month-end job ran after the bootstrap.
 */
class LedgerCashOneVoucherTest extends TestCase
{
    use RefreshDatabase;

    private function row(string $source, string $id, float $amount): void
    {
        DB::table('eir_actual_transactions')->insert(['contract_id' => '104430000071', 'transaction_date' => '2026-09-30', 'transaction_type' => 'Principal+Interest',
            'principal_component' => 0, 'interest_component' => 0, 'fee_component' => 0, 'total_amount' => $amount, 'source_system' => $source,
            'source_reference' => 'TRANTYPE test', 'external_transaction_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_the_ledger_route_does_not_load_again_a_voucher_the_contract_inputs_route_loaded(): void
    {
        $this->row(ContractTransactionImportService::SOURCE, 'CUMVOUCH:425670', 36976412.25);
        $receipt = LoanBookBuildService::TYPE_RECEIPT[0];
        $zone = Mockery::mock(LandingZoneReader::class);
        $zone->shouldReceive('lastLedgerDate')->andReturn('2026-09-30');
        $zone->shouldReceive('family')->andReturn([]);
        $zone->shouldReceive('accountMaster')->andReturn([]);
        $zone->shouldReceive('ledgerByAccount')->andReturn(['0104430000071' => [
            ['payload' => ['TRANTYPE' => $receipt, 'CUMVOUCH_DET_ID' => '425670', 'TRANSAMT' => '36,976,412.25', 'PARTICULARS' => 'Repayment'], 'row_date' => '2026-09-30', 'source_key' => 'k1'],
            ['payload' => ['TRANTYPE' => $receipt, 'CUMVOUCH_DET_ID' => '425671', 'TRANSAMT' => '1,000.00', 'PARTICULARS' => 'Repayment'], 'row_date' => '2026-09-30', 'source_key' => 'k2'],
        ]]);

        $r = (new EirLedgerCashService($zone))->load();
        $this->assertSame([1, 1], [$r['loaded'], $r['already_loaded_by_extract_b']]);
        $this->assertSame(36976412.25 + 1000.0, (float) DB::table('eir_actual_transactions')->where('contract_id', '104430000071')->sum('total_amount'), 'the voucher counts once');
        $this->assertSame(0, (new EirLedgerCashService($zone))->load()['loaded'], 'and a second run loads nothing');
    }

    public function test_the_contract_inputs_route_sees_a_voucher_the_ledger_route_loaded(): void
    {
        $this->row(EirLedgerCashService::SOURCE, '425670', 36976412.25);
        $this->assertArrayHasKey('CUMVOUCH:425670', EirLedgerCashService::vouchersLoadedElsewhere(ContractTransactionImportService::SOURCE));
        $this->assertSame([], EirLedgerCashService::vouchersLoadedElsewhere(EirLedgerCashService::SOURCE));
    }
}
