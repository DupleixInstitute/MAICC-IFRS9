<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFieldsToTransitionMatricesTable extends Migration
{
    public function up()
    {
        Schema::table('transition_matrices', function (Blueprint $table) {
            $table->string('pd_start_stage_total_type', 50)->nullable()->after('end_reporting_period');
            $table->unsignedBigInteger('portfolio_group_id')->nullable()->after('pd_start_stage_total_type');
            $table->string('calculation_source', 50)->nullable()->after('portfolio_group_id');

            // Foreign key for portfolio_group_id
            $table->foreign('portfolio_group_id')
                  ->references('id')->on('loan_portfolios')
                  ->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('transition_matrices', function (Blueprint $table) {
            $table->dropForeign(['portfolio_group_id']);
            $table->dropColumn([
                'pd_start_stage_total_type',
                'portfolio_group_id',
                'calculation_source'
            ]);
        });
    }
}

