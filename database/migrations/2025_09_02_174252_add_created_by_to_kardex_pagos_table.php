<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('kardex_pagos', function (Blueprint $table) {
            // Add created_by column only if it doesn't exist
            if (!Schema::hasColumn('kardex_pagos', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('observaciones');
                $table->foreign('created_by')->references('id')->on('users');
            }
        });
    }

    public function down()
    {
        Schema::table('kardex_pagos', function (Blueprint $table) {
            if (Schema::hasColumn('kardex_pagos', 'created_by')) {
                $table->dropForeign(['created_by']);
                $table->dropColumn('created_by');
            }
        });
    }
};
