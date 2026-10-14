<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An EIR locked by the bootstrap's automated data-readiness label in place of a second person
 * carries that label, so it is never read as a MAIIC approval (audit M8/H9, 14 October 2026).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contract_eir') && ! Schema::hasColumn('contract_eir', 'locked_label')) {
            Schema::table('contract_eir', function (Blueprint $table) {
                $table->string('locked_label', 120)->nullable()->after('locked_by');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('contract_eir') && Schema::hasColumn('contract_eir', 'locked_label')) {
            Schema::table('contract_eir', fn (Blueprint $table) => $table->dropColumn('locked_label'));
        }
    }
};
