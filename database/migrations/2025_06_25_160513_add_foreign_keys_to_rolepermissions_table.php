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
        Schema::table('rolepermissions', function (Blueprint $table) {
            $table->foreign(['permission_id'], 'rolepermissions_permission_id_fkey')->references(['id'])->on('permissions')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['role_id'], 'rolepermissions_role_id_fkey')->references(['id'])->on('roles')->onUpdate('no action')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rolepermissions', function (Blueprint $table) {
            $table->dropForeign('rolepermissions_permission_id_fkey');
            $table->dropForeign('rolepermissions_role_id_fkey');
        });
    }
};
