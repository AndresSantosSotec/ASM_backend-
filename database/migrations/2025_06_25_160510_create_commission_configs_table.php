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
        Schema::create('commission_configs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->decimal('base_rate', 5)->default(0);
            $table->integer('bonus_threshold')->default(0);
            $table->decimal('bonus_rate', 5)->default(0);
            $table->enum('period', ['monthly', 'quarterly'])->default('monthly');
            $table->boolean('respect_personalized')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commission_configs');
    }
};
