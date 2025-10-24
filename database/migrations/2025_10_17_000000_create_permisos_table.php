<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates the 'permisos' table for user-specific permissions.
     * This table is separate from 'permissions' (used for role permissions).
     */
    public function up(): void
    {
        Schema::create('permisos', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('moduleview_id');
            $table->string('action')->default('view');
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->timestamps();

            // Add indexes for performance
            $table->index('moduleview_id');
            $table->index('action');
            
            // Foreign key to moduleviews
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
        Schema::dropIfExists('permisos');
    }
};
