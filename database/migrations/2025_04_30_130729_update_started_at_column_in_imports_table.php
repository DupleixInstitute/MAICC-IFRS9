<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('imports', function (Blueprint $table) {
            $table->dateTime('started_at')->nullable()->default(null)->change();
        });
    }

    public function down()
    {
        Schema::table('imports', function (Blueprint $table) {
            $table->dateTime('started_at')->nullable(false)->default(now())->change();
        });
    }
};

