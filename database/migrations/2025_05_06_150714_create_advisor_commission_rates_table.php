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
        Schema::create('advisor_commission_rates', function (Blueprint $table) {
            $table->id();
            
            // FK al usuario (asesor)
            $table->unsignedBigInteger('user_id')->index();
            
            // Tasa de comisión personalizada (por ejemplo 12.00 = 12%)
            $table->decimal('rate', 5, 2)->default(0);
            
            // Desde cuándo aplica esta tasa (opcional)
            $table->date('effective_from')->nullable();
            
            $table->timestamps();

            // Restricción de clave foránea
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('advisor_commission_rates');
    }
};
