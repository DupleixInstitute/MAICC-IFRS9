<?php

namespace Tests\Feature\Concerns;

use App\Support\Approvals;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A supporting document on an item of an approval workflow, for a test of a
 * workflow whose approval needs evidence (Staging Module Specification v1,
 * section 8.6; App\Support\Approvals::assertEvidence).
 */
trait AttachesApprovalEvidence
{
    protected function attachApprovalEvidence(string $workflow, int $id, string $name = 'Evidence.pdf'): void
    {
        if (! Schema::hasTable('supporting_documents')) {
            Schema::create('supporting_documents', function (Blueprint $t) {
                $t->increments('id'); $t->string('documentable_type'); $t->unsignedBigInteger('documentable_id'); $t->string('disk')->default('local'); $t->string('path');
                $t->string('original_name'); $t->string('mime_type')->nullable(); $t->unsignedBigInteger('size')->default(0); $t->string('extension')->nullable();
                $t->string('document_type')->nullable(); $t->string('description')->nullable(); $t->string('hash')->nullable(); $t->unsignedBigInteger('uploaded_by')->nullable(); $t->timestamps(); $t->softDeletes();
            });
        }
        $table = Approvals::workflow($workflow)['table'];
        DB::table('supporting_documents')->insert(['documentable_type' => $table, 'documentable_id' => $id, 'disk' => 'local', 'path' => "approval-documents/{$table}/{$id}/x.pdf",
            'original_name' => $name, 'document_type' => 'other', 'hash' => str_repeat('b', 64), 'created_at' => now(), 'updated_at' => now()]);
    }
}
