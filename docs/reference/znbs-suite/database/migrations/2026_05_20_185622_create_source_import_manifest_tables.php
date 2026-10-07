<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 1 — Layer 1 of the blueprint's 5-layer model: raw source import
 * manifests.
 *
 * Captures the full file → sheet → row lineage for every upload event so
 * the platform can answer "what data was used to build this snapshot?"
 * down to the row level. Existing FinancialImportLog rows remain as the
 * statement-level summary; the new tables sit underneath them.
 *
 * Three tables:
 *
 *   - source_import_batches: one row per upload event.
 *   - source_import_files: files within a batch (e.g. each sheet of a
 *     multi-sheet workbook gets its own file row).
 *   - source_import_rows: individual parsed rows with raw_data (JSON of
 *     what the parser saw) + normalized_data (JSON of what got written
 *     downstream) + parse_status + optional target_table/target_id back-
 *     pointer so audit can trace every row to its final destination.
 *
 * Existing importers can adopt these incrementally — Step 1 wires the
 * historical CSV import as the proof-of-concept; FinancialsController +
 * SoceController hookups land in Step 2 alongside the canonical entity
 * tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── source_import_batches ───────────────────────────────────────────
        Schema::create('source_import_batches', function (Blueprint $table): void {
            $table->id();
            $table->string('batch_ref', 64)->unique();
            // What kind of source pack this is. Drives which canonical
            // table the parsed rows eventually feed into.
            $table->string('source_kind', 40);
            $table->string('primary_filename', 255)->nullable();
            // Lifecycle: pending → parsing → completed / failed / partial
            $table->string('status', 20)->default('pending');

            // Context (carried for convenience; sourced from caller)
            $table->string('statement_type', 40)->nullable();
            $table->string('reporting_period', 40)->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->unsignedTinyInteger('month')->nullable();
            $table->unsignedTinyInteger('quarter')->nullable();
            $table->string('currency', 8)->nullable();

            // Denormalised counts (kept in sync by SourceImportService)
            $table->unsignedInteger('file_count')->default(0);
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('accepted_row_count')->default(0);
            $table->unsignedInteger('rejected_row_count')->default(0);

            $table->text('error_summary')->nullable();

            // Provenance back-links: a batch CAN tie back to the legacy
            // FinancialImportLog (statement-level summary) and forward to
            // the FinancialDataSet that consumes the parsed rows. Both
            // nullable for batches that don't fit those flows.
            $table->unsignedBigInteger('linked_dataset_id')->nullable();
            $table->unsignedBigInteger('linked_import_log_id')->nullable();
            $table->unsignedBigInteger('imported_by_user_id')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->foreign('linked_dataset_id', 'sib_dataset_fk')
                ->references('id')->on('financial_data_sets')->nullOnDelete();
            $table->foreign('linked_import_log_id', 'sib_log_fk')
                ->references('id')->on('financial_import_logs')->nullOnDelete();
            $table->foreign('imported_by_user_id', 'sib_user_fk')
                ->references('id')->on('users')->nullOnDelete();

            $table->index(['source_kind', 'status'], 'sib_kind_status_idx');
            $table->index(['reporting_period', 'statement_type'], 'sib_period_type_idx');
            $table->index('linked_dataset_id', 'sib_dataset_idx');
        });

        // ── source_import_files ─────────────────────────────────────────────
        Schema::create('source_import_files', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('source_import_batch_id');
            $table->string('filename', 255);
            $table->string('sheet_name', 80)->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->string('content_sha256', 64)->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('parsed_row_count')->default(0);
            $table->unsignedInteger('rejected_row_count')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('source_import_batch_id', 'sif_batch_fk')
                ->references('id')->on('source_import_batches')
                ->cascadeOnDelete();

            $table->index(['source_import_batch_id', 'status'], 'sif_batch_status_idx');
            $table->index('content_sha256', 'sif_hash_idx');
        });

        // ── source_import_rows ──────────────────────────────────────────────
        Schema::create('source_import_rows', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('source_import_batch_id');
            $table->unsignedBigInteger('source_import_file_id');
            $table->unsignedInteger('row_number');
            // Raw row as parsed by the importer, before any mapping /
            // sign-flip / aggregation. This is what makes the manifest
            // useful for forensic audit later.
            $table->json('raw_data');
            // Normalised row after mapping & validation, written when the
            // row actually flows downstream. Null for rejected rows.
            $table->json('normalized_data')->nullable();
            $table->string('parse_status', 20);
            $table->text('parse_message')->nullable();
            // Optional pointer to the row this manifest entry produced
            // downstream. Lets audit traverse manifest → live row.
            $table->string('target_table', 80)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->timestamps();

            $table->foreign('source_import_batch_id', 'sir_batch_fk')
                ->references('id')->on('source_import_batches')
                ->cascadeOnDelete();
            $table->foreign('source_import_file_id', 'sir_file_fk')
                ->references('id')->on('source_import_files')
                ->cascadeOnDelete();

            $table->index(['source_import_batch_id', 'parse_status'], 'sir_batch_status_idx');
            $table->index(['source_import_file_id', 'row_number'], 'sir_file_row_idx');
            $table->index(['target_table', 'target_id'], 'sir_target_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_import_rows');
        Schema::dropIfExists('source_import_files');
        Schema::dropIfExists('source_import_batches');
    }
};
