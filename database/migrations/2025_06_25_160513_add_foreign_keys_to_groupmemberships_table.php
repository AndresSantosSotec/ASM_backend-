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
        Schema::table('groupmemberships', function (Blueprint $table) {
            $table->foreign(['group_id'], 'groupmemberships_group_id_fkey')->references(['id'])->on('groups')->onUpdate('no action')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('groupmemberships', function (Blueprint $table) {
            $table->dropForeign('groupmemberships_group_id_fkey');
        });
    }
};
