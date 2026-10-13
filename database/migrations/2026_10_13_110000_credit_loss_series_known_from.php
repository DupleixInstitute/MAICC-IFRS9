<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| When a credit-loss observation became knowable (FLI module D-F7, D-F10)
|--------------------------------------------------------------------------
| The proxies derived from MAIIC's own staged loan books are knowable at the
| end of their month (known_from stays empty). An external series entered on
| Credit Loss Data, such as the Reserve Bank's industry NPL ratio, is
| published weeks or months later: known_from carries its release month, and
| the as-at reader leaves the row out of any month before it.
*/
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('credit_loss_series') && ! Schema::hasColumn('credit_loss_series', 'known_from')) {
            Schema::table('credit_loss_series', function (Blueprint $t) {
                $t->char('known_from', 6)->nullable()->comment('YYYYMM the figure was published; empty = the end of its own month');
                $t->string('source', 64)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('credit_loss_series', 'known_from')) {
            Schema::table('credit_loss_series', fn (Blueprint $t) => $t->dropColumn(['known_from', 'source']));
        }
    }
};
