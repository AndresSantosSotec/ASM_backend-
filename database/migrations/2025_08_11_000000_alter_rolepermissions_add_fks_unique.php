<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rolepermissions', function (Blueprint $table) {
            try {
                $table->dropForeign(['role_id']);
            } catch (\Throwable $e) {
                // ignore if foreign key does not exist
            }
            try {
                $table->dropForeign(['permission_id']);
            } catch (\Throwable $e) {
                // ignore
            }
        });

        Schema::table('rolepermissions', function (Blueprint $table) {
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
            $table->unique(['role_id', 'permission_id', 'scope']);
        });
    }

    public function down(): void
    {
        Schema::table('rolepermissions', function (Blueprint $table) {
            $table->dropUnique(['role_id', 'permission_id', 'scope']);
            try {
                $table->dropForeign(['role_id']);
            } catch (\Throwable $e) {
            }
            try {
                $table->dropForeign(['permission_id']);
            } catch (\Throwable $e) {
            }
        });
    }
};
