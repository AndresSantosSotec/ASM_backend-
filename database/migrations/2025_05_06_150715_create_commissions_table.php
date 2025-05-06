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
        Schema::create('commissions', function (Blueprint $table) {
            $table->id();

            // Asesor al que corresponde esta comisión
            $table->unsignedBigInteger('user_id')->index();

            // Rango de fechas del periodo calculado
            $table->date('period_start');
            $table->date('period_end');

            // Totales del periodo
            $table->decimal('total_income', 14, 2)->default(0);
            $table->integer('conversions')->default(0);

            // Tasa aplicada y monto resultante
            $table->decimal('rate_applied', 5, 2)->default(0);
            $table->decimal('commission_amount', 14, 2)->default(0);

            // Diferencia frente a la comisión “predeterminada” (opcional)
            $table->decimal('difference', 14, 2)->default(0);

            // Referencia al snapshot de configuración usado (opcional)
            $table->unsignedBigInteger('config_id')->nullable()->index();

            $table->timestamps();

            // FK a users
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');

            // FK a la configuración global (si se desea trazabilidad)
            $table->foreign('config_id')
                  ->references('id')
                  ->on('commission_configs')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commissions');
    }
};
