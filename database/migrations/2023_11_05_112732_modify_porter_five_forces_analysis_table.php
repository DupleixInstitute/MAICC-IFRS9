<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use \Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE porter_five_forces_analysis MODIFY COLUMN time_and_cost_of_entry varchar(190)");
        DB::statement("ALTER TABLE porter_five_forces_analysis MODIFY COLUMN specialist_knowledge varchar(190)");
        DB::statement("ALTER TABLE porter_five_forces_analysis MODIFY COLUMN economies_of_scale varchar(190)");
        DB::statement("ALTER TABLE porter_five_forces_analysis MODIFY COLUMN cost_advantages varchar(190)");
        DB::statement("ALTER TABLE porter_five_forces_analysis MODIFY COLUMN technology_protection varchar(190)");
        DB::statement("ALTER TABLE porter_five_forces_analysis MODIFY COLUMN barriers_to_entry varchar(190)");
        DB::statement("ALTER TABLE porter_five_forces_analysis MODIFY COLUMN threats_of_new_entry varchar(190)");
        DB::statement("ALTER TABLE porter_five_forces_analysis MODIFY COLUMN number_of_competitors varchar(190)");
        DB::statement("ALTER TABLE porter_five_forces_analysis MODIFY COLUMN quality_differences varchar(190)");
        DB::statement("ALTER TABLE porter_five_forces_analysis MODIFY COLUMN other_differences varchar(190)");
        DB::statement("ALTER TABLE porter_five_forces_analysis MODIFY COLUMN switching_costs varchar(190)");
        DB::statement("ALTER TABLE porter_five_forces_analysis MODIFY COLUMN customer_loyalty varchar(190)");
        DB::statement("ALTER TABLE porter_five_forces_analysis MODIFY COLUMN competitive_rivalry varchar(190)");
        DB::statement("ALTER TABLE porter_five_forces_analysis MODIFY COLUMN number_of_suppliers varchar(190)");
        DB::statement("ALTER TABLE porter_five_forces_analysis MODIFY COLUMN size_of_suppliers varchar(190)");
        DB::statement("ALTER TABLE porter_five_forces_analysis MODIFY COLUMN uniqueness_of_service varchar(190)");
        DB::statement("ALTER TABLE porter_five_forces_analysis MODIFY COLUMN costs_of_supplier_change varchar(190)");
        DB::statement("ALTER TABLE porter_five_forces_analysis MODIFY COLUMN supplier_switching_costs varchar(190)");
        DB::statement("ALTER TABLE porter_five_forces_analysis MODIFY COLUMN supplier_power varchar(190)");
        DB::statement("ALTER TABLE porter_five_forces_analysis MODIFY COLUMN substitute_performance varchar(190)");
        DB::statement("ALTER TABLE porter_five_forces_analysis MODIFY COLUMN costs_of_substitution varchar(190)");
        DB::statement("ALTER TABLE porter_five_forces_analysis MODIFY COLUMN threats_of_substitution varchar(190)");
        DB::statement("ALTER TABLE porter_five_forces_analysis MODIFY COLUMN number_of_customers varchar(190)");
        DB::statement("ALTER TABLE porter_five_forces_analysis MODIFY COLUMN single_order_size varchar(190)");
        DB::statement("ALTER TABLE porter_five_forces_analysis MODIFY COLUMN competitor_differences varchar(190)");
        DB::statement("ALTER TABLE porter_five_forces_analysis MODIFY COLUMN price_sensitivity varchar(190)");
        DB::statement("ALTER TABLE porter_five_forces_analysis MODIFY COLUMN ability_to_substitute varchar(190)");
        DB::statement("ALTER TABLE porter_five_forces_analysis MODIFY COLUMN customers_switching_costs varchar(190)");
        DB::statement("ALTER TABLE porter_five_forces_analysis MODIFY COLUMN buyer_power varchar(190)");
        DB::statement("ALTER TABLE porter_five_forces_analysis MODIFY COLUMN grand_total varchar(190)");


    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
