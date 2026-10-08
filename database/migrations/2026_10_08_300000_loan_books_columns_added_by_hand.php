<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Columns the production database carries that no migration created
|--------------------------------------------------------------------------
| Found by the bootstrap of spec v4 section 6.10, whose clean install is the
| test that the migrations reproduce the schema: ead is read by the ECL
| service and the IFRS 9 reports, off_balance_sheet_exposure by the EAD
| arithmetic, customer_name_imported by the loan-book screens. Guarded so
| that a database which already has them (the production copy) is left as
| it is.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_books', function (Blueprint $table) {
            if (! Schema::hasColumn('loan_books', 'customer_name_imported')) {
                $table->string('customer_name_imported', 199)->nullable()->after('customer_name');
            }
            if (! Schema::hasColumn('loan_books', 'off_balance_sheet_exposure')) {
                $table->decimal('off_balance_sheet_exposure', 18, 2)->nullable()->after('facility_utilisation_rate');
            }
            if (! Schema::hasColumn('loan_books', 'ead')) {
                $table->decimal('ead', 18, 2)->nullable()->after('off_balance_sheet_exposure');
            }
        });
    }

    public function down(): void
    {
        // kept: the production database had them before this migration existed
    }
};
