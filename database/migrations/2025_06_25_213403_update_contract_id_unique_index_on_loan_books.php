<?php
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateContractIdUniqueIndexOnLoanBooks extends Migration
{
    public function up()
    {
        Schema::table('loan_books', function (Blueprint $table) {
            // Drop the old unique index
            $table->dropUnique(['contract_id']);

            // Add a composite unique index
            $table->unique(['contract_id', 'reporting_period']);
        });
    }

    public function down()
    {
        Schema::table('loan_books', function (Blueprint $table) {
            // Reverse: drop the composite and restore old unique
            $table->dropUnique(['contract_id', 'reporting_period']);
            $table->unique('contract_id');
        });
    }
}
