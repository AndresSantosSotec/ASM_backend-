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
        Schema::create('duplicate_records', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('original_prospect_id');
            $table->bigInteger('duplicate_prospect_id');
            $table->integer('similarity_score');
            $table->enum('status', ['pending', 'resolved'])->default('pending');
            $table->timestamps();

            $table->unique(['original_prospect_id', 'duplicate_prospect_id'], 'duplicate_records_original_prospect_id_duplicate_prospect_id_un');
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
