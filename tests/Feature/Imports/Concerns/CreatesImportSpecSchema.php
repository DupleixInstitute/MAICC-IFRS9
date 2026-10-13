<?php

namespace Tests\Feature\Imports\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * The tables the import specs read their allowed values from, and the
 * tables an import would write to (so a test can prove nothing was written).
 * SQLite in memory; the .env database is never touched.
 */
trait CreatesImportSpecSchema
{
    private string $storage;

    protected function createImportSpecSchema(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'activitylog.enabled' => false]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->storage = sys_get_temp_dir() . '/maiic-import-specs-' . uniqid();
        File::makeDirectory($this->storage . '/app/public', 0755, true);
        $this->app->useStoragePath($this->storage);

        Schema::create('imports', function (Blueprint $t) {
            $t->increments('id'); $t->string('kind')->nullable(); $t->string('name')->nullable(); $t->string('status')->default('pending');
            $t->integer('records')->default(0); $t->integer('failed_records')->default(0); $t->integer('rows_processed')->default(0);
            $t->text('settings')->nullable(); $t->string('failed_file_path')->nullable(); $t->string('started_at')->nullable(); $t->string('completed_at')->nullable(); $t->timestamps();
        });
        Schema::create('macro_credit_loss_definitions', function (Blueprint $t) { $t->increments('id'); $t->string('code'); $t->string('name'); $t->text('description')->nullable(); $t->timestamps(); });
        Schema::create('collateral_types', function (Blueprint $t) { $t->increments('id'); $t->string('type_code'); $t->string('name')->nullable(); $t->timestamps(); });
        Schema::create('collateral_registers', function (Blueprint $t) { $t->increments('id'); $t->string('customer_id')->nullable(); $t->string('collateral_type')->nullable(); $t->string('period')->nullable(); $t->timestamps(); });
        Schema::create('macro_statistics', function (Blueprint $t) { $t->increments('id'); $t->string('statistic_code'); $t->string('unit')->nullable(); $t->text('external_codes')->nullable(); $t->timestamps(); });
        Schema::create('macro_credit_loss_data', function (Blueprint $t) { $t->increments('id'); $t->integer('portfolio_id')->nullable(); $t->integer('definition_id'); $t->string('period'); $t->string('value')->nullable(); $t->timestamps(); });
        Schema::create('ebanker_queries', function (Blueprint $t) {
            $t->increments('id'); $t->string('query_id'); $t->string('title'); $t->string('source_table'); $t->string('key_column'); $t->string('account_column')->nullable();
            $t->string('date_column')->nullable(); $t->string('version')->default('1'); $t->timestamps();
        });
        Schema::create('import_mappings', function (Blueprint $t) { $t->increments('id'); $t->string('import_type'); $t->string('source_header'); $t->string('target_field'); $t->string('transform')->nullable(); $t->timestamps(); });
        Schema::create('loan_books', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id')->nullable(); $t->string('customer_id')->nullable(); $t->string('reporting_period')->nullable(); $t->timestamps(); });
        Schema::create('clients', function (Blueprint $t) { $t->increments('id'); $t->string('customer_id'); $t->string('name')->nullable(); $t->string('deleted_at')->nullable(); $t->timestamps(); });
        Schema::create('sicr_groups', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->text('description')->nullable(); $t->timestamps(); });

        DB::table('macro_credit_loss_definitions')->insert([
            ['code' => 'PD', 'name' => 'Probability of default'], ['code' => 'LGD', 'name' => 'Loss given default'], ['code' => 'NPL', 'name' => 'NPL ratio'],
        ]);
        DB::table('collateral_types')->insert([['type_code' => 'PROPERTY', 'name' => 'Property'], ['type_code' => 'VEHICLE', 'name' => 'Vehicle']]);
        DB::table('macro_statistics')->insert([['statistic_code' => 'GDP_GROWTH', 'unit' => 'Percent', 'external_codes' => json_encode(['imf_weo' => 'NGDP_RPCH'])]]);
        DB::table('ebanker_queries')->insert([
            ['query_id' => 'P1_01', 'title' => 'Ledger, every posting', 'source_table' => 'CUMVOUCH', 'key_column' => 'CUMVOUCH_DET_ID', 'account_column' => 'NEW_AC_NUMBER', 'date_column' => 'TRANSACTION_DATE', 'version' => '1'],
            ['query_id' => 'P1_02', 'title' => 'Account master', 'source_table' => 'ACMASTER', 'key_column' => 'NEW_AC_NUMBER', 'account_column' => 'NEW_AC_NUMBER', 'date_column' => 'ACCOUNT_OPEN_DATE', 'version' => '1'],
        ]);
    }

    protected function dropImportSpecStorage(): void
    {
        File::deleteDirectory($this->storage);
    }

    /** A CSV upload from rows (the first row is the heading). */
    protected function csvUpload(array $rows, string $name = 'upload.csv'): \Illuminate\Http\UploadedFile
    {
        $path = $this->storage . '/' . uniqid() . '-' . $name;
        $h = fopen($path, 'w');
        foreach ($rows as $r) {
            fputcsv($h, $r);
        }
        fclose($h);

        return new \Illuminate\Http\UploadedFile($path, $name, 'text/csv', null, true);
    }
}
