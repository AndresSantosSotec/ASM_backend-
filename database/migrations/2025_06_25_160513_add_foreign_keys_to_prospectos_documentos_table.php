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
        Schema::table('prospectos_documentos', function (Blueprint $table) {
            $table->foreign(['prospecto_id'], 'prospectos_documentos_prospecto_id_fkey')->references(['id'])->on('prospectos')->onUpdate('no action')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prospectos_documentos', function (Blueprint $table) {
            $table->dropForeign('prospectos_documentos_prospecto_id_fkey');
        });
    }
};
