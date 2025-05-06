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
        Schema::create('commission_configs', function (Blueprint $table) {
            $table->id();

            // Porcentaje base de comisión (ej. 20.00 = 20%)
            $table->decimal('base_rate', 5, 2)->default(0);

            // Umbral de conversiones para bonificación
            $table->integer('bonus_threshold')->default(0);

            // Porcentaje adicional cuando se supera el umbral (ej. 5.00 = 5%)
            $table->decimal('bonus_rate', 5, 2)->default(0);

            // Periodo de cálculo: monthly o quarterly
            $table->enum('period', ['monthly', 'quarterly'])->default('monthly');

            // Si respetar o no las tasas personalizadas de asesor
            $table->boolean('respect_personalized')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commission_configs');
    }
};
