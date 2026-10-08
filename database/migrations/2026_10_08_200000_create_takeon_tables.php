<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| The take-on schedules, landed not typed (spec v4 section 6.9)
|--------------------------------------------------------------------------
| takeon_blocks          one row per amortisation block in Tamanda's workbook:
|                        the block's own terms, the Loan Book row it belongs
|                        to, the mapped E-Banker account with its confidence
|                        and her tick, the fees, and the sheet and cell each
|                        value came from
| takeon_schedule_lines  one row per instalment line of a block
| contract_takeon        the build: per account, the origination date, the
|                        original principal, the contractual rate, the fees,
|                        the basis the engine treats the pre-migration history
|                        on, and the difference at take-on
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('takeon_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('load_id')->constrained('ebanker_loads');
            $table->unsignedInteger('block_no');
            $table->unsignedInteger('sheet_row')->comment('Row of the block title in Amortisation and Repayments');
            $table->string('title')->nullable();
            $table->decimal('principal', 20, 4)->nullable();
            $table->decimal('rate', 10, 6)->nullable()->comment('Opening rate, percent');
            $table->unsignedSmallInteger('periods_per_year')->nullable();
            $table->unsignedSmallInteger('total_periods')->nullable();
            $table->decimal('pmt', 20, 4)->nullable();
            $table->boolean('restructured')->default(false);
            $table->unsignedInteger('loan_book_row')->nullable();
            $table->string('facility_name')->nullable();
            $table->string('facility_type', 40)->nullable();
            $table->date('value_date')->nullable();
            $table->date('maturity_date')->nullable();
            $table->decimal('tenor_years', 8, 4)->nullable();
            $table->string('moratorium', 60)->nullable();
            $table->decimal('loan_book_rate', 10, 6)->nullable();
            $table->decimal('approved', 20, 4)->nullable();
            $table->decimal('disbursed', 20, 4)->nullable();
            $table->decimal('principal_31_oct_2024', 20, 4)->nullable();
            $table->decimal('interest_to_date', 20, 4)->nullable();
            $table->decimal('repayments', 20, 4)->nullable();
            $table->decimal('carrying_31_oct_2024', 20, 4)->nullable();
            $table->string('account', 30)->nullable()->index()->comment('Mapped E-Banker account, after any correction');
            $table->string('proposed_account', 30)->nullable();
            $table->string('confidence', 20)->nullable();
            $table->string('confirmed', 1)->nullable()->comment('Y, N or blank: Tamanda\'s tick');
            $table->string('corrected_account', 30)->nullable();
            $table->text('comment')->nullable();
            $table->decimal('arrangement_fee', 20, 4)->nullable();
            $table->decimal('legal_fees', 20, 4)->nullable();
            $table->decimal('other_fees', 20, 4)->nullable();
            $table->date('fee_date')->nullable();
            $table->string('fee_deducted', 1)->nullable();
            $table->string('fee_source')->nullable();
            $table->decimal('total_fees', 20, 4)->nullable()->comment('Null = no fee row; 0 = known nil');
            $table->json('cells')->comment('Sheet and cell of every value above');
            $table->json('gates')->nullable();
            $table->string('status', 20)->default('MAPPED')->comment('MAPPED | NOT_MATCHED | REFUSED | FLAGGED');
            $table->timestamps();
            $table->unique(['load_id', 'block_no']);
        });

        Schema::create('takeon_schedule_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('block_id')->constrained('takeon_blocks')->cascadeOnDelete();
            $table->unsignedInteger('serial');
            $table->unsignedInteger('sheet_row');
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->unsignedSmallInteger('days')->nullable();
            $table->decimal('opening_balance', 20, 4)->nullable();
            $table->decimal('instalment', 20, 4)->nullable();
            $table->decimal('interest', 20, 4)->nullable();
            $table->decimal('principal', 20, 4)->nullable();
            $table->decimal('closing_balance', 20, 4)->nullable();
            $table->decimal('amount_repaid', 20, 4)->nullable();
            $table->decimal('accumulated_arrears', 20, 4)->nullable();
            $table->boolean('out_of_order')->default(false)->comment('Serial out of due-date order; sorted by date on build (F16)');
            $table->timestamps();
            $table->index(['block_id', 'period_end']);
        });

        Schema::create('contract_takeon', function (Blueprint $table) {
            $table->id();
            $table->string('account', 30)->unique();
            $table->string('contract_id', 60)->index();
            $table->foreignId('block_id')->nullable()->constrained('takeon_blocks');
            $table->string('basis', 20)->comment('RECOMPUTED | TAKEON_BALANCE');
            $table->date('origination_date')->nullable();
            $table->decimal('original_principal', 20, 4)->nullable();
            $table->decimal('contractual_rate', 10, 6)->nullable();
            $table->decimal('fees_total', 20, 4)->nullable();
            $table->date('fee_date')->nullable();
            $table->boolean('fees_deducted')->nullable();
            $table->decimal('takeon_posting', 20, 4)->nullable()->comment('E-Banker opening principal at 31 Jul 2024');
            $table->decimal('takeon_opening_interest', 20, 4)->nullable();
            $table->decimal('takeon_opening_recovery', 20, 4)->nullable()->comment('Repayments to date at migration, the 305 opening leg');
            $table->decimal('schedule_balance_at_takeon', 20, 4)->nullable()->comment('The block\'s closing balance at 31 Jul 2024');
            $table->decimal('difference_at_takeon', 20, 4)->nullable()->comment('Take-on principal net of the opening recovery, less the schedule balance: arrears (+) or prepayment (-)');
            $table->json('flags')->nullable();
            $table->string('setting_value')->nullable()->comment('takeon_history_basis in force at the build');
            $table->timestamp('built_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_takeon');
        Schema::dropIfExists('takeon_schedule_lines');
        Schema::dropIfExists('takeon_blocks');
    }
};
