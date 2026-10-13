<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Externally calibrated model versions (FLI module, External Calculations)
|--------------------------------------------------------------------------
| A model calibrated outside the system (by a consultant, or in a validation
| workbook) is entered on External Calculations with its evidence. It is
| proposed by one person, approved by another, and then governs its months
| exactly as a version published from a fit does.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fli_model_versions', function (Blueprint $t) {
            if (! Schema::hasColumn('fli_model_versions', 'source')) {
                $t->string('source', 16)->default('fit')->comment('fit (the system\'s regression) or external');
            }
            if (! Schema::hasColumn('fli_model_versions', 'evidence')) {
                $t->text('evidence')->nullable();
            }
            if (! Schema::hasColumn('fli_model_versions', 'proposed_at')) {
                $t->timestamp('proposed_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('fli_model_versions', function (Blueprint $t) {
            foreach (['source', 'evidence', 'proposed_at'] as $c) {
                if (Schema::hasColumn('fli_model_versions', $c)) {
                    $t->dropColumn($c);
                }
            }
        });
    }
};
