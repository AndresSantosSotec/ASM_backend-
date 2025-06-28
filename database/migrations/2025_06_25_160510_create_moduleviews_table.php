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
        Schema::create('moduleviews', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('module_id');
            $table->string('menu', 100)->nullable();
            $table->string('submenu', 100)->nullable();
            $table->string('view_path')->nullable();
            $table->string('status', 20)->nullable();
            $table->integer('order_num')->nullable();
            $table->string('icon')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('moduleviews');
    }
};
