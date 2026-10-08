<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Macro statistics with their sources (spec v4 section 13, MS-1 and MS-3)
|--------------------------------------------------------------------------
| Each series carries the codes that identify it at each source and the
| country it is for; every commit from a source is a batch (source, address,
| fetched-at, who, rows, file hash) and every observation points to its
| batch, so the audit workbook can cite the provenance of each series.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('macro_statistics', function (Blueprint $table) {
            if (! Schema::hasColumn('macro_statistics', 'external_codes')) {
                $table->json('external_codes')->nullable()->after('website_link')->comment('{"world_bank": "NY.GDP.MKTP.KD.ZG", "imf_weo": "NGDP_RPCH", "rbm": "policy_rate"}');
            }
            if (! Schema::hasColumn('macro_statistics', 'country')) {
                $table->string('country', 3)->default('MWI')->after('external_codes');
            }
        });
        Schema::create('macro_source_import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('source', 40)->comment('world_bank | imf_weo | rbm_file | snapshot | manual');
            $table->string('address', 500)->nullable()->comment('The URL or file the rows came from');
            $table->string('country', 3)->default('MWI');
            $table->string('file_sha256', 64)->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->foreignId('committed_by')->nullable()->constrained('users');
            $table->unsignedInteger('rows')->default(0);
            $table->json('series')->nullable()->comment('Per series: code, indicator, rows, first, last, note');
            $table->text('note')->nullable();
            $table->timestamps();
        });
        Schema::table('macro_statistics_data', function (Blueprint $table) {
            if (! Schema::hasColumn('macro_statistics_data', 'source_import_batch_id')) {
                $table->foreignId('source_import_batch_id')->nullable()->after('source')->constrained('macro_source_import_batches');
            }
        });
    }

    public function down(): void
    {
        Schema::table('macro_statistics_data', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_import_batch_id');
        });
        Schema::dropIfExists('macro_source_import_batches');
        Schema::table('macro_statistics', function (Blueprint $table) {
            $table->dropColumn(['external_codes', 'country']);
        });
    }
};
