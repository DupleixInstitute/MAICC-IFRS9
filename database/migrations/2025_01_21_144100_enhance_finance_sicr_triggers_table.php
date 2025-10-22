<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_sicr_triggers', function (Blueprint $table) {
            $table->string('customer_id')->nullable()->after('id');
            $table->boolean('affect_all')->default(false)->after('customer_id');
            $table->date('effective_period')->nullable()->after('reason');
            $table->timestamp('last_update')->nullable()->after('updated_at');
            $table->timestamp('removal_date')->nullable()->after('last_update');
        });
    }

    public function down(): void
    {
        Schema::table('finance_sicr_triggers', function (Blueprint $table) {
            $table->dropColumn([
                'customer_id',
                'affect_all',
                'effective_period',
                'last_update',
                'removal_date'
            ]);
        });
    }
};