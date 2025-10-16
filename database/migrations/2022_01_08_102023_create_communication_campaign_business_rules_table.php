<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCommunicationCampaignBusinessRulesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('communication_campaign_business_rules', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('communication_campaign_business_rule_id')->unsigned()->nullable();
            $table->text('name')->nullable();
            $table->text('translated_name')->nullable();
            $table->text('description')->nullable();
            $table->tinyInteger('active')->default(1);
            $table->tinyInteger('is_trigger')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('communication_campaign_business_rules');
    }
}
