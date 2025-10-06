<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add default values for genero and pais_origen at database level
        // This ensures consistency even if the service layer doesn't set them
        DB::statement("ALTER TABLE prospectos ALTER COLUMN genero SET DEFAULT 'Masculino'");
        DB::statement("ALTER TABLE prospectos ALTER COLUMN pais_origen SET DEFAULT 'Guatemala'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove default values
        DB::statement("ALTER TABLE prospectos ALTER COLUMN genero DROP DEFAULT");
        DB::statement("ALTER TABLE prospectos ALTER COLUMN pais_origen DROP DEFAULT");
    }
};
