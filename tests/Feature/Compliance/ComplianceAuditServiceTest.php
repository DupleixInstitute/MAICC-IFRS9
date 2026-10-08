<?php

namespace Tests\Feature\Compliance;

use App\Services\Compliance\ComplianceAuditService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * The compliance register (spec v4 section 12.5, 12.7): loaded from the
 * builder's JSON; a status change needs a signer and a second approver,
 * both in the audit log with the old and new values; a row may be Done
 * only with a test named; a signed row keeps its state on reload; the
 * signed state is exported for the builder.
 */
class ComplianceAuditServiceTest extends TestCase
{
    protected $seed = false;
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('users', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->timestamps(); });
        Schema::create('audit_logs', function (Blueprint $t) { $t->increments('id'); $t->integer('user_id')->nullable(); $t->string('action'); $t->string('entity_type'); $t->integer('entity_id')->nullable(); $t->string('scope')->nullable(); $t->string('reporting_period')->nullable(); $t->integer('rows_affected')->nullable(); $t->text('old_values')->nullable(); $t->text('new_values')->nullable(); $t->text('meta')->nullable(); $t->string('ip_address')->nullable(); $t->string('user_agent')->nullable(); $t->timestamps(); });
        (require base_path('database/migrations/2026_10_09_300000_create_compliance_audit_register.php'))->up();
        DB::table('users')->insert([['id' => 1, 'name' => 'Signer', 'created_at' => now(), 'updated_at' => now()], ['id' => 2, 'name' => 'Approver', 'created_at' => now(), 'updated_at' => now()]]);
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'compliance_' . uniqid();
        mkdir($this->dir);
        file_put_contents($this->dir . '/Test_Audit.json', json_encode(['key' => 'test', 'meta' => ['file_stem' => 'Test_Audit', 'short' => 'Test', 'title' => 'A test standard', 'reviewer' => 'Someone'],
            'rows' => [
                ['part' => 'P', 'reference' => '1', 'section_name' => 'One', 'requirement' => 'r', 'status' => 'Outstanding', 'engine_comment' => 'e', 'general_comment' => '', 'compliance_comment' => 'c', 'where_to_see' => '/x', 'governance_setting' => '', 'test' => ''],
                ['part' => 'P', 'reference' => '2', 'section_name' => 'Two', 'requirement' => 'r', 'status' => 'Partially done', 'engine_comment' => 'e', 'general_comment' => '', 'compliance_comment' => 'c', 'where_to_see' => '/y', 'governance_setting' => 'k', 'test' => 'Tests\\Feature\\X'],
            ], 'findings' => [['number' => 'F1', 'reference' => '1', 'finding' => 'f', 'what_was_found' => 'w', 'impact' => 'i', 'recommended_action' => 'a', 'owner' => 'o', 'status' => 'Open']]]));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $f) { @unlink($f); }
        @rmdir($this->dir);
        parent::tearDown();
    }

    public function test_sign_and_approve_are_two_people_and_done_needs_a_test(): void
    {
        $s = new ComplianceAuditService();
        $this->assertSame(['test' => 2], $s->load($this->dir));
        $rowOne = DB::table('compliance_audit_rows')->where('reference', '1')->value('id');
        $rowTwo = DB::table('compliance_audit_rows')->where('reference', '2')->value('id');
        try {
            $s->sign($rowOne, 'Done', 1);
            $this->fail('Done without a test');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('test that proves it', $e->getMessage());
        }
        $s->sign($rowTwo, 'Done', 1, 'proven by the build');
        $this->assertSame('Partially done', DB::table('compliance_audit_rows')->where('id', $rowTwo)->value('status')); // not yet approved
        try {
            $s->approve($rowTwo, 1);
            $this->fail('the signer approved');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('different person', $e->getMessage());
        }
        $s->approve($rowTwo, 2);
        $row = DB::table('compliance_audit_rows')->where('id', $rowTwo)->first();
        $this->assertSame('Done', $row->status);
        $this->assertSame(2, (int) $row->approved_by);
        $logs = DB::table('audit_logs')->whereIn('action', ['Compliance Row Signed', 'Compliance Row Approved'])->orderBy('id')->get();
        $this->assertCount(2, $logs);
        $this->assertSame('Partially done', json_decode($logs[1]->old_values, true)['status']);
        $this->assertSame('Done', json_decode($logs[1]->new_values, true)['status']);
        // a reload from the module keeps the signed status
        $s->load($this->dir);
        $this->assertSame('Done', DB::table('compliance_audit_rows')->where('id', $rowTwo)->value('status'));
        $this->assertSame('Outstanding', DB::table('compliance_audit_rows')->where('id', $rowOne)->value('status'));
        $exported = $s->exportSigned($this->dir);
        $this->assertSame(['test' => 1], $exported);
        $this->assertSame('Done', json_decode(file_get_contents($this->dir . '/Test_Audit.signed.json'), true)['rows'][0]['status']);
    }
}
