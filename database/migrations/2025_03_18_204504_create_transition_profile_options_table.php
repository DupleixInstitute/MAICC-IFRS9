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
        Schema::create('transition_profile_options', function (Blueprint $table) {
            $table->id();
            $table->string('category_name');
            $table->unsignedBigInteger('profile_id'); // Matches the primary key type
            $table->enum('is_start_or_end', ['start', 'end']);
            $table->integer('ordering_index')->nullable();
            $table->decimal('min_value', 10, 2)->nullable();
            $table->decimal('max_value', 10, 2)->nullable();
            $table->text('text_value')->nullable();
            $table->boolean('default_value')->default(0);
            $table->timestamps(); // Replaces `created_at` and `updated_at`

            $table->foreign('profile_id')
                ->references('id')
                ->on('transition_profile_definitions')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transition_profile_options');
    }
};
