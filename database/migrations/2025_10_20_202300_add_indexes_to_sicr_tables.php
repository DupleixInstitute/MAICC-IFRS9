<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_sicr_items', function (Blueprint $table) {
            $table->unique(['group_id','name']);
        });
        Schema::table('finance_sicr_triggers', function (Blueprint $table) {
            $table->index('account_number');
        });
    }

    public function down(): void
    {
        Schema::table('finance_sicr_items', function (Blueprint $table) {
            $table->dropUnique(['finance_sicr_items_group_id_name_unique']);
        });
        Schema::table('finance_sicr_triggers', function (Blueprint $table) {
            $table->dropIndex(['finance_sicr_triggers_account_number_index']);
        });
    }
};
