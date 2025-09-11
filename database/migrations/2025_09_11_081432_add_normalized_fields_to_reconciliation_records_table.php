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
            $table->string('bank_normalized')->nullable()->after('bank');
            $table->string('reference_normalized')->nullable()->after('reference');
            $table->string('fingerprint')->nullable()->after('reference_normalized')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reconciliation_records', function (Blueprint $table) {
            $table->dropColumn(['bank_normalized', 'reference_normalized', 'fingerprint']);
        });
    }
};
