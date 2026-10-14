<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UI audit of 14 October 2026: a collateral type code is text (PROPERTY,
 * VEHICLE, LAND...). The register import, its sample file and the Collateral
 * Types screen all use text codes, and the register stores the code as text,
 * but collateral_types.type_code was created as an integer, so adding the
 * first type from the screen failed with "Incorrect integer value" and the
 * register import could never be loaded. Numeric codes already held are kept
 * as their text.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collateral_types', function (Blueprint $table) {
            $table->string('type_code', 50)->change();
        });
    }

    public function down(): void
    {
        // a text code cannot go back into an integer column without loss
    }
};
