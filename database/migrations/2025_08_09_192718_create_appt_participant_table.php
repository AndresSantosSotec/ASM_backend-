<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appt_participant', function (Blueprint $table) {
            $table->id();
            
            // Relación con la cita (usando tbcitas como en tu sistema)
            $table->foreignId('cita_id')
                  ->constrained('tbcitas')
                  ->cascadeOnDelete();
            
            // Relación con usuario (asesor)
            $table->foreignId('user_id')
                  ->nullable()
                  ->constrained('users') // Asumo que tu tabla de usuarios se llama 'users'
                  ->cascadeOnDelete();
            
            // Relación con prospecto (usando prospectos como en tu ejemplo)
            $table->foreignId('prospecto_id')
                  ->nullable()
                  ->constrained('prospectos') // Exactamente como en tu migración de prospectos
                  ->cascadeOnDelete();
            
            // Campo para tipo de participante
            $table->enum('tipo_participante', ['asesor', 'prospecto'])
                  ->default('prospecto');
            
            $table->timestamps();
        });

        // Restricción adicional para PostgreSQL (opcional pero recomendado)
        DB::statement('ALTER TABLE appt_participant ADD CONSTRAINT chk_participante_valido CHECK (
            (user_id IS NOT NULL AND prospecto_id IS NULL AND tipo_participante = \'asesor\') OR
            (user_id IS NULL AND prospecto_id IS NOT NULL AND tipo_participante = \'prospecto\')
        )');
    }

    public function down(): void
    {
        Schema::dropIfExists('appt_participant');
    }
};