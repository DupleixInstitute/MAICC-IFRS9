<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| The compliance audit register (spec v4 section 12.5, CA-3)
|--------------------------------------------------------------------------
| One row per workbook and one per section, loaded from the same data
| modules the workbooks are built from (docs/compliance/<stem>.json). A
| reviewer sets a row's status and signs it; a second person approves; the
| audit log records both; the workbook is regenerated from the signed state.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compliance_audits', function (Blueprint $t) {
            $t->id(); $t->string('key', 40)->unique(); $t->string('file_stem'); $t->string('short'); $t->string('title', 500); $t->text('basis')->nullable(); $t->text('source')->nullable(); $t->string('reviewer')->nullable(); $t->timestamp('loaded_at')->nullable(); $t->timestamps();
        });
        Schema::create('compliance_audit_rows', function (Blueprint $t) {
            $t->id(); $t->foreignId('audit_id')->constrained('compliance_audits')->cascadeOnDelete(); $t->string('part'); $t->string('reference', 60); $t->string('section_name'); $t->text('requirement');
            $t->string('status', 40); $t->text('engine_comment')->nullable(); $t->text('general_comment')->nullable(); $t->text('compliance_comment')->nullable(); $t->text('where_to_see')->nullable();
            $t->string('governance_setting')->nullable(); $t->string('test', 500)->nullable(); $t->unsignedSmallInteger('ordering')->default(0);
            $t->string('proposed_status', 40)->nullable(); $t->foreignId('signed_by')->nullable()->constrained('users'); $t->timestamp('signed_at')->nullable(); $t->string('sign_note')->nullable();
            $t->foreignId('approved_by')->nullable()->constrained('users'); $t->timestamp('approved_at')->nullable(); $t->timestamps(); $t->unique(['audit_id', 'reference']);
        });
        Schema::create('compliance_findings', function (Blueprint $t) {
            $t->id(); $t->foreignId('audit_id')->constrained('compliance_audits')->cascadeOnDelete(); $t->string('number', 10); $t->string('reference', 120); $t->string('finding', 300); $t->text('what_was_found'); $t->text('impact')->nullable(); $t->text('recommended_action')->nullable(); $t->string('owner')->nullable(); $t->string('status', 20)->default('Open'); $t->timestamps();
            $t->unique(['audit_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_findings'); Schema::dropIfExists('compliance_audit_rows'); Schema::dropIfExists('compliance_audits');
    }
};
