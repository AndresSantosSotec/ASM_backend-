<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reconciliation_records', function (Blueprint $table) {
            $table->unsignedBigInteger('kardex_pago_id')->nullable()->after('uploaded_by');
            $table->foreign('kardex_pago_id')->references('id')->on('kardex_pagos')->nullOnDelete();
            $table->index('kardex_pago_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reconciliation_records', function (Blueprint $table) {
            $table->dropForeign(['kardex_pago_id']);
            $table->dropIndex(['kardex_pago_id']);
            $table->dropColumn('kardex_pago_id');
        });
    }
};
