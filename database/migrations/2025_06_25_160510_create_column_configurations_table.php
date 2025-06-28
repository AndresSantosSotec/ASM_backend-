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
        Schema::create('column_configurations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('id_tipo');
            $table->string('column_name', 100);
            $table->string('excel_column_name', 100);
            $table->integer('column_number');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('column_configurations');
    }
};
