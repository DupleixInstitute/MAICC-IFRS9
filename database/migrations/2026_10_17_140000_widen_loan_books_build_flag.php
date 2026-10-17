<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * build_flag was a 255-character varchar, sized for one short named difference. A row can now
 * carry several flags at once — a point-in-time conflict, a cross-month jump, and a governed
 * staging basis override, each with its own reason — concatenated with "; ", which a long,
 * audited reason (section 4.3 of the Finance Overlay Importers Specification v1) can push past
 * 255 characters. Widened to text rather than truncated, so no audit reason is ever cut short.
 *
 * MySQL only: Doctrine DBAL's generic ->change() rebuilds the whole table on SQLite, and does
 * not quote loan_books' pre-existing 12m_pd column (a digit-leading identifier from the 27 June
 * 2025 LGD migration), so the rebuilt CREATE TABLE fails to parse ("unrecognized token: 12m_pd").
 * SQLite does not enforce column-length limits at all (the original MySQL "Data too long"
 * error this fixes cannot happen there), so the test database needs no change here.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'mysql' && Schema::hasColumn('loan_books', 'build_flag')) {
            DB::statement('ALTER TABLE loan_books MODIFY build_flag TEXT NULL');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql' && Schema::hasColumn('loan_books', 'build_flag')) {
            DB::statement('ALTER TABLE loan_books MODIFY build_flag VARCHAR(255) NULL');
        }
    }
};
