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
        Schema::create('adicional_estudiantes', function (Blueprint $table) {
            $table->id();
            $table->string('carnet')->unique();
            $table->text('notas_pago')->nullable();
            $table->string('nomenclatura')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('adicional_estudiantes');
    }
};
