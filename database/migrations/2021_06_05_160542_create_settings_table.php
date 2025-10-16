<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('setting_key')->unique();
            $table->text('setting_value')->nullable();
            $table->integer('order')->nullable();
            $table->string('category')->nullable();
            $table->string('type')->default('text');
            $table->text('options')->nullable();
            $table->text('rules')->nullable();
            $table->text('class')->nullable();
            $table->tinyInteger('required')->default(0);
            $table->string('db_columns')->nullable();
            $table->string('info')->nullable();
            $table->tinyInteger('displayed')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('settings');
    }
}
