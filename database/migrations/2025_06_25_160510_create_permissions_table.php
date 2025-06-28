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
        Schema::create('permissions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('module', 50)->nullable();
            $table->string('section', 50)->nullable();
            $table->string('resource', 50)->nullable();
            $table->string('action', 50)->nullable();
            $table->enum('effect', ['allow', 'deny'])->nullable();
            $table->text('description')->nullable();
            $table->string('route_path')->nullable();
            $table->string('file_name', 100)->nullable();
            $table->integer('object_id')->nullable();
            $table->boolean('is_enabled')->nullable()->default(true);
            $table->enum('level', ['view', 'create', 'edit', 'delete', 'export'])->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
