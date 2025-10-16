<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGeneralImportTemplatesTable extends Migration
{
    public function up()
    {
        Schema::create('general_import_templates', function (Blueprint $table) {
            $table->id();
            $table->string('template_name');
            $table->text('template_description')->nullable();
            $table->string('source_table_name');
            $table->integer('import_count')->default(0);
            $table->integer('column_count')->default(0);
            $table->tinyInteger('active_status')->default(1); // 1: active, 0: inactive, 2: deleted
            $table->timestamps();
            $table->foreignId('updated_by')->constrained('users');
        });
    }

    public function down()
    {
        Schema::dropIfExists('general_import_templates');
    }
}
