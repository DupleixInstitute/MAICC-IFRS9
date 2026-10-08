<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| The E-Banker landing zone (spec v4 section 6.3, decision D22)
|--------------------------------------------------------------------------
| Raw rows from E-Banker, loaded exactly as received and never edited.
|
|   ebanker_loads       one row per pack: period, route, who, when, the manifest,
|                       the gate results, the watermark after loading
|   ebanker_pack_files  one row per file in a pack: query id, version, rows,
|                       SHA-256, and whether the file passed its gates
|   ebanker_raw_rows    every row of every file, verbatim as JSON, keyed by the
|                       query id and the source table's own key. A row that
|                       arrives again with different content becomes a new
|                       version (the earlier one keeps superseded_at), so a
|                       month can be re-derived exactly as it looked at the time.
|
| The spec names one table per E-Banker source (ebanker_ledger, ebanker_
| balance_history, ...). They are kept here as logical tables inside one
| physical table, distinguished by query_id, so that the loader and the gates
| are one piece of code for every query and a new query needs no migration.
| Nothing downstream reads the raw rows except the build (section 6.6).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ebanker_loads', function (Blueprint $table) {
            $table->id();
            $table->string('pack_hash', 64)->unique()->comment('SHA-256 of the manifest');
            $table->string('pack_name');
            $table->string('route', 40)->default('ROUTE_1_MANUAL');
            $table->string('period', 7)->nullable()->comment('YYYY-MM the pack is for; null for the history pack');
            $table->date('run_at')->nullable()->comment('When the queries were run, from the manifest');
            $table->string('date_format', 20)->default('m/d/Y')->comment('How dates are written in the files, from the manifest');
            $table->json('manifest');
            $table->json('gates')->nullable()->comment('Each gate with pass/fail and the row named on failure');
            $table->string('status', 20)->default('PENDING')->comment('PENDING | LANDED | QUARANTINED');
            $table->json('watermarks')->nullable()->comment('Highest source key per query after loading');
            $table->foreignId('loaded_by')->nullable()->constrained('users');
            $table->timestamp('loaded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ebanker_pack_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('load_id')->constrained('ebanker_loads')->cascadeOnDelete();
            $table->string('file');
            $table->string('query_id', 20)->index();
            $table->string('query_version', 20)->default('1');
            $table->string('sha256', 64);
            $table->unsignedInteger('rows_declared')->nullable();
            $table->unsignedInteger('rows_loaded')->default(0);
            $table->unsignedInteger('rows_new')->default(0);
            $table->unsignedInteger('rows_versioned')->default(0);
            $table->unsignedInteger('rows_unchanged')->default(0);
            $table->string('status', 20)->default('PENDING');
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['load_id', 'file']);
        });

        Schema::create('ebanker_raw_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('load_id')->constrained('ebanker_loads');
            $table->string('query_id', 20);
            $table->string('source_key', 60)->comment('The source table\'s own key: CUMVOUCH_DET_ID, ACCOUNT_BAL_MST_ID, ...');
            $table->string('account', 30)->nullable()->index();
            $table->date('row_date')->nullable()->index()->comment('The row\'s own date, parsed once at landing');
            $table->json('payload')->comment('Every column of the row, verbatim');
            $table->string('row_hash', 64);
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('superseded_at')->nullable();
            $table->timestamps();
            $table->index(['query_id', 'source_key']);
            $table->index(['query_id', 'row_date']);
            $table->unique(['query_id', 'source_key', 'version']);
        });

        Schema::create('ebanker_queries', function (Blueprint $table) {
            $table->id();
            $table->string('query_id', 20)->unique();
            $table->string('title');
            $table->string('source_table', 60)->nullable();
            $table->string('key_column', 60)->nullable()->comment('Column that is the source key; null = row number');
            $table->string('account_column', 60)->nullable();
            $table->string('date_column', 60)->nullable();
            $table->string('version', 20)->default('1');
            $table->text('sql')->nullable();
            $table->string('sha256', 64)->nullable();
            $table->boolean('incremental')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ebanker_raw_rows');
        Schema::dropIfExists('ebanker_pack_files');
        Schema::dropIfExists('ebanker_loads');
        Schema::dropIfExists('ebanker_queries');
    }
};
