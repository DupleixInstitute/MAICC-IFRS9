<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The client's own loan books, landed as received, and every difference between each one and the
 * book the system rebuilt from the E-Banker queries and the ledger for the same month-end (Client
 * Book Reconciliation Specification v1, 17 October 2026).
 *
 *  - client_loan_books: one file the client gave for one month-end (MAIIC's monthly E-Banker loan
 *    book report, the audited December 2025 ECL model, or the reconciliation template), its hash,
 *    who landed it, and the summary of its last comparison.
 *  - client_loan_book_rows: the file's loan rows as read, never edited.
 *  - client_book_differences: each loan where the two books differ, with what each says, the
 *    cause, and what was decided: an override proposed (its id), or the system's figure kept with
 *    a reason. Nothing here changes the loan book; only an approved override does, at the rebuild.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('client_loan_books')) {
            Schema::create('client_loan_books', function (Blueprint $t) {
                $t->id();
                $t->string('reporting_period', 7)->index();
                $t->string('kind', 30); // ebanker_report, audited_model, template
                $t->string('source_label', 200);
                $t->string('file_name', 255);
                $t->string('file_hash', 64);
                $t->unsignedInteger('row_count')->default(0);
                $t->decimal('printed_total', 20, 2)->nullable();
                $t->decimal('rows_total', 20, 2)->nullable();
                $t->string('status', 20)->default('LANDED'); // LANDED, COMPARED, WITHDRAWN
                $t->unsignedBigInteger('landed_by')->nullable();
                $t->string('landed_label', 120)->nullable();
                $t->timestamp('landed_at')->nullable();
                $t->timestamp('compared_at')->nullable();
                $t->json('summary')->nullable();
                $t->string('csv_path', 500)->nullable();
                $t->timestamps();
                $t->unique(['reporting_period', 'file_hash']);
            });
        }
        if (! Schema::hasTable('client_loan_book_rows')) {
            Schema::create('client_loan_book_rows', function (Blueprint $t) {
                $t->id();
                $t->foreignId('client_book_id')->index();
                $t->unsignedInteger('row_no');
                $t->string('contract_id', 30)->nullable()->index();
                $t->string('customer_name', 200)->nullable();
                $t->string('product_code', 20)->nullable();
                $t->decimal('carrying_amount', 20, 2)->nullable();
                $t->decimal('principal_overdue', 20, 2)->nullable();
                $t->decimal('interest_overdue', 20, 2)->nullable();
                $t->string('arrears_category', 60)->nullable();
                $t->json('buckets')->nullable();
                $t->integer('days_past_due')->nullable();
                $t->string('days_basis', 200)->nullable();
                $t->unsignedTinyInteger('stage')->nullable();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('client_book_differences')) {
            Schema::create('client_book_differences', function (Blueprint $t) {
                $t->id();
                $t->foreignId('client_book_id')->index();
                $t->string('reporting_period', 7)->index();
                $t->unsignedBigInteger('client_row_id')->nullable();
                $t->string('contract_id', 30)->nullable()->index();
                $t->string('customer_name', 200)->nullable();
                $t->string('matched_on', 40)->nullable();
                $t->decimal('client_amount', 20, 2)->nullable();
                $t->decimal('system_amount', 20, 2)->nullable();
                $t->integer('client_days')->nullable();
                $t->string('client_bracket', 20)->nullable();
                $t->integer('system_days')->nullable();
                $t->string('system_bracket', 20)->nullable();
                $t->unsignedTinyInteger('client_stage')->nullable();
                $t->unsignedTinyInteger('system_stage')->nullable();
                $t->boolean('bucket_differs')->default(false);
                $t->boolean('balance_differs')->default(false);
                $t->boolean('stage_differs')->default(false);
                $t->string('cause', 200);
                $t->string('status', 20)->default('OPEN'); // OPEN, OVERRIDE_PROPOSED, RECONCILED, KEPT, INFO
                $t->unsignedBigInteger('staging_override_id')->nullable();
                $t->unsignedBigInteger('gross_override_id')->nullable();
                $t->unsignedBigInteger('decided_by')->nullable();
                $t->string('decision_reason', 2000)->nullable();
                $t->timestamp('decided_at')->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('client_book_differences');
        Schema::dropIfExists('client_loan_book_rows');
        Schema::dropIfExists('client_loan_books');
    }
};
