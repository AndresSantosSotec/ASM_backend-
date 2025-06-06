<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->enum('area', ['common', 'specialty']);
            $table->unsignedTinyInteger('credits');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('schedule');
            $table->string('duration');
            $table->foreignId('facilitator_id')->nullable()->constrained('users'); // Asumiendo que los facilitadores están en users
            $table->enum('status', ['draft', 'approved', 'synced'])->default('draft');
            $table->unsignedInteger('students')->default(0);
            $table->timestamps();
            $table->softDeletes();
            
            // Índices para mejorar el rendimiento en búsquedas
            $table->index('area');
            $table->index('status');
            $table->index('start_date');
            $table->index('end_date');
        });
    }

    public function down()
    {
        Schema::dropIfExists('courses');
    }
};