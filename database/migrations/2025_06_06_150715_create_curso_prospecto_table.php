<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCursoProspectoTable extends Migration
{
    public function up()
    {
        Schema::create('curso_prospecto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prospecto_id')
                  ->constrained('prospectos')
                  ->onDelete('cascade');
            $table->foreignId('course_id')
                  ->constrained('courses')
                  ->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('curso_prospecto');
    }
}
