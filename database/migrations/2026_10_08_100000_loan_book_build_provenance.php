<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Building the loan book from the landing zone (spec v4 sections 6.2, 6.6)
|--------------------------------------------------------------------------
| Every loan_books row built from the landing zone says which method built
| it (A bootstrap of the stored run, B derived from the ledger, C the printed
| report), which load and source row it came from, and, under method B, the
| stored report's carrying amount beside the derived one with a flag when
| they differ. A build is requested by one person and approved by another
| (loan_book_builds); a locked reporting period is never restated
| (reporting_period_locks).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_books', function (Blueprint $table) {
            $table->char('build_method', 1)->nullable()->after('is_month_end')->comment('A bootstrap | B derived | C report importer');
            $table->unsignedBigInteger('build_load_id')->nullable()->after('build_method')->comment('ebanker_loads.id the row was built from');
            $table->string('build_source_key', 60)->nullable()->after('build_load_id')->comment('The stored run row (LOAN_BOOK_DET_ID_A) or the report row');
            $table->decimal('interest_to_date', 65, 4)->nullable()->after('carrying_amount');
            $table->decimal('stored_carrying_amount', 65, 4)->nullable()->after('interest_to_date')->comment('E-Banker\'s stored report figure, kept beside a derived carrying amount');
            $table->string('build_flag', 255)->nullable()->after('build_source_key')->comment('Named difference or basis; null = clean');
            $table->json('build_basis')->nullable()->after('build_flag')->comment('The inputs the row was built from');
            $table->date('overdue_principal_date')->nullable()->after('overdue_days');
            $table->string('arrears_271_to_360', 199)->nullable()->after('arrears_180_to_270');
        });

        Schema::create('loan_book_builds', function (Blueprint $table) {
            $table->id();
            $table->char('method', 1);
            $table->string('period_from', 7);
            $table->string('period_to', 7);
            $table->string('status', 20)->default('PROPOSED')->comment('PROPOSED | APPROVED | BUILT | REJECTED | FAILED');
            $table->foreignId('requested_by')->nullable()->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->string('approver_label')->nullable()->comment('Set when the bootstrap, not a person, approved');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('built_at')->nullable();
            $table->json('pack_loads')->nullable()->comment('The ebanker_loads ids and pack hashes read');
            $table->json('result')->nullable()->comment('Per period: rows, flagged, differences from the previous build');
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('reporting_period_locks', function (Blueprint $table) {
            $table->id();
            $table->string('reporting_period', 7)->unique();
            $table->foreignId('locked_by')->nullable()->constrained('users');
            $table->timestamp('locked_at');
            $table->string('reason');
            $table->json('settings_snapshot')->nullable()->comment('The governance values in force when the period was locked');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reporting_period_locks');
        Schema::dropIfExists('loan_book_builds');
        Schema::table('loan_books', function (Blueprint $table) {
            $table->dropColumn(['build_method', 'build_load_id', 'build_source_key', 'interest_to_date', 'stored_carrying_amount', 'build_flag', 'build_basis', 'overdue_principal_date', 'arrears_271_to_360']);
        });
    }
};
