<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ChangeReportingPeriodColumnsToTextInTransitionMatricesTable extends Migration
{
    public function up()
    {
        // Drop the index first
        Schema::table('transition_matrices', function ($table) {
            $table->dropIndex('start_end_reporting_period_index');
        });

        // Then change to TEXT
        DB::statement('ALTER TABLE transition_matrices MODIFY start_reporting_period TEXT');
        DB::statement('ALTER TABLE transition_matrices MODIFY end_reporting_period TEXT');
    }

    public function down()
    {
        // Revert to DATE
        DB::statement('ALTER TABLE transition_matrices MODIFY start_reporting_period DATE NOT NULL');
        DB::statement('ALTER TABLE transition_matrices MODIFY end_reporting_period DATE NOT NULL');

        // Restore the index
        Schema::table('transition_matrices', function ($table) {
            $table->index(['start_reporting_period', 'end_reporting_period'], 'start_end_reporting_period_index');
        });
    }
}

