<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCalcHeaderFieldsToTransitionMatricesTable extends Migration
{
    public function up()
    {
        Schema::table('transition_matrices', function (Blueprint $table) {
            $table->integer('start_year')->default(0)->after('start_reporting_period');
            $table->integer('start_month')->default(0)->after('start_year');
            $table->integer('end_year')->default(0)->after('end_reporting_period');
            $table->integer('end_month')->default(0)->after('end_year');
            $table->tinyInteger('transition_years')->default(0)->after('end_month');

            $table->mediumInteger('run_no')->default(1)->after('transition_years');
            $table->unsignedBigInteger('records_count_updated')->nullable()->after('run_no');
            $table->unsignedBigInteger('records_count_transitioned')->nullable()->after('records_count_updated');

            $table->tinyInteger('reporting_periods_count')->nullable()->after('records_count_transitioned');
            $table->decimal('updated_balance', 20, 2)->nullable()->after('reporting_periods_count');
            $table->decimal('transition_balance', 20, 2)->nullable()->after('updated_balance');

            $table->dateTime('last_calculation_date')->useCurrent()->after('transition_balance');
            $table->tinyInteger('portfolio_count')->nullable()->after('last_calculation_date');
            $table->dateTime('book_updated_at')->useCurrent()->after('portfolio_count');

            $table->boolean('take_on_flag')->default(0)->after('book_updated_at');
            $table->text('comments')->nullable()->after('take_on_flag');
            $table->string('user_name', 20)->nullable()->after('comments');
        });
    }

    public function down()
    {
        Schema::table('transition_matrices', function (Blueprint $table) {
            $table->dropColumn([
                'start_year', 'start_month',
                'end_year', 'end_month',
                'transition_years', 'run_no',
                'records_count_updated', 'records_count_transitioned',
                'reporting_periods_count', 'updated_balance', 'transition_balance',
                'last_calculation_date', 'portfolio_count', 'book_updated_at',
                'take_on_flag', 'comments', 'user_name'
            ]);
        });
    }
}

