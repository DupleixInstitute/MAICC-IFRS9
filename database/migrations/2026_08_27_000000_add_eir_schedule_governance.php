<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('contract_remaining_cashflow_schedule', function (Blueprint $table) {
            $table->id(); $table->string('contract_id')->index(); $table->date('due_date');
            $table->decimal('principal_due', 20, 2)->default(0); $table->decimal('interest_due', 20, 2)->default(0);
            $table->decimal('fee_due', 20, 2)->default(0); $table->string('source_system', 50);
            $table->string('source_reference')->nullable(); $table->string('external_transaction_id');
            $table->text('row_note')->nullable(); $table->timestamps();
            $table->unique(['source_system', 'external_transaction_id'], 'uq_remaining_source_transaction');
        });
        Schema::table('contract_eir', function (Blueprint $table) {
            $table->string('schedule_approval_status', 30)->default('NOT_GENERATED')->after('schedule_source');
            $table->string('schedule_comparison_status', 30)->nullable()->after('schedule_approval_status');
            $table->text('schedule_review_notes')->nullable()->after('schedule_comparison_status');
            $table->timestamp('schedule_generated_at')->nullable()->after('schedule_review_notes');
            $table->timestamp('schedule_approved_at')->nullable()->after('schedule_generated_at');
            $table->foreignId('schedule_approved_by')->nullable()->after('schedule_approved_at')->constrained('users');
        });
    }
    public function down(): void
    {
        Schema::table('contract_eir', function (Blueprint $table) {
            $table->dropForeign(['schedule_approved_by']);
            $table->dropColumn(['schedule_approval_status','schedule_comparison_status','schedule_review_notes','schedule_generated_at','schedule_approved_at','schedule_approved_by']);
        });
        Schema::dropIfExists('contract_remaining_cashflow_schedule');
    }
};
