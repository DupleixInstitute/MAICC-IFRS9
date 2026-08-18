<?php

namespace Tests\Feature\Eir;

use App\Http\Controllers\EirIntakeController;
use App\Models\AuditLog;
use App\Models\Import;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EirIntakeStatusTest extends TestCase
{
    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('imports', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('pending');
            $table->integer('records')->nullable();
            $table->integer('rows_processed')->nullable();
            $table->integer('failed_records')->nullable();
            $table->string('failed_file_path')->nullable();
            $table->string('name')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action');
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('scope')->nullable();
            $table->string('reporting_period')->nullable();
            $table->integer('rows_affected')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('meta')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function test_completed_extract_b_status_includes_the_audited_outcome_and_exception_link(): void
    {
        $import = Import::create([
            'name' => 'EIR contract_transactions: Extract B.xlsx',
            'status' => 'completed',
            'records' => 25,
            'rows_processed' => 40,
            'failed_records' => 2,
            'failed_file_path' => 'failed_imports/eir_exception_99.csv',
            'started_at' => now()->subSeconds(3),
            'completed_at' => now(),
        ]);

        AuditLog::create([
            'action' => 'EIR Intake Import',
            'entity_type' => 'Import',
            'entity_id' => $import->id,
            'meta' => [
                'import_type' => 'contract_transactions',
                'file' => 'Extract B.xlsx',
                'result' => [
                    'scheduled_rows_routed' => 10,
                    'loaded_rows' => 8,
                    'loaded_contracts' => 1,
                    'actual_rows_loaded' => 17,
                    'held' => ['C-2' => 'contract not on the loan tape'],
                    'skipped' => ['C-3' => 'principal does not reconcile'],
                ],
            ],
        ]);

        $response = app(EirIntakeController::class)->status($import->fresh());
        $payload = $response->getData(true);

        $this->assertTrue($payload['terminal']);
        $this->assertSame('completed', $payload['import']['status']);
        $this->assertSame(40, $payload['import']['rows_processed']);
        $this->assertSame('contract_transactions', $payload['import_type']);
        $this->assertSame(10, $payload['result']['scheduled_rows_routed']);
        $this->assertSame(8, $payload['result']['loaded_rows']);
        $this->assertStringContainsString("/imports/download-failed/{$import->id}", $payload['exception_url']);
    }

    public function test_pending_extract_b_status_tells_the_page_to_continue_polling(): void
    {
        $import = Import::create([
            'name' => 'EIR contract_transactions: Extract B.xlsx',
            'status' => 'processing',
        ]);

        $payload = app(EirIntakeController::class)
            ->status($import->fresh())
            ->getData(true);

        $this->assertFalse($payload['terminal']);
        $this->assertNull($payload['result']);
        $this->assertNull($payload['exception_url']);
    }
}
