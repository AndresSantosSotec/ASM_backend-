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
        Schema::create('courses', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('code')->unique();
            $table->enum('area', ['common', 'specialty'])->index();
            $table->smallInteger('credits');
            $table->date('start_date')->index();
            $table->date('end_date')->index();
            $table->string('schedule');
            $table->string('duration');
            $table->bigInteger('facilitator_id')->nullable();
            $table->enum('status', ['draft', 'approved', 'synced'])->default('draft')->index();
            $table->integer('students')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
