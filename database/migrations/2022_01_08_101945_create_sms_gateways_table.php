<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSmsGatewaysTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sms_gateways', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('created_by_id')->unsigned()->nullable();
            $table->string('name')->nullable();
            $table->string('system_name')->nullable();
            $table->text('to_name')->nullable();
            $table->text('url')->nullable();
            $table->text('msg_name')->nullable();
            $table->tinyInteger('active')->default(0);
            $table->tinyInteger('is_current')->default(0);
            $table->tinyInteger('is_system')->default(0);
            $table->tinyInteger('http_api')->default(1);
            $table->text('options')->nullable();
            $table->text('description')->nullable();
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
        Schema::dropIfExists('sms_gateways');
    }
}
