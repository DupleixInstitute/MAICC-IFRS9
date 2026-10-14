<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The compliance register's "test" column holds the test that proves a row
 * (spec v4 section 12.5). It was created as a string of 500, and the
 * committed data modules (docs/compliance/*.json) carry a test of 630
 * characters, so the bootstrap's compliance register step stopped with
 * "Data too long for column 'test'" on every run and the register was never
 * loaded (bootstrap audit, 14 October 2026). The column becomes text, as the
 * other long columns of the row already are; nothing held is shortened.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compliance_audit_rows', function (Blueprint $t) {
            $t->text('test')->nullable()->change();
        });
    }

    public function down(): void
    {
        // a test longer than 500 characters cannot go back into the old column without loss
    }
};
