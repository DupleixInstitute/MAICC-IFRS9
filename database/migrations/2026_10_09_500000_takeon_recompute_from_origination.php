<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| The take-on recompute from origination (spec v4 section 6.9)
|--------------------------------------------------------------------------
| System audit of 9 October 2026, finding M7: the "recompute from
| origination" basis wrote a label and nothing else. The build now solves
| the EIR on the workbook schedule and the fees, rolls the amortised cost
| forward month by month to 31 July 2024, and writes the workbook schedule
| as the contract's version 1 with schedule_source TAKEON_WORKBOOK.
|
| contract_takeon gains the recomputed figures beside the take-on balance;
| the two schedule_source columns, which were an enum of IMPORTED and
| GENERATED, become plain strings so that TAKEON_WORKBOOK fits. The enum is
| widened by a raw statement on MySQL, as this project's earlier migrations
| do; SQLite declares the enum as a check constraint that only a table
| rebuild would change, and every SQLite schema in the tests declares the
| column as a string already.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_takeon', function (Blueprint $table) {
            $table->decimal('takeon_balance', 20, 4)->nullable()->after('difference_at_takeon')
                ->comment('E-Banker carrying amount after the opening legs: principal plus opening interest less opening recovery');
            $table->decimal('net_investment', 20, 4)->nullable()->after('takeon_balance')
                ->comment('Original principal less the integral fees: the amount the EIR is solved on');
            $table->decimal('recomputed_eir', 12, 8)->nullable()->after('net_investment')
                ->comment('Effective annual rate solved from origination on the workbook schedule');
            $table->decimal('recomputed_eir_monthly', 12, 8)->nullable()->after('recomputed_eir');
            $table->decimal('recomputed_amortised_cost', 20, 4)->nullable()->after('recomputed_eir_monthly')
                ->comment('Amortised cost rolled forward at the EIR to 31 Jul 2024');
            $table->decimal('recomputed_difference', 20, 4)->nullable()->after('recomputed_amortised_cost')
                ->comment('Take-on balance less the recomputed amortised cost');
            $table->unsignedInteger('schedule_lines_written')->default(0)->after('recomputed_difference')
                ->comment('Version 1 schedule lines written from the workbook (schedule_source TAKEON_WORKBOOK)');
            $table->json('fees_detail')->nullable()->after('schedule_lines_written')
                ->comment('The take-on fees found on the block, each with its cell');
            $table->json('recompute_detail')->nullable()->after('fees_detail')
                ->comment('The solve (inputs, method, residual) and the month-by-month roll-forward');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE contract_cashflow_schedule MODIFY COLUMN schedule_source VARCHAR(20) NOT NULL DEFAULT 'IMPORTED'");
            DB::statement('ALTER TABLE contract_eir MODIFY COLUMN schedule_source VARCHAR(20) NULL');
        }
    }

    public function down(): void
    {
        Schema::table('contract_takeon', function (Blueprint $table) {
            $table->dropColumn(['takeon_balance', 'net_investment', 'recomputed_eir', 'recomputed_eir_monthly', 'recomputed_amortised_cost',
                'recomputed_difference', 'schedule_lines_written', 'fees_detail', 'recompute_detail']);
        });
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE contract_cashflow_schedule MODIFY COLUMN schedule_source ENUM('IMPORTED','GENERATED') NOT NULL DEFAULT 'IMPORTED'");
            DB::statement("ALTER TABLE contract_eir MODIFY COLUMN schedule_source ENUM('IMPORTED','GENERATED') NULL");
        }
    }
};
