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
        Schema::table('contactos_enviados', function (Blueprint $table) {
            $table->foreign(['prospecto_id'], 'contactos_enviados_prospecto_id_fkey')->references(['id'])->on('prospectos')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contactos_enviados', function (Blueprint $table) {
            $table->dropForeign('contactos_enviados_prospecto_id_fkey');
        });
    }
};
