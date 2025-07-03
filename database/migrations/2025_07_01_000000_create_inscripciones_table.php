<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inscripciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prospecto_id')->constrained('prospectos')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('semestre', 10);
            $table->smallInteger('credits')->default(0);
            $table->decimal('calificacion', 5, 2)->nullable();
            $table->timestamps();
            $table->unique(['prospecto_id','course_id','semestre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inscripciones');
    }
};
