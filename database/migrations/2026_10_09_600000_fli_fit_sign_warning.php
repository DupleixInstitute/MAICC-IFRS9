<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| The advisory sign test leaves a warning on the fit (spec v4 section 14.4)
|--------------------------------------------------------------------------
| System audit of 9 October 2026, finding M12: fli_expected_sign_test was
| read by nothing. Under its advisory option a fit with the wrong sign is
| not declined; the guardrail records the warning here instead, so the fit
| stays applicable and a reviewer sees the sign before approving it.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fli_fits', function (Blueprint $t) {
            $t->string('sign_warning', 255)->nullable()->after('sign_ok')
                ->comment('Set when the governed sign test is advisory and the realised sign is not the expected one');
        });
    }

    public function down(): void
    {
        Schema::table('fli_fits', function (Blueprint $t) {
            $t->dropColumn('sign_warning');
        });
    }
};
