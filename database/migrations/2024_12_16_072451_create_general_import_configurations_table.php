<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGeneralImportConfigurationsTable extends Migration
{
    public function up()
    {
        Schema::create('general_import_configurations', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_reporting_period')->default(false);
            $table->boolean('is_portfolio_group_id')->default(false);
            $table->foreignId('template_id')->constrained('general_import_templates');
            $table->integer('template_column_position');
            $table->string('column_description');
            $table->string('column_data_type');
            $table->decimal('minimum_value', 15, 2)->nullable();
            $table->decimal('maximum_value', 15, 2)->nullable();
            $table->boolean('active_status')->default(true);
            $table->timestamps();
            $table->foreignId('updated_by')->constrained('users');

            // Using a shorter index name
            $table->index(['template_id', 'template_column_position'], 'template_column_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('general_import_configurations');
    }
}
