<?php

namespace Tests\Feature\Eir;

use App\Services\Eir\ContractMasterImportService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Extract A since P2: E-Banker's own codes are stored verbatim (decision
 * D16), reprice_flag follows the interest policy alone, and the moratorium
 * type is mapped from the two shapes E-Banker can express (decision D4).
 * Private in-memory sqlite schema, as in EirIntakeServicesTest.
 */
class ContractMasterNewFieldsTest extends TestCase
{
    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('loan_books', function (Blueprint $t) {
            $t->increments('id');
            $t->string('contract_id')->nullable();
            $t->string('customer_id')->nullable();
            $t->string('reporting_period')->nullable();
            $t->timestamps();
        });

        Schema::create('contract_eir', function (Blueprint $t) {
            $t->increments('id');
            $t->string('contract_id')->unique();
            $t->string('instrument_type')->default('AMORTISED_LOAN');
            $t->string('rate_type')->default('FIXED');
            $t->string('portfolio')->nullable();
            $t->string('product_type')->nullable();
            $t->string('sub_account_no')->nullable();
            $t->string('currency', 3)->nullable();
            $t->double('contractual_rate')->nullable();
            $t->double('markup')->nullable();
            $t->string('origination_date')->nullable();
            $t->string('first_repayment_date')->nullable();
            $t->string('maturity_date')->nullable();
            $t->double('approved_amount')->nullable();
            $t->double('drawn_amount')->nullable();
            $t->unsignedSmallInteger('payments_per_year')->default(12);
            $t->string('frequency_source')->default('ASSUMED');
            $t->unsignedSmallInteger('tenor_months')->nullable();
            $t->unsignedSmallInteger('moratorium_months')->default(0);
            $t->string('terms_source_system')->nullable();
            $t->string('terms_source_reference')->nullable();
            $t->string('terms_imported_at')->nullable();
            $t->string('locked_at')->nullable();
            // P2 columns, as the migration declares them.
            $t->string('scheme_code', 30)->nullable();
            $t->string('interest_policy', 1)->nullable();
            $t->string('floating_flag', 1)->nullable();
            $t->string('interest_calc_base', 1)->nullable();
            $t->string('installment_based_on', 20)->nullable();
            $t->string('emi_calc_type', 1)->nullable();
            $t->string('moratorium_type', 20)->nullable();
            $t->string('moratorium_type_verbatim', 40)->nullable();
            $t->unsignedTinyInteger('grace_period_months')->nullable();
            $t->string('moratorium_from')->nullable();
            $t->string('interest_start_date')->nullable();
            $t->string('first_instalment_date')->nullable();
            $t->double('spread_over_prime')->nullable();
            $t->string('spread_source', 10)->nullable();
            $t->boolean('spread_drift_flag')->default(false);
            $t->boolean('reprice_flag')->nullable();
            $t->string('account_status_code', 10)->nullable();
            $t->string('los_application_no', 40)->nullable();
            $t->string('los_process_ref', 40)->nullable();
            $t->string('predecessor_sub_account', 60)->nullable();
            $t->timestamps();
        });

        Schema::create('contract_fees', function (Blueprint $t) {
            $t->increments('id');
            $t->string('contract_id');
            $t->string('fee_type');
            $t->double('amount');
            $t->string('basis')->default('ON_APPROVED');
            $t->string('description')->nullable();
            $t->string('transaction_date')->nullable();
            $t->string('cashflow_direction')->nullable();
            $t->string('currency')->nullable();
            $t->string('source_system')->nullable();
            $t->string('source_reference')->nullable();
            $t->string('external_transaction_id')->nullable();
            $t->boolean('integral')->nullable();
            $t->string('classification_status')->default('PENDING');
            $t->text('classification_reason')->nullable();
            $t->unsignedInteger('suggested_rule_id')->nullable();
            $t->boolean('suggested_integral')->nullable();
            $t->string('gl_account_ref')->nullable();
            $t->timestamps();
        });

        Schema::create('eir_accounting_rules', function (Blueprint $t) {
            $t->increments('id');
            $t->string('name');
            $t->string('fee_type')->nullable();
            $t->string('description_contains')->nullable();
            $t->string('gl_account_ref')->nullable();
            $t->string('cashflow_direction')->nullable();
            $t->boolean('proposed_integral');
            $t->text('rationale');
            $t->unsignedInteger('priority')->default(100);
            $t->boolean('active')->default(true);
            $t->string('approved_at')->nullable();
            $t->timestamps();
        });

        DB::table('loan_books')->insert([
            'contract_id' => '104450000053', 'customer_id' => '93', 'reporting_period' => '2026-06-30',
        ]);
    }

    /** A row in the shape the delivered file plus the requested fields would take, after the reader's aliases. */
    private function row(array $overrides = []): array
    {
        return $overrides + [
            'run_id' => '7',
            'customer_id' => '93',
            'contract_id' => '000104450000053',
            'sub_account_no' => '1',
            'origination_date' => '2025-05-22',
            'maturity_date' => '2027-05-22',
            'approved_amount' => 100_000_000,
            'drawn_amount' => 100_000_000,
            'contractual_rate' => 30.3,
            'rate_type' => 'Variable',
            'repayment_frequency' => 'Monthly',
            'scheme_code' => 'MAIIC-IND-01',
            'interest_policy' => 'P',
            'floating_flag' => 'F',
            'interest_calc_base' => 'B',
            'installment_based_on' => 'Disbursement',
            'emi_calc_type' => 'E',
            'moratorium_type' => 'Principle Only',
            'grace_period_months' => '3 M',
            'interest_start_date' => '2025-05-22',
            'first_instalment_date' => '2025-08-22',
            'account_status_code' => 'A',
            'los_application_no' => 'LOS-2025-0417',
            'los_process_ref' => 'PR-88213',
            'predecessor_sub_account' => '',
        ];
    }

    private function contract(): object
    {
        return DB::table('contract_eir')->where('contract_id', '104450000053')->first();
    }

    public function test_ebanker_codes_are_stored_verbatim_and_the_lineage_fields_load(): void
    {
        $result = app(ContractMasterImportService::class)->import([$this->row()]);

        $this->assertSame(1, $result['created']);
        $this->assertSame([], $result['notes']);

        $c = $this->contract();
        $this->assertSame('MAIIC-IND-01', $c->scheme_code);
        $this->assertSame('P', $c->interest_policy);
        $this->assertSame('F', $c->floating_flag);
        $this->assertSame('B', $c->interest_calc_base);
        $this->assertSame('Disbursement', $c->installment_based_on);
        $this->assertSame('E', $c->emi_calc_type);
        $this->assertSame(3, (int) $c->grace_period_months);
        $this->assertSame('2025-05-22', $c->interest_start_date);
        $this->assertSame('2025-08-22', $c->first_instalment_date);
        $this->assertSame('A', $c->account_status_code);
        $this->assertSame('LOS-2025-0417', $c->los_application_no);
        $this->assertSame('PR-88213', $c->los_process_ref);
        $this->assertNull($c->predecessor_sub_account);
        // The existing rate_type mapping from RATE_BASIS is untouched (D16).
        $this->assertSame('FLOATING', $c->rate_type);
        // The spread is derived later, by SpreadDerivationService; the import leaves it alone.
        $this->assertNull($c->spread_over_prime);
        $this->assertSame(0, (int) $c->spread_drift_flag);
    }

    public function test_reprice_flag_follows_the_interest_policy_alone(): void
    {
        $service = app(ContractMasterImportService::class);

        $service->import([$this->row(['interest_policy' => 'P', 'floating_flag' => 'N'])]);
        $this->assertSame(1, (int) $this->contract()->reprice_flag, 'P reprices at every PLR change');

        $service->import([$this->row(['interest_policy' => 'F', 'floating_flag' => 'N'])]);
        $this->assertSame(0, (int) $this->contract()->reprice_flag, 'F never reprices');

        $second = $service->import([$this->row(['interest_policy' => 'M', 'floating_flag' => 'N'])]);
        $this->assertSame(1, $second['updated']);
        $this->assertNull($this->contract()->reprice_flag, 'M is read from the loan book each month; the engine does not decide');
        $this->assertSame('M', $this->contract()->interest_policy);
    }

    public function test_a_longer_policy_value_is_read_by_its_leading_letter_and_junk_is_named(): void
    {
        $service = app(ContractMasterImportService::class);

        $service->import([$this->row(['interest_policy' => 'P - Link with PLR', 'emi_calc_type' => 'E (EMI)'])]);
        $this->assertSame('P', $this->contract()->interest_policy);
        $this->assertSame('E', $this->contract()->emi_calc_type);

        $result = $service->import([$this->row(['interest_calc_base' => '365'])]);
        $this->assertStringContainsString("LOAN_INTEREST_CALC_BASE '365' is not a one-letter E-Banker code", $result['notes']['104450000053']);
    }

    public function test_moratorium_type_is_mapped_from_ebankers_two_options_and_kept_verbatim(): void
    {
        $service = app(ContractMasterImportService::class);

        $service->import([$this->row(['moratorium_type' => 'Principle Only'])]);
        $this->assertSame('PRINCIPAL_ONLY', $this->contract()->moratorium_type);
        $this->assertSame('Principle Only', $this->contract()->moratorium_type_verbatim);

        $service->import([$this->row(['moratorium_type' => 'Both (Interest + Principle)'])]);
        $this->assertSame('BOTH', $this->contract()->moratorium_type);
        $this->assertSame('Both (Interest + Principle)', $this->contract()->moratorium_type_verbatim);

        $service->import([$this->row(['moratorium_type' => 'Both'])]);
        $this->assertSame('BOTH', $this->contract()->moratorium_type);

        $result = $service->import([$this->row(['moratorium_type' => 'Interest Only'])]);
        $this->assertStringContainsString("moratorium type 'Interest Only' is not one of E-Banker's two options", $result['notes']['104450000053']);
        $this->assertSame('Interest Only', $this->contract()->moratorium_type_verbatim);
        // The mapped value is not blanked by a sparse or unrecognised re-delivery; the note is the signal.
        $this->assertSame('BOTH', $this->contract()->moratorium_type);
    }

    public function test_a_floating_flag_that_disagrees_with_the_policy_is_noted_not_acted_on(): void
    {
        $result = app(ContractMasterImportService::class)->import([$this->row([
            'interest_policy' => 'P', 'floating_flag' => 'N', 'rate_type' => 'Fixed',
        ])]);

        $this->assertArrayHasKey('104450000053', $result['notes']);
        $this->assertStringContainsString("floating flag 'N' disagrees with interest policy 'P'", $result['notes']['104450000053']);

        $c = $this->contract();
        $this->assertSame('N', $c->floating_flag);
        $this->assertSame(1, (int) $c->reprice_flag, 'the policy decides');
        $this->assertSame('FIXED', $c->rate_type, 'rate_type still comes from RATE_BASIS, never from the flag');

        $agreeing = app(ContractMasterImportService::class)->import([$this->row(['interest_policy' => 'F', 'floating_flag' => 'N'])]);
        $this->assertSame([], $agreeing['notes']);
    }

    public function test_reimporting_the_same_codes_is_a_no_op(): void
    {
        $service = app(ContractMasterImportService::class);
        $service->import([$this->row()]);

        $second = $service->import([$this->row()]);

        $this->assertSame(0, $second['updated']);
        $this->assertSame(1, $second['unchanged']);
    }
}
