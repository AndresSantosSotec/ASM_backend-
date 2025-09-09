<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('kardex_pagos', function (Blueprint $table) {
            // Unique constraint to prevent duplicate boletas for same student/program and bank
            $table->unique(['estudiante_programa_id', 'banco_norm', 'numero_boleta_norm'], 'unique_boleta_per_student_bank');
            
            // Unique constraint to prevent reuse of same file for same student/program
            $table->unique(['estudiante_programa_id', 'file_sha256'], 'unique_file_per_student');
        });
    }

    public function down()
    {
        Schema::table('kardex_pagos', function (Blueprint $table) {
            $table->dropUnique('unique_boleta_per_student_bank');
            $table->dropUnique('unique_file_per_student');
        });
    }
};