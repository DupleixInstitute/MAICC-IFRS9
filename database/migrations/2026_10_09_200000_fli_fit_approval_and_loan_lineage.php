<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| A fit approved under maker-checker; the lineage on the loan (spec v4 s.14.6, 14.8)
|--------------------------------------------------------------------------
| The chain's applied fits are proposals. A reviewer proposes one for the
| FLI route and a second person approves it; the route then runs the
| approved fit once per scenario of the approved set and the loan records
| the route, the method, the fit and the set it was adjusted under, so two
| loans adjusted differently in different periods can both be explained.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fli_fits', function (Blueprint $t) {
            $t->string('approval_status', 12)->default('NONE')->after('verdict')->comment('NONE | PROPOSED | APPROVED | REJECTED');
            $t->unsignedBigInteger('proposed_by')->nullable()->after('approval_status');
            $t->timestamp('proposed_at')->nullable()->after('proposed_by');
            $t->unsignedBigInteger('approved_by')->nullable()->after('proposed_at');
            $t->string('approver_label')->nullable()->after('approved_by');
            $t->timestamp('approved_at')->nullable()->after('approver_label');
            $t->string('approval_note')->nullable()->after('approved_at');
        });
        Schema::table('loan_books', function (Blueprint $t) {
            if (! Schema::hasColumn('loan_books', 'fli_route')) {
                $t->string('fli_route', 40)->nullable()->after('pd_post_fli');
                $t->string('fli_method', 60)->nullable()->after('fli_route');
                $t->unsignedBigInteger('fli_fit_id')->nullable()->after('fli_method');
                $t->unsignedBigInteger('fli_set_id')->nullable()->after('fli_fit_id');
                $t->json('fli_by_scenario')->nullable()->after('fli_set_id')->comment('The PD under each scenario and its weight');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fli_fits', function (Blueprint $t) {
            $t->dropColumn(['approval_status', 'proposed_by', 'proposed_at', 'approved_by', 'approver_label', 'approved_at', 'approval_note']);
        });
        Schema::table('loan_books', function (Blueprint $t) {
            $t->dropColumn(['fli_route', 'fli_method', 'fli_fit_id', 'fli_set_id', 'fli_by_scenario']);
        });
    }
};
