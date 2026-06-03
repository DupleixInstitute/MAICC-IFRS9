<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $table = 'loss_given_default';

    private function indexExists(string $index): bool
    {
        return (int) DB::selectOne(
            'SELECT COUNT(1) AS c FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
            [$this->table, $index]
        )->c > 0;
    }

    /**
     * Run the migrations.
     *
     * Idempotent: a prior run added the 7 columns and the 2 single-column
     * indexes before failing on composite indexes that referenced a
     * non-existent `portfolio_group` column. The real portfolio FK on this
     * table is `lgd_calculation_id`, so the composite indexes use that.
     */
    public function up(): void
    {
        Schema::table($this->table, function (Blueprint $table) {
            if (! Schema::hasColumn($this->table, 'is_discounting')) {
                $table->boolean('is_discounting')->default(false)->comment('Whether discounting calculation is enabled');
            }
            if (! Schema::hasColumn($this->table, 'discount_rate_source')) {
                $table->enum('discount_rate_source', ['manual', 'loan_book'])->nullable()->comment('Source of interest rate for discounting');
            }
            if (! Schema::hasColumn($this->table, 'interest_rate')) {
                $table->decimal('interest_rate', 8, 4)->nullable()->comment('Manual interest rate for discounting (when source is manual)');
            }
            if (! Schema::hasColumn($this->table, 'discounted_payment_partly')) {
                $table->decimal('discounted_payment_partly', 18, 2)->default(0.00)->comment('Discounted value of partially recovered payments');
            }
            if (! Schema::hasColumn($this->table, 'discounted_payment_full')) {
                $table->decimal('discounted_payment_full', 18, 2)->default(0.00)->comment('Discounted value of fully recovered payments');
            }
            if (! Schema::hasColumn($this->table, 'discount_loss')) {
                $table->decimal('discount_loss', 18, 2)->default(0.00)->comment('Loss due to discounting (total_payments - total_discounted)');
            }
            if (! Schema::hasColumn($this->table, 'total_payment')) {
                $table->decimal('total_payment', 18, 2)->default(0.00)->comment('Total payments before discounting');
            }
        });

        $indexes = [
            'loss_given_default_is_discounting_index'      => '(`is_discounting`)',
            'loss_given_default_discount_rate_source_index' => '(`discount_rate_source`)',
            'idx_lgd_portfolio_period'                      => '(`lgd_calculation_id`, `reporting_period`)',
            'idx_lgd_discounting_period'                    => '(`is_discounting`, `reporting_period`)',
            'idx_lgd_portfolio_discounting'                 => '(`lgd_calculation_id`, `is_discounting`, `reporting_period`)',
        ];

        foreach ($indexes as $name => $columns) {
            if (! $this->indexExists($name)) {
                DB::statement("ALTER TABLE `{$this->table}` ADD INDEX `{$name}` {$columns}");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ([
            'loss_given_default_is_discounting_index',
            'loss_given_default_discount_rate_source_index',
            'idx_lgd_portfolio_period',
            'idx_lgd_discounting_period',
            'idx_lgd_portfolio_discounting',
        ] as $name) {
            if ($this->indexExists($name)) {
                DB::statement("ALTER TABLE `{$this->table}` DROP INDEX `{$name}`");
            }
        }

        Schema::table($this->table, function (Blueprint $table) {
            $columns = array_values(array_filter([
                'is_discounting',
                'discount_rate_source',
                'interest_rate',
                'discounted_payment_partly',
                'discounted_payment_full',
                'discount_loss',
                'total_payment',
            ], fn ($c) => Schema::hasColumn($this->table, $c)));

            if (! empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
