<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DB-driven user manual (Ticket #010): chapters, articles, numbered steps,
 * figures and route mappings. One source of truth feeds the in-app reader,
 * the per-page help lookups and the PDF export.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('help_categories', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });

        Schema::create('help_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('help_category_id')->constrained('help_categories')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('body')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->string('status', 20)->default('published');
            $table->string('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('help_article_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('help_article_id')->constrained('help_articles')->cascadeOnDelete();
            $table->unsignedInteger('step_no')->default(1);
            $table->text('text');
            $table->timestamps();
        });

        Schema::create('help_article_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('help_article_id')->constrained('help_articles')->cascadeOnDelete();
            $table->string('path');
            $table->string('caption')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });

        Schema::create('help_article_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('help_article_id')->constrained('help_articles')->cascadeOnDelete();
            $table->string('route_name')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_article_routes');
        Schema::dropIfExists('help_article_images');
        Schema::dropIfExists('help_article_steps');
        Schema::dropIfExists('help_articles');
        Schema::dropIfExists('help_categories');
    }
};
