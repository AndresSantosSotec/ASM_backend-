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
        Schema::create('role_permissions_config', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('role_id');
            $table->unsignedInteger('permission_id');
            $table->enum('action', ['view', 'create', 'edit', 'delete', 'export'])->default('view');
            $table->enum('scope', ['global', 'group', 'self'])->default('self');
            $table->timestamps();

            // Indexes for performance
            $table->index('role_id');
            $table->index('permission_id');
            $table->unique(['role_id', 'permission_id', 'action'], 'unique_role_permission_action');

            // Foreign key constraints
            $table->foreign('role_id')
                ->references('id')
                ->on('roles')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('permission_id')
                ->references('id')
                ->on('permissions')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_permissions_config');
    }
};