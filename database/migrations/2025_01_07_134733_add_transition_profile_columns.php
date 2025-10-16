<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTransitionProfileColumns extends Migration
{
    public function up()
    {
        Schema::table('scoring_attribute_groups', function (Blueprint $table) {
            $table->string('field_type')->default('number');
            $table->string('db_table_name')->nullable();
            $table->string('db_table_column_name')->nullable();
            $table->boolean('is_default')->default(false);
            // Drop the active column as it's being replaced by is_default
            $table->dropColumn('active');
        });
    }

    public function down()
    {
        Schema::table('scoring_attribute_groups', function (Blueprint $table) {
            $table->dropColumn(['field_type', 'db_table_name', 'db_table_column_name', 'is_default']);
            $table->boolean('active')->default(true);
        });
    }
}
