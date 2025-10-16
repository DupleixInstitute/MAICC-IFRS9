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
        Schema::create('loan_application_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_application_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('comment_section_id');
            $table->text('content');
            $table->json('metadata')->nullable(); // For storing additional comment metadata
            $table->enum('status', ['active', 'edited', 'deleted'])->default('active');
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();

            // Foreign key for comment section
            $table->foreign('comment_section_id')->references('id')->on('scoring_attribute_groups');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_application_comments');
    }
};
