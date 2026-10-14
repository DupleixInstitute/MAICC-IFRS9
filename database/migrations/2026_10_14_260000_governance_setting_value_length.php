<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| A governed option longer than 60 characters (14 October 2026)
|--------------------------------------------------------------------------
| The value of a governance setting was a string of 60. The setting
| fli_without_model (FLI module D-F1, note of 14 October 2026) has an option
| of 108 characters ("Proceed: the PD holds with no forward-looking
| adjustment, recorded and disclosed; sign-off needs a commentary"), which a
| 60-character column would cut. The value column of the settings and of
| their history is widened to 255; no value changes.
*/
return new class extends Migration
{
    public function up(): void
    {
        foreach (['governance_settings', 'governance_setting_history'] as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $t) {
                    $t->string('value', 255)->change();
                });
            }
        }
    }

    public function down(): void
    {
        // narrowing back would cut a long option already stored; the column stays at 255
    }
};
