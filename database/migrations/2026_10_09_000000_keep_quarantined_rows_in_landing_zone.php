<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Quarantined rows are kept (system audit of 9 October 2026, finding M2 f)
|--------------------------------------------------------------------------
| Spec v4 section 6.4: when a pack fails a gate "the raw rows are kept in
| quarantine against the load". Until now a quarantined load wrote no row, so
| nobody could open the file that was refused. The rows of a quarantined
| load now sit in ebanker_raw_rows against that load, and every reader
| limits itself to loads whose status is LANDED.
|
| The unique key moves from (query_id, source_key, version) to (load_id,
| query_id, source_key): a quarantined load may hold the same source key,
| at version 1, as a later load that passes, and the lineage of versions is
| kept by the landing service, which only ever versions against rows of
| landed loads.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ebanker_raw_rows', function (Blueprint $table) {
            $table->dropUnique(['query_id', 'source_key', 'version']);
            $table->unique(['load_id', 'query_id', 'source_key']);
        });
    }

    public function down(): void
    {
        Schema::table('ebanker_raw_rows', function (Blueprint $table) {
            $table->dropUnique(['load_id', 'query_id', 'source_key']);
            $table->unique(['query_id', 'source_key', 'version']);
        });
    }
};
