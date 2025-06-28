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
        Schema::create('tb_periodo_programa', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('periodo_id');
            $table->bigInteger('programa_id');

            $table->unique(['periodo_id', 'programa_id'], 'uq_periodo_programa');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_periodo_programa');
    }
};
