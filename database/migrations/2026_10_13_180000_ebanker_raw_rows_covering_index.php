<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The landing zone's readers all start from the current rows of landed loads for a set
 * of query ids (LandingZoneReader::current): superseded_at null, load_id landed,
 * query_id in the family. After packs 3 and 4 (13 October 2026) the table holds about
 * 620,000 rows, and the E-Banker Feed screen's count per query scanned all of them and
 * timed out. This index answers those filters, the count and the last date from the
 * index alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ebanker_raw_rows', function (Blueprint $t) {
            $t->index(['query_id', 'superseded_at', 'load_id', 'row_date'], 'ebanker_raw_rows_current_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ebanker_raw_rows', function (Blueprint $t) {
            $t->dropIndex('ebanker_raw_rows_current_idx');
        });
    }
};
