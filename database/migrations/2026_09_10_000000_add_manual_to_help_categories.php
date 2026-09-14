<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The help centre hosts more than one manual (Ticket #011): the User Manual
 * and the Administrator Manual share the chapter, article, step and figure
 * tables and are told apart by this column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('help_categories', function (Blueprint $table) {
            $table->string('manual', 20)->default('user')->after('id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('help_categories', function (Blueprint $table) {
            $table->dropIndex(['manual']);
            $table->dropColumn('manual');
        });
    }
};
