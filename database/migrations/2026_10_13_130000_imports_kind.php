<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| What each import was (audit of 13 October 2026)
|--------------------------------------------------------------------------
| The import pages (clients, loan book, collateral) each showed every import
| of every kind, because an import did not record what it loaded. kind is set
| by each importer from now on; the imports already held are classified from
| their file names: the collateral register, the EIR intake files ("EIR ..."),
| the credit-loss files, and the loan books (every other file held today).
*/
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('imports', 'kind')) {
            Schema::table('imports', function (Blueprint $t) {
                $t->string('kind', 32)->nullable()->index()->comment('loan_book, clients, collateral, credit_loss, eir_intake');
            });
        }
        DB::table('imports')->whereNull('kind')->where('name', 'like', '%ollateral%')->update(['kind' => 'collateral']);
        DB::table('imports')->whereNull('kind')->where('name', 'like', 'EIR %')->update(['kind' => 'eir_intake']);
        DB::table('imports')->whereNull('kind')->where(fn ($q) => $q->where('name', 'like', '%redit loss%')->orWhere('name', 'like', '%credit_loss%'))->update(['kind' => 'credit_loss']);
        DB::table('imports')->whereNull('kind')->where(fn ($q) => $q->where('name', 'like', '%lient%')->orWhere('name', 'like', '%ustomer%'))->update(['kind' => 'clients']);
        DB::table('imports')->whereNull('kind')->update(['kind' => 'loan_book']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('imports', 'kind')) {
            Schema::table('imports', fn (Blueprint $t) => $t->dropColumn('kind'));
        }
    }
};
