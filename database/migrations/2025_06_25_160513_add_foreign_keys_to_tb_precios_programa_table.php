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
        Schema::table('tb_precios_programa', function (Blueprint $table) {
            $table->foreign(['programa_id'], 'tb_precios_programa_programa_id_fkey')->references(['id'])->on('tb_programas')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tb_precios_programa', function (Blueprint $table) {
            $table->dropForeign('tb_precios_programa_programa_id_fkey');
        });
    }
};
