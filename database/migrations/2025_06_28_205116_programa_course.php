<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('programa_course', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programa_id')
                  ->constrained('tb_programas')
                  ->onDelete('cascade');
            $table->foreignId('course_id')
                  ->constrained('courses')
                  ->onDelete('cascade');
            $table->unique(['programa_id', 'course_id']);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('programa_course');
    }
};
