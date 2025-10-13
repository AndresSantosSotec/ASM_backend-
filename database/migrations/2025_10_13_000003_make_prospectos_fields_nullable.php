<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Makes telefono and correo_electronico nullable in prospectos table
     * Required for payment import when student data is incomplete
     */
    public function up(): void
    {
        Schema::table('prospectos', function (Blueprint $table) {
            // Make telefono nullable
            $table->string('telefono')->nullable()->change();
            // Make correo_electronico nullable
            $table->string('correo_electronico')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prospectos', function (Blueprint $table) {
            // Revert to NOT NULL - this may fail if there are NULL values
            $table->string('telefono')->nullable(false)->change();
            $table->string('correo_electronico')->nullable(false)->change();
        });
    }
};
