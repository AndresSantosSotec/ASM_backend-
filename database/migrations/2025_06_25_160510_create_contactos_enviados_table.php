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
        Schema::create('contactos_enviados', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('prospecto_id')->index('idx_contactos_env_prosp');
            $table->string('canal', 50);
            $table->string('tipo_contacto', 50)->nullable();
            $table->timestamp('fecha_envio')->default(DB::raw("now()"));
            $table->string('resultado', 100)->nullable();
            $table->text('observaciones')->nullable();
            $table->integer('creado_por')->nullable();
            $table->timestamp('created_at')->default(DB::raw("now()"));
            $table->timestamp('updated_at')->nullable();

            $table->unique(['prospecto_id', 'canal'], 'ux_contactos_env_prosp_canal_dia');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contactos_enviados');
    }
};
