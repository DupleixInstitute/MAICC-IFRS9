<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use function Livewire\before;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
        public function up()
        {
            Schema::table('loss_given_default', function (Blueprint $table) {
                $table->decimal('partially_recovered_amount', 18,2)->nullable()->before('last_reporting_period'); 
                $table->decimal('fully_recovered_amount', 18,2)->nullable()->before('last_reporting_period'); 
                $table->decimal('total_disbursments', 18, 2)->nullable()->before('last_reporting_period'); 
                $table->string('calculation_source')->default('system')->before('last_reporting_period'); // 'system' or 'manual'// 'system' or 'manual'
                $table->decimal('written_offs', 18, 2)->nullable();
                 $table->unique(['reporting_period', 'calculation_source'], 'lgd_period_calcsource_unique');
            });
        }
public function down()
{
    Schema::table('loss_given_default', function (Blueprint $table) {
        if (Schema::hasColumn('loss_given_default', 'calculation_source')) {
            $table->dropColumn('calculation_source');
        }

        if (Schema::hasColumn('loss_given_default', 'partially_recovered_amount')) {
            $table->dropColumn('partially_recovered_amount');
        }

        if (Schema::hasColumn('loss_given_default', 'fully_recovered_amount')) {
            $table->dropColumn('fully_recovered_amount');
        }

        if (Schema::hasColumn('loss_given_default', 'total_disbursments')) {
            $table->dropColumn('total_disbursments');
        }

        if (Schema::hasColumn('loss_given_default', 'written_offs')) {
            $table->dropColumn('written_offs');
        }

        // Optional: drop the unique constraint if it exists
        DB::statement('ALTER TABLE loss_given_default DROP INDEX IF EXISTS lgd_period_calcsource_unique');
    });
}
};
