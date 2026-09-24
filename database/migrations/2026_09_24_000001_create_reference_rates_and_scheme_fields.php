<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase P2 of the EIR engine (spec v3, 24 Sep 2026, sections 6.3 and 12.1):
 * the reference-rate series, the E-Banker scheme table, and the columns on
 * contract_eir that hold E-Banker's own codes verbatim.
 *
 * reference_rate_series is the Reserve Bank prime lending rate (PLR) as a
 * dated series, one row per rate change per index. Rates are stored as
 * percentages (25.30000 means 25.3 percent), the way the file and the loan
 * book write them, so the spread arithmetic in SpreadDerivationService is a
 * plain subtraction. The three audit columns (source_row, as_delivered,
 * interpretation) keep what the delivered file said and how it was read,
 * because 20 of the 48 delivered rows had day and month transposed by Excel
 * and the repair must stay visible (decision D19).
 *
 * schemes mirrors E-Banker's scheme master: the six settings that shape a
 * schedule (interest policy, floating flag, calculation base, instalment
 * basis, EMI type, default moratorium type) by scheme and effective date.
 * Phase P4 reads it; P2 only creates it so the intake can be built against
 * a real table.
 *
 * On contract_eir the new columns fall into three groups. The verbatim codes
 * (interest_policy, floating_flag, interest_calc_base, installment_based_on,
 * emi_calc_type, moratorium_type_verbatim, account_status_code) are stored as
 * E-Banker writes them and never reinterpreted on the way in (decision D16).
 * The derived columns (moratorium_type, reprice_flag, spread_over_prime,
 * spread_source, spread_drift_flag) are what the engine concluded from them,
 * kept apart so the conclusion can be checked against the source. The
 * lineage columns (scheme_code, los_application_no, los_process_ref,
 * predecessor_sub_account) are the only join back to the offer letter and
 * the restructure history.
 *
 * spread_over_prime is in percentage points, like the series it is derived
 * from. The existing markup column keeps MAIIC's supplied figure as a decimal
 * fraction; the two are compared, never merged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reference_rate_series', function (Blueprint $table) {
            $table->id();
            $table->string('index_code', 20)->default('PLR');
            $table->date('effective_date');
            $table->decimal('rate', 8, 5)->comment('Percent, e.g. 25.30000');
            $table->string('source_row')->nullable();
            $table->string('as_delivered')->nullable();
            $table->string('interpretation')->nullable();
            $table->unsignedBigInteger('import_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['index_code', 'effective_date'], 'uq_reference_rate_index_date');
        });

        Schema::create('schemes', function (Blueprint $table) {
            $table->id();
            $table->string('scheme_code', 30);
            $table->string('product_code', 30)->nullable();
            $table->char('interest_policy', 1)->nullable();
            $table->char('floating_flag', 1)->nullable();
            $table->char('interest_calc_base', 1)->nullable();
            $table->string('installment_based_on', 20)->nullable();
            $table->char('emi_calc_type', 1)->nullable();
            $table->string('default_moratorium_type', 20)->nullable();
            $table->date('effective_from');
            $table->timestamps();

            $table->unique(['scheme_code', 'effective_from'], 'uq_scheme_code_effective');
        });

        Schema::table('contract_eir', function (Blueprint $table) {
            $table->string('scheme_code', 30)->nullable()->after('product_type');
            $table->char('interest_policy', 1)->nullable()->after('scheme_code');
            $table->char('floating_flag', 1)->nullable()->after('interest_policy');
            $table->char('interest_calc_base', 1)->nullable()->after('floating_flag');
            $table->string('installment_based_on', 20)->nullable()->after('interest_calc_base');
            $table->char('emi_calc_type', 1)->nullable()->after('installment_based_on');
            $table->string('moratorium_type', 20)->nullable()->after('emi_calc_type')
                ->comment('PRINCIPAL_ONLY or BOTH, derived from moratorium_type_verbatim');
            $table->string('moratorium_type_verbatim', 40)->nullable()->after('moratorium_type');
            $table->unsignedTinyInteger('grace_period_months')->nullable()->after('moratorium_type_verbatim');
            $table->date('moratorium_from')->nullable()->after('grace_period_months');
            $table->date('interest_start_date')->nullable()->after('moratorium_from');
            $table->date('first_instalment_date')->nullable()->after('interest_start_date');
            $table->decimal('spread_over_prime', 8, 5)->nullable()->after('first_instalment_date')
                ->comment('Percentage points over the reference rate, derived');
            $table->string('spread_source', 10)->nullable()->after('spread_over_prime')
                ->comment('DERIVED or SUPPLIED');
            $table->boolean('spread_drift_flag')->default(false)->after('spread_source');
            $table->boolean('reprice_flag')->nullable()->after('spread_drift_flag')
                ->comment('true = reprice at every PLR change (policy P); false = never (F); null = manual or unknown');
            $table->string('account_status_code', 10)->nullable()->after('reprice_flag');
            $table->string('los_application_no', 40)->nullable()->after('account_status_code');
            $table->string('los_process_ref', 40)->nullable()->after('los_application_no');
            $table->string('predecessor_sub_account', 60)->nullable()->after('los_process_ref');
        });
    }

    public function down(): void
    {
        Schema::table('contract_eir', function (Blueprint $table) {
            $table->dropColumn([
                'scheme_code', 'interest_policy', 'floating_flag', 'interest_calc_base',
                'installment_based_on', 'emi_calc_type', 'moratorium_type', 'moratorium_type_verbatim',
                'grace_period_months', 'moratorium_from', 'interest_start_date', 'first_instalment_date',
                'spread_over_prime', 'spread_source', 'spread_drift_flag', 'reprice_flag',
                'account_status_code', 'los_application_no', 'los_process_ref', 'predecessor_sub_account',
            ]);
        });

        Schema::dropIfExists('schemes');
        Schema::dropIfExists('reference_rate_series');
    }
};
