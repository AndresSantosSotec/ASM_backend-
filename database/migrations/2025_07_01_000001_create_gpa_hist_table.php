<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gpa_hist', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prospecto_id')->constrained('prospectos')->cascadeOnDelete();
            $table->string('semestre', 10);
            $table->decimal('gpa', 4, 2);
            $table->timestamps();
            $table->unique(['prospecto_id','semestre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gpa_hist');
    }
};
