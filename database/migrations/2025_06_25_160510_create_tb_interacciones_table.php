<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tb_interacciones', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('id_asesor');
            $table->bigInteger('id_actividades');
            $table->text('defec_interaccion')->nullable();
            $table->string('duracion', 15)->nullable();
            $table->string('notas')->nullable();
            $table->integer('created_by')->nullable();
            $table->timestamp('created_at')->nullable()->default(DB::raw("now()"));
            $table->timestamp('updated_at')->nullable()->default(DB::raw("now()"));
            $table->bigInteger('id_lead')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_interacciones');
    }
};
