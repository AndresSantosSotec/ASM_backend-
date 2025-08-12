<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE rolepermissions DROP CONSTRAINT IF EXISTS rolepermissions_role_id_foreign');
        DB::statement('ALTER TABLE rolepermissions DROP CONSTRAINT IF EXISTS rolepermissions_permission_id_foreign');

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
        });

        DB::statement('ALTER TABLE rolepermissions DROP CONSTRAINT IF EXISTS rolepermissions_role_id_foreign');
        DB::statement('ALTER TABLE rolepermissions DROP CONSTRAINT IF EXISTS rolepermissions_permission_id_foreign');
    }
};
