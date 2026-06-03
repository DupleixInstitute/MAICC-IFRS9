<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * IFRS 9 numeric precision fix.
 *
 * PD / LGD / FLI factors are probabilities and ratios in roughly [0, 1].
 * They were stored as decimal(8,2), which silently rounds a PD of 0.0345 to
 * 0.03 and destroys ECL accuracy. The original
 * add_fli_columns_to_loan_book_table migration intended decimal(10,6) but
 * targeted the wrong table name ("loan_book" vs "loan_books") and never ran.
 * This migration widens the precision on the real table.
 *
 * Widening a decimal column never loses data. Raw ALTER statements are used
 * (instead of doctrine/dbal ->change()) and the session sql_mode is relaxed
 * for the operation only: loan_books contains a legacy "date NOT NULL"
 * column (old_ifrs9stage_post_qualitative) full of '0000-00-00' values that
 * would otherwise abort the table rebuild under NO_ZERO_DATE. No row data is
 * modified by this migration.
 */
return new class extends Migration
{
    private array $loanBookColumns = [
        'pd_value',
        'pd_prefli',
        'pd_post_fli',
        'fli_adj',
        'lgd_value',
        'customer_lgd',
        'collection_lgd',
    ];

    private array $predictionColumns = [
        'pred_value',
        'ci_lower',
        'ci_upper',
        'actual_value',
        'error',
    ];

    private function withRelaxedSqlMode(callable $callback): void
    {
        $original = DB::selectOne('SELECT @@SESSION.sql_mode AS mode')->mode;
        DB::statement("SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'");

        try {
            $callback();
        } finally {
            DB::statement('SET SESSION sql_mode = ?', [$original]);
        }
    }

    private function modify(string $table, array $columns, string $definition): void
    {
        foreach ($columns as $column) {
            if (Schema::hasColumn($table, $column)) {
                DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` {$definition}");
            }
        }
    }

    public function up(): void
    {
        $this->withRelaxedSqlMode(function () {
            if (Schema::hasTable('loan_books')) {
                $this->modify('loan_books', $this->loanBookColumns, 'DECIMAL(16,8) NULL');
            }

            if (Schema::hasTable('regression_predictions')) {
                $this->modify('regression_predictions', $this->predictionColumns, 'DECIMAL(30,8) NULL');

                if (! Schema::hasColumn('regression_predictions', 'macro_data_used')) {
                    DB::statement('ALTER TABLE `regression_predictions` ADD COLUMN `macro_data_used` JSON NULL AFTER `period`');
                }
            }
        });
    }

    public function down(): void
    {
        $this->withRelaxedSqlMode(function () {
            if (Schema::hasTable('loan_books')) {
                $this->modify('loan_books', $this->loanBookColumns, 'DECIMAL(8,2) NULL');
            }

            if (Schema::hasTable('regression_predictions')) {
                if (Schema::hasColumn('regression_predictions', 'macro_data_used')) {
                    DB::statement('ALTER TABLE `regression_predictions` DROP COLUMN `macro_data_used`');
                }

                $this->modify('regression_predictions', $this->predictionColumns, 'DECIMAL(65,2) NULL');
            }
        });
    }
};
