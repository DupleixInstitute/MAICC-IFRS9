<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One document store for every decision (Staging Module Specification v1,
 * section 8.7): a minute attached once to one decision is linked to the other
 * decisions it covers, instead of being uploaded again. A link names the
 * document, the item it supports (the same documentable type and id as an
 * attached document), who linked it and why. The document itself stays where
 * it was attached; a link never copies or moves the file.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('supporting_document_links')) {
            return;
        }
        Schema::create('supporting_document_links', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('supporting_document_id')->index();
            $t->string('documentable_type');
            $t->unsignedBigInteger('documentable_id');
            $t->unsignedBigInteger('linked_by')->nullable();
            $t->text('reason');
            $t->timestamps();
            $t->unique(['supporting_document_id', 'documentable_type', 'documentable_id'], 'supporting_document_links_unique');
            $t->index(['documentable_type', 'documentable_id'], 'supporting_document_links_item');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supporting_document_links');
    }
};
