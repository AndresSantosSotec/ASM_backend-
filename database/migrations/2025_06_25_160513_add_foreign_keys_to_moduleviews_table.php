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
        Schema::table('moduleviews', function (Blueprint $table) {
            $table->foreign(['module_id'], 'moduleviews_module_id_fkey')->references(['id'])->on('modules')->onUpdate('no action')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('moduleviews', function (Blueprint $table) {
            $table->dropForeign('moduleviews_module_id_fkey');
        });
    }
};
