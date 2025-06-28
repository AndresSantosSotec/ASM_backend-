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
        Schema::table('duplicate_records', function (Blueprint $table) {
            $table->foreign(['duplicate_prospect_id'])->references(['id'])->on('prospectos')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['original_prospect_id'])->references(['id'])->on('prospectos')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('duplicate_records', function (Blueprint $table) {
            $table->dropForeign('duplicate_records_duplicate_prospect_id_foreign');
            $table->dropForeign('duplicate_records_original_prospect_id_foreign');
        });
    }
};
