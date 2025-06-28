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
        Schema::table('tb_interacciones', function (Blueprint $table) {
            $table->foreign(['id_actividades'], 'fk_interacciones_actividades')->references(['id'])->on('tb_actividades')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tb_interacciones', function (Blueprint $table) {
            $table->dropForeign('fk_interacciones_actividades');
        });
    }
};
