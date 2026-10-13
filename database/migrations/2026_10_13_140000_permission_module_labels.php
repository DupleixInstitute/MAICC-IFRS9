<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Role editor labels only: the staging module was spelled "Stageing". Permission names
 * (stageing-rules.*) are what the routes check and are left as they are.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->where('module', 'Stageing Rules')->update(['module' => 'Staging and SICR Rules']);
        DB::table('permissions')->where('display_name', 'like', '%Stageing%')
            ->update(['display_name' => DB::raw("REPLACE(display_name, 'Stageing', 'Staging')")]);
    }

    public function down(): void
    {
        DB::table('permissions')->where('module', 'Staging and SICR Rules')->update(['module' => 'Stageing Rules']);
    }
};
