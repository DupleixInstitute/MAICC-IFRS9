<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The generated version 1 schedule against E-Banker's live instalment chart
 * (App\Services\Eir\ScheduleChartCheck): the status (AGREES, DIFFERS,
 * NO_CHART), the reasons, and the two sets of figures, recorded when the
 * schedule is generated and when its approval is asked for.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contract_eir') && ! Schema::hasColumn('contract_eir', 'schedule_chart_check')) {
            Schema::table('contract_eir', function (Blueprint $t) {
                $t->json('schedule_chart_check')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('contract_eir', 'schedule_chart_check')) {
            Schema::table('contract_eir', function (Blueprint $t) {
                $t->dropColumn('schedule_chart_check');
            });
        }
    }
};
