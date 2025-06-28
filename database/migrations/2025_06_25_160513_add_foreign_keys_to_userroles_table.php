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
        Schema::table('userroles', function (Blueprint $table) {
            $table->foreign(['role_id'], 'userroles_role_id_fkey')->references(['id'])->on('roles')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['user_id'], 'userroles_user_id_fkey')->references(['id'])->on('users')->onUpdate('no action')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('userroles', function (Blueprint $table) {
            $table->dropForeign('userroles_role_id_fkey');
            $table->dropForeign('userroles_user_id_fkey');
        });
    }
};
