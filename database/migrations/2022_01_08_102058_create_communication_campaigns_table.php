<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCommunicationCampaignsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('communication_campaigns', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('created_by_id')->unsigned()->nullable();
            $table->bigInteger('sms_gateway_id')->unsigned()->nullable();
            $table->bigInteger('communication_campaign_business_rule_id')->unsigned()->nullable();
            $table->bigInteger('communication_campaign_attachment_type_id')->unsigned()->nullable();
            $table->bigInteger('branch_id')->unsigned()->nullable();
            $table->bigInteger('user_id')->unsigned()->nullable();
            $table->bigInteger('client_id')->unsigned()->nullable();
            $table->bigInteger('loan_product_id')->unsigned()->nullable();
            $table->bigInteger('province_id')->unsigned()->nullable();
            $table->bigInteger('district_id')->unsigned()->nullable();
            $table->bigInteger('ward_id')->unsigned()->nullable();
            $table->bigInteger('village_id')->unsigned()->nullable();
            $table->bigInteger('currency_id')->unsigned()->nullable();
            $table->text('subject')->nullable();
            $table->text('name')->nullable();
            $table->enum('campaign_type', ['sms', 'email'])->default('email');
            $table->enum('trigger_type', ['direct', 'schedule', 'triggered'])->default('direct');
            $table->date('scheduled_date')->nullable();
            $table->time('scheduled_time')->nullable();
            $table->tinyInteger('recurring')->default(0);
            $table->string('recur_frequency')->nullable();
            $table->date('recur_start_date')->nullable();
            $table->date('recur_end_date')->nullable();
            $table->date('recur_next_date')->nullable();
            $table->date('recur_last_run_date')->nullable();
            $table->enum('recur_type',
                array('days', 'weeks', 'months', 'years', 'selected_days'))->default('months');
            $table->json('selected_days')->nullable();
            $table->integer('from_x')->nullable();
            $table->integer('to_y')->nullable();
            $table->integer('cycle_x')->nullable();
            $table->integer('cycle_y')->nullable();
            $table->integer('overdue_x')->nullable();
            $table->integer('overdue_y')->nullable();
            $table->tinyInteger('active')->default(0);
            $table->enum('status', ['pending', 'active', 'inactive', 'closed','done'])->default('pending');
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
        Schema::dropIfExists('communication_campaigns');
    }
}
