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
        Schema::table('column_configurations', function (Blueprint $table) {
            $table->foreign(['id_tipo'])->references(['id_tipo'])->on('nom')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('column_configurations', function (Blueprint $table) {
            $table->dropForeign('column_configurations_id_tipo_foreign');
        });
    }
};
