<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('ecl_recovery_cashflows', function(Blueprint $table){
            $table->id(); $table->string('contract_id')->index(); $table->string('reporting_period',7);
            $table->date('recovery_date'); $table->decimal('expected_recovery',20,2);
            $table->string('recovery_type',30)->default('COLLECTION');
            $table->string('source',40)->default('REVIEWED_ESTIMATE'); $table->string('status',20)->default('APPROVED');
            $table->text('rationale')->nullable(); $table->timestamps();
            $table->unique(['contract_id','reporting_period','recovery_date','recovery_type'],'uq_recovery_contract_period_date_type');
        });
    }
    public function down(): void { Schema::dropIfExists('ecl_recovery_cashflows'); }
};
