<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
        public function up(): void
        {
            // Migration for generic supporting_documents
            Schema::create('supporting_documents', function (Blueprint $table) {
                $table->id();
                
                $table->string('documentable_type');
                $table->unsignedBigInteger('documentable_id');
                $table->string('disk')->default('public');
                $table->string('path');
                $table->string('original_name');
                $table->string('mime_type')->nullable();
                $table->unsignedBigInteger('size')->comment('Size in bytes');
                $table->string('extension')->nullable();
                $table->string('document_type')->nullable();
                $table->string('description')->nullable();
                $table->string('hash')->nullable();
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
                
                // Indexes
                $table->index(['documentable_type', 'documentable_id']);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supporting_documents');
    }
};
