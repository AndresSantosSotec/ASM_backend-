<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('duplicate_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('original_prospect_id');
            $table->unsignedBigInteger('duplicate_prospect_id');
            $table->integer('similarity_score'); // 0–100
            $table->enum('status', ['pending', 'resolved'])->default('pending');
            $table->timestamps();
    
            $table->foreign('original_prospect_id')->references('id')->on('prospectos')->onDelete('cascade');
            $table->foreign('duplicate_prospect_id')->references('id')->on('prospectos')->onDelete('cascade');
            $table->unique(['original_prospect_id','duplicate_prospect_id']);
        });
    }
    

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('duplicate_records');
    }
};
