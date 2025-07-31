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
        // Sólo agregar si no existe
        if (! Schema::hasColumn('prospectos', 'status')) {
            Schema::table('prospectos', function (Blueprint $table) {
                $table->string('status')->nullable()->after('interes');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('prospectos', 'status')) {
            Schema::table('prospectos', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }
    }
};
