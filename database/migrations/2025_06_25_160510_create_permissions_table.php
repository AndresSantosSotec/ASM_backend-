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
            $table->enum('action', ['view', 'create', 'edit', 'delete', 'export']);
            $table->unsignedInteger('moduleview_id')->nullable();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index('action');
            $table->index('moduleview_id');
            $table->foreign('moduleview_id')
                ->references('id')
                ->on('moduleviews')
                ->onUpdate('cascade')
                ->onDelete('cascade');
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
