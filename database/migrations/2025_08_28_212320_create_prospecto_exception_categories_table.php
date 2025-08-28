<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prospecto_exception_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prospecto_id')->constrained('prospectos')->onDelete('cascade');
            $table->foreignId('payment_exception_category_id')->constrained('payment_exception_categories')->onDelete('cascade');
            $table->date('effective_from')->nullable(); // Fecha de inicio de la excepción
            $table->date('effective_until')->nullable(); // Fecha de fin de la excepción
            $table->text('notes')->nullable(); // Notas adicionales del por qué se asignó la excepción
            $table->timestamps();

            // Evitar duplicados: un prospecto no puede tener la misma categoría dos veces activa
            $table->unique(['prospecto_id', 'payment_exception_category_id'], 'prospecto_category_unique');

            // Índices para consultas por fechas
            $table->index(['effective_from', 'effective_until']);
            $table->index(['prospecto_id', 'effective_from', 'effective_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospecto_exception_categories');
    }
};
