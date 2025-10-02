<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('kardex_pagos', function (Blueprint $table) {
            // Add fecha_recibo column

            // Add uploaded_by column with foreign key
            $table->unsignedBigInteger('uploaded_by')->nullable()->after('created_by');
            $table->foreign('uploaded_by')->references('id')->on('users');

            // Add updated_by column with foreign key
            $table->unsignedBigInteger('updated_by')->nullable()->after('uploaded_by');
            $table->foreign('updated_by')->references('id')->on('users');
        });
    }

    public function down()
    {
        Schema::table('kardex_pagos', function (Blueprint $table) {
            // Drop foreign keys first
            $table->dropForeign(['uploaded_by']);
            $table->dropForeign(['updated_by']);

            // Drop columns
            $table->dropColumn([ 'uploaded_by', 'updated_by']);
        });
    }
};
