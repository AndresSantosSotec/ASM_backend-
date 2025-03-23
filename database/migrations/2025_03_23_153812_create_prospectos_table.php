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
        Schema::create('prospectos', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->string('nombre_completo');
            $table->string('telefono');
            $table->string('correo_electronico');
            $table->string('genero');
            $table->string('empresa_donde_labora_actualmente')->nullable();
            $table->string('puesto')->nullable();
            $table->text('notas_generales')->nullable();
            $table->text('observaciones')->nullable();
            $table->string('interes')->nullable();
            $table->string('status')->nullable(); // ✅ nuevo campo
            $table->string('nota1')->nullable();
            $table->string('nota2')->nullable();
            $table->string('nota3')->nullable();
            $table->text('cierre')->nullable();
            $table->timestamps();
        });        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prospectos');
    }
};
